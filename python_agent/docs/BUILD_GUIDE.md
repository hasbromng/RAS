# RAS Agent - Build Guide

Creating a standalone installer for RAS Monitoring Agent with all dependencies bundled.

## Prerequisites

### Required Tools

1. **Python 3.7+** - For building the executable
2. **PyInstaller** - Creates standalone EXE
3. **NSIS** - Creates professional installer (optional)
4. **Git** - For cloning the repository

### Install Prerequisites

```bash
# Install Python dependencies
pip install pyinstaller pywin32 requests psutil schedule

# Download NSIS from: https://nsis.sourceforge.io/
# Or install via chocolatey: choco install nsis
```

---

## Quick Build Process

### Step 1: Build Standalone EXE

```bash
# Navigate to python_agent directory
cd python_agent

# Run build script
build.bat
```

This will:
- Install all dependencies
- Create standalone EXE with PyInstaller
- Package everything in `dist/ras_agent_package/`

### Step 2: Create Installer (Optional)

```bash
# With NSIS installed
makensis /DVERSION=1.0.0 installer.nsi
```

This creates `ras_agent_setup.exe` - a professional installer.

---

## Detailed Build Process

### 1. Build Standalone EXE

The `build.bat` script performs:

```
1. Install Dependencies
   ├── pyinstaller
   ├── pywin32
   ├── requests
   ├── psutil
   └── schedule

2. Build Executable
   ├── PyInstaller bundles everything
   ├── Includes all Python dependencies
   └── Creates single EXE file

3. Package Files
   ├── ras_agent.exe (standalone)
   ├── config.json + templates
   ├── documentation
   └── install/uninstall scripts
```

### 2. Build Installer with NSIS

The `installer.nsi` script creates:

```
ras_agent_setup.exe containing:
├── RAS Agent (core files)
├── Start Menu shortcuts
├── Desktop shortcut (optional)
├── Windows Service installation
└── Uninstaller
```

---

## File Structure After Build

### Standalone Package
```
dist/ras_agent_package/
├── ras_agent.exe           # Main executable (standalone)
├── config.json             # Default configuration
├── config.json.template    # Configuration template
├── config.ngrok.json       # ngrok testing config
├── config.local.json.template
├── config.production.json.template
├── README.md
├── NGROK_SETUP.md
├── CONFIGURATION_GUIDE.md
├── install.bat            # Quick installer
└── uninstall.bat          # Quick uninstaller
```

### Installer Distribution
```
ras_agent_setup.exe         # Professional installer
```

---

## Deployment Options

### Option 1: Direct Distribution

Copy the entire `ras_agent_package` folder to client machines:

```bash
# Via network share
\\server\share\ras_agent_package\

# Via USB
Copy to USB drive → Copy to client machine

# Via download
Upload to file server → Download on client
```

**Installation on Client:**
```bash
# Navigate to folder
cd ras_agent_package

# Run installer
install.bat

# Configure settings
notepad config.json

# Start service
ras_agent.exe start
```

### Option 2: Professional Installer

Distribute `ras_agent_setup.exe`:

```bash
# Run installer on client
ras_agent_setup.exe

# Follow installation wizard
- Choose components
- Select install location
- Configure service installation
- Complete installation
```

---

## Customization

### Change Product Information

Edit `installer.nsi`:

```nsis
!define PRODUCT_NAME "RAS Monitoring Agent"
!define PRODUCT_VERSION "1.0.0"
!define PRODUCT_PUBLISHER "Your Company"
!define PRODUCT_WEB_SITE "https://your-website.com"
```

### Add Custom Icon

1. Create `icon.ico` file
2. Place in `python_agent` directory
3. Rebuild with build script

### Modify Configuration Defaults

Edit `build.bat` to change default config:

```batch
echo     "api_endpoint": "https://your-server.com/RAS/admin/api/metrics.php", >> "%DIST_DIR%\config.json"
echo     "api_key": "your-default-api-key", >> "%DIST_DIR%\config.json"
```

---

## Testing the Build

### 1. Test Standalone EXE

```bash
# Navigate to dist folder
cd dist/ras_agent_package

# Test connection
ras_agent.exe test

# Run standalone
ras_agent.exe

# Install as service
ras_agent.exe install
ras_agent.exe start
```

### 2. Test Installer

```bash
# Run installer
ras_agent_setup.exe

# After installation
ras_agent.exe test
ras_agent.exe status

# Check Windows Services
services.msc → Look for "RAS Agent"
```

---

## Troubleshooting Build

### PyInstaller Fails

**Problem:** Build fails with import errors

