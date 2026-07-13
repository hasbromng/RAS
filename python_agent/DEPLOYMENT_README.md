# RAS Monitoring Agent - Deployment Package

## 📦 Apa yang Ada di Package Ini?

Python Monitoring Agent yang sudah lengkap untuk di-deploy ke komputer klien:

### ✅ Sudah Siap Pakai:
- ✅ **Python Agent Source Code** - Semua modul lengkap
- ✅ **Unit Tests** - Test coverage untuk semua modul
- ✅ **Documentation** - Panduan lengkap
- ✅ **Build Scripts** - Script untuk buat standalone EXE
- ✅ **Installer Scripts** - Script untuk install/uninstall
- ✅ **Configuration Templates** - Berbagai template konfigurasi

## 🎯 Cara Deploy ke Klien

### Metode 1: Build Standalone EXE (Rekomendasi)

#### Di Server Lokal:

```bash
cd python_agent

# Build standalone EXE
build_complete.bat

# Hasil: dist\ras_agent\ras_agent.exe (standalone, tanpa perlu Python)
```

#### Deploy ke Klien:

1. **Copy folder** `dist\ras_agent` ke komputer klien
2. **Edit config.json** dengan settings server
3. **Jalankan** `install.bat` sebagai administrator
4. **Selesai!** Agent sudah running sebagai Windows service

### Metode 2: Installer EXE Professional

```bash
# Setelah build complete, buat NSIS installer
makensis /DVERSION=1.0.0 installer.nsi

# Hasil: ras_agent_setup.exe
# Deploy: Copy file ini, double-click, install!
```

---

## 📁 Struktur Package

```
python_agent/
├── ras_agent/              # Source code (Python)
│   ├── __init__.py
│   ├── agent.py            # Main orchestrator
│   ├── api_client.py       # API communication
│   ├── buffer.py           # Local buffering
│   ├── collector.py        # Metrics collection
│   ├── config.py           # Configuration
│   └── logger.py           # Logging
│
├── service/                # Service scripts
│   └── windows_service.py  # Windows service wrapper
│
├── tests/                  # Unit tests
│   ├── test_config.py
│   ├── test_collector.py
│   ├── test_api_client.py
│   ├── test_buffer.py
│   ├── test_logger.py
│   └── test_agent.py
│
├── config.json.template     # Configuration templates
├── config.ngrok.json
├── config.local.json.template
├── config.production.json.template
│
├── build_complete.bat       # Build script (NEW!)
├── installer.nsi            # NSIS installer script
├── ras_agent_main.py        # Main entry point
├── requirements.txt         # Python dependencies
├── requirements-test.txt     # Test dependencies
│
├── README.md                # Main documentation
├── CLIENT_INSTALL_GUIDE.md   # Panduan untuk klien (NEW!)
├── BUILD_GUIDE.md           # Build documentation
├── NGROK_SETUP.md           # ngrok testing guide
├── CONFIGURATION_GUIDE.md   # Configuration guide
│
└── DEPLOYMENT_README.md     # File ini!
```

---

## 🚀 Quick Start Deployment

### 1️⃣ Di Server Lokal (Build)

```bash
cd python_agent
build_complete.bat
```

**Hasil:** Folder `dist\ras_agent\` dengan `ras_agent.exe` standalone

### 2️⃣ Distribusi ke Klien

**Option A: Copy Folder**
```
Copy dist\ras_agent → USB → Client PC
```

**Option B: Network Share**
```
Copy dist\ras_agent → \\server\share\
Access from: \\server\share\ras_agent\
```

**Option C: Download**
```
Upload to internal server → Download on client
```

### 3️⃣ Instalasi di Klien

```bash
# Di komputer klien, sebagai administrator:

cd ras_agent
install.bat

# Edit config.json dengan server settings
notepad config.json

# Test koneksi
test_connection.bat

