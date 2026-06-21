#!/usr/bin/env bash
# Build the self-contained installer (Setup.exe) which embeds DataRecovery.exe.
# Builds the application first, then the installer that wraps it.
set -euo pipefail

CXX=${CXX:-x86_64-w64-mingw32-g++}
WINDRES=${WINDRES:-x86_64-w64-mingw32-windres}
OUT=build

# 1) Build the application (produces build/DataRecovery.exe).
./build_mingw.sh

# 2) Compile the installer resource (embeds build/DataRecovery.exe + manifest).
echo "[setup 1/2] Compiling installer resources..."
"$WINDRES" setup.rc -O coff -o "$OUT/setup.res"

# 3) Compile and link the installer.
echo "[setup 2/2] Linking Setup.exe..."
"$CXX" -std=c++17 -O2 -municode -mwindows -DUNICODE -D_UNICODE \
    src/setup.cpp "$OUT/setup.res" \
    -static -static-libgcc -static-libstdc++ \
    -lshell32 -lole32 -luuid \
    -o "$OUT/DataRecoverySetup.exe"

echo "Done -> $OUT/DataRecoverySetup.exe"
