// gui.cpp - Win32 GUI front-end for the recovery engine.
//
// Wizard-style layout inspired by friendly consumer recovery tools:
//   Page 1  "Select a location"  - large clickable drive/location cards.
//   Page 2  "Scan & recover"     - category sidebar (Type View), search box,
//                                  sortable results list, progress + ETA, and a
//                                  prominent Recover button.
// Scans run on a worker thread and post results back to the UI thread.
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
    // Page 1
    IDC_TITLE = 1001,
    IDC_DEEP,
    IDC_REFRESH,
    // Page 2
    IDC_BACK,
    IDC_STOP,
    IDC_PROGRESS,
    IDC_STATUS,
    IDC_TREE,
    IDC_FILTER,
    IDC_LIST,
    IDC_RECOVER,
    IDC_RECOVERALL,
    IDC_PREVIEW,
    // menu
    IDM_SAVE,
    IDM_LOAD,
    IDM_EXIT,
    // drive cards start here
    IDC_CARD_BASE = 3000,
};

constexpr UINT WM_APP_ADD      = WM_APP + 1; // wParam = result index
constexpr UINT WM_APP_PROGRESS = WM_APP + 2; // wParam = pct, lParam = wstring*
constexpr UINT WM_APP_DONE     = WM_APP + 3;

enum Category { CAT_ALL, CAT_PHOTO, CAT_VIDEO, CAT_DOC, CAT_AUDIO,
                CAT_ARCHIVE, CAT_OTHER };

HWND g_main = nullptr;
// Page 1 controls
HWND g_title, g_deep, g_refresh;
std::vector<HWND> g_cards;
// Page 2 controls
HWND g_back, g_stop, g_progress, g_status, g_tree, g_filter, g_list,
     g_btnRecover, g_btnRecoverAll, g_btnPreview;

int                        g_page = 1;
std::wstring               g_activeDevicePath;
std::wstring               g_filterText;
Category                   g_catFilter = CAT_ALL;
std::vector<DiskInfo>      g_disks;
std::vector<RecoveredFile> g_results;
std::mutex                 g_resultsMutex;
std::atomic<bool>          g_cancel{false};
std::atomic<bool>          g_running{false};
std::thread                g_worker;
ULONGLONG                  g_scanStart = 0;
int                        g_sortCol = -1;
bool                       g_sortAsc = true;

// --- small helpers ------------------------------------------------------

