# Hard Disk Data Recovery

A Windows GUI application (C++ / Win32) for recovering lost files from hard
disks, SSDs and removable drives. It implements three recovery strategies:

| Mode | What it does |
|------|--------------|
| **Undelete Scan** | Auto-detects the filesystem and recovers deleted files. **NTFS**: parses the `$MFT` and decodes data runs. **FAT12/16/32** and **exFAT**: walks directory entries for deleted records and recovers them assuming contiguous allocation. Best right after accidental deletion. |
| **Deep Scan (Carve)** | Streams raw sectors and reconstructs files by their signatures (JPG, PNG, GIF, PDF, ZIP/Office, RAR, GZIP, MP3, legacy DOC). Works even when the filesystem is gone or formatted. |
| **Scan Partitions** | Reads the MBR / GPT partition tables and lists partitions (start offset, size, type) — useful for diagnosing lost partitions. |
| **Preview** | Select a result and click **Preview** (or double-click it) to see it before recovering: image thumbnail for JPG/PNG/GIF/BMP (via GDI+), or a hex/text dump for anything else. |

> ⚠️ **Read-only by design.** The tool opens disks for reading only and writes
> recovered files to a folder *you* choose. Always recover to a **different**
> drive than the one you are scanning, to avoid overwriting data you are trying
> to get back.

## Requirements

- Windows 10/11 (x64)
- **Run as Administrator** — raw disk access (`\\.\PhysicalDriveN`) requires
  elevation. The bundled manifest prompts for it automatically.

## How to use

1. Launch `DataRecovery.exe` (accept the UAC prompt).
2. Pick a physical disk or volume from the dropdown.
3. Click **Undelete Scan** for recently deleted files, or **Deep Scan (Carve)**
   for a thorough signature scan, or **Scan Partitions** to inspect layout.
4. Select a file and click **Preview** (or double-click) to inspect it first.
5. Select one or more files in the results list.
6. Click **Recover Selected...** and choose an output folder (on another drive).

## Building

### On Windows (MSVC, recommended)

```bat
cmake -B build -G "Visual Studio 17 2022" -A x64
cmake --build build --config Release
:: -> build\Release\DataRecovery.exe
```

### On Windows or Linux (MinGW-w64)

```bash
# Linux cross-compile or MSYS2 native:
./build_mingw.sh
# -> build/DataRecovery.exe
```

## Project layout

```
data-recovery/
├── src/
│   ├── disk.{h,cpp}       # raw sector I/O + device enumeration (Win32)
│   ├── partition.cpp      # MBR / GPT partition parsing
│   ├── ntfs.cpp           # MFT parsing, fixups, data-run decode (undelete)
│   ├── fat.cpp            # FAT12/16/32 + exFAT deleted-file recovery
│   ├── carver.cpp         # signature-based file carving (deep scan)
│   ├── recovery.{h,cpp}   # shared types, fs detection, file read/write
│   ├── preview.{h,cpp}    # image (GDI+) / hex preview window
│   └── gui.cpp            # Win32 GUI + worker thread
├── app.manifest           # elevation + common-controls v6
├── app.rc                 # manifest + version resource
├── installer.iss          # Inno Setup installer script
├── CMakeLists.txt
└── build_mingw.sh
```

## Packaging an installer

Install [Inno Setup](https://jrsoftware.org/isdl.php), build the exe, then:

```bat
iscc installer.iss
:: -> Output\DataRecoverySetup.exe
```

The installer deploys the app per-machine, adds Start Menu (and optional
desktop) shortcuts, and registers an uninstaller.

## Current limitations / roadmap

- FAT/exFAT undelete assumes contiguous allocation (deleted cluster chains are
  freed); fragmented deleted files may be partially recovered.
- Carving uses fixed signature heuristics; fragmented files may be truncated.
- FAT undelete reconstructs the 8.3 short name (first character is lost on
  deletion and shown as `_`); long-file-name reassembly is not yet done.
- No scan-result save/load.
- Recovered carved files are named by disk offset, not original name.

Contributions and refinements to any of the above are welcome.
