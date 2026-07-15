@echo off 
REM Request Admin Privileges 
net session >nul 2>&1 
if %errorLevel% neq 0 ( 
    powershell -Command "Start-Process cmd -ArgumentList '/c ""%~dpnx0""' -Verb RunAs" 
    exit /b 
) 
cd /d "%~dp0" 
echo ======================================== 
echo RAS Agent Uninstallation 
echo ======================================== 
set /p CONFIRM="Are you sure you want to uninstall RAS Agent? (y/n): " 
if /i not "%CONFIRM%"=="y" ( 
    echo Uninstallation cancelled 
    pause 
    exit /b 0 
) 
echo Stopping agent and services... 
net stop RASAgent 2>nul 
taskkill /F /IM ras_agent.exe /T 2>nul 
echo Removing service... 
sc delete RASAgent 2>nul 
echo Cleaning up files and folders... 
echo ======================================== 
echo Uninstallation Complete This folder will now self-destruct. 
echo ======================================== 
ping 127.0.0.1 -n 3 > nul 
cd \ 
start cmd /c "timeout /t 2 ^>nul ^& rmdir /s /q ""%~dp0""" 
