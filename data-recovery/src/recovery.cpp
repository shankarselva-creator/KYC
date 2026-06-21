// recovery.cpp - Write discovered files back out to disk.
#include "recovery.h"
#include <algorithm>
#include <cstring>
#include <cstdio>

FsType DetectFilesystem(Disk& disk, uint64_t partStart) {
    uint8_t boot[512];
    if (!disk.read(partStart, boot, sizeof(boot)))
        return FsType::Unknown;
    if (std::memcmp(boot + 3, "NTFS    ", 8) == 0)
        return FsType::NTFS;
    if (std::memcmp(boot + 3, "EXFAT   ", 8) == 0)
        return FsType::ExFAT;
    if (std::memcmp(boot + 0x52, "FAT32   ", 8) == 0 ||
        std::memcmp(boot + 0x36, "FAT16   ", 8) == 0 ||
        std::memcmp(boot + 0x36, "FAT12   ", 8) == 0 ||
        std::memcmp(boot + 0x36, "FAT", 3) == 0)
        return FsType::FAT;
    return FsType::Unknown;
}

void ScanDeletedAuto(Disk& disk, uint64_t partStart,
                     const std::wstring& sourceLabel,
                     const ProgressFn& progress, const ResultFn& onResult) {
    switch (DetectFilesystem(disk, partStart)) {
        case FsType::NTFS:
            ScanNtfsDeleted(disk, partStart, sourceLabel, progress, onResult);
            break;
        case FsType::FAT:
            ScanFatDeleted(disk, partStart, sourceLabel, progress, onResult);
            break;
        case FsType::ExFAT:
            ScanExfatDeleted(disk, partStart, sourceLabel, progress, onResult);
            break;
        default:
            if (progress)
                progress(100, sourceLabel + L": unsupported/unknown filesystem");
            break;
    }
}

std::vector<uint8_t> ReadRecoveredBytes(Disk& disk, const RecoveredFile& file,
                                        size_t maxBytes) {
    std::vector<uint8_t> out;
    uint64_t want = std::min<uint64_t>(file.size, maxBytes);
    if (file.resident) {
        size_t n = static_cast<size_t>(
            std::min<uint64_t>(want, file.residentData.size()));
        out.assign(file.residentData.begin(), file.residentData.begin() + n);
        return out;
    }
    out.reserve(static_cast<size_t>(want));
    for (const Extent& ex : file.extents) {
        if (out.size() >= want)
            break;
        uint64_t left = ex.length;
        uint64_t off = ex.diskOffset;
        while (left > 0 && out.size() < want) {
            size_t chunk = static_cast<size_t>(
                std::min<uint64_t>({(uint64_t)(1u << 20), left,
                                    want - out.size()}));
            size_t base = out.size();
            out.resize(base + chunk);
            if (!disk.read(off, out.data() + base, chunk)) {
                out.resize(base);
                return out;
            }
            off += chunk;
            left -= chunk;
        }
    }
    return out;
}

std::wstring SanitizeFileName(const std::wstring& name) {
    std::wstring out;
    out.reserve(name.size());
    for (wchar_t c : name) {
        if (c == L'\\' || c == L'/' || c == L':' || c == L'*' || c == L'?' ||
            c == L'"' || c == L'<' || c == L'>' || c == L'|' || c < 0x20)
            out.push_back(L'_');
        else
            out.push_back(c);
    }
    if (out.empty())
        out = L"recovered.bin";
    if (out.size() > 200)
        out.resize(200);
    return out;
}

// --- scan-result serialization -----------------------------------------
// Simple little-endian binary format; strings are stored as UTF-16 with a
// preceding 32-bit length (in code units).
namespace {

const char kMagic[4] = {'D', 'R', 'S', 'V'};
const uint32_t kVersion = 1;

void putU32(FILE* f, uint32_t v) { fwrite(&v, 4, 1, f); }
void putU64(FILE* f, uint64_t v) { fwrite(&v, 8, 1, f); }
void putStr(FILE* f, const std::wstring& s) {
    putU32(f, static_cast<uint32_t>(s.size()));
    for (wchar_t c : s) {
        uint16_t u = static_cast<uint16_t>(c);
        fwrite(&u, 2, 1, f);
    }
}
bool getU32(FILE* f, uint32_t& v) { return fread(&v, 4, 1, f) == 1; }
bool getU64(FILE* f, uint64_t& v) { return fread(&v, 8, 1, f) == 1; }
bool getStr(FILE* f, std::wstring& s) {
    uint32_t n;
    if (!getU32(f, n) || n > (1u << 24))
        return false;
    s.clear();
    s.reserve(n);
    for (uint32_t i = 0; i < n; ++i) {
        uint16_t u;
        if (fread(&u, 2, 1, f) != 1)
            return false;
        s.push_back(static_cast<wchar_t>(u));
    }
    return true;
}

} // namespace

