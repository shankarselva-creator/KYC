// preview.cpp - File preview popup (image via GDI+, otherwise hex dump).
#include "preview.h"
#include <windows.h>
#include <objidl.h>
#include <gdiplus.h>
#include <vector>
#include <string>

#pragma comment(lib, "gdiplus.lib")
#pragma comment(lib, "ole32.lib")

namespace {

ULONG_PTR g_gdiToken = 0;

struct PreviewState {
    Gdiplus::Image* image = nullptr;  // owned; null when showing hex
    IStream*        stream = nullptr; // backs the image; freed with window
};

bool LooksLikeImage(const std::vector<uint8_t>& d) {
    if (d.size() < 4) return false;
    if (d[0] == 0xFF && d[1] == 0xD8 && d[2] == 0xFF) return true;       // jpg
    if (d[0] == 0x89 && d[1] == 'P' && d[2] == 'N' && d[3] == 'G') return true; // png
    if (d[0] == 'G' && d[1] == 'I' && d[2] == 'F') return true;          // gif
    if (d[0] == 'B' && d[1] == 'M') return true;                          // bmp
    return false;
}

std::wstring HexDump(const std::vector<uint8_t>& d) {
    std::wstring out;
    out.reserve(d.size() * 4);
    wchar_t line[128];
    for (size_t i = 0; i < d.size(); i += 16) {
        int n = swprintf(line, 128, L"%08zX  ", i);
        std::wstring text;
        for (size_t j = 0; j < 16; ++j) {
            if (i + j < d.size()) {
                swprintf(line + n, 128 - n, L"%02X ", d[i + j]);
                n += 3;
                uint8_t c = d[i + j];
                text.push_back((c >= 32 && c < 127) ? (wchar_t)c : L'.');
            } else {
                wcscpy(line + n, L"   ");
                n += 3;
            }
        }
        out += line;
        out += L" ";
        out += text;
        out += L"\r\n";
    }
    return out;
}

LRESULT CALLBACK PreviewProc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    auto* st = reinterpret_cast<PreviewState*>(
        GetWindowLongPtr(hwnd, GWLP_USERDATA));
    switch (msg) {
    case WM_PAINT: {
        PAINTSTRUCT ps;
        HDC hdc = BeginPaint(hwnd, &ps);
        if (st && st->image) {
            RECT rc;
            GetClientRect(hwnd, &rc);
            Gdiplus::Graphics g(hdc);
            g.SetInterpolationMode(Gdiplus::InterpolationModeHighQuality);
            UINT iw = st->image->GetWidth(), ih = st->image->GetHeight();
            if (iw && ih) {
                double sx = double(rc.right) / iw, sy = double(rc.bottom) / ih;
                double s = sx < sy ? sx : sy;
                if (s > 1.0) s = 1.0;
                int w = int(iw * s), h = int(ih * s);
                int x = (rc.right - w) / 2, y = (rc.bottom - h) / 2;
                g.DrawImage(st->image, x, y, w, h);
            }
        }
        EndPaint(hwnd, &ps);
        return 0;
    }
    case WM_DESTROY:
        if (st) {
            if (st->image) delete st->image;
            if (st->stream) st->stream->Release();
            delete st;
            SetWindowLongPtr(hwnd, GWLP_USERDATA, 0);
        }
        return 0;
    }
    return DefWindowProcW(hwnd, msg, wp, lp);
}

void EnsureClass(HINSTANCE inst) {
    static bool registered = false;
    if (registered) return;
    WNDCLASSW wc{};
    wc.lpfnWndProc = PreviewProc;
    wc.hInstance = inst;
    wc.lpszClassName = L"DataRecoveryPreview";
    wc.hCursor = LoadCursor(nullptr, IDC_ARROW);
    wc.hbrBackground = (HBRUSH)(COLOR_WINDOW + 1);
    RegisterClassW(&wc);
    registered = true;
}

} // namespace

void PreviewInit() {
    Gdiplus::GdiplusStartupInput in;
    Gdiplus::GdiplusStartup(&g_gdiToken, &in, nullptr);
}

void PreviewShutdown() {
    if (g_gdiToken) {
        Gdiplus::GdiplusShutdown(g_gdiToken);
        g_gdiToken = 0;
    }
}

void ShowPreview(HWND owner, const std::wstring& devicePath,
                 const RecoveredFile& file) {
    Disk disk;
    if (!disk.open(devicePath)) {
        MessageBoxW(owner, L"Cannot open device for preview (Administrator?)",
                    L"Preview", MB_OK | MB_ICONWARNING);
        return;
    }
    // Up to 16 MiB is plenty for a preview.
    std::vector<uint8_t> data =
        ReadRecoveredBytes(disk, file, 16u * 1024 * 1024);
    if (data.empty()) {
        MessageBoxW(owner, L"No readable data for this item.", L"Preview",
                    MB_OK | MB_ICONINFORMATION);
        return;
    }

    HINSTANCE inst = (HINSTANCE)GetWindowLongPtr(owner, GWLP_HINSTANCE);
    EnsureClass(inst);

    auto* st = new PreviewState();
    std::wstring title = L"Preview - " + file.name;

    if (LooksLikeImage(data)) {
        HGLOBAL hg = GlobalAlloc(GMEM_MOVEABLE, data.size());
        if (hg) {
            void* p = GlobalLock(hg);
            memcpy(p, data.data(), data.size());
            GlobalUnlock(hg);
            if (CreateStreamOnHGlobal(hg, TRUE, &st->stream) == S_OK) {
                st->image = Gdiplus::Image::FromStream(st->stream);
                if (st->image &&
                    st->image->GetLastStatus() != Gdiplus::Ok) {
                    delete st->image;
                    st->image = nullptr;
                }
            }
        }
    }

    HWND win = CreateWindowExW(0, L"DataRecoveryPreview", title.c_str(),
        WS_OVERLAPPEDWINDOW, CW_USEDEFAULT, CW_USEDEFAULT, 640, 520,
        owner, nullptr, inst, nullptr);
    if (!win) {
        if (st->image) delete st->image;
        if (st->stream) st->stream->Release();
        delete st;
        return;
    }
    SetWindowLongPtr(win, GWLP_USERDATA, reinterpret_cast<LONG_PTR>(st));

    if (!st->image) {
        // Hex/text fallback in a read-only multiline edit control.
        std::wstring dump = HexDump(data);
        HFONT font = (HFONT)GetStockObject(ANSI_FIXED_FONT);
        RECT rc; GetClientRect(win, &rc);
        HWND edit = CreateWindowW(L"EDIT", dump.c_str(),
            WS_CHILD | WS_VISIBLE | WS_VSCROLL | WS_HSCROLL | ES_MULTILINE |
            ES_READONLY | ES_AUTOVSCROLL | ES_AUTOHSCROLL,
            0, 0, rc.right, rc.bottom, win, nullptr, inst, nullptr);
        SendMessageW(edit, WM_SETFONT, (WPARAM)font, TRUE);
    }

    ShowWindow(win, SW_SHOW);
    UpdateWindow(win);
}
