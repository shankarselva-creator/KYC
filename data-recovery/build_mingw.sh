#!/usr/bin/env bash
# Cross-compile the Windows .exe from Linux using mingw-w64.
# Produces: build/DataRecovery.exe
set -euo pipefail

CXX=${CXX:-x86_64-w64-mingw32-g++}
WINDRES=${WINDRES:-x86_64-w64-mingw32-windres}
OUT=build
mkdir -p "$OUT"

echo "[1/2] Compiling resources..."
"$WINDRES" app.rc -O coff -o "$OUT/app.res"

echo "[2/2] Compiling and linking..."
"$CXX" -std=c++17 -O2 -municode -mwindows \
    -DUNICODE -D_UNICODE \
    src/disk.cpp src/partition.cpp src/ntfs.cpp src/fat.cpp \
    src/carver.cpp src/recovery.cpp src/preview.cpp src/gui.cpp \
    "$OUT/app.res" \
    -static -static-libgcc -static-libstdc++ \
    -lcomctl32 -lshell32 -lole32 -lgdiplus \
    -o "$OUT/DataRecovery.exe"

echo "Done -> $OUT/DataRecovery.exe"
