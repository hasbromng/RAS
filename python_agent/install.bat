@echo off
REM RAS Monitoring Agent - Windows Installation Script
REM This script installs the RAS monitoring agent as a Windows service

setlocal enabledelayedexpansion

echo ========================================
echo RAS Monitoring Agent Installation
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

REM Check Python installation
echo Checking Python installation...
python --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: Python is not installed or not in PATH
    echo Please install Python 3.7 or higher from https://www.python.org/
    pause
    exit /b 1
)

REM Show Python version
echo Python version:
python --version
echo.

REM Create virtual environment
echo Creating virtual environment...
if exist venv (
    echo Virtual environment already exists, removing old one...
    rmdir /s /q venv
)

python -m venv venv
if %errorlevel% neq 0 (
    echo Error: Failed to create virtual environment
    pause
    exit /b 1
)

echo Virtual environment created successfully
echo.

REM Activate virtual environment
echo Activating virtual environment...
call venv\Scripts\activate.bat

REM Upgrade pip
echo Upgrading pip...
python -m pip install --upgrade pip

REM Install dependencies
echo Installing dependencies...
pip install -r requirements.txt
if %errorlevel% neq 0 (
    echo Error: Failed to install dependencies
    pause
    exit /b 1
)

echo Dependencies installed successfully
echo.

REM Check if config.json exists
if not exist config.json (
    echo Creating configuration file...
    if exist config.json.template (
        copy config.json.template config.json >nul
        echo Configuration file created from template
        echo Please edit config.json to update your settings
    ) else (
        echo Warning: config.json.template not found
        echo Creating default configuration...
        echo {"agent":{"device_id":"","hostname":"","api_endpoint":"http://localhost/RAS/admin/api/metrics.php","api_key":"change-this-to-secure-key","collect_interval":60,"buffer_max_size":1000,"buffer_file":"buffer.json","log_file":"ras_agent.log","log_max_size_mb":10,"log_backup_count":5},"thresholds":{"cpu_warning":80,"cpu_critical":90,"memory_warning":80,"memory_critical":90,"disk_warning":75,"disk_critical":85}} > config.json
    )
    echo.
    echo IMPORTANT: Please edit config.json to set your API endpoint and API key
    echo.
)

REM Install Windows service
echo Installing Windows service...
python service/windows_service.py install
if %errorlevel% neq 0 (
    echo Error: Failed to install Windows service
    echo You may need to check your pywin32 installation
    pause
    exit /b 1
)

echo.
echo ========================================
echo Installation Complete!
echo ========================================
echo.
echo Service Name: RASAgent
echo Display Name: RAS Monitoring Agent
echo.
echo The service is set to start automatically on system boot
echo.
echo Commands to manage the service:
echo   Start:   sc start RASAgent
echo   Stop:    sc stop RASAgent
echo   Status:  sc query RASAgent
echo.
echo Or use the service manager (services.msc)
echo.
echo IMPORTANT: Edit config.json to configure your settings:
echo   - API endpoint
echo   - API key
echo   - Collection interval
echo.
echo After configuration, start the service:
echo   python service/windows_service.py start
echo.

pause
