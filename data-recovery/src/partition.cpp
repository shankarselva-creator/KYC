// partition.cpp - MBR and GPT partition table parsing.
#include "recovery.h"
#include <cstring>

namespace {

uint32_t rd32(const uint8_t* p) {
    return p[0] | (p[1] << 8) | (p[2] << 16) | (uint32_t(p[3]) << 24);
}
uint64_t rd64(const uint8_t* p) {
    uint64_t v = 0;
    for (int i = 7; i >= 0; --i) v = (v << 8) | p[i];
    return v;
}

std::wstring MbrTypeName(uint8_t t) {
    switch (t) {
        case 0x07: return L"NTFS/exFAT";
        case 0x0B:
        case 0x0C: return L"FAT32";
        case 0x06:
        case 0x0E: return L"FAT16";
        case 0x83: return L"Linux";
        case 0x82: return L"Linux swap";
        case 0xEE: return L"GPT protective";
        case 0xEF: return L"EFI System";
        case 0x05:
        case 0x0F: return L"Extended";
        default:   return L"Unknown";
    }
}

// Well-known GPT partition type GUIDs (first 4 bytes are enough to disambiguate
// the common cases for a label).
std::wstring GptTypeName(const uint8_t* guid) {
    static const uint8_t kBasicData[16] = {
        0xA2,0xA0,0xD0,0xEB,0xE5,0xB9,0x33,0x44,
        0x87,0xC0,0x68,0xB6,0xB7,0x26,0x99,0xC7};
    static const uint8_t kEfi[16] = {
        0x28,0x73,0x2A,0xC1,0x1F,0xF8,0xD2,0x11,
        0xBA,0x4B,0x00,0xA0,0xC9,0x3E,0xC9,0x3B};
    static const uint8_t kMsr[16] = {
        0x16,0xE3,0xC9,0xE3,0x5C,0x0B,0xB8,0x4D,
        0x81,0x7D,0xF9,0x2D,0xF0,0x02,0x15,0xAE};
    if (!std::memcmp(guid, kBasicData, 16)) return L"NTFS/Basic data";
    if (!std::memcmp(guid, kEfi, 16))       return L"EFI System";
    if (!std::memcmp(guid, kMsr, 16))       return L"MS Reserved";
    return L"GPT partition";
}

bool IsZeroGuid(const uint8_t* g) {
    for (int i = 0; i < 16; ++i)
        if (g[i]) return false;
    return true;
}

} // namespace

std::vector<PartitionInfo> ScanPartitions(Disk& disk) {
    std::vector<PartitionInfo> parts;
    const uint32_t ss = disk.sectorSize();

    uint8_t mbr[512];
    if (!disk.read(0, mbr, sizeof(mbr)))
        return parts;
    if (mbr[510] != 0x55 || mbr[511] != 0xAA)
        return parts; // no valid signature

    bool isGpt = false;
    for (int i = 0; i < 4; ++i) {
        const uint8_t* e = mbr + 446 + i * 16;
        if (e[4] == 0xEE) { isGpt = true; break; }
    }

    if (!isGpt) {
        int idx = 0;
        for (int i = 0; i < 4; ++i) {
            const uint8_t* e = mbr + 446 + i * 16;
            uint8_t type = e[4];
            uint32_t startLba = rd32(e + 8);
            uint32_t numSec = rd32(e + 12);
            if (type == 0 || numSec == 0)
                continue;
            PartitionInfo p;
            p.index = idx++;
            p.scheme = L"MBR";
            p.type = MbrTypeName(type);
            p.startOffset = static_cast<uint64_t>(startLba) * ss;
            p.sizeBytes = static_cast<uint64_t>(numSec) * ss;
            parts.push_back(std::move(p));
        }
        return parts;
    }

    // GPT: header at LBA 1.
    uint8_t hdr[512];
    if (!disk.read(static_cast<uint64_t>(ss), hdr, sizeof(hdr)))
        return parts;
    if (std::memcmp(hdr, "EFI PART", 8) != 0)
        return parts;

    uint64_t entryLba = rd64(hdr + 72);
    uint32_t numEntries = rd32(hdr + 80);
    uint32_t entrySize = rd32(hdr + 84);
    if (entrySize < 128 || numEntries == 0 || numEntries > 4096)
        return parts;

    std::vector<uint8_t> table(static_cast<size_t>(numEntries) * entrySize);
    if (!disk.read(entryLba * ss, table.data(), table.size()))
        return parts;

    int idx = 0;
    for (uint32_t i = 0; i < numEntries; ++i) {
        const uint8_t* e = table.data() + static_cast<size_t>(i) * entrySize;
        if (IsZeroGuid(e))
            continue;
        uint64_t first = rd64(e + 32);
        uint64_t last = rd64(e + 40);
        if (last < first)
            continue;
        PartitionInfo p;
        p.index = idx++;
        p.scheme = L"GPT";
        p.type = GptTypeName(e);
        p.startOffset = first * ss;
        p.sizeBytes = (last - first + 1) * ss;
        parts.push_back(std::move(p));
    }
    return parts;
}
