# Hard Disk Data Recovery

A Windows GUI application (C++ / Win32) for recovering lost files from hard
disks, SSDs and removable drives. It implements three recovery strategies:

| Mode | What it does |
|------|--------------|
| **Undelete Scan** | Auto-detects the filesystem and recovers deleted files. **NTFS**: parses the `$MFT` and decodes data runs. **FAT12/16/32** and **exFAT**: walks directory entries for deleted records and recovers them assuming contiguous allocation. Best right after accidental deletion. |
| **Deep Scan (Carve)** | Streams raw sectors and reconstructs files by their signatures: JPG, PNG, GIF, BMP, TIFF, PDF, ZIP/Office, RAR, 7z, GZIP, MP3, legacy DOC, SQLite, MP4/MOV, WAV, AVI (wildcard matching handles container formats). Works even when the filesystem is gone or formatted. |
| **Scan Partitions** | Reads the MBR / GPT partition tables and lists partitions (start offset, size, type) — useful for diagnosing lost partitions. |
| **Preview** | Select a result and click **Preview** (or double-click it) to see it before recovering: image thumbnail for JPG/PNG/GIF/BMP (via GDI+), or a hex/text dump for anything else. |
| **Cancel** | Stop a long-running scan at any time with the **Cancel** button. |
| **Filter** | Type in the **Filter** box to narrow the results list by name, method or source (case-insensitive). |
| **Recover All** | Recover every file currently shown (i.e. matching the filter) in one click, instead of selecting them by hand. |
| **Progress + ETA** | The status line shows elapsed time and an estimated time remaining during scans. |
| **Sortable columns** | Click any results-list column header (Name / Size / Method / Source) to sort; click again to reverse. |
| **Save / Load results** | `File ▸ Save Results...` writes the current scan to a `.drsv` file; `File ▸ Load Results...` restores it later so you can recover without rescanning. |

> ⚠️ **Read-only by design.** The tool opens disks for reading only and writes
> recovered files to a folder *you* choose. Always recover to a **different**
> drive than the one you are scanning, to avoid overwriting data you are trying
> to get back.

## Installing & uninstalling

The app is a portable single `.exe` — you can just run it. If you'd like it
properly installed:

- **Install**: run the app and choose **Tools ▸ Install on this PC...**. This
  copies it to Program Files, adds a Start Menu shortcut, and registers it in
  **Settings ▸ Apps** (Add/Remove Programs).
- **Uninstall**: either use Windows **Settings ▸ Apps ▸ Uninstall**, or open the
  app and choose **Tools ▸ Uninstall...**. Both remove the shortcut, the
  registry entry, and the installed files.

(A traditional `installer.iss` for Inno Setup is also provided — see below.)

## Requirements

- Windows 10/11 (x64)
- **Run as Administrator** — raw disk access (`\\.\PhysicalDriveN`) requires
  elevation. The bundled manifest prompts for it automatically.

## How to use

The app follows a simple two-step, wizard-style flow:

1. Launch `DataRecovery.exe` (accept the UAC prompt).
2. **Select a location** — every drive and volume is shown as a large clickable
   card (path, model, size). Tick **Deep scan** first if you want signature
   carving in addition to the quick undelete pass. Click a card to start.
3. The scan runs with a live progress bar and **ETA**; press **Stop** anytime.
4. **Review results** — use the category sidebar (All / Photos / Videos /
   Documents / Audio / Archives / Other), the **search** box, or click a column
   header to sort. Selecting a file shows it in the **inline preview pane** on
   the right (image thumbnail or hex for binaries); double-click for a full-size
   preview window.
5. Select files and click **Recover Selected...**, or **Recover All Shown**, then
   choose an output folder **on a different drive**.

Use `File ▸ Save Results` / `Load Results` to keep a scan for later.

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

## Building the Setup.exe installer (no extra tools)

A self-contained installer that embeds the app is built with mingw-w64 — no
Inno Setup required:

```bash
./build_setup.sh
# -> build/DataRecoverySetup.exe
```

Running `DataRecoverySetup.exe` (as Administrator) extracts the app to Program
Files, creates a Start Menu shortcut (and an optional desktop shortcut), and
registers it in Settings ▸ Apps. Uninstall from Settings ▸ Apps or from the
app's Tools menu.

## Packaging an installer (Inno Setup, optional)

Install [Inno Setup](https://jrsoftware.org/isdl.php), build the exe, then:

```bat
iscc installer.iss
:: -> Output\DataRecoverySetup.exe
```

The installer deploys the app per-machine, adds Start Menu (and optional
desktop) shortcuts, and registers an uninstaller.

## Current limitations / roadmap

- **Deep scan (carving) cannot recover original file names or folder paths** —
  those live in filesystem metadata, which carving deliberately bypasses. Where
  a file embeds its own metadata the carver derives a friendlier label (MP3 ID3
  title/artist, PDF `/Title`, JPEG EXIF date `IMG_YYYYMMDD_HHMMSS`); otherwise it
  uses `recovered_000123.jpg`. This is the file's *internal* title, still not the
  original filename. Use the **Undelete scan** (Deep scan unticked) to get real
  names and paths when the filesystem is still intact.
- FAT/exFAT undelete assumes contiguous allocation (deleted cluster chains are
  freed); fragmented deleted files may be partially recovered.
- Carving uses fixed signature heuristics; fragmented files may be truncated.
- FAT undelete reassembles the long file name when its directory entries are
  intact; otherwise it falls back to the 8.3 short name (whose first character
  is lost on deletion and shown as `_`).
- Recovered carved files are named by disk offset, not original name.
- A loaded `.drsv` result set recovers correctly only against the same physical
  device it was scanned from (extents are absolute disk offsets).

Contributions and refinements to any of the above are welcome.