bool SaveResults(const std::wstring& path, const std::wstring& devicePath,
                 const std::vector<RecoveredFile>& results) {
    FILE* f = _wfopen(path.c_str(), L"wb");
    if (!f)
        return false;
    fwrite(kMagic, 1, 4, f);
    putU32(f, kVersion);
    putStr(f, devicePath);
    putU32(f, static_cast<uint32_t>(results.size()));
    for (const auto& r : results) {
        putStr(f, r.name);
        putStr(f, r.source);
        putU64(f, r.size);
        putU32(f, static_cast<uint32_t>(r.method));
        fputc(r.deleted ? 1 : 0, f);
        fputc(r.resident ? 1 : 0, f);
        putU32(f, static_cast<uint32_t>(r.residentData.size()));
        if (!r.residentData.empty())
            fwrite(r.residentData.data(), 1, r.residentData.size(), f);
        putU32(f, static_cast<uint32_t>(r.extents.size()));
        for (const auto& e : r.extents) {
            putU64(f, e.diskOffset);
            putU64(f, e.length);
        }
    }
    fclose(f);
    return true;
}

bool LoadResults(const std::wstring& path, std::wstring& devicePath,
                 std::vector<RecoveredFile>& results) {
    FILE* f = _wfopen(path.c_str(), L"rb");
    if (!f)
        return false;
    char magic[4];
    uint32_t version = 0, count = 0;
    bool ok = fread(magic, 1, 4, f) == 4 && std::memcmp(magic, kMagic, 4) == 0 &&
              getU32(f, version) && version == kVersion &&
              getStr(f, devicePath) && getU32(f, count) && count <= (1u << 22);
    if (!ok) { fclose(f); return false; }

    results.clear();
    results.reserve(count);
    for (uint32_t i = 0; i < count && ok; ++i) {
        RecoveredFile r;
        uint32_t method = 0, rlen = 0, ecount = 0;
        ok = getStr(f, r.name) && getStr(f, r.source) && getU64(f, r.size) &&
             getU32(f, method);
        if (!ok) break;
        r.method = static_cast<RecMethod>(method);
        r.deleted = fgetc(f) ? true : false;
        r.resident = fgetc(f) ? true : false;
        if (!getU32(f, rlen) || rlen > (1u << 26)) { ok = false; break; }
        r.residentData.resize(rlen);
        if (rlen && fread(r.residentData.data(), 1, rlen, f) != rlen) {
            ok = false; break;
        }
        if (!getU32(f, ecount) || ecount > (1u << 22)) { ok = false; break; }
        for (uint32_t j = 0; j < ecount; ++j) {
            Extent e;
            if (!getU64(f, e.diskOffset) || !getU64(f, e.length)) {
                ok = false; break;
            }
            r.extents.push_back(e);
        }
        if (ok)
            results.push_back(std::move(r));
    }
    fclose(f);
    return ok;
}

int64_t RecoverFile(Disk& disk, const RecoveredFile& file,
                    const std::wstring& outputPath) {
    HANDLE out = CreateFileW(outputPath.c_str(), GENERIC_WRITE, 0, nullptr,
                             CREATE_ALWAYS, FILE_ATTRIBUTE_NORMAL, nullptr);
    if (out == INVALID_HANDLE_VALUE)
        return -1;

    uint64_t remaining = file.size;
    int64_t written = 0;
    bool ok = true;

    if (file.resident) {
        DWORD w = 0;
        DWORD n = static_cast<DWORD>(
            std::min<uint64_t>(remaining, file.residentData.size()));
        if (n && WriteFile(out, file.residentData.data(), n, &w, nullptr))
            written += w;
    } else {
        std::vector<uint8_t> buf(1u * 1024 * 1024);
        for (const Extent& ex : file.extents) {
            uint64_t left = ex.length;
            uint64_t off = ex.diskOffset;
            while (left > 0 && remaining > 0) {
                size_t chunk = static_cast<size_t>(
                    std::min<uint64_t>({(uint64_t)buf.size(), left, remaining}));
                if (!disk.read(off, buf.data(), chunk)) {
                    ok = false;
                    break;
                }
                DWORD w = 0;
                if (!WriteFile(out, buf.data(), static_cast<DWORD>(chunk), &w,
                               nullptr)) {
                    ok = false;
                    break;
                }
                written += w;
                off += chunk;
                left -= chunk;
                remaining -= chunk;
            }
            if (!ok || remaining == 0)
                break;
        }
    }

    CloseHandle(out);
    return ok ? written : -1;
}
