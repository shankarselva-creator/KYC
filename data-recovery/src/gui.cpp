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
#include <commdlg.h>
#include <thread>
#include <atomic>
#include <mutex>
#include <vector>
#include <string>
#include <cwctype>

#pragma comment(lib, "comctl32.lib")
#pragma comment(lib, "shell32.lib")
#pragma comment(lib, "ole32.lib")
#pragma comment(lib, "comdlg32.lib")

namespace {

enum {
    IDC_COMBO = 1001,
    IDC_REFRESH,
    IDC_UNDELETE,
    IDC_CARVE,
    IDC_PARTITIONS,
    IDC_PREVIEW,
    IDC_RECOVER,
    IDC_RECOVERALL,
    IDC_CANCEL,
    IDC_FILTER,
    IDC_LIST,
    IDC_PROGRESS,
    IDC_STATUS,
    IDM_SAVE,
    IDM_LOAD,
    IDM_EXIT,
};

constexpr UINT WM_APP_ADD      = WM_APP + 1; // wParam = result index
constexpr UINT WM_APP_PROGRESS = WM_APP + 2; // wParam = pct, lParam = wstring*
constexpr UINT WM_APP_DONE     = WM_APP + 3;

HWND g_main = nullptr, g_combo, g_list, g_progress, g_status, g_filter;
HWND g_btnUndelete, g_btnCarve, g_btnPartitions, g_btnRecover, g_btnRefresh,
     g_btnPreview, g_btnCancel, g_btnRecoverAll;

std::wstring               g_activeDevicePath; // device the results belong to
std::wstring               g_filterText;       // current lowercase filter
std::vector<DiskInfo>      g_disks;
std::vector<RecoveredFile> g_results;
std::mutex                 g_resultsMutex;
std::atomic<bool>          g_cancel{false};
std::atomic<bool>          g_running{false};
std::thread                g_worker;
ULONGLONG                  g_scanStart = 0;    // GetTickCount64 at scan start
int                        g_sortCol = -1;     // column being sorted
bool                       g_sortAsc = true;

std::wstring ToLower(std::wstring s) {
    for (wchar_t& c : s)
        c = static_cast<wchar_t>(towlower(c));
    return s;
}

std::wstring FormatDuration(uint64_t seconds) {
    uint64_t m = seconds / 60, s = seconds % 60;
    wchar_t buf[32];
    if (m >= 60) {
        uint64_t h = m / 60; m %= 60;
        swprintf(buf, 32, L"%lluh%02llum", h, m);
    } else {
        swprintf(buf, 32, L"%llum%02llus", m, s);
    }
    return buf;
}

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
    EnableWindow(g_btnRecoverAll, enable);
    EnableWindow(g_btnPreview, enable);
    EnableWindow(g_btnRefresh, enable);
    EnableWindow(g_combo, enable);
    EnableWindow(g_btnCancel, !enable); // cancel is active only during a scan
}

