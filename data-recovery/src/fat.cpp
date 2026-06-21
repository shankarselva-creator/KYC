// fat.cpp - Deleted-file recovery for FAT12/16/32 and exFAT.
//
// FAT: deleted directory entries keep the starting cluster and size but have
// their first name byte replaced with 0xE5 and their cluster chain freed, so we
// recover assuming contiguous allocation from the starting cluster.
// exFAT: similar, using File + Stream-Extension + File-Name entry sets whose
// type bytes have the in-use bit (0x80) cleared.
#include "recovery.h"
#include <cstring>
#include <algorithm>
#include <set>

namespace {

uint16_t rd16(const uint8_t* p) { return p[0] | (p[1] << 8); }
uint32_t rd32(const uint8_t* p) {
    return p[0] | (p[1] << 8) | (p[2] << 16) | (uint32_t(p[3]) << 24);
}
uint64_t rd64(const uint8_t* p) {
    uint64_t v = 0;
    for (int i = 7; i >= 0; --i) v = (v << 8) | p[i];
    return v;
}

// --- classic FAT --------------------------------------------------------

struct FatLayout {
    uint64_t partStart = 0;
    uint32_t bytesPerSector = 512;
    uint32_t secPerClus = 1;
    uint32_t clusterBytes = 512;
    uint64_t fatStartByte = 0;       // first FAT
    uint64_t rootDirByte = 0;        // FAT12/16 fixed root
    uint32_t rootDirEntries = 0;     // FAT12/16
    uint32_t rootCluster = 0;        // FAT32
    uint64_t dataStartByte = 0;
    int      fatBits = 32;           // 12, 16 or 32
    bool     valid = false;

