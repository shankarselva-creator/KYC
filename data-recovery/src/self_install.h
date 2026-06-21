// self_install.h - Optional self-install / uninstall support.
#pragma once
#include <windows.h>

// Copy the running exe into Program Files, create a Start Menu shortcut, and
// register an entry in Windows "Apps & Features" (Add/Remove Programs).
bool InstallApp(HWND owner);

// Remove the Start Menu shortcut, the registry entry, and the installed files.
// Returns true if the caller should now exit the application.
bool UninstallApp(HWND owner);
