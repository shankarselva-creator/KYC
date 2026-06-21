// recovery.h - Common recovery types and orchestration entry points.
#pragma once
#include "disk.h"
#include <string>
#include <vector>
#include <cstdint>
#include <functional>

// A contiguous byte range on the opened Disk.
struct Extent {
    uint64_t diskOffset = 0;
    uint64_t length = 0;
};

enum class RecMethod { NtfsUndelete, Carve };

// One recoverable item discovered by a scan.
struct RecoveredFile {
    std::wstring          name;       // file name (or generated for carving)
    std::wstring          source;     // where it was found (partition / "Deep scan")
    uint64_t              size = 0;    // logical size in bytes
    RecMethod             method = RecMethod::Carve;
    bool                  deleted = false;
    bool                  resident = false;          // data stored inline (small NTFS files)
    std::vector<uint8_t>  residentData;              // valid when resident
    std::vector<Extent>   extents;                   // disk byte ranges otherwise
};

// A partition discovered on a physical disk.
struct PartitionInfo {
    int          index = 0;
    std::wstring type;          // "NTFS", "FAT32", "EFI System", ...
    std::wstring scheme;        // "MBR" or "GPT"
    uint64_t     startOffset = 0;
    uint64_t     sizeBytes = 0;
};

// Progress callback: (percent 0-100, status text). Return false to cancel.
using ProgressFn = std::function<bool(int, const std::wstring&)>;
// Result callback: invoked for each discovered file.
using ResultFn   = std::function<void(const RecoveredFile&)>;

// --- partition.cpp ---
std::vector<PartitionInfo> ScanPartitions(Disk& disk);

// --- ntfs.cpp ---
// Scan an NTFS filesystem starting at `partitionStart` (bytes, absolute on disk)
// for deleted files. Pass 0 for a volume opened directly (\\.\C:).
void ScanNtfsDeleted(Disk& disk, uint64_t partitionStart,
                     const std::wstring& sourceLabel,
                     const ProgressFn& progress, const ResultFn& onResult);

// --- carver.cpp ---
void ScanCarve(Disk& disk, uint64_t startOffset, uint64_t length,
               const ProgressFn& progress, const ResultFn& onResult);

// --- recovery.cpp ---
// Write a discovered file out to `outputPath`. Returns bytes written, or -1.
int64_t RecoverFile(Disk& disk, const RecoveredFile& file,
                    const std::wstring& outputPath);

// Sanitize an arbitrary name into something safe for the filesystem.
std::wstring SanitizeFileName(const std::wstring& name);