    uint64_t clusterOffset(uint32_t c) const {
        return dataStartByte + uint64_t(c - 2) * clusterBytes;
    }
};

FatLayout ReadFatLayout(Disk& disk, uint64_t partStart) {
    FatLayout L;
    L.partStart = partStart;
    uint8_t b[512];
    if (!disk.read(partStart, b, sizeof(b)))
        return L;

    uint32_t bps = rd16(b + 0x0B);
    uint32_t spc = b[0x0D];
    uint32_t reserved = rd16(b + 0x0E);
    uint32_t numFats = b[0x10];
    uint32_t rootEnt = rd16(b + 0x11);
    uint32_t totSec16 = rd16(b + 0x13);
    uint32_t fatSz16 = rd16(b + 0x16);
    uint32_t totSec32 = rd32(b + 0x20);
    uint32_t fatSz32 = rd32(b + 0x24);
    L.rootCluster = rd32(b + 0x2C);
    if (bps == 0 || spc == 0 || numFats == 0)
        return L;

    uint32_t fatSz = fatSz16 ? fatSz16 : fatSz32;
    uint32_t totSec = totSec16 ? totSec16 : totSec32;
    uint32_t rootDirSectors = ((rootEnt * 32) + (bps - 1)) / bps;
    uint32_t dataStartSector = reserved + numFats * fatSz + rootDirSectors;
    if (dataStartSector >= totSec)
        return L;
    uint32_t countOfClusters = (totSec - dataStartSector) / spc;

    L.bytesPerSector = bps;
    L.secPerClus = spc;
    L.clusterBytes = bps * spc;
    L.fatStartByte = partStart + uint64_t(reserved) * bps;
    L.rootDirByte = partStart + uint64_t(reserved + numFats * fatSz) * bps;
    L.rootDirEntries = rootEnt;
    L.dataStartByte = partStart + uint64_t(dataStartSector) * bps;
    L.fatBits = (countOfClusters < 4085) ? 12
              : (countOfClusters < 65525) ? 16 : 32;
    L.valid = true;
    return L;
}

uint32_t FatNext(Disk& disk, const FatLayout& L, uint32_t c) {
    if (L.fatBits == 16) {
        uint8_t v[2];
        if (!disk.read(L.fatStartByte + uint64_t(c) * 2, v, 2)) return 0x0FFFFFFF;
        uint16_t e = rd16(v);
        return e >= 0xFFF8 ? 0x0FFFFFFF : e;
    } else if (L.fatBits == 32) {
        uint8_t v[4];
        if (!disk.read(L.fatStartByte + uint64_t(c) * 4, v, 4)) return 0x0FFFFFFF;
        uint32_t e = rd32(v) & 0x0FFFFFFF;
        return e >= 0x0FFFFFF8 ? 0x0FFFFFFF : e;
    } else { // FAT12
        uint64_t off = L.fatStartByte + c + (c / 2);
        uint8_t v[2];
        if (!disk.read(off, v, 2)) return 0x0FFFFFFF;
        uint16_t e = rd16(v);
        e = (c & 1) ? (e >> 4) : (e & 0x0FFF);
        return e >= 0xFF8 ? 0x0FFFFFFF : e;
    }
}

// Extract the (up to) 13 UTF-16 characters held in one LFN directory entry.
std::wstring LfnSegment(const uint8_t* e) {
    static const int idx[13] = {1, 3, 5, 7, 9, 14, 16, 18, 20, 22, 24, 28, 30};
    std::wstring s;
    for (int i = 0; i < 13; ++i) {
        wchar_t c = static_cast<wchar_t>(rd16(e + idx[i]));
        if (c == 0x0000 || c == 0xFFFF)
            break;
        s.push_back(c);
    }
    return s;
}

std::wstring ShortName(const uint8_t* e) {
    char name[13];
    int n = 0;
    for (int i = 0; i < 8; ++i) {
        if (e[i] == ' ') break;
        name[n++] = (i == 0 && e[0] == 0x05) ? (char)0xE5 : (char)e[i];
    }
    if (e[8] != ' ') {
        name[n++] = '.';
        for (int i = 8; i < 11; ++i) {
            if (e[i] == ' ') break;
            name[n++] = (char)e[i];
        }
    }
    name[n] = 0;
    std::string s(name);
    return std::wstring(s.begin(), s.end());
}

// Recursively walk live directories; emit deleted files found inside them.
void WalkFatDir(Disk& disk, const FatLayout& L, uint32_t startCluster,
                bool isFixedRoot, const std::wstring& label,
                const ResultFn& onResult, std::set<uint32_t>& visited,
                int depth, uint64_t& found) {
    if (depth > 64)
        return;

    std::vector<uint8_t> buf;
    std::vector<std::wstring> lfnParts;     // accumulated across entries
    auto processBuffer = [&](const uint8_t* data, size_t len) {
        for (size_t off = 0; off + 32 <= len; off += 32) {
            const uint8_t* e = data + off;
            if (e[0] == 0x00)
                return false;            // end of directory
            uint8_t attr = e[0x0B];
            if ((attr & 0x0F) == 0x0F) {
                lfnParts.push_back(LfnSegment(e)); // long-file-name component
                continue;
            }
            if (attr & 0x08) {
                lfnParts.clear();
                continue;                // volume label
            }

            // Reassemble the long name (entries precede the short one in
            // reverse sequence order). Survives deletion when the name bytes
            // are intact.
            std::wstring longName;
            for (auto it = lfnParts.rbegin(); it != lfnParts.rend(); ++it)
                longName += *it;
            lfnParts.clear();

            uint32_t clus = (uint32_t(rd16(e + 0x14)) << 16) | rd16(e + 0x1A);
            uint32_t size = rd32(e + 0x1C);

            if (e[0] == 0xE5) {          // deleted
                if (attr & 0x10)
                    continue;            // skip deleted directories
                if (clus < 2 || size == 0)
                    continue;
                RecoveredFile rf;
                rf.method = RecMethod::NtfsUndelete;
                rf.deleted = true;
                rf.source = label;
                rf.name = longName.empty() ? ShortName(e) : longName;
                rf.size = size;
                uint64_t clustersNeeded =
                    (uint64_t(size) + L.clusterBytes - 1) / L.clusterBytes;
                Extent ext{L.clusterOffset(clus),
                           clustersNeeded * L.clusterBytes};
                rf.extents.push_back(ext);
                ++found;
                if (onResult) onResult(rf);
            } else if ((attr & 0x10) && e[0] != '.') { // live subdirectory
                if (clus >= 2 && !visited.count(clus))
                    WalkFatDir(disk, L, clus, false, label, onResult, visited,
                               depth + 1, found);
            }
        }
        return true;
    };

    if (isFixedRoot) {
        buf.resize(L.rootDirEntries * 32);
        if (!disk.read(L.rootDirByte, buf.data(), buf.size()))
            return;
        processBuffer(buf.data(), buf.size());
        return;
    }

    uint32_t c = startCluster;
    buf.resize(L.clusterBytes);
    while (c >= 2 && c < 0x0FFFFFF8) {
        if (visited.count(c))
            break;
        visited.insert(c);
        if (!disk.read(L.clusterOffset(c), buf.data(), buf.size()))
            break;
        if (!processBuffer(buf.data(), buf.size()))
            break;
        c = FatNext(disk, L, c);
    }
}

// --- exFAT --------------------------------------------------------------

struct ExfatLayout {
    uint64_t partStart = 0;
    uint32_t bytesPerSector = 512;
    uint32_t clusterBytes = 4096;
    uint64_t fatOffsetByte = 0;
    uint64_t clusterHeapByte = 0;
    uint32_t rootCluster = 0;
    bool     valid = false;

