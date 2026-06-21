// gui.cpp - Win32 GUI front-end for the recovery engine.
//
// Layout: a device combo box, Refresh / Undelete Scan / Deep Scan / Recover
// buttons, a results list view, a progress bar and a status line. Scans run on a
// worker thread and post results back to the UI thread.
#include "recovery.h"
#include "preview.h"
#include <windows.h>
#include <commctrl.h>
#include <shlobj.h>
#include <thread>
#include <atomic>
#include <mutex>
#include <vector>
#include <string>

#pragma comment(lib, "comctl32.lib")
#pragma comment(lib, "shell32.lib")
#pragma comment(lib, "ole32.lib")

namespace {

enum {
    IDC_COMBO = 1001,
    IDC_REFRESH,
    IDC_UNDELETE,
    IDC_CARVE,
    IDC_PARTITIONS,
    IDC_PREVIEW,
    IDC_RECOVER,
    IDC_LIST,
    IDC_PROGRESS,
    IDC_STATUS,
};

constexpr UINT WM_APP_ADD      = WM_APP + 1; // wParam = result index
constexpr UINT WM_APP_PROGRESS = WM_APP + 2; // wParam = pct, lParam = wstring*
constexpr UINT WM_APP_DONE     = WM_APP + 3;

HWND g_main = nullptr, g_combo, g_list, g_progress, g_status;
HWND g_btnUndelete, g_btnCarve, g_btnPartitions, g_btnRecover, g_btnRefresh,
     g_btnPreview;

std::vector<DiskInfo>      g_disks;
std::vector<RecoveredFile> g_results;
std::mutex                 g_resultsMutex;
std::atomic<bool>          g_cancel{false};
std::atomic<bool>          g_running{false};
std::thread                g_worker;

std::wstring HumanSize(uint64_t bytes) {
    const wchar_t* units[] = {L"B", L"KB", L"MB", L"GB", L"TB"};
    double v = static_cast<double>(bytes);
    int u = 0;
    while (v >= 1024.0 && u < 4) { v /= 1024.0; ++u; }
    wchar_t buf[48];
    swprintf(buf, 48, L"%.1f %s", v, units[u]);
    return buf;
}

void SetStatus(const std::wstring& s) { SetWindowTextW(g_status, s.c_str()); }

void EnableScanButtons(bool enable) {
    EnableWindow(g_btnUndelete, enable);
    EnableWindow(g_btnCarve, enable);
    EnableWindow(g_btnPartitions, enable);
    EnableWindow(g_btnRecover, enable);
    EnableWindow(g_btnPreview, enable);
    EnableWindow(g_btnRefresh, enable);
    EnableWindow(g_combo, enable);
}

void PopulateDevices() {
    SendMessageW(g_combo, CB_RESETCONTENT, 0, 0);
    g_disks = EnumerateDisks();
    for (const auto& d : g_disks) {
        std::wstring label = d.path + L"  [" + d.model + L"]  " +
                             HumanSize(d.sizeBytes);
        SendMessageW(g_combo, CB_ADDSTRING, 0,
                     reinterpret_cast<LPARAM>(label.c_str()));
    }
    if (!g_disks.empty())
        SendMessageW(g_combo, CB_SETCURSEL, 0, 0);
}

void ClearResults() {
    ListView_DeleteAllItems(g_list);
    std::lock_guard<std::mutex> lk(g_resultsMutex);
    g_results.clear();
}

// Posted from the worker thread for each discovered file.
void OnAddResult(int index) {
    RecoveredFile rf;
    {
        std::lock_guard<std::mutex> lk(g_resultsMutex);
        if (index < 0 || index >= static_cast<int>(g_results.size()))
            return;
        rf = g_results[index];
    }
    LVITEMW it{};
    it.mask = LVIF_TEXT | LVIF_PARAM;
    it.iItem = ListView_GetItemCount(g_list);
    it.lParam = index;
    std::wstring name = rf.name;
    it.pszText = const_cast<wchar_t*>(name.c_str());
    int row = ListView_InsertItem(g_list, &it);

    std::wstring sz = HumanSize(rf.size);
    ListView_SetItemText(g_list, row, 1, const_cast<wchar_t*>(sz.c_str()));

    const wchar_t* method =
        rf.method == RecMethod::NtfsUndelete ? L"Undelete" : L"Carved";
    ListView_SetItemText(g_list, row, 2, const_cast<wchar_t*>(method));
    ListView_SetItemText(g_list, row, 3,
                         const_cast<wchar_t*>(rf.source.c_str()));
}

// Marshal a result discovered on the worker thread to the UI thread.
ResultFn MakeResultSink() {
    return [](const RecoveredFile& rf) {
        int index;
        {
            std::lock_guard<std::mutex> lk(g_resultsMutex);
            g_results.push_back(rf);
            index = static_cast<int>(g_results.size()) - 1;
        }
        PostMessageW(g_main, WM_APP_ADD, static_cast<WPARAM>(index), 0);
    };
}

ProgressFn MakeProgressSink() {
    return [](int pct, const std::wstring& text) -> bool {
        auto* s = new std::wstring(text);
        PostMessageW(g_main, WM_APP_PROGRESS, static_cast<WPARAM>(pct),
                     reinterpret_cast<LPARAM>(s));
        return !g_cancel.load();
    };
}

void RunUndelete(DiskInfo info) {
    Disk disk;
    if (!disk.open(info.path)) {
        MakeProgressSink()(100, L"Failed to open device (run as Administrator)");
        PostMessageW(g_main, WM_APP_DONE, 0, 0);
        return;
    }
    auto progress = MakeProgressSink();
    auto sink = MakeResultSink();

    if (info.isPhysical) {
        auto parts = ScanPartitions(disk);
        if (parts.empty()) {
            ScanDeletedAuto(disk, 0, L"whole disk", progress, sink);
        } else {
            for (const auto& p : parts) {
                if (g_cancel.load()) break;
                wchar_t lbl[64];
                swprintf(lbl, 64, L"partition %d (%s)", p.index, p.type.c_str());
                ScanDeletedAuto(disk, p.startOffset, lbl, progress, sink);
            }
        }
    } else {
        ScanDeletedAuto(disk, 0, L"volume", progress, sink);
    }
    PostMessageW(g_main, WM_APP_DONE, 0, 0);
}

void RunCarve(DiskInfo info) {
    Disk disk;
    if (!disk.open(info.path)) {
        MakeProgressSink()(100, L"Failed to open device (run as Administrator)");
        PostMessageW(g_main, WM_APP_DONE, 0, 0);
        return;
    }
    ScanCarve(disk, 0, 0, MakeProgressSink(), MakeResultSink());
    PostMessageW(g_main, WM_APP_DONE, 0, 0);
}

void RunPartitions(DiskInfo info) {
    Disk disk;
    auto progress = MakeProgressSink();
    if (!disk.open(info.path)) {
        progress(100, L"Failed to open device (run as Administrator)");
        PostMessageW(g_main, WM_APP_DONE, 0, 0);
        return;
    }
    auto parts = ScanPartitions(disk);
    auto sink = MakeResultSink();
    for (const auto& p : parts) {
        RecoveredFile rf;
        rf.method = RecMethod::NtfsUndelete;
        rf.size = p.sizeBytes;
        wchar_t nm[96];
        swprintf(nm, 96, L"Partition %d  start=%llu", p.index,
                 (unsigned long long)p.startOffset);
        rf.name = nm;
        rf.source = p.scheme + L" / " + p.type;
        sink(rf);
    }
    progress(100, parts.empty() ? L"No partitions found"
                                : L"Partition scan complete");
    PostMessageW(g_main, WM_APP_DONE, 0, 0);
}

void StartScan(int mode) { // 0 = undelete, 1 = carve, 2 = partitions
    if (g_running.load())
        return;
    int sel = static_cast<int>(SendMessageW(g_combo, CB_GETCURSEL, 0, 0));
    if (sel < 0 || sel >= static_cast<int>(g_disks.size())) {
        SetStatus(L"Select a device first.");
        return;
    }
    ClearResults();
    g_cancel.store(false);
    g_running.store(true);
    EnableScanButtons(false);
    DiskInfo info = g_disks[sel];
    if (g_worker.joinable())
        g_worker.join();
    if (mode == 0)      g_worker = std::thread(RunUndelete, info);
    else if (mode == 1) g_worker = std::thread(RunCarve, info);
    else                g_worker = std::thread(RunPartitions, info);
}

std::wstring PickFolder(HWND owner) {
    wchar_t path[MAX_PATH] = {0};
    BROWSEINFOW bi{};
    bi.hwndOwner = owner;
    bi.lpszTitle = L"Choose a folder to recover files into";
    bi.ulFlags = BIF_RETURNONLYFSDIRS | BIF_NEWDIALOGSTYLE;
    LPITEMIDLIST pidl = SHBrowseForFolderW(&bi);
    if (!pidl)
        return L"";
    SHGetPathFromIDListW(pidl, path);
    CoTaskMemFree(pidl);
    return path;
}

void RecoverSelected() {
    int count = ListView_GetSelectedCount(g_list);
    if (count == 0) {
        SetStatus(L"Select one or more files in the list first.");
        return;
    }
    int sel = static_cast<int>(SendMessageW(g_combo, CB_GETCURSEL, 0, 0));
    if (sel < 0 || sel >= static_cast<int>(g_disks.size()))
        return;
    std::wstring folder = PickFolder(g_main);
    if (folder.empty())
        return;

    Disk disk;
    if (!disk.open(g_disks[sel].path)) {
        SetStatus(L"Cannot reopen device for recovery.");
        return;
    }

    int ok = 0, fail = 0;
    int item = -1;
    while ((item = ListView_GetNextItem(g_list, item, LVNI_SELECTED)) != -1) {
        LVITEMW q{};
        q.mask = LVIF_PARAM;
        q.iItem = item;
        ListView_GetItem(g_list, &q);
        int idx = static_cast<int>(q.lParam);

        RecoveredFile rf;
        {
            std::lock_guard<std::mutex> lk(g_resultsMutex);
            if (idx < 0 || idx >= static_cast<int>(g_results.size()))
                continue;
            rf = g_results[idx];
        }
        if (rf.method == RecMethod::NtfsUndelete && rf.extents.empty() &&
            !rf.resident && rf.source.find(L'/') != std::wstring::npos) {
            continue; // partition pseudo-entry, not a recoverable file
        }
        std::wstring out = folder + L"\\" + SanitizeFileName(rf.name);
        if (RecoverFile(disk, rf, out) >= 0) ++ok; else ++fail;
    }
    wchar_t msg[128];
    swprintf(msg, 128, L"Recovered %d file(s), %d failed -> %s", ok, fail,
             folder.c_str());
    SetStatus(msg);
}

void PreviewSelected() {
    int item = ListView_GetNextItem(g_list, -1, LVNI_SELECTED);
    if (item < 0) {
        SetStatus(L"Select a file in the list to preview.");
        return;
    }
    int sel = static_cast<int>(SendMessageW(g_combo, CB_GETCURSEL, 0, 0));
    if (sel < 0 || sel >= static_cast<int>(g_disks.size()))
        return;

    LVITEMW q{};
    q.mask = LVIF_PARAM;
    q.iItem = item;
    ListView_GetItem(g_list, &q);
    int idx = static_cast<int>(q.lParam);

    RecoveredFile rf;
    {
        std::lock_guard<std::mutex> lk(g_resultsMutex);
        if (idx < 0 || idx >= static_cast<int>(g_results.size()))
            return;
        rf = g_results[idx];
    }
    ShowPreview(g_main, g_disks[sel].path, rf);
}

void CreateControls(HWND hwnd) {
    g_combo = CreateWindowW(L"COMBOBOX", L"",
        WS_CHILD | WS_VISIBLE | CBS_DROPDOWNLIST | WS_VSCROLL,
        10, 10, 560, 300, hwnd, (HMENU)IDC_COMBO, nullptr, nullptr);
    g_btnRefresh = CreateWindowW(L"BUTTON", L"Refresh",
        WS_CHILD | WS_VISIBLE, 580, 9, 90, 26, hwnd, (HMENU)IDC_REFRESH,
        nullptr, nullptr);

    g_btnUndelete = CreateWindowW(L"BUTTON", L"Undelete Scan",
        WS_CHILD | WS_VISIBLE, 10, 46, 130, 28, hwnd, (HMENU)IDC_UNDELETE,
        nullptr, nullptr);
    g_btnCarve = CreateWindowW(L"BUTTON", L"Deep Scan (Carve)",
        WS_CHILD | WS_VISIBLE, 150, 46, 150, 28, hwnd, (HMENU)IDC_CARVE,
        nullptr, nullptr);
    g_btnPartitions = CreateWindowW(L"BUTTON", L"Scan Partitions",
        WS_CHILD | WS_VISIBLE, 310, 46, 120, 28, hwnd, (HMENU)IDC_PARTITIONS,
        nullptr, nullptr);
    g_btnPreview = CreateWindowW(L"BUTTON", L"Preview",
        WS_CHILD | WS_VISIBLE, 436, 46, 90, 28, hwnd, (HMENU)IDC_PREVIEW,
        nullptr, nullptr);
    g_btnRecover = CreateWindowW(L"BUTTON", L"Recover Selected...",
        WS_CHILD | WS_VISIBLE, 532, 46, 138, 28, hwnd, (HMENU)IDC_RECOVER,
        nullptr, nullptr);

    g_list = CreateWindowW(WC_LISTVIEWW, L"",
        WS_CHILD | WS_VISIBLE | LVS_REPORT | WS_BORDER,
        10, 86, 660, 380, hwnd, (HMENU)IDC_LIST, nullptr, nullptr);
    ListView_SetExtendedListViewStyle(g_list,
        LVS_EX_FULLROWSELECT | LVS_EX_GRIDLINES);

    LVCOLUMNW col{};
    col.mask = LVCF_TEXT | LVCF_WIDTH | LVCF_SUBITEM;
    struct { const wchar_t* t; int w; } cols[] = {
        {L"Name", 300}, {L"Size", 100}, {L"Method", 100}, {L"Source", 150}};
    for (int i = 0; i < 4; ++i) {
        col.iSubItem = i;
        col.cx = cols[i].w;
        col.pszText = const_cast<wchar_t*>(cols[i].t);
        ListView_InsertColumn(g_list, i, &col);
    }

    g_progress = CreateWindowW(PROGRESS_CLASSW, L"",
        WS_CHILD | WS_VISIBLE, 10, 476, 660, 18, hwnd, (HMENU)IDC_PROGRESS,
        nullptr, nullptr);
    SendMessageW(g_progress, PBM_SETRANGE, 0, MAKELPARAM(0, 100));

    g_status = CreateWindowW(L"STATIC", L"Ready. Run as Administrator for raw "
        L"disk access.", WS_CHILD | WS_VISIBLE,
        10, 500, 660, 20, hwnd, (HMENU)IDC_STATUS, nullptr, nullptr);
}

LRESULT CALLBACK WndProc(HWND hwnd, UINT msg, WPARAM wParam, LPARAM lParam) {
    switch (msg) {
    case WM_CREATE:
        CreateControls(hwnd);
        PopulateDevices();
        return 0;

    case WM_COMMAND:
        switch (LOWORD(wParam)) {
        case IDC_REFRESH:    PopulateDevices(); return 0;
        case IDC_UNDELETE:   StartScan(0); return 0;
        case IDC_CARVE:      StartScan(1); return 0;
        case IDC_PARTITIONS: StartScan(2); return 0;
        case IDC_PREVIEW:    PreviewSelected(); return 0;
        case IDC_RECOVER:    RecoverSelected(); return 0;
        }
        return 0;

    case WM_NOTIFY: {
        auto* nm = reinterpret_cast<LPNMHDR>(lParam);
        if (nm->idFrom == IDC_LIST && nm->code == NM_DBLCLK) {
            PreviewSelected();
            return 0;
        }
        return 0;
    }

    case WM_APP_ADD:
        OnAddResult(static_cast<int>(wParam));
        return 0;

    case WM_APP_PROGRESS: {
        SendMessageW(g_progress, PBM_SETPOS, wParam, 0);
        auto* s = reinterpret_cast<std::wstring*>(lParam);
        if (s) { SetStatus(*s); delete s; }
        return 0;
    }

    case WM_APP_DONE:
        g_running.store(false);
        EnableScanButtons(true);
        if (g_worker.joinable())
            g_worker.detach();
        return 0;

    case WM_CLOSE:
        g_cancel.store(true);
        if (g_worker.joinable())
            g_worker.join();
        DestroyWindow(hwnd);
        return 0;

    case WM_DESTROY:
        PostQuitMessage(0);
        return 0;
    }
    return DefWindowProcW(hwnd, msg, wParam, lParam);
}

} // namespace

