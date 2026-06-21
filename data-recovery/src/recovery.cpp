// recovery.cpp - Write discovered files back out to disk.
#include "recovery.h"
#include <algorithm>

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