    uint64_t clusterOffset(uint32_t c) const {
        return clusterHeapByte + uint64_t(c - 2) * clusterBytes;
    }
};

ExfatLayout ReadExfatLayout(Disk& disk, uint64_t partStart) {
    ExfatLayout L;
    L.partStart = partStart;
    uint8_t b[512];
    if (!disk.read(partStart, b, sizeof(b)))
        return L;
    if (std::memcmp(b + 3, "EXFAT   ", 8) != 0)
        return L;

    uint32_t fatOffset = rd32(b + 0x50);
    uint32_t heapOffset = rd32(b + 0x58);
    L.rootCluster = rd32(b + 0x60);
    uint32_t bpsShift = b[0x6C];
    uint32_t spcShift = b[0x6D];
    if (bpsShift > 31 || spcShift > 31)
        return L;
    L.bytesPerSector = 1u << bpsShift;
    L.clusterBytes = 1u << (bpsShift + spcShift);
    L.fatOffsetByte = partStart + uint64_t(fatOffset) * L.bytesPerSector;
    L.clusterHeapByte = partStart + uint64_t(heapOffset) * L.bytesPerSector;
    L.valid = true;
    return L;
}

uint32_t ExfatNext(Disk& disk, const ExfatLayout& L, uint32_t c) {
    uint8_t v[4];
    if (!disk.read(L.fatOffsetByte + uint64_t(c) * 4, v, 4))
        return 0xFFFFFFFF;
    uint32_t e = rd32(v);
    return e >= 0xFFFFFFF7 ? 0xFFFFFFFF : e;
}

void WalkExfatDir(Disk& disk, const ExfatLayout& L, uint32_t startCluster,
                  const std::wstring& label, const ResultFn& onResult,
                  std::set<uint32_t>& visited, int depth, uint64_t& found) {
    if (depth > 64 || startCluster < 2)
        return;

    // Read the whole directory (chain) into one buffer.
    std::vector<uint8_t> dir;
    uint32_t c = startCluster;
    while (c >= 2 && c < 0xFFFFFFF7 && dir.size() < (64u << 20)) {
        if (visited.count(c))
            break;
        visited.insert(c);
        size_t base = dir.size();
        dir.resize(base + L.clusterBytes);
        if (!disk.read(L.clusterOffset(c), dir.data() + base, L.clusterBytes)) {
            dir.resize(base);
            break;
        }
        c = ExfatNext(disk, L, c);
    }

    for (size_t off = 0; off + 32 <= dir.size(); off += 32) {
        const uint8_t* e = dir.data() + off;
        uint8_t type = e[0];
        if (type == 0x00)
            break;
        bool inUse = (type & 0x80) != 0;
        uint8_t base = type & 0x7F;
        if (base != 0x05) // 0x85 file directory entry (0x05 = deleted)
            continue;

        uint8_t secondary = e[1];
        uint16_t attrs = rd16(e + 0x04);
        bool isDir = (attrs & 0x10) != 0;

        const uint8_t* stream = e + 32;
        if (off + 64 > dir.size())
            break;
        if ((stream[0] & 0x7F) != 0x40) // stream extension entry
            continue;
        uint8_t nameLen = stream[0x03];
        uint32_t firstClus = rd32(stream + 0x14);
        uint64_t dataLen = rd64(stream + 0x18);

        // Assemble the name from File Name entries (type 0xC1/0x41).
        std::wstring name;
        for (int i = 1; i < secondary; ++i) {
            size_t neOff = off + 32 + uint64_t(i) * 32;
            if (neOff + 32 > dir.size())
                break;
            const uint8_t* ne = dir.data() + neOff;
            if ((ne[0] & 0x7F) != 0x41)
                continue;
            for (int k = 0; k < 15 && name.size() < nameLen; ++k)
                name.push_back(static_cast<wchar_t>(rd16(ne + 2 + k * 2)));
        }

        if (isDir) {
            if (inUse && firstClus >= 2 && !visited.count(firstClus))
                WalkExfatDir(disk, L, firstClus, label, onResult, visited,
                             depth + 1, found);
            continue;
        }

        if (!inUse) { // deleted file
            if (firstClus < 2 || dataLen == 0)
                continue;
            RecoveredFile rf;
            rf.method = RecMethod::NtfsUndelete;
            rf.deleted = true;
            rf.source = label;
            rf.name = name.empty() ? L"exfat_deleted.bin" : name;
            rf.size = dataLen;
            uint64_t clustersNeeded =
                (dataLen + L.clusterBytes - 1) / L.clusterBytes;
            Extent ext{L.clusterOffset(firstClus),
                       clustersNeeded * L.clusterBytes};
            rf.extents.push_back(ext);
            ++found;
            if (onResult) onResult(rf);
        }
    }
}

} // namespace

