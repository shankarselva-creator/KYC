// preview.h - File preview popup.
#pragma once
#include "recovery.h"
#include <windows.h>
#include <string>

// Initialize / shut down GDI+ (call once each from WinMain).
void PreviewInit();
void PreviewShutdown();

// Open a preview window for `file`, reading its bytes from `devicePath`.
// Shows an image (JPG/PNG/GIF/BMP) when GDI+ can decode it, otherwise a
// hex/text dump of the leading bytes.
void ShowPreview(HWND owner, const std::wstring& devicePath,
                 const RecoveredFile& file);
