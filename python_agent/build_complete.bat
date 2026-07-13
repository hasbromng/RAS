@echo off
REM RAS Agent Complete Build Script
REM Builds standalone EXE and creates installer package

setlocal enabledelayedexpansion

echo ========================================
echo RAS Agent - Complete Build Process
echo ========================================
echo(
echo This script will:
echo   1. Install build dependencies
echo   2. Create standalone EXE with PyInstaller
echo   3. Create distribution package
echo   4. (Optional) Create NSIS installer
echo(

echo ========================================
echo Step 1: Environment Check
echo ========================================
echo(

REM Check Python
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] Python is not installed or not in PATH
    echo Please install Python 3.7+ from https://www.python.org/
    exit /b 1
)
echo [OK] Python is available

REM Check PyInstaller
python -c "import PyInstaller" >nul 2>&1
if %errorlevel% neq 0 (
    echo [INFO] Installing PyInstaller...
    python -m pip install pyinstaller
)
echo [OK] PyInstaller is available

REM Check dependencies
echo [INFO] Checking required packages...
python -c "import psutil, requests, schedule, win32service" >nul 2>&1
if %errorlevel% neq 0 (
    echo [INFO] Installing dependencies...
    python -m pip install psutil requests schedule pywin32
)
echo [OK] All dependencies are installed

echo(
echo ========================================
echo Step 2: Build Standalone EXE
echo ========================================
echo.

REM Clean previous builds
if exist "build" rmdir /s /q build
if exist "dist" rmdir /s /q dist

echo [INFO] Building standalone executable with PyInstaller...
echo.

REM Build with PyInstaller
python -m PyInstaller ^
    --onefile ^
    --name ras_agent ^
    --add-data "ras_agent;ras_agent" ^
    --hidden-import "psutil" ^
    --hidden-import "requests" ^
    --hidden-import "schedule" ^
    --hidden-import "win32service" ^
    --hidden-import "win32serviceutil" ^
    --hidden-import "win32con" ^
    --hidden-import "win32api" ^
    --hidden-import "win32event" ^
    --hidden-import "win32timezone" ^
    --hidden-import "pywintypes" ^
    --hidden-import "win32com" ^
    --hidden-import "win32com.client" ^
    --noconfirm ^
    --clean ^
    --noconsole ^
    ras_agent_main.py

if %errorlevel% neq 0 (
    echo [ERROR] PyInstaller build failed
    echo Checking for detailed error information...
    exit /b 1
)

echo [OK] Standalone EXE created: dist\ras_agent.exe
echo.

echo ========================================
echo Step 3: Create Distribution Package
echo ========================================
echo.

REM Create distribution directory
set "DIST_DIR=dist\ras_agent"
if exist "%DIST_DIR%" rmdir /s /q "%DIST_DIR%"
mkdir "%DIST_DIR%"

REM Copy files
echo [INFO] Copying files to distribution package...

copy "dist\ras_agent.exe" "%DIST_DIR%\" >nul
echo   [OK] ras_agent.exe

REM Copy configuration files
if exist "config.json" (
    copy "config.json" "%DIST_DIR%\" >nul
    echo   [OK] config.json existing
) else (
    copy "config.json.template" "%DIST_DIR%\config.json" >nul
    echo   [OK] config.json (from template)
)

copy "config.json.template" "%DIST_DIR%\" >nul
echo   [OK] config.json.template

if exist "config.ngrok.json" (
    copy "config.ngrok.json" "%DIST_DIR%\" >nul
    echo   [OK] config.ngrok.json
)

if exist "config.local.json.template" (
    copy "config.local.json.template" "%DIST_DIR%\" >nul
    echo   [OK] config.local.json.template
)

if exist "config.production.json.template" (
    copy "config.production.json.template" "%DIST_DIR%\" >nul
    echo   [OK] config.production.json.template
)

REM Copy documentation
if exist "README.md" (
    copy "README.md" "%DIST_DIR%\" >nul
    echo   [OK] README.md
)

if exist "CLIENT_INSTALL_GUIDE.md" (
    copy "CLIENT_INSTALL_GUIDE.md" "%DIST_DIR%\INSTALL_GUIDE.md" >nul
    echo   [OK] CLIENT_INSTALL_GUIDE.md
)

if exist "NGROK_SETUP.md" (
    copy "NGROK_SETUP.md" "%DIST_DIR%\" >nul
    echo   [OK] NGROK_SETUP.md
)

if exist "CONFIGURATION_GUIDE.md" (
    copy "CONFIGURATION_GUIDE.md" "%DIST_DIR%\" >nul
    echo   [OK] CONFIGURATION_GUIDE.md
)

if exist "INSTALL_GUIDE.md" (
    copy "INSTALL_GUIDE.md" "%DIST_DIR%\" >nul
    echo   [OK] INSTALL_GUIDE.md
)

REM Create installer script
echo [INFO] Creating installation script...

(
echo @echo off
echo title RAS Agent Installation
echo echo ========================================
echo echo RAS Monitoring Agent Installation
echo echo ========================================
echo echo This will install RAS Agent as a Windows service.
echo echo Installing RAS Agent service...
echo cd /d "%%~dp0"
echo ras_agent.exe install
echo if %%errorlevel%% neq 0 ^(
echo     echo [ERROR] Failed to install service
echo     echo Please run this script as Administrator.
echo     exit /b 1
echo ^)
echo echo ========================================
echo echo Installation Complete!
echo echo ========================================
echo echo Next steps:
echo echo   1. Edit config.json with your server settings
echo echo   2. Run: ras_agent.exe test
echo echo   3. Start service: ras_agent.exe start
) > "%DIST_DIR%\install.bat"

echo   [OK] install.bat

REM Create uninstall script
(
echo @echo off
echo title RAS Agent Uninstallation
echo echo ========================================
echo echo RAS Monitoring Agent Uninstallation
echo echo ========================================
echo set /p CONFIRM="Are you sure you want to uninstall? (y/n): "
echo if /i not "%%CONFIRM%%"=="y" ^(
echo     echo Uninstallation cancelled.
echo     exit /b 0
echo ^)
echo echo Stopping service...
echo ras_agent.exe stop
echo echo Removing service...
echo ras_agent.exe remove
echo echo Cleaning up files...
echo del /q config.json buffer.json ras_agent.log 2^>nul
echo echo ========================================
echo echo Uninstallation Complete!
echo echo ========================================
) > "%DIST_DIR%\uninstall.bat"

echo   [OK] uninstall.bat

REM Create quick test script
(
echo @echo off
echo echo Testing RAS Agent connection...
echo ras_agent.exe test
) > "%DIST_DIR%\test_connection.bat"

echo   [OK] test_connection.bat

echo.
echo [OK] Distribution package created: dist\ras_agent\

if exist "test_connection.bat" (
    copy "test_connection.bat" "%DIST_DIR%\" >nul
    echo   [OK] test_connection.bat
)

REM Mirror distribution into ras_agent_package for direct packaging workflows
set "PKG_DIR=dist\ras_agent_package"
if exist "%PKG_DIR%" rmdir /s /q "%PKG_DIR%"
xcopy "%DIST_DIR%" "%PKG_DIR%" /E /I /Q >nul
echo   [OK] ras_agent_package mirror created

echo.
echo ========================================
echo Step 4: Check Package Size
echo ========================================
echo.

for /f "tokens=3" %%a in ('dir "%DIST_DIR%" /s ^| find "File(s)"') do set SIZE=%%a
echo Package size: %SIZE% bytes

echo.
echo ========================================
echo Build Complete!
echo ========================================
echo.
echo Distribution location: dist\ras_agent\
echo Main executable: dist\ras_agent\ras_agent.exe
echo.
echo Package contents:
dir /b "%DIST_DIR%"
echo.
echo.
echo ========================================
echo Distribution Options
echo ========================================
echo.
echo Option 1: Direct folder distribution
echo   - Copy entire "ras_agent" folder to client machines
echo   - Run install.bat as administrator on client
echo.
echo Option 2: Network share
echo   - Copy "ras_agent" folder to network share
echo   - Access from client: \\server\share\ras_agent\
echo.
echo Option 3: Create NSIS installer ^(if available^)
echo   - Run: makensis /DVERSION=1.0.0 installer.nsi
echo   - Creates: ras_agent_setup.exe
echo.
echo ========================================
echo Testing the Build
echo ========================================
echo.
echo Before distributing, test the build:
echo   1. cd dist\ras_agent
echo   2. test_connection.bat
echo   3. Check that connection works
echo   4. Install service: ras_agent.exe install
echo   5. Start service: ras_agent.exe start
echo   6. Verify in services.msc
echo.