int WINAPI wWinMain(HINSTANCE hInst, HINSTANCE, PWSTR, int nShow) {
    INITCOMMONCONTROLSEX icc{sizeof(icc),
        ICC_LISTVIEW_CLASSES | ICC_PROGRESS_CLASS | ICC_STANDARD_CLASSES};
    InitCommonControlsEx(&icc);
    CoInitializeEx(nullptr, COINIT_APARTMENTTHREADED);
    PreviewInit();

    const wchar_t* kClass = L"DataRecoveryWnd";
    WNDCLASSW wc{};
    wc.lpfnWndProc = WndProc;
    wc.hInstance = hInst;
    wc.lpszClassName = kClass;
    wc.hCursor = LoadCursor(nullptr, IDC_ARROW);
    wc.hbrBackground = (HBRUSH)(COLOR_BTNFACE + 1);
    wc.hIcon = LoadIcon(nullptr, IDI_APPLICATION);
    RegisterClassW(&wc);

    g_main = CreateWindowW(kClass, L"Hard Disk Data Recovery",
        WS_OVERLAPPEDWINDOW & ~WS_THICKFRAME & ~WS_MAXIMIZEBOX,
        CW_USEDEFAULT, CW_USEDEFAULT, 700, 580,
        nullptr, nullptr, hInst, nullptr);
    if (!g_main)
        return 1;
    ShowWindow(g_main, nShow);
    UpdateWindow(g_main);

    MSG m;
    while (GetMessageW(&m, nullptr, 0, 0) > 0) {
        TranslateMessage(&m);
        DispatchMessageW(&m);
    }
    PreviewShutdown();
    CoUninitialize();
    return 0;
}
