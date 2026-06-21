// setup.cpp - Self-contained installer for Hard Disk Data Recovery.
//
// The application exe is embedded as an RCDATA resource ("MAINEXE"). Running
// this Setup.exe extracts it to Program Files, creates Start Menu (and optional
// desktop) shortcuts, and registers an Apps & Features entry. Uninstall is
// handled by the installed app itself (DataRecovery.exe --uninstall).
#include <windows.h>
#include <shlobj.h>
#include <knownfolders.h>
#include <string>

namespace {

const wchar_t* kAppName = L"Hard Disk Data Recovery";
const wchar_t* kExeName = L"DataRecovery.exe";
const wchar_t* kRegKey  =
    L"Software\\Microsoft\\Windows\\CurrentVersion\\Uninstall\\HardDiskDataRecovery";

std::wstring KnownFolder(REFKNOWNFOLDERID id) {
    PWSTR p = nullptr;
    std::wstring r;
    if (SUCCEEDED(SHGetKnownFolderPath(id, 0, nullptr, &p))) { r = p; CoTaskMemFree(p); }
    return r;
}

bool ExtractMainExe(const std::wstring& dest) {
    HRSRC res = FindResourceW(nullptr, L"MAINEXE", RT_RCDATA);
    if (!res) return false;
    HGLOBAL h = LoadResource(nullptr, res);
    if (!h) return false;
    void* data = LockResource(h);
    DWORD size = SizeofResource(nullptr, res);
    if (!data || !size) return false;

    HANDLE f = CreateFileW(dest.c_str(), GENERIC_WRITE, 0, nullptr,
                           CREATE_ALWAYS, FILE_ATTRIBUTE_NORMAL, nullptr);
    if (f == INVALID_HANDLE_VALUE) return false;
    DWORD written = 0;
    BOOL ok = WriteFile(f, data, size, &written, nullptr);
    CloseHandle(f);
    return ok && written == size;
}

bool CreateShortcut(const std::wstring& target, const std::wstring& lnk) {
    IShellLinkW* sl = nullptr;
    if (FAILED(CoCreateInstance(CLSID_ShellLink, nullptr, CLSCTX_INPROC_SERVER,
                                IID_IShellLinkW, (void**)&sl)))
        return false;
    sl->SetPath(target.c_str());
    std::wstring dir = target.substr(0, target.find_last_of(L'\\'));
    sl->SetWorkingDirectory(dir.c_str());
    sl->SetDescription(kAppName);
    IPersistFile* pf = nullptr;
    bool ok = false;
    if (SUCCEEDED(sl->QueryInterface(IID_IPersistFile, (void**)&pf))) {
        ok = SUCCEEDED(pf->Save(lnk.c_str(), TRUE));
        pf->Release();
    }
    sl->Release();
    return ok;
}

void WriteUninstallEntry(const std::wstring& dir, const std::wstring& dest) {
    HKEY h;
    if (RegCreateKeyExW(HKEY_LOCAL_MACHINE, kRegKey, 0, nullptr, 0,
                        KEY_WRITE, nullptr, &h, nullptr) != ERROR_SUCCESS)
        return;
    auto setS = [&](const wchar_t* n, const std::wstring& v) {
        RegSetValueExW(h, n, 0, REG_SZ, (const BYTE*)v.c_str(),
                       (DWORD)((v.size() + 1) * sizeof(wchar_t)));
    };
    setS(L"DisplayName", kAppName);
    setS(L"DisplayVersion", L"0.1.0");
    setS(L"Publisher", L"Data Recovery");
    setS(L"DisplayIcon", dest);
    setS(L"InstallLocation", dir);
    setS(L"UninstallString", L"\"" + dest + L"\" --uninstall");
    DWORD one = 1;
    RegSetValueExW(h, L"NoModify", 0, REG_DWORD, (const BYTE*)&one, 4);
    RegSetValueExW(h, L"NoRepair", 0, REG_DWORD, (const BYTE*)&one, 4);
    RegCloseKey(h);
}

} // namespace

int WINAPI wWinMain(HINSTANCE, HINSTANCE, PWSTR, int) {
    CoInitializeEx(nullptr, COINIT_APARTMENTTHREADED);

    if (MessageBoxW(nullptr,
            L"Install Hard Disk Data Recovery on this PC?\n\n"
            L"It will be copied to Program Files with a Start Menu shortcut.",
            kAppName, MB_YESNO | MB_ICONQUESTION) != IDYES) {
        CoUninitialize();
        return 0;
    }

    std::wstring pf = KnownFolder(FOLDERID_ProgramFiles);
    if (pf.empty()) { CoUninitialize(); return 1; }
    std::wstring dir = pf + L"\\" + kAppName;
    std::wstring dest = dir + L"\\" + kExeName;

    CreateDirectoryW(dir.c_str(), nullptr);
    if (!ExtractMainExe(dest)) {
        MessageBoxW(nullptr,
            L"Installation failed while copying files.\n"
            L"Please run Setup as Administrator.",
            kAppName, MB_OK | MB_ICONERROR);
        CoUninitialize();
        return 1;
    }

    std::wstring progs = KnownFolder(FOLDERID_CommonPrograms);
    if (!progs.empty())
        CreateShortcut(dest, progs + L"\\" + kAppName + L".lnk");

    if (MessageBoxW(nullptr, L"Create a desktop shortcut?", kAppName,
                    MB_YESNO | MB_ICONQUESTION) == IDYES) {
        std::wstring desk = KnownFolder(FOLDERID_Desktop);
        if (!desk.empty())
            CreateShortcut(dest, desk + L"\\" + kAppName + L".lnk");
    }

    WriteUninstallEntry(dir, dest);

    if (MessageBoxW(nullptr,
            L"Installation complete.\n\nLaunch Hard Disk Data Recovery now?",
            kAppName, MB_YESNO | MB_ICONINFORMATION) == IDYES) {
        ShellExecuteW(nullptr, L"open", dest.c_str(), nullptr, nullptr, SW_SHOW);
    }

    CoUninitialize();
    return 0;
}