void ScanFatDeleted(Disk& disk, uint64_t partStart, const std::wstring& label,
                    const ProgressFn& progress, const ResultFn& onResult) {
    FatLayout L = ReadFatLayout(disk, partStart);
    if (!L.valid) {
        if (progress) progress(100, label + L": not a FAT volume");
        return;
    }
    if (progress) progress(10, label + L": scanning FAT directories...");
    std::set<uint32_t> visited;
    uint64_t found = 0;
    if (L.fatBits == 32)
        WalkFatDir(disk, L, L.rootCluster, false, label, onResult, visited, 0,
                   found);
    else
        WalkFatDir(disk, L, 0, true, label, onResult, visited, 0, found);

    if (progress) {
        wchar_t s[128];
        swprintf(s, 128, L"%s: done, %llu deleted files found",
                 label.c_str(), (unsigned long long)found);
        progress(100, s);
    }
}

void ScanExfatDeleted(Disk& disk, uint64_t partStart, const std::wstring& label,
                      const ProgressFn& progress, const ResultFn& onResult) {
    ExfatLayout L = ReadExfatLayout(disk, partStart);
    if (!L.valid) {
        if (progress) progress(100, label + L": not an exFAT volume");
        return;
    }
    if (progress) progress(10, label + L": scanning exFAT directories...");
    std::set<uint32_t> visited;
    uint64_t found = 0;
    WalkExfatDir(disk, L, L.rootCluster, label, onResult, visited, 0, found);

    if (progress) {
        wchar_t s[128];
        swprintf(s, 128, L"%s: done, %llu deleted files found",
                 label.c_str(), (unsigned long long)found);
        progress(100, s);
    }
}
