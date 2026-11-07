; ========================================================
; TunnelDeck Lite - Inno Setup Script
; ========================================================
; Optional alternative builder for developers using Inno Setup (ISCC).
; The primary builder is win\build.bat which requires zero external tools.

#define MyAppName "TunnelDeck Lite"
#define MyAppVersion "1.0.0"
#define MyAppPublisher "TunnelDeck"
#define MyAppURL "https://tunneldeck.online"
#define MyAppExeName "App.exe"

[Setup]
AppId={{5E8C9462-8F51-4B9F-8491-D36A350EF42B}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName={localappdata}\Programs\TunnelDeck Lite
DefaultGroupName={#MyAppName}
DisableProgramGroupPage=yes
OutputDir=output
OutputBaseFilename=TunnelDeck-Lite-InnoSetup
SetupIconFile=..\app.ico
Compression=lzma2/max
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=lowest
UninstallDisplayIcon={app}\app.ico

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Files]
; Root launcher files
Source: "..\App.exe"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\app.ico"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\loading.html"; DestDir: "{app}"; Flags: ignoreversion
Source: "..\serve.bat"; DestDir: "{app}"; Flags: ignoreversion

; Portable PHP runtime
Source: "..\bin\*"; DestDir: "{app}\bin"; Flags: ignoreversion recursesubdirs createallsubdirs

; Laravel application tree (excluding dev caches and logs)
Source: "..\src\*"; DestDir: "{app}\src"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: ".git\*,.github\*,tests\*,node_modules\*,storage\logs\*.log,src\bin\*.exe,dist\*"

[Icons]
Name: "{group}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; IconFilename: "{app}\app.ico"
Name: "{group}\{cm:UninstallProgram,{#MyAppName}}"; Filename: "{uninstallexe}"
Name: "{autodesktop}\{#MyAppName}"; Filename: "{app}\{#MyAppExeName}"; IconFilename: "{app}\app.ico"; Tasks: desktopicon

[Run]
Filename: "{app}\{#MyAppExeName}"; Description: "{cm:LaunchProgram,{#StringChange(MyAppName, '&', '&&')}}"; Flags: nowait postinstall skipifsilent

