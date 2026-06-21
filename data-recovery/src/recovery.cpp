// recovery.cpp - Write discovered files back out to disk.
#include "recovery.h"
#include <algorithm>
#include <cstring>

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
