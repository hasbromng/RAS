@echo off
setlocal
title RAS Agent Installer / Updater

net session >nul 2>&1
if not "%errorlevel%"=="0" (
    echo [ERROR] Jalankan file ini sebagai Administrator.
    pause
    exit /b 1
)

set "PACKAGE_DIR=%~dp0"
set "INSTALL_DIR=%ProgramFiles%\RAS Agent"
set "SERVICE_NAME=RASAgent"

if exist "%TEMP%\ras_agent_config_backup.json" del /q "%TEMP%\ras_agent_config_backup.json" >nul 2>&1

echo Menghentikan agent lama bila ada...
sc query "%SERVICE_NAME%" >nul 2>&1
if "%errorlevel%"=="0" (
    sc stop "%SERVICE_NAME%" >nul 2>&1
    timeout /t 3 /nobreak >nul
    sc delete "%SERVICE_NAME%" >nul 2>&1
    timeout /t 2 /nobreak >nul
)
taskkill /f /im ras_agent.exe >nul 2>&1

if not exist "%INSTALL_DIR%" mkdir "%INSTALL_DIR%"
if exist "%INSTALL_DIR%\config.json" (
    copy /y "%INSTALL_DIR%\config.json" "%TEMP%\ras_agent_config_backup.json" >nul
    if not "%errorlevel%"=="0" (
        echo [ERROR] Gagal membuat cadangan config.json.
        pause
        exit /b 1
    )
)

copy /y "%PACKAGE_DIR%ras_agent.exe" "%INSTALL_DIR%\ras_agent.exe" >nul
if not "%errorlevel%"=="0" (
    echo [ERROR] Gagal menyalin executable baru.
    pause
    exit /b 1
)
if not exist "%INSTALL_DIR%\ras_agent.exe" (
    echo [ERROR] Executable baru tidak ditemukan setelah penyalinan.
    pause
    exit /b 1
)

if exist "%TEMP%\ras_agent_config_backup.json" (
    copy /y "%TEMP%\ras_agent_config_backup.json" "%INSTALL_DIR%\config.json" >nul
    if not "%errorlevel%"=="0" (
        echo [ERROR] Gagal memulihkan config.json.
        pause
        exit /b 1
    )
    del /q "%TEMP%\ras_agent_config_backup.json" >nul 2>&1
) else if not exist "%INSTALL_DIR%\config.json" (
    if exist "%PACKAGE_DIR%..\ras_agent\config.json" (
        copy /y "%PACKAGE_DIR%..\ras_agent\config.json" "%INSTALL_DIR%\config.json" >nul
    ) else if exist "%PACKAGE_DIR%..\config.json" (
        copy /y "%PACKAGE_DIR%..\config.json" "%INSTALL_DIR%\config.json" >nul
    ) else (
        copy /y "%PACKAGE_DIR%config.json" "%INSTALL_DIR%\config.json" >nul
    )
)

pushd "%INSTALL_DIR%"
ras_agent.exe install
if not "%errorlevel%"=="0" (
    popd
    echo [ERROR] Service baru gagal dipasang.
    pause
    exit /b 1
)
ras_agent.exe start
if not "%errorlevel%"=="0" (
    popd
    echo [ERROR] Service gagal dijalankan.
    pause
    exit /b 1
)
popd

echo Upgrade selesai. Konfigurasi dipertahankan di %INSTALL_DIR%\config.json
pause
