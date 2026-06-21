; installer.iss - Inno Setup script for Hard Disk Data Recovery.
; Build with: iscc installer.iss   (https://jrsoftware.org/isdl.php)
; Expects the compiled exe at build\DataRecovery.exe (or build\Release\...).

#define MyAppName "Hard Disk Data Recovery"
#define MyAppVersion "0.1.0"
#define MyAppPublisher "Data Recovery"
#define MyAppExeName "DataRecovery.exe"

[Setup]
AppId={{B3F2A7C4-9D11-4E2A-8C7E-DR0001RECOVER}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
DefaultDirName={autopf}\DataRecovery
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
OutputBaseFilename=DataRecoverySetup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
; The app needs admin rights to run; install per-machine.
PrivilegesRequired=admin
ArchitecturesInstallIn64BitMode=x64compatible

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Additional icons:"; Flags: unchecked

[Files]
; Prefer the MSVC Release output if present, else the top-level build output.
Source: "build\Release\{#MyAppExeName}"; DestDir: "{app}"; Flags: ignoreversion skipifsourcedoesntexist
Source: "build\{#MyAppExeName}"; DestDir: "{app}"; Flags: ignoreversion skipifsourcedoesntexist
Source: "README.md"; DestDir: "{app}"; Flags: ignoreversion isreadme

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"
Name: "{group}\Uninstall {#MyAppName}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "Launch {#MyAppName}"; Flags: nowait postinstall skipifsilent runascurrentuser
