// ntfs.cpp - NTFS Master File Table parsing for deleted-file recovery.
//
// Reads the $MFT, walks every FILE record, and reports records that are marked
// "not in use" (deleted) together with the disk extents of their $DATA, so the
// recovery stage can read the still-present clusters back off the disk.
#include "recovery.h"
#include <cstring>
#include <algorithm>

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

// NTFS $Boot ($BPB) geometry.
struct NtfsGeometry {
    uint32_t bytesPerSector = 512;
    uint32_t sectorsPerCluster = 8;
    uint64_t clusterSize = 4096;
    uint64_t mftStartLcn = 0;
    uint32_t recordSize = 1024;
    bool     valid = false;
};

NtfsGeometry ReadGeometry(Disk& disk, uint64_t partStart) {
    NtfsGeometry g;
    uint8_t boot[512];
    if (!disk.read(partStart, boot, sizeof(boot)))
        return g;
    if (std::memcmp(boot + 3, "NTFS    ", 8) != 0)
        return g;

    g.bytesPerSector = rd16(boot + 0x0B);
    g.sectorsPerCluster = boot[0x0D];
    if (g.bytesPerSector == 0 || g.sectorsPerCluster == 0)
        return g;
    g.clusterSize = static_cast<uint64_t>(g.bytesPerSector) * g.sectorsPerCluster;
    g.mftStartLcn = rd64(boot + 0x30);

    int8_t clustersPerRec = static_cast<int8_t>(boot[0x40]);
    if (clustersPerRec >= 0)
        g.recordSize = static_cast<uint32_t>(clustersPerRec) * g.clusterSize;
    else
        g.recordSize = 1u << (-clustersPerRec); // 2^(-value)
    if (g.recordSize == 0)
        g.recordSize = 1024;
    g.valid = true;
    return g;
}

// Apply the NTFS fixup (Update Sequence Array) to an in-memory record.
bool ApplyFixup(uint8_t* rec, uint32_t recSize, uint32_t sectorSize) {
    uint16_t usaOff = rd16(rec + 0x04);
    uint16_t usaCount = rd16(rec + 0x06);
    if (usaCount == 0 || usaOff + usaCount * 2u > recSize)
        return false;
    const uint8_t* usa = rec + usaOff;
    uint16_t usn = rd16(usa);
    for (uint16_t i = 1; i < usaCount; ++i) {
        uint32_t tail = i * sectorSize - 2;
        if (tail + 2 > recSize)
            break;
        if (rd16(rec + tail) != usn)
            return false; // checksum mismatch -> record is corrupt
        rec[tail]     = usa[i * 2];
        rec[tail + 1] = usa[i * 2 + 1];
    }
    return true;
}

// Decode an NTFS data-run list into absolute-disk extents.
std::vector<Extent> DecodeRunList(const uint8_t* run, const uint8_t* end,
                                  uint64_t clusterSize, uint64_t partStart) {
    std::vector<Extent> extents;
    int64_t lcn = 0;
    while (run < end && *run != 0) {
        uint8_t header = *run++;
        uint8_t lenBytes = header & 0x0F;
        uint8_t offBytes = (header >> 4) & 0x0F;
        if (lenBytes == 0 || run + lenBytes + offBytes > end)
            break;

        uint64_t length = 0;
        for (int i = 0; i < lenBytes; ++i)
            length |= static_cast<uint64_t>(run[i]) << (8 * i);
        run += lenBytes;

        int64_t delta = 0;
        if (offBytes) {
            for (int i = 0; i < offBytes; ++i)
                delta |= static_cast<int64_t>(run[i]) << (8 * i);
            // sign-extend
            if (run[offBytes - 1] & 0x80)
                delta |= -(int64_t(1) << (8 * offBytes));
        }
        run += offBytes;

        if (offBytes == 0) {
            // sparse run: no on-disk data, skip
            continue;
        }
        lcn += delta;
        if (lcn < 0)
            break;
        Extent e;
        e.diskOffset = partStart + static_cast<uint64_t>(lcn) * clusterSize;
        e.length = length * clusterSize;
        extents.push_back(e);
    }
    return extents;
}

// Extract the best file name from a record's $FILE_NAME attributes.
std::wstring ParseFileName(const uint8_t* content, uint32_t contentLen) {
    if (contentLen < 0x42)
        return L"";
    uint8_t nameLen = content[0x40];
    uint8_t nameSpace = content[0x41];
    if (0x42 + nameLen * 2u > contentLen)
        return L"";
    std::wstring name;
    name.reserve(nameLen);
    const uint8_t* p = content + 0x42;
    for (uint8_t i = 0; i < nameLen; ++i)
        name.push_back(static_cast<wchar_t>(rd16(p + i * 2)));
    // nameSpace 2 == DOS short name; prefer long names but accept if nothing else
    (void)nameSpace;
    return name;
}

} // namespace

