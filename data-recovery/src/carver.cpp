// carver.cpp - Signature-based ("deep scan") file carving.
//
// Streams the raw disk in large windows, looks for known file-header magic, and
// emits a RecoveredFile spanning from the header to a detected footer or a
// per-type maximum size. Works even when filesystem metadata is gone.
#include "recovery.h"
#include <cstring>
#include <algorithm>

namespace {

struct Signature {
    const wchar_t* ext;
    const uint8_t* header;
    size_t         headerLen;
    const uint8_t* footer;     // optional; nullptr = size-bounded only
    size_t         footerLen;
    uint64_t       maxSize;
};

const uint8_t JPG_H[] = {0xFF, 0xD8, 0xFF};
const uint8_t JPG_F[] = {0xFF, 0xD9};
const uint8_t PNG_H[] = {0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A};
const uint8_t PNG_F[] = {0x49, 0x45, 0x4E, 0x44, 0xAE, 0x42, 0x60, 0x82};
const uint8_t GIF_H[] = {0x47, 0x49, 0x46, 0x38};
const uint8_t GIF_F[] = {0x00, 0x3B};
const uint8_t PDF_H[] = {0x25, 0x50, 0x44, 0x46};            // %PDF
const uint8_t PDF_F[] = {0x25, 0x25, 0x45, 0x4F, 0x46};      // %%EOF
const uint8_t ZIP_H[] = {0x50, 0x4B, 0x03, 0x04};            // also docx/xlsx
const uint8_t RAR_H[] = {0x52, 0x61, 0x72, 0x21, 0x1A, 0x07};
const uint8_t GZ_H[]  = {0x1F, 0x8B, 0x08};
const uint8_t MP3_H[] = {0x49, 0x44, 0x33};                  // ID3
const uint8_t DOC_H[] = {0xD0, 0xCF, 0x11, 0xE0, 0xA1, 0xB1, 0x1A, 0xE1};

const Signature kSignatures[] = {
    {L"jpg", JPG_H, sizeof(JPG_H), JPG_F, sizeof(JPG_F), 30ull * 1024 * 1024},
    {L"png", PNG_H, sizeof(PNG_H), PNG_F, sizeof(PNG_F), 50ull * 1024 * 1024},
    {L"gif", GIF_H, sizeof(GIF_H), GIF_F, sizeof(GIF_F), 20ull * 1024 * 1024},
    {L"pdf", PDF_H, sizeof(PDF_H), PDF_F, sizeof(PDF_F), 100ull * 1024 * 1024},
    {L"zip", ZIP_H, sizeof(ZIP_H), nullptr, 0,           200ull * 1024 * 1024},
    {L"rar", RAR_H, sizeof(RAR_H), nullptr, 0,           200ull * 1024 * 1024},
    {L"gz",  GZ_H,  sizeof(GZ_H),  nullptr, 0,           200ull * 1024 * 1024},
    {L"mp3", MP3_H, sizeof(MP3_H), nullptr, 0,           30ull * 1024 * 1024},
    {L"doc", DOC_H, sizeof(DOC_H), nullptr, 0,           50ull * 1024 * 1024},
};

// Find `needle` within [start,end); returns pointer or nullptr.
const uint8_t* FindBytes(const uint8_t* start, const uint8_t* end,
                         const uint8_t* needle, size_t n) {
    if (n == 0 || static_cast<size_t>(end - start) < n)
        return nullptr;
    const uint8_t* limit = end - n;
    for (const uint8_t* p = start; p <= limit; ++p) {
        if (p[0] == needle[0] && std::memcmp(p, needle, n) == 0)
            return p;
    }
    return nullptr;
}

} // namespace

void ScanCarve(Disk& disk, uint64_t startOffset, uint64_t length,
               const ProgressFn& progress, const ResultFn& onResult) {
    const size_t kWindow = 16ull * 1024 * 1024; // 16 MiB read window
    const size_t kOverlap = 64 * 1024;          // carry-over for split headers

    if (length == 0 || startOffset + length > disk.size())
        length = disk.size() - startOffset;

    std::vector<uint8_t> buf(kWindow + kOverlap);
    uint64_t pos = startOffset;
    uint64_t scanEnd = startOffset + length;
    uint64_t carry = 0; // bytes carried from previous window at buf[0..carry)
    uint64_t found = 0;

    while (pos < scanEnd) {
        size_t want = static_cast<size_t>(
            std::min<uint64_t>(kWindow, scanEnd - pos));
        if (!disk.read(pos, buf.data() + carry, want)) {
            pos += want;
            carry = 0;
            continue;
        }
        size_t avail = static_cast<size_t>(carry) + want;
        uint64_t windowBase = pos - carry; // disk offset of buf[0]

        const uint8_t* base = buf.data();
        const uint8_t* end = buf.data() + avail;
        for (const auto& sig : kSignatures) {
            const uint8_t* p = base;
            while ((p = FindBytes(p, end, sig.header, sig.headerLen)) != nullptr) {
                uint64_t fileOff = windowBase + (p - base);
                uint64_t fileSize = 0;

                if (sig.footer && sig.footerLen) {
                    // Try to locate the footer; bounded by maxSize.
                    const uint8_t* searchEnd = end;
                    const uint8_t* f = FindBytes(p + sig.headerLen, searchEnd,
                                                 sig.footer, sig.footerLen);
                    if (f) {
                        fileSize = (f + sig.footerLen) - p;
                    } else {
                        // footer not in this window: emit a size-capped estimate
                        fileSize = std::min<uint64_t>(sig.maxSize, scanEnd - fileOff);
                    }
                } else {
                    fileSize = std::min<uint64_t>(sig.maxSize, scanEnd - fileOff);
                }
                if (fileSize == 0) { ++p; continue; }

                RecoveredFile rf;
                rf.method = RecMethod::Carve;
                rf.deleted = true;
                rf.resident = false;
                rf.size = fileSize;
                rf.source = L"Deep scan";
                Extent e{fileOff, fileSize};
                rf.extents.push_back(e);
                wchar_t nm[64];
                swprintf(nm, 64, L"carved_%08llX.%s",
                         (unsigned long long)fileOff, sig.ext);
                rf.name = nm;
                ++found;
                if (onResult)
                    onResult(rf);
                p += sig.headerLen;
            }
        }

        // Advance; keep an overlap so a header split across windows is caught.
        if (pos + want >= scanEnd)
            break;
        carry = kOverlap;
        std::memmove(buf.data(), buf.data() + avail - kOverlap, kOverlap);
        pos += want;

        int pct = static_cast<int>(((pos - startOffset) * 100) / length);
        wchar_t st[96];
        swprintf(st, 96, L"Deep scan %d%% (%llu files)", pct,
                 (unsigned long long)found);
        if (progress && !progress(pct, st))
            return;
    }

    if (progress) {
        wchar_t st[96];
        swprintf(st, 96, L"Deep scan done, %llu files", (unsigned long long)found);
        progress(100, st);
    }
}
