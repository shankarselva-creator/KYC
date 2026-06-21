// disk.cpp - Implementation of raw disk / volume I/O.
#include "disk.h"
#include <winioctl.h>
#include <algorithm>
#include <cstdio>
#include <cstring>

Disk::~Disk() { close(); }

void Disk::close() {
    if (handle_ != INVALID_HANDLE_VALUE) {
        CloseHandle(handle_);
        handle_ = INVALID_HANDLE_VALUE;
    }
    size_ = 0;
    sectorSize_ = 512;
    path_.clear();
}

bool Disk::open(const std::wstring& path) {
    close();
    handle_ = CreateFileW(path.c_str(), GENERIC_READ,
                          FILE_SHARE_READ | FILE_SHARE_WRITE, nullptr,
                          OPEN_EXISTING, 0, nullptr);
    if (handle_ == INVALID_HANDLE_VALUE)
        return false;

    path_ = path;
    DWORD ret = 0;

    DISK_GEOMETRY_EX geo{};
    if (DeviceIoControl(handle_, IOCTL_DISK_GET_DRIVE_GEOMETRY_EX, nullptr, 0,
                        &geo, sizeof(geo), &ret, nullptr)) {
        sectorSize_ = geo.Geometry.BytesPerSector;
        size_ = static_cast<uint64_t>(geo.DiskSize.QuadPart);
    } else {
        GET_LENGTH_INFORMATION li{};
        if (DeviceIoControl(handle_, IOCTL_DISK_GET_LENGTH_INFO, nullptr, 0,
                            &li, sizeof(li), &ret, nullptr)) {
            size_ = static_cast<uint64_t>(li.Length.QuadPart);
        }
        sectorSize_ = 512;
    }
    if (sectorSize_ == 0)
        sectorSize_ = 512;
    return true;
}

bool Disk::read(uint64_t offset, void* buf, size_t size) {
    if (handle_ == INVALID_HANDLE_VALUE || size == 0)
        return false;

    const uint32_t ss = sectorSize_;
    const uint64_t alignedStart = (offset / ss) * ss;
    const uint64_t end = offset + size;
    const uint64_t alignedEnd = ((end + ss - 1) / ss) * ss;
    const size_t   alignedSize = static_cast<size_t>(alignedEnd - alignedStart);

    std::vector<uint8_t> tmp(alignedSize);

    LARGE_INTEGER li;
    li.QuadPart = static_cast<LONGLONG>(alignedStart);
    if (!SetFilePointerEx(handle_, li, nullptr, FILE_BEGIN))
        return false;

    size_t total = 0;
    while (total < alignedSize) {
        DWORD got = 0;
        DWORD toRead = static_cast<DWORD>(
            std::min<size_t>(alignedSize - total, 16u * 1024 * 1024));
        if (!ReadFile(handle_, tmp.data() + total, toRead, &got, nullptr))
            break;            // tolerate read errors near bad/EOF sectors
        if (got == 0)
            break;
        total += got;
    }

    const size_t copyOff = static_cast<size_t>(offset - alignedStart);
    if (total <= copyOff) {
        std::memset(buf, 0, size);
        return false;
    }
    const size_t avail = total - copyOff;
    const size_t copyLen = std::min(size, avail);
    std::memcpy(buf, tmp.data() + copyOff, copyLen);
    if (copyLen < size)
        std::memset(static_cast<uint8_t*>(buf) + copyLen, 0, size - copyLen);
    return true;
}

// ---------------------------------------------------------------------------

static std::wstring QueryModel(HANDLE h) {
    STORAGE_PROPERTY_QUERY q{};
    q.PropertyId = StorageDeviceProperty;
    q.QueryType = PropertyStandardQuery;

    std::vector<uint8_t> buf(1024);
    DWORD ret = 0;
    if (!DeviceIoControl(h, IOCTL_STORAGE_QUERY_PROPERTY, &q, sizeof(q),
                         buf.data(), static_cast<DWORD>(buf.size()), &ret,
                         nullptr))
        return L"Unknown device";

    auto* d = reinterpret_cast<STORAGE_DEVICE_DESCRIPTOR*>(buf.data());
    std::string s;
    if (d->VendorIdOffset && d->VendorIdOffset < buf.size())
        s += reinterpret_cast<char*>(buf.data() + d->VendorIdOffset);
    if (d->ProductIdOffset && d->ProductIdOffset < buf.size()) {
        if (!s.empty()) s += " ";
        s += reinterpret_cast<char*>(buf.data() + d->ProductIdOffset);
    }
    // collapse surrounding whitespace
    while (!s.empty() && s.back() == ' ') s.pop_back();
    size_t b = s.find_first_not_of(' ');
    if (b != std::string::npos) s = s.substr(b);
    return std::wstring(s.begin(), s.end());
}

std::vector<DiskInfo> EnumerateDisks() {
    std::vector<DiskInfo> out;

    // Physical drives.
    for (int i = 0; i < 32; ++i) {
        wchar_t path[64];
        swprintf(path, 64, L"\\\\.\\PhysicalDrive%d", i);
        HANDLE h = CreateFileW(path, GENERIC_READ,
                               FILE_SHARE_READ | FILE_SHARE_WRITE, nullptr,
                               OPEN_EXISTING, 0, nullptr);
        if (h == INVALID_HANDLE_VALUE)
            continue;

        DiskInfo di;
        di.path = path;
        di.isPhysical = true;

        DISK_GEOMETRY_EX geo{};
        DWORD ret = 0;
        if (DeviceIoControl(h, IOCTL_DISK_GET_DRIVE_GEOMETRY_EX, nullptr, 0,
                            &geo, sizeof(geo), &ret, nullptr)) {
            di.sizeBytes = static_cast<uint64_t>(geo.DiskSize.QuadPart);
            di.sectorSize = geo.Geometry.BytesPerSector;
        }
        di.model = QueryModel(h);
        CloseHandle(h);
        out.push_back(std::move(di));
    }

    // Logical volumes.
    DWORD mask = GetLogicalDrives();
    for (int i = 0; i < 26; ++i) {
        if (!(mask & (1u << i)))
            continue;
        wchar_t letter = static_cast<wchar_t>(L'A' + i);
        wchar_t root[8];
        swprintf(root, 8, L"%c:\\", letter);
        UINT type = GetDriveTypeW(root);
        if (type != DRIVE_FIXED && type != DRIVE_REMOVABLE)
            continue;

        wchar_t path[16];
        swprintf(path, 16, L"\\\\.\\%c:", letter);
        HANDLE h = CreateFileW(path, GENERIC_READ,
                               FILE_SHARE_READ | FILE_SHARE_WRITE, nullptr,
                               OPEN_EXISTING, 0, nullptr);
        if (h == INVALID_HANDLE_VALUE)
            continue;

        DiskInfo di;
        di.path = path;
        di.isPhysical = false;
        di.sectorSize = 512;

        GET_LENGTH_INFORMATION lenInfo{};
        DWORD ret = 0;
        if (DeviceIoControl(h, IOCTL_DISK_GET_LENGTH_INFO, nullptr, 0,
                            &lenInfo, sizeof(lenInfo), &ret, nullptr))
            di.sizeBytes = static_cast<uint64_t>(lenInfo.Length.QuadPart);

        di.model = std::wstring(L"Volume ") + letter + L":";
        CloseHandle(h);
        out.push_back(std::move(di));
    }

    return out;
}
