// disk.h - Raw physical-disk / volume I/O for Windows.
#pragma once
#include <windows.h>
#include <string>
#include <vector>
#include <cstdint>

// Information about a selectable storage device.
struct DiskInfo {
    std::wstring path;     // e.g. \\.\PhysicalDrive0  or  \\.\C:
    std::wstring model;    // human-readable label
    uint64_t     sizeBytes = 0;
    uint32_t     sectorSize = 512;
    bool         isPhysical = false; // true = whole disk, false = volume
};

// Read-only handle to a physical disk or volume. All reads are transparently
// sector-aligned, so callers may request arbitrary byte offsets / lengths.
class Disk {
public:
    Disk() = default;
    ~Disk();
    Disk(const Disk&) = delete;
    Disk& operator=(const Disk&) = delete;

    bool open(const std::wstring& path);
    void close();
    bool isOpen() const { return handle_ != INVALID_HANDLE_VALUE; }

    // Read `size` bytes starting at byte `offset`. Returns false on hard error.
    // Short reads past end-of-device are zero-filled.
    bool read(uint64_t offset, void* buf, size_t size);

    uint64_t size() const { return size_; }
    uint32_t sectorSize() const { return sectorSize_; }
    const std::wstring& path() const { return path_; }

private:
    HANDLE       handle_     = INVALID_HANDLE_VALUE;
    uint64_t     size_       = 0;
    uint32_t     sectorSize_ = 512;
    std::wstring path_;
};

// Enumerate physical drives and fixed/removable volumes on this machine.
std::vector<DiskInfo> EnumerateDisks();
