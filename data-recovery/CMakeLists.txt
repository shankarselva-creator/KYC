cmake_minimum_required(VERSION 3.15)
project(DataRecovery LANGUAGES CXX RC)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

add_executable(DataRecovery WIN32
    src/disk.cpp
    src/partition.cpp
    src/ntfs.cpp
    src/fat.cpp
    src/carver.cpp
    src/metadata.cpp
    src/recovery.cpp
    src/preview.cpp
    src/self_install.cpp
    src/gui.cpp
    app.rc
)

# Unicode (wWinMain / *W APIs).
target_compile_definitions(DataRecovery PRIVATE UNICODE _UNICODE)

target_link_libraries(DataRecovery PRIVATE comctl32 shell32 ole32 gdiplus uuid)

if(MINGW)
    # Use the Windows (GUI) subsystem, Unicode entry point, static runtime.
    target_link_options(DataRecovery PRIVATE
        -municode -mwindows -static -static-libgcc -static-libstdc++)
    target_compile_options(DataRecovery PRIVATE -municode)
endif()

if(MSVC)
    target_compile_options(DataRecovery PRIVATE /W3 /utf-8)
endif()