# Service otomatis started
```

### 4️⃣ Verifikasi

Di server lokal, buka dashboard:
```
http://localhost/RAS/admin/
```

Device klien harus muncul di halaman **Devices**!

---

## 🎓 Panduan yang Tersedia

| Dokumentasi | Untuk | Isi |
|-------------|--------|-----|
| [`README.md`](README.md) | Developer | Overview dan panduan teknis |
| [`CLIENT_INSTALL_GUIDE.md`](CLIENT_INSTALL_GUIDE.md) | End User | Panduan instalasi untuk klien |
| [`BUILD_GUIDE.md`](BUILD_GUIDE.md) | Builder | Cara build standalone EXE |
| [`NGROK_SETUP.md`](NGROK_SETUP.md) | Tester | Setup testing dengan ngrok |
| [`CONFIGURATION_GUIDE.md`](CONFIGURATION_GUIDE.md) | Admin | Panduan konfigurasi |
| [`DEPLOYMENT_README.md`](DEPLOYMENT_README.md) | Deployer | Deployment overview (ini) |

---

## 🔧 Fitur Agent

### Metrik yang Dikumpulkan:
- ✅ CPU usage (overall dan per-core)
- ✅ Memory usage (physical dan swap)
- ✅ Disk usage dan free space
- ✅ Storage health status
- ✅ Network status dan IP address
- ✅ System info (hostname, OS, uptime)

### Fitur:
- ✅ **Standalone** - Tidak perlu install Python di klien
- ✅ **Windows Service** - Berjalan otomatis di background
- ✅ **Offline Buffer** - Simpan data saat offline, kirim saat online
- ✅ **Resource Minimal** - <1% CPU, <50MB memory
- ✅ **Auto-retry** - Retry dengan exponential backoff
- ✅ **Graceful Shutdown** - Handle signals properly

---

## 📝 Configuration

### Minimal Config untuk Klien:

```json
{
  "agent": {
    "device_id": "client-laptop-001",
    "hostname": "Laptop-Budi",
    "api_endpoint": "https://heathered-dortha-unparsed.ngrok-free.dev/RAS/admin/api/metrics.php",
    "api_key": "api-key-dari-server",
    "collect_interval": 60
  }
}
```

### Endpoint Options:

| Type | Example | Kapan Pakai |
|------|---------|--------------|
| **ngrok** | `https://xxx.ngrok.dev/RAS/admin/api/metrics.php` | Testing remote |
| **Local IP** | `http://192.168.1.100/RAS/admin/api/metrics.php` | Same network |
| **Domain** | `https://monitoring.company.com/...` | Production |
| **VPS IP** | `http://203.0.113.10/...` | Production tanpa domain |

---

## 🧪 Testing

### Test di Server Lokal:

```bash
cd python_agent

# Test unit tests
pip install -r requirements-test.txt
pytest tests/ -v

# Test manual dengan ngrok
python test_ngrok_connection.py
```

### Test di Klien:

```bash
cd ras_agent

# Test koneksi
ras_agent.exe test

# Cek logs
type ras_agent.log
```

---

## 📦 Build Process Summary

```
1. build_complete.bat
   ├── Install dependencies
   ├── Build standalone EXE (PyInstaller)
   ├── Create distribution package
   └── Copy files + create scripts

2. dist/ras_agent/
   ├── ras_agent.exe          # Standalone EXE (~20MB)
   ├── config.json             # Configuration
   ├── config.*.template       # Templates
   ├── *.md                    # Documentation
   ├── install.bat            # Installer
   ├── uninstall.bat          # Uninstaller
   └── test_connection.bat    # Test script

3. Deploy to clients
   ├── Copy folder to client
   ├── Edit config.json
   └── Run install.bat
```

---

## 🎯 Deployment Checklist

### Di Server (Build):
- [ ] Python 3.7+ installed
- [ ] Dependencies installed (`build_complete.bat`)
- [ ] Build successful (`dist\ras_agent\` created)
- [ ] Test connection works

### Di Klien (Install):
- [ ] Folder `ras_agent` copied
- [ ] `config.json` edited with correct settings
- [ ] `install.bat` run as administrator
- [ ] Service status: Running
- [ ] Test connection: Success
- [ ] Device appears in dashboard

---

## 🔄 Update Process

### Update Agent di Klien:

```bash
# Di klien, sebagai administrator:

# Stop service
ras_agent.exe stop

# Replace ras_agent.exe dengan versi baru
# (Copy new file ke C:\Program Files\RAS Agent\)

# Start service
ras_agent.exe start
```

---

## 📞 Support

### Troubleshooting Umum:

**Problem:** Agent tidak connect ke server
**Solution:**
1. Cek internet di klien
2. Cek `api_endpoint` di config.json
3. Test: `ras_agent.exe test`

**Problem:** Service tidak starting
**Solution:**
1. Run install.bat sebagai administrator
2. Cek Windows Event Viewer untuk error

**Problem:** Device tidak muncul di dashboard
**Solution:**
1. Cek service running: `sc query RASAgent`
2. Test koneksi: `ras_agent.exe test`
3. Cek log: `type ras_agent.log`
4. Refresh dashboard

---

## 📊 Resource Usage

### Agent Resource Consumption:

| Resource | Usage | Maksimal |
|----------|-------|---------|
| CPU | ~0.1% saat idle | <1% saat collection |
| Memory | ~30MB | <50MB |
| Disk | ~10MB (logs) | ~50MB dengan buffer |
| Network | ~1KB per 60 detik | ~2KB per 60 detik |

---

## 🎉 Kesimpulan

**Python Agent sudah siap untuk deployment ke klien!**

### Cara Paling Mudah:

1. Di server: `build_complete.bat`
2. Copy `dist\ras_agent` ke klien
3. Di klien: `install.bat` (sebagai admin)
4. Edit `config.json`
5. Test: `test_connection.bat`
6. Selesai!

**Selamat! Monitoring agent sudah siap digunakan di komputer klien! 🚀**

---

*Dokumentasi ini adalah ringkasan dari seluruh proses development dan deployment RAS Monitoring Agent.*
