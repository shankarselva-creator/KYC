// metadata.h - Derive a friendly name for a carved file from embedded metadata.
#pragma once
#include <string>
#include <cstdint>
#include <cstddef>

// Inspect the leading bytes of a carved file and return a human-friendly base
// name (without extension) drawn from embedded metadata:
//   mp3 -> ID3 title / "artist - title"
//   pdf -> document /Title
//   jpg -> EXIF capture date  (IMG_YYYYMMDD_HHMMSS)
// Returns an empty string when no usable metadata is present.
std::wstring DeriveCarvedName(const wchar_t* ext, const uint8_t* data,
                              size_t avail);