// Device that the current results belong to (from a scan or a loaded file),
// falling back to the combo box selection.
std::wstring ActiveDevice() {
    if (!g_activeDevicePath.empty())
        return g_activeDevicePath;
    int sel = static_cast<int>(SendMessageW(g_combo, CB_GETCURSEL, 0, 0));
    if (sel >= 0 && sel < static_cast<int>(g_disks.size()))
        return g_disks[sel].path;
    return L"";
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

bool MatchesFilter(const RecoveredFile& rf) {
    if (g_filterText.empty())
        return true;
    return ToLower(rf.name).find(g_filterText) != std::wstring::npos ||
           ToLower(rf.source).find(g_filterText) != std::wstring::npos;
}

// Insert one result (by index into g_results) into the list view, honoring the
// active filter.
void AddRow(int index) {
    RecoveredFile rf;
    {
        std::lock_guard<std::mutex> lk(g_resultsMutex);
        if (index < 0 || index >= static_cast<int>(g_results.size()))
            return;
        rf = g_results[index];
    }
    if (!MatchesFilter(rf))
        return;

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

// Posted from the worker thread for each discovered file.
void OnAddResult(int index) { AddRow(index); }

// Re-apply the current filter to all results.
void RebuildList() {
    g_filterText = ToLower(g_filterText);
    ListView_DeleteAllItems(g_list);
    int n;
    {
        std::lock_guard<std::mutex> lk(g_resultsMutex);
        n = static_cast<int>(g_results.size());
    }
    for (int i = 0; i < n; ++i)
        AddRow(i);
}

void OnFilterChanged() {
    wchar_t buf[256];
    GetWindowTextW(g_filter, buf, 256);
    g_filterText = ToLower(buf);
    RebuildList();
}

// Sort comparator: l1/l2 are item lParams (indices into g_results).
int CALLBACK CompareResults(LPARAM l1, LPARAM l2, LPARAM) {
    RecoveredFile a, b;
    {
        std::lock_guard<std::mutex> lk(g_resultsMutex);
        int i1 = static_cast<int>(l1), i2 = static_cast<int>(l2);
        int n = static_cast<int>(g_results.size());
        if (i1 < 0 || i1 >= n || i2 < 0 || i2 >= n)
            return 0;
        a = g_results[i1];
        b = g_results[i2];
    }
    int cmp = 0;
    switch (g_sortCol) {
        case 1: cmp = (a.size < b.size) ? -1 : (a.size > b.size) ? 1 : 0; break;
        case 2: cmp = static_cast<int>(a.method) - static_cast<int>(b.method); break;
        case 3: cmp = _wcsicmp(a.source.c_str(), b.source.c_str()); break;
        default: cmp = _wcsicmp(a.name.c_str(), b.name.c_str()); break;
    }
    return g_sortAsc ? cmp : -cmp;
}

void SortByColumn(int col) {
    if (g_sortCol == col)
        g_sortAsc = !g_sortAsc;     // toggle direction on repeat click
    else {
        g_sortCol = col;
        g_sortAsc = true;
    }
    ListView_SortItems(g_list, CompareResults, 0);
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
    g_scanStart = GetTickCount64();
    EnableScanButtons(false);
    DiskInfo info = g_disks[sel];
    g_activeDevicePath = info.path;
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

// Recover a set of result indices to a user-chosen folder.
void RecoverIndices(const std::vector<int>& indices) {
    if (indices.empty()) {
        SetStatus(L"Nothing to recover.");
        return;
    }
    std::wstring device = ActiveDevice();
    if (device.empty())
        return;
    std::wstring folder = PickFolder(g_main);
    if (folder.empty())
        return;

    Disk disk;
    if (!disk.open(device)) {
        SetStatus(L"Cannot reopen device for recovery.");
        return;
    }

    int ok = 0, fail = 0, dup = 0;
    for (int idx : indices) {
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
        // Avoid clobbering identical names: prefix with the result index.
        wchar_t prefix[16];
        swprintf(prefix, 16, L"%05d_", idx);
        std::wstring out = folder + L"\\" + prefix + SanitizeFileName(rf.name);
        if (RecoverFile(disk, rf, out) >= 0) ++ok; else ++fail;
    }
    (void)dup;
    wchar_t msg[160];
    swprintf(msg, 160, L"Recovered %d file(s), %d failed -> %s", ok, fail,
             folder.c_str());
    SetStatus(msg);
}

void RecoverSelected() {
    if (ListView_GetSelectedCount(g_list) == 0) {
        SetStatus(L"Select one or more files in the list first.");
        return;
    }
    std::vector<int> indices;
    int item = -1;
    while ((item = ListView_GetNextItem(g_list, item, LVNI_SELECTED)) != -1) {
        LVITEMW q{};
        q.mask = LVIF_PARAM;
        q.iItem = item;
        ListView_GetItem(g_list, &q);
        indices.push_back(static_cast<int>(q.lParam));
    }
    RecoverIndices(indices);
}

// Recover everything currently shown in the list (i.e. matching the filter).
void RecoverAll() {
    int n = ListView_GetItemCount(g_list);
    if (n == 0) {
        SetStatus(L"No files to recover.");
        return;
    }
    std::vector<int> indices;
    for (int i = 0; i < n; ++i) {
        LVITEMW q{};
        q.mask = LVIF_PARAM;
        q.iItem = i;
        ListView_GetItem(g_list, &q);
        indices.push_back(static_cast<int>(q.lParam));
    }
    RecoverIndices(indices);
}

void PreviewSelected() {
    int item = ListView_GetNextItem(g_list, -1, LVNI_SELECTED);
    if (item < 0) {
        SetStatus(L"Select a file in the list to preview.");
        return;
    }
    std::wstring device = ActiveDevice();
    if (device.empty())
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
    ShowPreview(g_main, device, rf);
}

void OnCancel() {
    if (g_running.load()) {
        g_cancel.store(true);
        SetStatus(L"Cancelling...");
    }
}

std::wstring RunFileDialog(bool save) {
    wchar_t file[MAX_PATH] = L"scan.drsv";
    OPENFILENAMEW ofn{};
    ofn.lStructSize = sizeof(ofn);
    ofn.hwndOwner = g_main;
    ofn.lpstrFilter = L"Recovery scan (*.drsv)\0*.drsv\0All files\0*.*\0";
    ofn.lpstrFile = file;
    ofn.nMaxFile = MAX_PATH;
    ofn.lpstrDefExt = L"drsv";
    ofn.Flags = save ? (OFN_OVERWRITEPROMPT | OFN_PATHMUSTEXIST)
                     : (OFN_FILEMUSTEXIST | OFN_PATHMUSTEXIST);
    BOOL ok = save ? GetSaveFileNameW(&ofn) : GetOpenFileNameW(&ofn);
    return ok ? std::wstring(file) : std::wstring();
}

void SaveResultsCmd() {
    if (g_running.load()) return;
    std::vector<RecoveredFile> snapshot;
    {
        std::lock_guard<std::mutex> lk(g_resultsMutex);
        snapshot = g_results;
    }
    if (snapshot.empty()) {
        SetStatus(L"Nothing to save - run a scan first.");
        return;
    }
    std::wstring path = RunFileDialog(true);
    if (path.empty()) return;
    if (SaveResults(path, g_activeDevicePath, snapshot))
        SetStatus(L"Saved " + std::to_wstring(snapshot.size()) +
                  L" results to " + path);
    else
        SetStatus(L"Failed to save results.");
}

void LoadResultsCmd() {
    if (g_running.load()) return;
    std::wstring path = RunFileDialog(false);
    if (path.empty()) return;

    std::wstring device;
    std::vector<RecoveredFile> loaded;
    if (!LoadResults(path, device, loaded)) {
        SetStatus(L"Failed to load (not a valid .drsv file).");
        return;
    }
    ClearResults();
    g_activeDevicePath = device;
    {
        std::lock_guard<std::mutex> lk(g_resultsMutex);
        g_results = std::move(loaded);
    }
    int n;
    {
        std::lock_guard<std::mutex> lk(g_resultsMutex);
        n = static_cast<int>(g_results.size());
    }
    for (int i = 0; i < n; ++i)
        OnAddResult(i);
    SetStatus(L"Loaded " + std::to_wstring(n) + L" results (device " +
              device + L")");
}

void CreateControls(HWND hwnd) {
    g_combo = CreateWindowW(L"COMBOBOX", L"",
        WS_CHILD | WS_VISIBLE | CBS_DROPDOWNLIST | WS_VSCROLL,
        10, 10, 560, 300, hwnd, (HMENU)IDC_COMBO, nullptr, nullptr);
    g_btnRefresh = CreateWindowW(L"BUTTON", L"Refresh",
        WS_CHILD | WS_VISIBLE, 580, 9, 90, 26, hwnd, (HMENU)IDC_REFRESH,
        nullptr, nullptr);

    g_btnUndelete = CreateWindowW(L"BUTTON", L"Undelete Scan",
        WS_CHILD | WS_VISIBLE, 10, 46, 110, 28, hwnd, (HMENU)IDC_UNDELETE,
        nullptr, nullptr);
    g_btnCarve = CreateWindowW(L"BUTTON", L"Deep Scan",
        WS_CHILD | WS_VISIBLE, 124, 46, 100, 28, hwnd, (HMENU)IDC_CARVE,
        nullptr, nullptr);
    g_btnPartitions = CreateWindowW(L"BUTTON", L"Partitions",
        WS_CHILD | WS_VISIBLE, 228, 46, 90, 28, hwnd, (HMENU)IDC_PARTITIONS,
        nullptr, nullptr);
    g_btnPreview = CreateWindowW(L"BUTTON", L"Preview",
        WS_CHILD | WS_VISIBLE, 322, 46, 80, 28, hwnd, (HMENU)IDC_PREVIEW,
        nullptr, nullptr);
    g_btnRecover = CreateWindowW(L"BUTTON", L"Recover Selected...",
        WS_CHILD | WS_VISIBLE, 406, 46, 130, 28, hwnd, (HMENU)IDC_RECOVER,
        nullptr, nullptr);
    g_btnCancel = CreateWindowW(L"BUTTON", L"Cancel",
        WS_CHILD | WS_VISIBLE, 540, 46, 80, 28, hwnd, (HMENU)IDC_CANCEL,
        nullptr, nullptr);
    EnableWindow(g_btnCancel, FALSE);

    // Filter row.
    CreateWindowW(L"STATIC", L"Filter:", WS_CHILD | WS_VISIBLE,
        10, 86, 42, 20, hwnd, nullptr, nullptr, nullptr);
    g_filter = CreateWindowW(L"EDIT", L"",
        WS_CHILD | WS_VISIBLE | WS_BORDER | ES_AUTOHSCROLL,
        54, 82, 360, 24, hwnd, (HMENU)IDC_FILTER, nullptr, nullptr);
    g_btnRecoverAll = CreateWindowW(L"BUTTON", L"Recover All",
        WS_CHILD | WS_VISIBLE, 540, 81, 130, 26, hwnd, (HMENU)IDC_RECOVERALL,
        nullptr, nullptr);

    g_list = CreateWindowW(WC_LISTVIEWW, L"",
        WS_CHILD | WS_VISIBLE | LVS_REPORT | WS_BORDER,
        10, 114, 660, 348, hwnd, (HMENU)IDC_LIST, nullptr, nullptr);
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
        WS_CHILD | WS_VISIBLE, 10, 470, 660, 18, hwnd, (HMENU)IDC_PROGRESS,
        nullptr, nullptr);
    SendMessageW(g_progress, PBM_SETRANGE, 0, MAKELPARAM(0, 100));

    g_status = CreateWindowW(L"STATIC", L"Ready. Run as Administrator for raw "
        L"disk access.", WS_CHILD | WS_VISIBLE,
        10, 494, 660, 20, hwnd, (HMENU)IDC_STATUS, nullptr, nullptr);
}

LRESULT CALLBACK WndProc(HWND hwnd, UINT msg, WPARAM wParam, LPARAM lParam) {
    switch (msg) {
    case WM_CREATE: {
        HMENU bar = CreateMenu();
        HMENU fileMenu = CreatePopupMenu();
        AppendMenuW(fileMenu, MF_STRING, IDM_SAVE, L"&Save Results...");
        AppendMenuW(fileMenu, MF_STRING, IDM_LOAD, L"&Load Results...");
        AppendMenuW(fileMenu, MF_SEPARATOR, 0, nullptr);
        AppendMenuW(fileMenu, MF_STRING, IDM_EXIT, L"E&xit");
        AppendMenuW(bar, MF_POPUP, (UINT_PTR)fileMenu, L"&File");
        SetMenu(hwnd, bar);
        CreateControls(hwnd);
        PopulateDevices();
        return 0;
    }

    case WM_COMMAND:
        switch (LOWORD(wParam)) {
        case IDC_REFRESH:    PopulateDevices(); return 0;
        case IDC_UNDELETE:   StartScan(0); return 0;
        case IDC_CARVE:      StartScan(1); return 0;
        case IDC_PARTITIONS: StartScan(2); return 0;
        case IDC_PREVIEW:    PreviewSelected(); return 0;
        case IDC_RECOVER:    RecoverSelected(); return 0;
        case IDC_RECOVERALL: RecoverAll(); return 0;
        case IDC_CANCEL:     OnCancel(); return 0;
        case IDC_FILTER:
            if (HIWORD(wParam) == EN_CHANGE) OnFilterChanged();
            return 0;
        case IDM_SAVE:       SaveResultsCmd(); return 0;
        case IDM_LOAD:       LoadResultsCmd(); return 0;
        case IDM_EXIT:       SendMessageW(hwnd, WM_CLOSE, 0, 0); return 0;
        }
        return 0;

    case WM_NOTIFY: {
        auto* nm = reinterpret_cast<LPNMHDR>(lParam);
        if (nm->idFrom == IDC_LIST && nm->code == NM_DBLCLK) {
            PreviewSelected();
            return 0;
        }
        if (nm->idFrom == IDC_LIST && nm->code == LVN_COLUMNCLICK) {
            auto* lv = reinterpret_cast<LPNMLISTVIEW>(lParam);
            SortByColumn(lv->iSubItem);
            return 0;
        }
        return 0;
    }

    case WM_APP_ADD:
        OnAddResult(static_cast<int>(wParam));
        return 0;

    case WM_APP_PROGRESS: {
        int pct = static_cast<int>(wParam);
        SendMessageW(g_progress, PBM_SETPOS, wParam, 0);
        auto* s = reinterpret_cast<std::wstring*>(lParam);
        if (s) {
            std::wstring text = *s;
            delete s;
            if (g_running.load() && g_scanStart) {
                uint64_t elapsed = (GetTickCount64() - g_scanStart) / 1000;
                std::wstring suffix = L"  [" + FormatDuration(elapsed);
                if (pct > 0 && pct < 100) {
                    uint64_t eta = elapsed * (100 - pct) / pct;
                    suffix += L" elapsed, ~" + FormatDuration(eta) + L" left";
                } else {
                    suffix += L" elapsed";
                }
                suffix += L"]";
                text += suffix;
            }
            SetStatus(text);
        }
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
        CW_USEDEFAULT, CW_USEDEFAULT, 700, 605,
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