**Solution:**
```bash
# Clean build artifacts
rmdir /s /q build dist __pycache__

# Rebuild
build.bat
```

### Missing Dependencies

**Problem:** EXE runs but shows module not found

**Solution:**
Add to `build.bat` hidden imports:
```batch
--hidden-import "missing_module" ^
```

### Large File Size

**Problem:** EXE size is too large (>100MB)

**Solution:**
- Use UPX compression (already enabled)
- Exclude unnecessary modules:
```batch
--exclude-module tkinter ^
--exclude-module matplotlib ^
```

### NSIS Not Found

**Problem:** Can't create installer

**Solution:**
```bash
# Install NSIS
choco install nsis

# Or download from: https://nsis.sourceforge.io/
```

---

## Build Configuration Files

### build.bat Options

Customize `build.bat` for different builds:

| Option | Description |
|--------|-------------|
| `--onefile` | Create single EXE (default) |
| `--onedir` | Create directory with EXE and DLLs |
| `--noconsole` | Hide console window |
| `--uac-admin` | Request admin privileges |

### NSIS Components

Edit `installer.nsi` sections:

```nsis
Section "RAS Agent (required)" SecAgent
SectionIn RO              ; Always installed
SectionEnd

Section "Start Menu Shortcuts" SecShortcuts
  ; Optional components
SectionEnd

Section "Install as Service" SecService
  ; Recommended but optional
SectionEnd
```

---

## Version Management

### Update Version

1. Edit version info:
   - `installer.nsi`: `!define PRODUCT_VERSION "1.0.1"`
   - `ras_agent/__init__.py`: `__version__ = "1.0.1"`

2. Rebuild:
   ```bash
   build.bat
   makensis /DVERSION=1.0.1 installer.nsi
   ```

3. Test new version

---

## Signing the Executable (Optional)

For production, sign the EXE with code signing certificate:

```bash
signtool sign /f certificate.pfx /p password ras_agent.exe
signtool sign /f certificate.pfx /p password ras_agent_setup.exe
```

---

## Distribution Methods

### 1. Network Share

```
\\fileserver\software\ras_agent_setup.exe
```

### 2. Web Download

Upload to internal server:
```
https://internal.company.com/software/ras_agent_setup.exe
```

### 3. Email Attachment

Attach `ras_agent_setup.exe` (if < 10MB)

### 4. USB Drive

Copy to USB for manual distribution

---

## Post-Installation Configuration

### Required Steps

1. **Edit Configuration:**
   ```bash
   notepad config.json
   ```

2. **Update Settings:**
   - `api_endpoint`: Your server URL
   - `api_key`: Your API key
   - `device_id`: Unique device ID (optional)
   - `hostname`: Device hostname (optional)

3. **Test Connection:**
   ```bash
   ras_agent.exe test
   ```

4. **Start Service:**
   ```bash
   ras_agent.exe start
   ```

---

## Automated Deployment

### Group Policy Deployment

1. Place `ras_agent_setup.exe` on network share
2. Create GPO to deploy software
3. Assign to computer or user policy
4. Update GPO: `gpupdate /force`

### PowerShell Deployment

```powershell
# Copy installer
Copy-Item "\\server\share\ras_agent_setup.exe" "C:\Temp\"

# Install silently
Start-Process "C:\Temp\ras_agent_setup.exe" -ArgumentList "/silent /norestart" -Wait

# Cleanup
Remove-Item "C:\Temp\ras_agent_setup.exe"
```

### SCCM Deployment

1. Create application in SCCM
2. Upload `ras_agent_setup.exe`
3. Create deployment program:
   ```
   ras_agent_setup.exe /silent
   ```
4. Deploy to target collection

---

## Maintenance

### Update Existing Installations

```bash
# Run new installer
ras_agent_setup.exe

# Uninstall old version
ras_agent.exe remove

# Or update via GPO/SCCM
```

### Monitor Installation Status

Check Windows Services:
```bash
sc query RASAgent
```

Check logs:
```bash
type "C:\Program Files\RAS Agent\ras_agent.log"
```

---

## Build Outputs

| File | Size | Description |
|------|------|-------------|
| `ras_agent.exe` | ~15-25MB | Standalone executable |
| `ras_agent_setup.exe` | ~20-30MB | Professional installer |
| `ras_agent_package/` | ~25MB | Distribution package |

---

## Support

For build issues:
1. Check [`BUILD_GUIDE.md`](BUILD_GUIDE.md)
2. Review build logs
3. Test with verbose PyInstaller output
4. Check system requirements

---

**Ready to build?** Run `build.bat` to create your standalone installer! 🚀