std::wstring ToLower(std::wstring s) {
    for (wchar_t& c : s) c = static_cast<wchar_t>(towlower(c));
    return s;
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

std::wstring FormatDuration(uint64_t seconds) {
    uint64_t m = seconds / 60, s = seconds % 60;
    wchar_t buf[32];
    if (m >= 60) { uint64_t h = m / 60; m %= 60; swprintf(buf, 32, L"%lluh%02llum", h, m); }
    else swprintf(buf, 32, L"%llum%02llus", m, s);
    return buf;
}

void SetStatus(const std::wstring& s) { SetWindowTextW(g_status, s.c_str()); }

Category CategoryOf(const std::wstring& name) {
    size_t dot = name.find_last_of(L'.');
    std::wstring e = (dot == std::wstring::npos) ? L"" : ToLower(name.substr(dot + 1));
    auto in = [&](std::initializer_list<const wchar_t*> xs) {
        for (auto x : xs) if (e == x) return true;
        return false;
    };
    if (in({L"jpg", L"jpeg", L"png", L"gif", L"bmp", L"tif", L"tiff", L"webp", L"heic"}))
        return CAT_PHOTO;
    if (in({L"mp4", L"mov", L"avi", L"mkv", L"wmv", L"flv", L"m4v"}))
        return CAT_VIDEO;
    if (in({L"pdf", L"doc", L"docx", L"xls", L"xlsx", L"ppt", L"pptx", L"txt",
            L"rtf", L"sqlite"}))
        return CAT_DOC;
    if (in({L"mp3", L"wav", L"flac", L"aac", L"ogg", L"m4a"}))
        return CAT_AUDIO;
    if (in({L"zip", L"rar", L"7z", L"gz", L"tar"}))
        return CAT_ARCHIVE;
    return CAT_OTHER;
}

std::wstring ActiveDevice() {
    return g_activeDevicePath;
}

// --- results list -------------------------------------------------------

bool MatchesFilter(const RecoveredFile& rf) {
    if (g_catFilter != CAT_ALL && CategoryOf(rf.name) != g_catFilter)
        return false;
    if (g_filterText.empty())
        return true;
    return ToLower(rf.name).find(g_filterText) != std::wstring::npos ||
           ToLower(rf.source).find(g_filterText) != std::wstring::npos;
}

void AddRow(int index) {
    RecoveredFile rf;
    {
        std::lock_guard<std::mutex> lk(g_resultsMutex);
        if (index < 0 || index >= static_cast<int>(g_results.size())) return;
        rf = g_results[index];
    }
    if (!MatchesFilter(rf)) return;

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
    ListView_SetItemText(g_list, row, 3, const_cast<wchar_t*>(rf.source.c_str()));
}

void OnAddResult(int index) { AddRow(index); }

void RebuildList() {
    ListView_DeleteAllItems(g_list);
    int n;
    { std::lock_guard<std::mutex> lk(g_resultsMutex); n = static_cast<int>(g_results.size()); }
    for (int i = 0; i < n; ++i) AddRow(i);
    wchar_t st[64];
    swprintf(st, 64, L"%d item(s) shown", ListView_GetItemCount(g_list));
    SetStatus(st);
}

void OnFilterChanged() {
    wchar_t buf[256];
    GetWindowTextW(g_filter, buf, 256);
    g_filterText = ToLower(buf);
    RebuildList();
}

int CALLBACK CompareResults(LPARAM l1, LPARAM l2, LPARAM) {
    RecoveredFile a, b;
    {
        std::lock_guard<std::mutex> lk(g_resultsMutex);
        int i1 = static_cast<int>(l1), i2 = static_cast<int>(l2);
        int n = static_cast<int>(g_results.size());
        if (i1 < 0 || i1 >= n || i2 < 0 || i2 >= n) return 0;
        a = g_results[i1]; b = g_results[i2];
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
    if (g_sortCol == col) g_sortAsc = !g_sortAsc;
    else { g_sortCol = col; g_sortAsc = true; }
    ListView_SortItems(g_list, CompareResults, 0);
}

// --- worker bridge ------------------------------------------------------

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

void RunScan(DiskInfo info, bool deep) {
    Disk disk;
    auto progress = MakeProgressSink();
    auto sink = MakeResultSink();
    if (!disk.open(info.path)) {
        progress(100, L"Failed to open device - run as Administrator");
        PostMessageW(g_main, WM_APP_DONE, 0, 0);
        return;
    }

    // Phase 1: quick undelete scan (per partition for physical disks).
    if (info.isPhysical) {
        auto parts = ScanPartitions(disk);
        if (parts.empty()) {
            ScanDeletedAuto(disk, 0, L"disk", progress, sink);
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

    // Phase 2: optional deep (carving) scan.
    if (deep && !g_cancel.load())
        ScanCarve(disk, 0, 0, progress, sink);

    PostMessageW(g_main, WM_APP_DONE, 0, 0);
}

// --- recovery -----------------------------------------------------------

std::wstring PickFolder(HWND owner) {
    wchar_t path[MAX_PATH] = {0};
    BROWSEINFOW bi{};
    bi.hwndOwner = owner;
    bi.lpszTitle = L"Choose a folder to recover files into (use a different drive)";
    bi.ulFlags = BIF_RETURNONLYFSDIRS | BIF_NEWDIALOGSTYLE;
    LPITEMIDLIST pidl = SHBrowseForFolderW(&bi);
    if (!pidl) return L"";
    SHGetPathFromIDListW(pidl, path);
    CoTaskMemFree(pidl);
    return path;
}

void RecoverIndices(const std::vector<int>& indices) {
    if (indices.empty()) { SetStatus(L"Nothing to recover."); return; }
    std::wstring device = ActiveDevice();
    if (device.empty()) return;
    std::wstring folder = PickFolder(g_main);
    if (folder.empty()) return;

    Disk disk;
    if (!disk.open(device)) { SetStatus(L"Cannot reopen device for recovery."); return; }

    int ok = 0, fail = 0;
    for (int idx : indices) {
        RecoveredFile rf;
        {
            std::lock_guard<std::mutex> lk(g_resultsMutex);
            if (idx < 0 || idx >= static_cast<int>(g_results.size())) continue;
            rf = g_results[idx];
        }
        if (rf.method == RecMethod::NtfsUndelete && rf.extents.empty() &&
            !rf.resident && rf.source.find(L'/') != std::wstring::npos)
            continue;
        wchar_t prefix[16];
        swprintf(prefix, 16, L"%05d_", idx);
        std::wstring out = folder + L"\\" + prefix + SanitizeFileName(rf.name);
        if (RecoverFile(disk, rf, out) >= 0) ++ok; else ++fail;
    }
    wchar_t msg[200];
    swprintf(msg, 200, L"Recovered %d file(s), %d failed  ->  %s", ok, fail,
             folder.c_str());
    SetStatus(msg);
}

std::vector<int> SelectedIndices() {
    std::vector<int> v;
    int item = -1;
    while ((item = ListView_GetNextItem(g_list, item, LVNI_SELECTED)) != -1) {
        LVITEMW q{}; q.mask = LVIF_PARAM; q.iItem = item;
        ListView_GetItem(g_list, &q);
        v.push_back(static_cast<int>(q.lParam));
    }
    return v;
}

void RecoverSelected() {
    auto idx = SelectedIndices();
    if (idx.empty()) { SetStatus(L"Select one or more files first."); return; }
    RecoverIndices(idx);
}

void RecoverAll() {
    int n = ListView_GetItemCount(g_list);
    std::vector<int> idx;
    for (int i = 0; i < n; ++i) {
        LVITEMW q{}; q.mask = LVIF_PARAM; q.iItem = i;
        ListView_GetItem(g_list, &q);
        idx.push_back(static_cast<int>(q.lParam));
    }
    RecoverIndices(idx);
}

void PreviewSelected() {
    int item = ListView_GetNextItem(g_list, -1, LVNI_SELECTED);
    if (item < 0) { SetStatus(L"Select a file to preview."); return; }
    std::wstring device = ActiveDevice();
    if (device.empty()) return;
    LVITEMW q{}; q.mask = LVIF_PARAM; q.iItem = item;
    ListView_GetItem(g_list, &q);
    int idx = static_cast<int>(q.lParam);
    RecoveredFile rf;
    {
        std::lock_guard<std::mutex> lk(g_resultsMutex);
        if (idx < 0 || idx >= static_cast<int>(g_results.size())) return;
        rf = g_results[idx];
    }
    ShowPreview(g_main, device, rf);
}

void OnStop() {
    if (g_running.load()) { g_cancel.store(true); SetStatus(L"Stopping..."); }
}

// --- save / load --------------------------------------------------------

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
    std::vector<RecoveredFile> snap;
    { std::lock_guard<std::mutex> lk(g_resultsMutex); snap = g_results; }
    if (snap.empty()) { SetStatus(L"Nothing to save - run a scan first."); return; }
    std::wstring path = RunFileDialog(true);
    if (path.empty()) return;
    SetStatus(SaveResults(path, g_activeDevicePath, snap)
                  ? L"Saved results to " + path
                  : L"Failed to save results.");
}

void ShowPage(int page); // fwd

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
    g_activeDevicePath = device;
    { std::lock_guard<std::mutex> lk(g_resultsMutex); g_results = std::move(loaded); }
    ShowPage(2);
    RebuildList();
}

// --- pages --------------------------------------------------------------

void BuildLocationCards() {
    for (HWND h : g_cards) DestroyWindow(h);
    g_cards.clear();
    g_disks = EnumerateDisks();

    int x = 20, y = 96, w = 840, h = 56, gap = 10;
    int i = 0;
    for (const auto& d : g_disks) {
        std::wstring text = d.path + L"\n   " + d.model + L"      " +
                            HumanSize(d.sizeBytes) +
                            (d.isPhysical ? L"   (whole disk)" : L"");
        HWND card = CreateWindowW(L"BUTTON", text.c_str(),
            WS_CHILD | WS_VISIBLE | BS_MULTILINE | BS_LEFT | BS_PUSHLIKE,
            x, y + i * (h + gap), w, h, g_main,
            (HMENU)(INT_PTR)(IDC_CARD_BASE + i), nullptr, nullptr);
        g_cards.push_back(card);
        ++i;
    }
    if (g_disks.empty()) {
        HWND none = CreateWindowW(L"STATIC",
            L"No drives found. Launch the app as Administrator.",
            WS_CHILD | WS_VISIBLE, x, y, w, 24, g_main, nullptr, nullptr, nullptr);
        g_cards.push_back(none);
    }
}

void ShowPage(int page) {
    g_page = page;
    int s1 = (page == 1) ? SW_SHOW : SW_HIDE;
    int s2 = (page == 2) ? SW_SHOW : SW_HIDE;
    ShowWindow(g_title, s1); ShowWindow(g_deep, s1); ShowWindow(g_refresh, s1);
    for (HWND h : g_cards) ShowWindow(h, s1);

    HWND p2[] = {g_back, g_stop, g_progress, g_status, g_tree, g_filter,
                 g_list, g_btnRecover, g_btnRecoverAll, g_btnPreview};
    for (HWND h : p2) ShowWindow(h, s2);

    if (page == 1) BuildLocationCards();
}

void StartScanForDisk(int diskIndex) {
    if (g_running.load()) return;
    if (diskIndex < 0 || diskIndex >= static_cast<int>(g_disks.size())) return;
    DiskInfo info = g_disks[diskIndex];
    bool deep = SendMessageW(g_deep, BM_GETCHECK, 0, 0) == BST_CHECKED;

    // reset state
    { std::lock_guard<std::mutex> lk(g_resultsMutex); g_results.clear(); }
    ListView_DeleteAllItems(g_list);
    g_activeDevicePath = info.path;
    g_cancel.store(false);
    g_running.store(true);
    g_scanStart = GetTickCount64();
    SendMessageW(g_progress, PBM_SETPOS, 0, 0);
    EnableWindow(g_stop, TRUE);

    ShowPage(2);
    SetWindowTextW(g_main, (L"Recovering - " + info.path).c_str());

    if (g_worker.joinable()) g_worker.join();
    g_worker = std::thread(RunScan, info, deep);
}

// --- controls -----------------------------------------------------------

void CreateControls(HWND hwnd) {
    // ---- Page 1 ----
    g_title = CreateWindowW(L"STATIC",
        L"Select a location to scan for lost files:",
        WS_CHILD, 20, 20, 600, 28, hwnd, (HMENU)IDC_TITLE, nullptr, nullptr);
    g_deep = CreateWindowW(L"BUTTON", L"Deep scan (slower, finds more by signature)",
        WS_CHILD | BS_AUTOCHECKBOX, 20, 56, 400, 24, hwnd, (HMENU)IDC_DEEP,
        nullptr, nullptr);
    g_refresh = CreateWindowW(L"BUTTON", L"Refresh drives",
        WS_CHILD, 700, 54, 160, 28, hwnd, (HMENU)IDC_REFRESH, nullptr, nullptr);

    // ---- Page 2 ----
    g_back = CreateWindowW(L"BUTTON", L"< Back",
        WS_CHILD, 10, 8, 70, 26, hwnd, (HMENU)IDC_BACK, nullptr, nullptr);
    g_stop = CreateWindowW(L"BUTTON", L"Stop",
        WS_CHILD, 790, 8, 80, 26, hwnd, (HMENU)IDC_STOP, nullptr, nullptr);
    g_progress = CreateWindowW(PROGRESS_CLASSW, L"",
        WS_CHILD, 10, 40, 860, 14, hwnd, (HMENU)IDC_PROGRESS, nullptr, nullptr);
    SendMessageW(g_progress, PBM_SETRANGE, 0, MAKELPARAM(0, 100));
    g_status = CreateWindowW(L"STATIC", L"Ready.",
        WS_CHILD, 10, 58, 860, 18, hwnd, (HMENU)IDC_STATUS, nullptr, nullptr);

    g_tree = CreateWindowW(WC_TREEVIEWW, L"",
        WS_CHILD | WS_BORDER | TVS_HASLINES | TVS_LINESATROOT | TVS_SHOWSELALWAYS,
        10, 84, 180, 410, hwnd, (HMENU)IDC_TREE, nullptr, nullptr);
    struct { const wchar_t* t; Category c; } cats[] = {
        {L"All files", CAT_ALL}, {L"Photos", CAT_PHOTO}, {L"Videos", CAT_VIDEO},
        {L"Documents", CAT_DOC}, {L"Audio", CAT_AUDIO}, {L"Archives", CAT_ARCHIVE},
        {L"Other", CAT_OTHER}};
    for (auto& c : cats) {
        TVINSERTSTRUCTW ins{};
        ins.hParent = TVI_ROOT;
        ins.hInsertAfter = TVI_LAST;
        ins.item.mask = TVIF_TEXT | TVIF_PARAM;
        ins.item.pszText = const_cast<wchar_t*>(c.t);
        ins.item.lParam = c.c;
        TreeView_InsertItem(g_tree, &ins);
    }

    g_filter = CreateWindowW(L"EDIT", L"",
        WS_CHILD | WS_BORDER | ES_AUTOHSCROLL, 200, 84, 670, 24, hwnd,
        (HMENU)IDC_FILTER, nullptr, nullptr);
    SendMessageW(g_filter, EM_SETCUEBANNER, TRUE, (LPARAM)L"Search by name...");

    g_list = CreateWindowW(WC_LISTVIEWW, L"",
        WS_CHILD | LVS_REPORT | WS_BORDER, 200, 114, 670, 372, hwnd,
        (HMENU)IDC_LIST, nullptr, nullptr);
    ListView_SetExtendedListViewStyle(g_list,
        LVS_EX_FULLROWSELECT | LVS_EX_GRIDLINES);
    LVCOLUMNW col{};
    col.mask = LVCF_TEXT | LVCF_WIDTH | LVCF_SUBITEM;
    struct { const wchar_t* t; int w; } cols[] = {
        {L"Name", 320}, {L"Size", 90}, {L"Method", 90}, {L"Source", 160}};
    for (int i = 0; i < 4; ++i) {
        col.iSubItem = i; col.cx = cols[i].w;
        col.pszText = const_cast<wchar_t*>(cols[i].t);
        ListView_InsertColumn(g_list, i, &col);
    }

    g_btnRecover = CreateWindowW(L"BUTTON", L"Recover Selected...",
        WS_CHILD | BS_DEFPUSHBUTTON, 200, 498, 170, 34, hwnd,
        (HMENU)IDC_RECOVER, nullptr, nullptr);
    g_btnRecoverAll = CreateWindowW(L"BUTTON", L"Recover All Shown",
        WS_CHILD, 380, 498, 150, 34, hwnd, (HMENU)IDC_RECOVERALL, nullptr, nullptr);
    g_btnPreview = CreateWindowW(L"BUTTON", L"Preview",
        WS_CHILD, 540, 498, 100, 34, hwnd, (HMENU)IDC_PREVIEW, nullptr, nullptr);
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
        ShowPage(1);
        return 0;
    }

    case WM_COMMAND: {
        int id = LOWORD(wParam);
        if (id >= IDC_CARD_BASE &&
            id < IDC_CARD_BASE + static_cast<int>(g_disks.size())) {
            StartScanForDisk(id - IDC_CARD_BASE);
            return 0;
        }
        switch (id) {
        case IDC_REFRESH:    BuildLocationCards(); return 0;
        case IDC_BACK:
            if (!g_running.load()) { ShowPage(1);
                SetWindowTextW(hwnd, L"Hard Disk Data Recovery"); }
            else SetStatus(L"Stop the scan before going back.");
            return 0;
        case IDC_STOP:       OnStop(); return 0;
        case IDC_RECOVER:    RecoverSelected(); return 0;
        case IDC_RECOVERALL: RecoverAll(); return 0;
        case IDC_PREVIEW:    PreviewSelected(); return 0;
        case IDC_FILTER:
            if (HIWORD(wParam) == EN_CHANGE) OnFilterChanged();
            return 0;
        case IDM_SAVE:       SaveResultsCmd(); return 0;
        case IDM_LOAD:       LoadResultsCmd(); return 0;
        case IDM_EXIT:       SendMessageW(hwnd, WM_CLOSE, 0, 0); return 0;
        }
        return 0;
    }

    case WM_NOTIFY: {
        auto* nm = reinterpret_cast<LPNMHDR>(lParam);
        if (nm->idFrom == IDC_LIST && nm->code == NM_DBLCLK) {
            PreviewSelected(); return 0;
        }
        if (nm->idFrom == IDC_LIST && nm->code == LVN_COLUMNCLICK) {
            SortByColumn(reinterpret_cast<LPNMLISTVIEW>(lParam)->iSubItem);
            return 0;
        }
        if (nm->idFrom == IDC_TREE && nm->code == TVN_SELCHANGEDW) {
            auto* tv = reinterpret_cast<LPNMTREEVIEWW>(lParam);
            g_catFilter = static_cast<Category>(tv->itemNew.lParam);
            RebuildList();
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
            std::wstring text = *s; delete s;
            if (g_running.load() && g_scanStart) {
                uint64_t el = (GetTickCount64() - g_scanStart) / 1000;
                text += L"  [" + FormatDuration(el);
                if (pct > 0 && pct < 100)
                    text += L" elapsed, ~" + FormatDuration(el * (100 - pct) / pct)
                            + L" left]";
                else text += L" elapsed]";
            }
            SetStatus(text);
        }
        return 0;
    }

    case WM_APP_DONE: {
        g_running.store(false);
        EnableWindow(g_stop, FALSE);
        if (g_worker.joinable()) g_worker.detach();
        int n;
        { std::lock_guard<std::mutex> lk(g_resultsMutex); n = (int)g_results.size(); }
        wchar_t st[96];
        swprintf(st, 96, L"Scan complete - %d file(s) found. Select files and click Recover.", n);
        SetStatus(st);
        SendMessageW(g_progress, PBM_SETPOS, 100, 0);
        return 0;
    }

    case WM_CLOSE:
        g_cancel.store(true);
        if (g_worker.joinable()) g_worker.join();
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
        ICC_LISTVIEW_CLASSES | ICC_TREEVIEW_CLASSES | ICC_PROGRESS_CLASS |
        ICC_STANDARD_CLASSES};
    InitCommonControlsEx(&icc);
    CoInitializeEx(nullptr, COINIT_APARTMENTTHREADED);
    PreviewInit();

    const wchar_t* kClass = L"DataRecoveryWnd";
    WNDCLASSW wc{};
    wc.lpfnWndProc = WndProc;
    wc.hInstance = hInst;
    wc.lpszClassName = kClass;
    wc.hCursor = LoadCursor(nullptr, IDC_ARROW);
    wc.hbrBackground = (HBRUSH)(COLOR_WINDOW + 1);
    wc.hIcon = LoadIcon(nullptr, IDI_APPLICATION);
    RegisterClassW(&wc);

    g_main = CreateWindowW(kClass, L"Hard Disk Data Recovery",
        WS_OVERLAPPEDWINDOW & ~WS_THICKFRAME & ~WS_MAXIMIZEBOX,
        CW_USEDEFAULT, CW_USEDEFAULT, 900, 600,
        nullptr, nullptr, hInst, nullptr);
    if (!g_main) return 1;
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
