# Hard Disk Data Recovery

A Windows GUI application (C++ / Win32) for recovering lost files from hard
disks, SSDs and removable drives. It implements three recovery strategies:

| Mode | What it does |
|------|--------------|
| **Undelete Scan** | Parses the NTFS Master File Table (`$MFT`) for records marked deleted, decodes their data runs, and recovers the still-present file content. Best right after accidental deletion. |
| **Deep Scan (Carve)** | Streams raw sectors and reconstructs files by their signatures (JPG, PNG, GIF, PDF, ZIP/Office, RAR, GZIP, MP3, legacy DOC). Works even when the filesystem is gone or formatted. |
| **Scan Partitions** | Reads the MBR / GPT partition tables and lists partitions (start offset, size, type) — useful for diagnosing lost partitions. |

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
4. Select one or more files in the results list.
5. Click **Recover Selected...** and choose an output folder (on another drive).

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
│   ├── carver.cpp         # signature-based file carving (deep scan)
│   ├── recovery.{h,cpp}   # shared types + writing files out
│   └── gui.cpp            # Win32 GUI + worker thread
├── app.manifest           # elevation + common-controls v6
├── app.rc                 # manifest + version resource
├── CMakeLists.txt
└── build_mingw.sh
```

## Current limitations / roadmap

- NTFS only for undelete (FAT32/exFAT MFT-equivalent not yet implemented).
- Carving uses fixed signature heuristics; fragmented files may be truncated.
- No preview/thumbnail of recovered files yet.
- No scan-result save/load.
- Recovered carved files are named by disk offset, not original name.

Contributions and refinements to any of the above are welcome.