void ScanNtfsDeleted(Disk& disk, uint64_t partStart,
                     const std::wstring& sourceLabel,
                     const ProgressFn& progress, const ResultFn& onResult) {
    NtfsGeometry g = ReadGeometry(disk, partStart);
    if (!g.valid) {
        if (progress) progress(100, sourceLabel + L": not an NTFS volume");
        return;
    }

    // Read MFT record 0 to discover the full $MFT extent map.
    uint64_t mftOffset = partStart + g.mftStartLcn * g.clusterSize;
    std::vector<uint8_t> rec0(g.recordSize);
    if (!disk.read(mftOffset, rec0.data(), g.recordSize) ||
        std::memcmp(rec0.data(), "FILE", 4) != 0) {
        if (progress) progress(100, sourceLabel + L": cannot read $MFT");
        return;
    }
    ApplyFixup(rec0.data(), g.recordSize, g.bytesPerSector);

    // Find $MFT's own $DATA run list -> the list of MFT fragments on disk.
    std::vector<Extent> mftExtents;
    uint64_t mftDataSize = 0;
    {
        uint16_t attrOff = rd16(rec0.data() + 0x14);
        const uint8_t* end = rec0.data() + g.recordSize;
        const uint8_t* a = rec0.data() + attrOff;
        while (a + 8 <= end) {
            uint32_t type = rd32(a);
            if (type == 0xFFFFFFFF)
                break;
            uint32_t len = rd32(a + 4);
            if (len < 8 || a + len > end)
                break;
            if (type == 0x80 && a[8] != 0) { // non-resident $DATA
                mftDataSize = rd64(a + 0x30);
                uint16_t runOff = rd16(a + 0x20);
                mftExtents = DecodeRunList(a + runOff, a + len, g.clusterSize,
                                           partStart);
                break;
            }
            a += len;
        }
    }
    if (mftExtents.empty()) {
        if (progress) progress(100, sourceLabel + L": empty $MFT run list");
        return;
    }

    uint64_t totalRecords = mftDataSize / g.recordSize;
    if (totalRecords == 0)
        totalRecords = 1;

    // Helper: read MFT record N by walking the fragment map.
    std::vector<uint8_t> rec(g.recordSize);
    auto readRecord = [&](uint64_t n) -> bool {
        uint64_t want = n * g.recordSize;
        uint64_t seen = 0;
        for (const Extent& ex : mftExtents) {
            if (want < seen + ex.length) {
                uint64_t off = ex.diskOffset + (want - seen);
                return disk.read(off, rec.data(), g.recordSize);
            }
            seen += ex.length;
        }
        return false;
    };

    uint64_t found = 0;
    for (uint64_t n = 0; n < totalRecords; ++n) {
        if ((n & 0x3FF) == 0) {
            int pct = static_cast<int>((n * 100) / totalRecords);
            wchar_t buf[128];
            swprintf(buf, 128, L"%s: scanning MFT %llu/%llu (%llu deleted)",
                     sourceLabel.c_str(), (unsigned long long)n,
                     (unsigned long long)totalRecords,
                     (unsigned long long)found);
            if (progress && !progress(pct, buf))
                return; // cancelled
        }

        if (!readRecord(n))
            continue;
        if (std::memcmp(rec.data(), "FILE", 4) != 0)
            continue;
        if (!ApplyFixup(rec.data(), g.recordSize, g.bytesPerSector))
            continue;

        uint16_t flags = rd16(rec.data() + 0x16);
        bool inUse = (flags & 0x01) != 0;
        bool isDir = (flags & 0x02) != 0;
        if (inUse || isDir)
            continue; // only want deleted files

        // Walk attributes for $FILE_NAME and $DATA.
        std::wstring name;
        bool haveData = false;
        RecoveredFile rf;
        rf.method = RecMethod::NtfsUndelete;
        rf.deleted = true;
        rf.source = sourceLabel;

        uint16_t attrOff = rd16(rec.data() + 0x14);
        const uint8_t* end = rec.data() + g.recordSize;
        const uint8_t* a = rec.data() + attrOff;
        while (a + 8 <= end) {
            uint32_t type = rd32(a);
            if (type == 0xFFFFFFFF)
                break;
            uint32_t len = rd32(a + 4);
            if (len < 0x18 || a + len > end)
                break;
            uint8_t nonResident = a[8];
            uint8_t nameLen = a[9];

            if (type == 0x30 && nonResident == 0) { // $FILE_NAME (always resident)
                uint16_t cOff = rd16(a + 0x14);
                uint32_t cLen = rd32(a + 0x10);
                if (a + cOff + cLen <= end) {
                    std::wstring fn = ParseFileName(a + cOff, cLen);
                    // Prefer a longer (long) name over a short DOS name.
                    if (fn.size() > name.size())
                        name = fn;
                }
            } else if (type == 0x80 && nameLen == 0) { // unnamed $DATA
                if (nonResident == 0) {
                    uint16_t cOff = rd16(a + 0x14);
                    uint32_t cLen = rd32(a + 0x10);
                    if (a + cOff + cLen <= end) {
                        rf.resident = true;
                        rf.residentData.assign(a + cOff, a + cOff + cLen);
                        rf.size = cLen;
                        haveData = true;
                    }
                } else {
                    uint64_t realSize = rd64(a + 0x30);
                    uint16_t runOff = rd16(a + 0x20);
                    rf.extents = DecodeRunList(a + runOff, a + len,
                                               g.clusterSize, partStart);
                    rf.size = realSize;
                    rf.resident = false;
                    if (!rf.extents.empty())
                        haveData = true;
                }
            }
            a += len;
        }

        if (name.empty() || !haveData || rf.size == 0)
            continue;
        rf.name = name;
        ++found;
        if (onResult)
            onResult(rf);
    }

    if (progress) {
        wchar_t buf[128];
        swprintf(buf, 128, L"%s: done, %llu deleted files found",
                 sourceLabel.c_str(), (unsigned long long)found);
        progress(100, buf);
    }
}
