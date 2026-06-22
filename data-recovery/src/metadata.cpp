// metadata.cpp - Extract a friendly name from a carved file's embedded metadata.
#include "metadata.h"
#include <windows.h>
#include <algorithm>
#include <cstring>
#include <vector>

namespace {

std::wstring Utf8ToW(const uint8_t* d, size_t n) {
    if (n == 0) return L"";
    int wn = MultiByteToWideChar(CP_UTF8, 0, (const char*)d, (int)n, nullptr, 0);
    std::wstring w(wn, L'\0');
    MultiByteToWideChar(CP_UTF8, 0, (const char*)d, (int)n, &w[0], wn);
    return w;
}

std::wstring Latin1ToW(const uint8_t* d, size_t n) {
    std::wstring w;
    for (size_t i = 0; i < n; ++i) {
        if (d[i] == 0) break;
        w.push_back((wchar_t)d[i]);
    }
    return w;
}

// Trim, strip filesystem-illegal characters, and cap length.
std::wstring Clean(std::wstring s) {
    std::wstring out;
    for (wchar_t c : s) {
        if (c == L'\\' || c == L'/' || c == L':' || c == L'*' || c == L'?' ||
            c == L'"' || c == L'<' || c == L'>' || c == L'|' || c < 0x20)
            out.push_back(L'_');
        else
            out.push_back(c);
    }
    size_t b = out.find_first_not_of(L" _");
    size_t e = out.find_last_not_of(L" _");
    if (b == std::wstring::npos) return L"";
    out = out.substr(b, e - b + 1);
    if (out.size() > 80) out.resize(80);
    return out;
}

uint32_t be32(const uint8_t* p) {
    return (uint32_t(p[0]) << 24) | (p[1] << 16) | (p[2] << 8) | p[3];
}
uint32_t synch32(const uint8_t* p) { // ID3 synchsafe (7 bits/byte)
    return (uint32_t(p[0] & 0x7F) << 21) | ((p[1] & 0x7F) << 14) |
           ((p[2] & 0x7F) << 7) | (p[3] & 0x7F);
}

std::wstring DecodeId3Text(const uint8_t* d, size_t n) {
    if (n < 1) return L"";
    uint8_t enc = d[0];
    const uint8_t* t = d + 1;
    size_t tn = n - 1;
    if (enc == 1 || enc == 2) {            // UTF-16 (BOM) / UTF-16BE
        size_t i = 0; bool be = (enc == 2);
        if (enc == 1 && tn >= 2) {
            if (t[0] == 0xFF && t[1] == 0xFE) { be = false; i = 2; }
            else if (t[0] == 0xFE && t[1] == 0xFF) { be = true; i = 2; }
        }
        std::wstring w;
        for (; i + 1 < tn; i += 2) {
            wchar_t c = be ? (wchar_t)((t[i] << 8) | t[i + 1])
                           : (wchar_t)((t[i + 1] << 8) | t[i]);
            if (c == 0) break;
            w.push_back(c);
        }
        return w;
    }
    if (enc == 3) return Utf8ToW(t, tn);   // UTF-8
    return Latin1ToW(t, tn);               // ISO-8859-1
}

// MP3: parse the ID3v2 tag at the start for title (TIT2) and artist (TPE1).
std::wstring FromId3(const uint8_t* d, size_t n) {
    if (n < 10 || std::memcmp(d, "ID3", 3) != 0) return L"";
    uint8_t major = d[3];
    uint32_t tagSize = synch32(d + 6);
    size_t end = std::min<size_t>(n, 10 + tagSize);
    size_t pos = 10;
    std::wstring title, artist;
    while (pos + 10 <= end) {
        char id[5] = {0};
        std::memcpy(id, d + pos, 4);
        if (id[0] == 0) break;             // padding
        uint32_t fsz = (major >= 4) ? synch32(d + pos + 4) : be32(d + pos + 4);
        size_t fdata = pos + 10;
        if (fsz == 0 || fdata + fsz > end) break;
        if (!std::memcmp(id, "TIT2", 4))
            title = DecodeId3Text(d + fdata, fsz);
        else if (!std::memcmp(id, "TPE1", 4))
            artist = DecodeId3Text(d + fdata, fsz);
        pos = fdata + fsz;
    }
    if (!title.empty())
        return artist.empty() ? title : (artist + L" - " + title);
    return L"";
}

// PDF: find /Title (...) or /Title <hex> in the document info.
std::wstring FromPdf(const uint8_t* d, size_t n) {
    size_t scan = std::min<size_t>(n, 256 * 1024);
    const char* key = "/Title";
    for (size_t i = 0; i + 6 < scan; ++i) {
        if (std::memcmp(d + i, key, 6) != 0) continue;
        size_t j = i + 6;
        while (j < scan && (d[j] == ' ' || d[j] == '\t')) ++j;
        if (j >= scan) break;
        if (d[j] == '(') {                 // literal string
            std::wstring s; ++j;
            while (j < scan && d[j] != ')') {
                if (d[j] == '\\' && j + 1 < scan) ++j;
                s.push_back((wchar_t)d[j]); ++j;
            }
            return Clean(s);
        }
        if (d[j] == '<') {                 // hex string (often UTF-16BE w/ BOM)
            ++j;
            std::vector<uint8_t> bytes;
            auto hex = [](uint8_t c) -> int {
                if (c >= '0' && c <= '9') return c - '0';
                if (c >= 'A' && c <= 'F') return c - 'A' + 10;
                if (c >= 'a' && c <= 'f') return c - 'a' + 10;
                return -1;
            };
            while (j + 1 < scan && d[j] != '>') {
                int hi = hex(d[j]), lo = hex(d[j + 1]);
                if (hi < 0 || lo < 0) break;
                bytes.push_back((uint8_t)((hi << 4) | lo));
                j += 2;
            }
            std::wstring s;
            if (bytes.size() >= 2 && bytes[0] == 0xFE && bytes[1] == 0xFF)
                for (size_t k = 2; k + 1 < bytes.size(); k += 2)
                    s.push_back((wchar_t)((bytes[k] << 8) | bytes[k + 1]));
            else
                for (uint8_t b : bytes) s.push_back((wchar_t)b);
            return Clean(s);
        }
        break;
    }
    return L"";
}

// JPEG: read EXIF DateTimeOriginal/DateTime -> IMG_YYYYMMDD_HHMMSS.
std::wstring FromJpegExif(const uint8_t* d, size_t n) {
    if (n < 4 || d[0] != 0xFF || d[1] != 0xD8) return L"";
    size_t p = 2;
    while (p + 4 < n && d[p] == 0xFF) {
        uint8_t marker = d[p + 1];
        uint16_t seg = (d[p + 2] << 8) | d[p + 3];
        if (marker == 0xE1 && p + 4 + 6 <= n &&
            !std::memcmp(d + p + 4, "Exif\0\0", 6)) {
            const uint8_t* t = d + p + 10;          // TIFF header start
            size_t tn = (n - (p + 10));
            if (tn < 8) return L"";
            bool le = (t[0] == 'I');
            auto r16 = [&](const uint8_t* x) -> uint16_t {
                return le ? (x[0] | (x[1] << 8)) : ((x[0] << 8) | x[1]); };
            auto r32 = [&](const uint8_t* x) -> uint32_t {
                return le ? (x[0] | (x[1] << 8) | (x[2] << 16) | (uint32_t(x[3]) << 24))
                          : ((uint32_t(x[0]) << 24) | (x[1] << 16) | (x[2] << 8) | x[3]); };
            uint32_t ifd0 = r32(t + 4);
            auto findDate = [&](uint32_t off) -> std::wstring {
                if (off + 2 > tn) return L"";
                uint16_t cnt = r16(t + off);
                for (uint16_t i = 0; i < cnt; ++i) {
                    size_t e = off + 2 + i * 12;
                    if (e + 12 > tn) break;
                    uint16_t tag = r16(t + e);
                    if (tag == 0x0132 || tag == 0x9003) { // DateTime / DTOriginal
                        uint32_t vo = r32(t + e + 8);
                        if (vo + 19 <= tn) {
                            const uint8_t* s = t + vo; // "YYYY:MM:DD HH:MM:SS"
                            wchar_t buf[32];
                            swprintf(buf, 32, L"IMG_%c%c%c%c%c%c%c%c_%c%c%c%c%c%c",
                                s[0], s[1], s[2], s[3], s[5], s[6], s[8], s[9],
                                s[11], s[12], s[14], s[15], s[17], s[18]);
                            return buf;
                        }
                    }
                }
                return L"";
            };
            std::wstring dt = findDate(ifd0);
            if (!dt.empty()) return dt;
            // Follow ExifIFD pointer (tag 0x8769) for DateTimeOriginal.
            if (ifd0 + 2 <= tn) {
                uint16_t cnt = r16(t + ifd0);
                for (uint16_t i = 0; i < cnt; ++i) {
                    size_t e = ifd0 + 2 + i * 12;
                    if (e + 12 > tn) break;
                    if (r16(t + e) == 0x8769) {
                        dt = findDate(r32(t + e + 8));
                        if (!dt.empty()) return dt;
                    }
                }
            }
            return L"";
        }
        if (marker == 0xDA) break;          // start of scan: no more metadata
        p += 2 + seg;
    }
    return L"";
}

} // namespace

std::wstring DeriveCarvedName(const wchar_t* ext, const uint8_t* data,
                              size_t avail) {
    if (!ext || !data || avail == 0) return L"";
    std::wstring e = ext;
    if (e == L"mp3") return Clean(FromId3(data, avail));
    if (e == L"pdf") return FromPdf(data, avail);
    if (e == L"jpg") return FromJpegExif(data, avail);
    return L"";
}
