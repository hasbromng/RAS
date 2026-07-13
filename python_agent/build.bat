@echo off
REM RAS Agent Build Script
REM Creates standalone EXE installer with all dependencies bundled

setlocal enabledelayedexpansion

echo ========================================
echo RAS Agent - Build Standalone EXE
echo ========================================
echo.

REM Check if Python is available
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: Python is not installed or not in PATH
    pause
    exit /b 1
)

echo Step 1: Installing build dependencies...
echo.

REM Install PyInstaller and dependencies
pip install pyinstaller pywin32 requests psutil schedule
if %errorlevel% neq 0 (
    echo Error: Failed to install dependencies
    pause
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
    --noconfirm ^
    --clean ^
    ras_agent_main.py

if %errorlevel% neq 0 (
    echo Error: Failed to build executable
    pause
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
    pause
    exit /b 1
)

REM Copy configuration templates
copy "config.json.template" "%DIST_DIR%\"
copy "config.ngrok.json" "%DIST_DIR%\"
copy "config.local.json.template" "%DIST_DIR%\"
copy "config.production.json.template" "%DIST_DIR%\"

REM Copy documentation
copy "README.md" "%DIST_DIR%\"
copy "NGROK_SETUP.md" "%DIST_DIR%\"
copy "CONFIGURATION_GUIDE.md" "%DIST_DIR%\"

REM Create empty config.json for first run
echo { > "%DIST_DIR%\config.json"
echo   "agent": { >> "%DIST_DIR%\config.json"
echo     "device_id": "", >> "%DIST_DIR%\config.json"
echo     "hostname": "", >> "%DIST_DIR%\config.json"
echo     "api_endpoint": "https://your-server.com/RAS/admin/api/metrics.php", >> "%DIST_DIR%\config.json"
echo     "api_key": "change-this-to-secure-key", >> "%DIST_DIR%\config.json"
echo     "collect_interval": 60, >> "%DIST_DIR%\config.json"
echo     "buffer_max_size": 1000, >> "%DIST_DIR%\config.json"
echo     "buffer_file": "buffer.json", >> "%DIST_DIR%\config.json"
echo     "log_file": "ras_agent.log", >> "%DIST_DIR%\config.json"
echo     "log_max_size_mb": 10, >> "%DIST_DIR%\config.json"
echo     "log_backup_count": 5 >> "%DIST_DIR%\config.json"
echo   }, >> "%DIST_DIR%\config.json"
echo   "thresholds": { >> "%DIST_DIR%\config.json"
echo     "cpu_warning": 80, >> "%DIST_DIR%\config.json"
echo     "cpu_critical": 90, >> "%DIST_DIR%\config.json"
echo     "memory_warning": 80, >> "%DIST_DIR%\config.json"
echo     "memory_critical": 90, >> "%DIST_DIR%\config.json"
echo     "disk_warning": 75, >> "%DIST_DIR%\config.json"
echo     "disk_critical": 85 >> "%DIST_DIR%\config.json"
echo   } >> "%DIST_DIR%\config.json"
echo } >> "%DIST_DIR%\config.json"

echo.
echo Step 4: Creating installer script...
echo.

REM Copy installer that safely replaces old agents and preserves configuration
copy /y "install_or_upgrade.bat" "%DIST_DIR%\install.bat"

REM Create uninstall script
echo @echo off > "%DIST_DIR%\uninstall.bat"
echo echo ======================================== >> "%DIST_DIR%\uninstall.bat"
echo echo RAS Agent Uninstallation >> "%DIST_DIR%\uninstall.bat"
echo echo ======================================== >> "%DIST_DIR%\uninstall.bat"
echo echo. >> "%DIST_DIR%\uninstall.bat"
echo. >> "%DIST_DIR%\uninstall.bat"
echo set /p CONFIRM="Are you sure you want to uninstall RAS Agent? (y/n): " >> "%DIST_DIR%\uninstall.bat"
echo if /i not "%%CONFIRM%%"=="y" ( >> "%DIST_DIR%\uninstall.bat"
echo     echo Uninstallation cancelled >> "%DIST_DIR%\uninstall.bat"
echo     pause >> "%DIST_DIR%\uninstall.bat"
echo     exit /b 0 >> "%DIST_DIR%\uninstall.bat"
echo ^) >> "%DIST_DIR%\uninstall.bat"
echo. >> "%DIST_DIR%\uninstall.bat"
echo echo Stopping service... >> "%DIST_DIR%\uninstall.bat"
echo ras_agent.exe stop >> "%DIST_DIR%\uninstall.bat"
echo. >> "%DIST_DIR%\uninstall.bat"
echo echo Removing service... >> "%DIST_DIR%\uninstall.bat"
echo ras_agent.exe remove >> "%DIST_DIR%\uninstall.bat"
echo. >> "%DIST_DIR%\uninstall.bat"
echo echo Cleaning up files... >> "%DIST_DIR%\uninstall.bat"
echo del /q config.json buffer.json ras_agent.log 2^>nul >> "%DIST_DIR%\uninstall.bat"
echo. >> "%DIST_DIR%\uninstall.bat"
echo echo ======================================== >> "%DIST_DIR%\uninstall.bat"
echo echo Uninstallation Complete! >> "%DIST_DIR%\uninstall.bat"
echo echo ======================================== >> "%DIST_DIR%\uninstall.bat"
echo pause >> "%DIST_DIR%\uninstall.bat"

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
echo   - Configuration templates
echo   - Documentation
echo   - install.bat (installer script)
echo   - uninstall.bat (uninstaller script)
echo.
echo To create installer:
echo   1. Use Inno Setup or NSIS with ras_agent_package directory
echo   2. Or distribute the directory directly
echo.
echo For NSIS script, run: makensis /DVERSION=1.0.0 installer.nsi
echo.

pause
