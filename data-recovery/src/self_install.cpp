// self_install.cpp - Self-install / uninstall (Start Menu + Apps & Features).
#include "self_install.h"
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
    if (SUCCEEDED(SHGetKnownFolderPath(id, 0, nullptr, &p))) {
        r = p;
        CoTaskMemFree(p);
    }
    return r;
}

std::wstring SelfPath() {
    wchar_t buf[MAX_PATH];
    GetModuleFileNameW(nullptr, buf, MAX_PATH);
    return buf;
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

std::wstring ShortcutPath() {
    std::wstring progs = KnownFolder(FOLDERID_CommonPrograms);
    return progs.empty() ? L"" : progs + L"\\" + kAppName + L".lnk";
}

std::wstring InstallDir() {
    std::wstring pf = KnownFolder(FOLDERID_ProgramFiles);
    return pf.empty() ? L"" : pf + L"\\" + kAppName;
}

} // namespace

bool InstallApp(HWND owner) {
    std::wstring dir = InstallDir();
    if (dir.empty()) return false;
    std::wstring dest = dir + L"\\" + kExeName;

    CreateDirectoryW(dir.c_str(), nullptr);
    if (!CopyFileW(SelfPath().c_str(), dest.c_str(), FALSE)) {
        MessageBoxW(owner,
            L"Could not copy files to Program Files.\nRun the app as Administrator.",
            kAppName, MB_OK | MB_ICONERROR);
        return false;
    }

    std::wstring lnk = ShortcutPath();
    if (!lnk.empty()) CreateShortcut(dest, lnk);

    HKEY h;
    if (RegCreateKeyExW(HKEY_LOCAL_MACHINE, kRegKey, 0, nullptr, 0,
                        KEY_WRITE, nullptr, &h, nullptr) == ERROR_SUCCESS) {
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

    MessageBoxW(owner,
        (L"Installed to:\n" + dir +
         L"\n\nA Start Menu shortcut was created and the app now appears in "
         L"Settings > Apps (Add/Remove Programs).").c_str(),
        kAppName, MB_OK | MB_ICONINFORMATION);
    return true;
}

bool UninstallApp(HWND owner) {
    if (MessageBoxW(owner,
            L"Remove Hard Disk Data Recovery from this computer?",
            kAppName, MB_YESNO | MB_ICONQUESTION) != IDYES)
        return false;

    std::wstring lnk = ShortcutPath();
    if (!lnk.empty()) DeleteFileW(lnk.c_str());

    RegDeleteKeyW(HKEY_LOCAL_MACHINE, kRegKey);

    // Remove the installed folder. A short delay lets this process exit so the
    // running exe (inside that folder) can be deleted too.
    std::wstring dir = InstallDir();
    if (!dir.empty()) {
        std::wstring cmd = L"/C ping 127.0.0.1 -n 3 >nul & rmdir /S /Q \"" +
                           dir + L"\"";
        ShellExecuteW(nullptr, L"open", L"cmd.exe", cmd.c_str(), nullptr,
                      SW_HIDE);
    }

    MessageBoxW(owner,
        L"Uninstalled. The program folder will be removed momentarily.",
        kAppName, MB_OK | MB_ICONINFORMATION);
    return true;
}
