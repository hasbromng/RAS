; RAS Agent Installer Script for NSIS
; Creates a professional installer with all dependencies bundled

!define PRODUCT_NAME "RAS Monitoring Agent"
!define PRODUCT_VERSION "1.0.0"
!define PRODUCT_PUBLISHER "RAS Team"
!define PRODUCT_WEB_SITE "https://github.com/your-repo/RAS"

; Set compression
SetCompressor lzma

; Modern UI
!include "MUI2.nsh"

; General configuration
Name "${PRODUCT_NAME} ${PRODUCT_VERSION}"
OutFile "ras_agent_setup.exe"
InstallDir "$PROGRAMFILES\RAS Agent"
InstallDirRegKey HKLM "Software\RAS Agent" ""
RequestExecutionLevel admin

; Variables
Var StartMenuFolder

; Interface configuration
!define MUI_ABORTWARNING
!define MUI_ICON "icon.ico"
!define MUI_UNICON "icon.ico"
!define MUI_HEADERIMAGE
; !define MUI_HEADERIMAGE_BITMAP "header.bmp"  ; Optional: Add header image
!define MUI_WELCOMEFINISHPAGE_BITMAP "side.bmp"   ; Optional: Add side image

; Pages
!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_LICENSE "LICENSE.txt"
!insertmacro MUI_PAGE_COMPONENTS
!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_STARTMENU Application $StartMenuFolder
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH

!insertmacro MUI_UNPAGE_WELCOME
!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES
!insertmacro MUI_UNPAGE_FINISH

; Languages
!insertmacro MUI_LANGUAGE "English"

; Installer Sections
Section "RAS Agent (required)" SecAgent
  SectionIn RO

  SetOutPath $INSTDIR
  File "ras_agent.exe"

  ; Configuration files
  File "config.json"
  File "config.json.template"
  File "config.ngrok.json"
  File "config.local.json.template"
  File "config.production.json.template"

  ; Documentation
  File "README.md"
  File "NGROK_SETUP.md"
  File "CONFIGURATION_GUIDE.md"

  ; Store installation folder
  WriteRegStr HKLM "Software\RAS Agent" "" $INSTDIR

  ; Create uninstaller
  WriteUninstaller "$INSTDIR\uninstall.exe"

  ; Add uninstaller to Add/Remove Programs
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent" "DisplayName" "${PRODUCT_NAME}"
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent" "UninstallString" "$INSTDIR\uninstall.exe"
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent" "DisplayIcon" "$INSTDIR\ras_agent.exe"
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent" "Publisher" "${PRODUCT_PUBLISHER}"
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent" "URLInfoAbout" "${PRODUCT_WEB_SITE}"
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent" "DisplayVersion" "${PRODUCT_VERSION}"
  WriteRegDWORD HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent" "VersionMajor" 1
  WriteRegDWORD HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent" "VersionMinor" 0
  WriteRegDWORD HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent" "NoModify" 1
  WriteRegDWORD HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent" "NoRepair" 1

SectionEnd

Section "Start Menu Shortcuts" SecShortcuts
  CreateDirectory "$SMPROGRAMS\$StartMenuFolder"
  CreateShortCut "$SMPROGRAMS\$StartMenuFolder\RAS Agent.lnk" "$INSTDIR\ras_agent.exe" "" "$INSTDIR\ras_agent.exe" 0
  CreateShortCut "$SMPROGRAMS\$StartMenuFolder\Uninstall.lnk" "$INSTDIR\uninstall.exe"
  CreateShortCut "$SMPROGRAMS\$StartMenuFolder\Configuration.lnk" "notepad.exe" "$INSTDIR\config.json"
  CreateShortCut "$SMPROGRAMS\$StartMenuFolder\View Logs.lnk" "notepad.exe" "$INSTDIR\ras_agent.log"
SectionEnd

Section "Desktop Shortcut" SecDesktop
  CreateShortCut "$DESKTOP\RAS Agent.lnk" "$INSTDIR\ras_agent.exe" "" "$INSTDIR\ras_agent.exe" 0
SectionEnd

Section "Install as Windows Service" SecService
  ; Install the Windows service
  ExecWait '"$INSTDIR\ras_agent.exe" install' $0
  DetailPrint "Service installation result: $0"

  ; Start the service
  ExecWait '"$INSTDIR\ras_agent.exe" start' $0
  DetailPrint "Service start result: $0"
SectionEnd

Section "Auto-start Configuration" SecAutostart
  ; Service will be set to auto-start by default
  ; This section is for documentation purposes
SectionEnd

; Section descriptions
!insertmacro MUI_FUNCTION_DESCRIPTION_BEGIN
  !insertmacro MUI_DESCRIPTION_TEXT ${SecAgent} "The main RAS Monitoring Agent executable and configuration files."
  !insertmacro MUI_DESCRIPTION_TEXT ${SecShortcuts} "Create shortcuts in the Start Menu for easy access."
  !insertmacro MUI_DESCRIPTION_TEXT ${SecDesktop} "Create a shortcut on the desktop."
  !insertmacro MUI_DESCRIPTION_TEXT ${SecService} "Install and start the agent as a Windows service (recommended)."
  !insertmacro MUI_DESCRIPTION_TEXT ${SecAutostart} "Configure the agent to start automatically with Windows."
!insertmacro MUI_FUNCTION_DESCRIPTION_END

; Installer functions
Function .onInit
  ; Check for already installed version
  ReadRegStr $R0 HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent" "UninstallString"
  StrCmp $R0 "" done

  MessageBox MB_YESNO|MB_ICONQUESTION \
    "RAS Agent is already installed. $\n$\nDo you want to uninstall the previous version before installing the new one?" \
    IDYES uninst
  IDNO done

uninst:
  ; Run the uninstaller
  ExecWait '$R0 _?=$INSTDIR'

done:
FunctionEnd

; Uninstaller Section
Section "Uninstall"
  ; Stop and remove service
  ExecWait '"$INSTDIR\ras_agent.exe" stop' $0
  ExecWait '"$INSTDIR\ras_agent.exe" remove' $0

  ; Remove files
  Delete "$INSTDIR\ras_agent.exe"
  Delete "$INSTDIR\config.json"
  Delete "$INSTDIR\config.json.template"
  Delete "$INSTDIR\config.ngrok.json"
  Delete "$INSTDIR\config.local.json.template"
  Delete "$INSTDIR\config.production.json.template"
  Delete "$INSTDIR\README.md"
  Delete "$INSTDIR\NGROK_SETUP.md"
  Delete "$INSTDIR\CONFIGURATION_GUIDE.md"
  Delete "$INSTDIR\ras_agent.log"
  Delete "$INSTDIR\buffer.json"
  Delete "$INSTDIR\uninstall.exe"

  ; Remove shortcuts
  Delete "$SMPROGRAMS\$StartMenuFolder\RAS Agent.lnk"
  Delete "$SMPROGRAMS\$StartMenuFolder\Uninstall.lnk"
  Delete "$SMPROGRAMS\$StartMenuFolder\Configuration.lnk"
  Delete "$SMPROGRAMS\$StartMenuFolder\View Logs.lnk"
  Delete "$DESKTOP\RAS Agent.lnk"

  ; Remove directories
  RMDir "$SMPROGRAMS\$StartMenuFolder"
  RMDir $INSTDIR

  ; Remove registry keys
  DeleteRegKey HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\RAS Agent"
  DeleteRegKey HKLM "Software\RAS Agent"

  ; Show completion message
  MessageBox MB_OK "RAS Agent has been uninstalled successfully."
SectionEnd
