@echo off 
echo ======================================== 
echo RAS Agent Uninstallation 
echo ======================================== 
echo. 
 
set /p CONFIRM="Are you sure you want to uninstall RAS Agent? (y/n): " 
if /i not "%CONFIRM%"=="y" ( 
    echo Uninstallation cancelled 
    pause 
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
pause 
