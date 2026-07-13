@echo off
REM RAS Monitoring Agent - Windows Uninstallation Script
REM This script removes the RAS monitoring agent Windows service

setlocal enabledelayedexpansion

echo ========================================
echo RAS Monitoring Agent Uninstallation
echo ========================================
echo.

REM Check if running as administrator
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: This script must be run as Administrator
    echo Right-click the script and select "Run as administrator"
    pause
    exit /b 1
)

REM Get script directory
set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%"

echo Installation directory: %CD%
echo.

REM Ask for confirmation
echo This will:
echo   - Stop the RASAgent service (if running)
echo   - Remove the RASAgent service
echo   - Optionally remove the virtual environment
echo.
set /p CONFIRM="Continue with uninstallation? (y/n): "
if /i not "%CONFIRM%"=="y" (
    echo Uninstallation cancelled
    pause
    exit /b 0
)

echo.
echo Stopping RASAgent service...
python service/windows_service.py stop 2>nul

echo Removing RASAgent service...
python service/windows_service.py remove
if %errorlevel% neq 0 (
    echo Warning: Failed to remove service (may already be removed)
)

echo.
set /p REMOVE_VENV="Remove virtual environment? (y/n): "
if /i "%REMOVE_VENV%"=="y" (
    echo Removing virtual environment...
    if exist venv (
        rmdir /s /q venv
        echo Virtual environment removed
    )
)

set /p REMOVE_CONFIG="Remove configuration files? (y/n): "
if /i "%REMOVE_CONFIG%"=="y" (
    echo Removing configuration files...
    if exist config.json (
        del config.json
        echo config.json removed
    )
    if exist buffer.json (
        del buffer.json
        echo buffer.json removed
    )
    if exist ras_agent.log (
        del ras_agent.log
        echo ras_agent.log removed
    )
)

echo.
echo ========================================
echo Uninstallation Complete!
echo ========================================
echo.
echo The RAS Monitoring Agent has been uninstalled
echo.

pause
