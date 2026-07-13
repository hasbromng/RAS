@echo off
title RAS Agent Installation
echo ========================================
echo RAS Monitoring Agent Installation
echo ========================================
echo.
echo This will install RAS Agent as a Windows service.
echo.
pause

echo Installing RAS Agent service...

cd /d "%~dp0"
ras_agent.exe install
if %errorlevel% neq 0 (
    echo.
    echo [ERROR] Failed to install service
    echo Please run this script as Administrator.
    pause
    exit /b 1
)

echo.
echo ========================================
echo Installation Complete
echo ========================================
echo.
echo Next steps:
echo   1. Edit config.json with your server settings
echo   2. Run: ras_agent.exe test
echo   3. Start service: ras_agent.exe start
echo.
pause
