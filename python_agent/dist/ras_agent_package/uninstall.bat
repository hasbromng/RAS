@echo off
title RAS Agent Uninstallation
echo ========================================
echo RAS Monitoring Agent Uninstallation
echo ========================================
set /p CONFIRM="Are you sure you want to uninstall? (y/n): "
if /i not "%CONFIRM%"=="y" (
    echo Uninstallation cancelled.
    exit /b 0
)
echo Stopping service...
ras_agent.exe stop
echo Removing service...
ras_agent.exe remove
echo Cleaning up files...
del /q config.json buffer.json ras_agent.log 2>nul
echo ========================================
echo Uninstallation Complete
echo ========================================
