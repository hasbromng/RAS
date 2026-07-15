@echo off
REM RAS Agent Build Script
REM Creates standalone EXE installer with all dependencies bundled

setlocal enabledelayedexpansion

REM Fix working directory if run as Administrator
cd /d "%~dp0"

echo ========================================
echo RAS Agent - Build Standalone EXE
echo ========================================
echo.

REM Check if Python is available
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: Python is not installed or not in PATH
    exit /b 1
)

echo Step 1: Installing build dependencies...
echo.

REM Install PyInstaller and dependencies
python -m pip install pyinstaller pywin32 requests psutil schedule
if %errorlevel% neq 0 (
    echo Error: Failed to install dependencies
    exit /b 1
)

echo Dependencies installed successfully
echo.

echo Step 2: Creating standalone executable...
echo.

REM Create build directory
if not exist "build" mkdir build
if not exist "dist" mkdir dist

REM Build with PyInstaller
pyinstaller --onefile ^
    --name ras_agent ^
    --add-data "ras_agent;ras_agent" ^
    --add-data "service;service" ^
    --hidden-import "psutil" ^
    --hidden-import "requests" ^
    --hidden-import "schedule" ^
    --hidden-import "win32service" ^
    --hidden-import "win32serviceutil" ^
    --hidden-import "win32con" ^
    --hidden-import "win32api" ^
    --hidden-import "win32event" ^
    --hidden-import "pywintypes" ^
    --hidden-import "win32timezone" ^
    --hidden-import "win32com" ^
    --hidden-import "win32com.client" ^
    --noconfirm ^
    --clean ^
    --noconsole ^
    ras_agent_main.py

if %errorlevel% neq 0 (
    echo Error: Failed to build executable
        exit /b 1
)

echo.
echo Step 3: Creating installer package...
echo.

REM Create distribution directory
set "DIST_DIR=dist\ras_agent_package"
if exist "%DIST_DIR%" rmdir /s /q "%DIST_DIR%"
mkdir "%DIST_DIR%"

REM Copy executable
copy "dist\ras_agent.exe" "%DIST_DIR%\"
if %errorlevel% neq 0 (
    echo Error: Failed to copy executable
        exit /b 1
)
del /q "dist\ras_agent.exe" 2>nul

REM Copy configuration templates - REMOVED FOR SECURITY (Client package should only contain what's needed)

REM Copy documentation - Only include the client install guide as a .txt file
if exist "docs\CLIENT_INSTALL_GUIDE.md" (
    copy "docs\CLIENT_INSTALL_GUIDE.md" "%DIST_DIR%\PANDUAN_INSTALASI.txt" >nul
) else if exist "docs\INSTALL_GUIDE.md" (
    copy "docs\INSTALL_GUIDE.md" "%DIST_DIR%\PANDUAN_INSTALASI.txt" >nul
)

REM Copy existing config.json so API URL and settings are preserved
if exist "config.json" (
    copy "config.json" "%DIST_DIR%\config.json" >nul
)

echo.
echo Step 4: Creating installer script...
echo.

REM Copy installer that safely replaces old agents and preserves configuration
copy /y "install_or_upgrade.bat" "%DIST_DIR%\install.bat"

REM Create quick connection test script
(
echo @echo off
echo echo Testing RAS Agent connection...
echo ras_agent.exe test
echo ) > "%DIST_DIR%\test_connection.bat"

REM Create uninstall script
echo @echo off > "%DIST_DIR%\uninstall.bat"
echo REM Request Admin Privileges >> "%DIST_DIR%\uninstall.bat"
echo net session ^>nul 2^>^&1 >> "%DIST_DIR%\uninstall.bat"
echo if %%errorLevel%% neq 0 ( >> "%DIST_DIR%\uninstall.bat"
echo     powershell -Command "Start-Process cmd -ArgumentList '/c ""%%~dpnx0""' -Verb RunAs" >> "%DIST_DIR%\uninstall.bat"
echo     exit /b >> "%DIST_DIR%\uninstall.bat"
echo ) >> "%DIST_DIR%\uninstall.bat"
echo cd /d "%%~dp0" >> "%DIST_DIR%\uninstall.bat"
echo echo ======================================== >> "%DIST_DIR%\uninstall.bat"
echo echo RAS Agent Uninstallation >> "%DIST_DIR%\uninstall.bat"
echo echo ======================================== >> "%DIST_DIR%\uninstall.bat"
echo set /p CONFIRM="Are you sure you want to uninstall RAS Agent? (y/n): " >> "%DIST_DIR%\uninstall.bat"
echo if /i not "%%CONFIRM%%"=="y" ( >> "%DIST_DIR%\uninstall.bat"
echo     echo Uninstallation cancelled >> "%DIST_DIR%\uninstall.bat"
echo     pause >> "%DIST_DIR%\uninstall.bat"
echo     exit /b 0 >> "%DIST_DIR%\uninstall.bat"
echo ^) >> "%DIST_DIR%\uninstall.bat"
echo echo Stopping agent and services... >> "%DIST_DIR%\uninstall.bat"
echo net stop RASAgent 2^>nul >> "%DIST_DIR%\uninstall.bat"
echo taskkill /F /IM ras_agent.exe /T 2^>nul >> "%DIST_DIR%\uninstall.bat"
echo echo Removing service... >> "%DIST_DIR%\uninstall.bat"
echo sc delete RASAgent 2^>nul >> "%DIST_DIR%\uninstall.bat"
echo echo Cleaning up files and folders... >> "%DIST_DIR%\uninstall.bat"
echo echo ======================================== >> "%DIST_DIR%\uninstall.bat"
echo echo Uninstallation Complete! This folder will now self-destruct. >> "%DIST_DIR%\uninstall.bat"
echo echo ======================================== >> "%DIST_DIR%\uninstall.bat"
echo ping 127.0.0.1 -n 3 ^> nul >> "%DIST_DIR%\uninstall.bat"
echo cd \ >> "%DIST_DIR%\uninstall.bat"
echo start cmd /c "timeout /t 2 ^>nul ^& rmdir /s /q ""%%~dp0""" >> "%DIST_DIR%\uninstall.bat"

echo.
echo ========================================
echo Build Complete!
echo ========================================
echo.
echo Package location: dist\ras_agent_package\
echo Executable: ras_agent.exe
echo.
echo Package contains:
echo   - ras_agent.exe (standalone executable)
echo   - config.json (default configuration)
echo   - PANDUAN_INSTALASI.txt (Installation Guide)
echo   - install.bat (installer script)
echo   - test_connection.bat (connection tester)
echo   - uninstall.bat (uninstaller script)
echo.
echo To create installer:
echo   1. Use Inno Setup or NSIS with ras_agent_package directory
echo   2. Or distribute the directory directly
echo.
echo For NSIS script, run: makensis /DVERSION=1.0.0 installer.nsi
echo.
