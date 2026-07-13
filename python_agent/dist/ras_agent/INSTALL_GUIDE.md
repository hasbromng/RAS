# Panduan Instalasi RAS Agent di Komputer Klien

## 📦 Cara 1: Instalasi dengan Installer EXE (Rekomendasi)

### Langkah-langkah:

1. **Download Installer**
   - Dapatkan file `ras_agent_setup.exe`
   - Simpan di komputer klien

2. **Jalankan Installer**
   - Klik kanan → "Run as administrator"
   - Ikuti wizard instalasi
   - Pilih komponen yang diinginkan:
     - ✅ RAS Agent (wajib)
     - ✅ Start Menu Shortcuts (rekomendasi)
     - ✅ Desktop Shortcut (opsional)
     - ✅ Install as Windows Service (rekomendasi)

3. **Selesai!**
   - Agent sudah terinstal dan running sebagai service
   - Bisa diakses via Start Menu → RAS Agent

---

## 📁 Cara 2: Instalasi Manual

### Langkah-langkah:

1. **Copy Folder**
   - Copy folder `ras_agent_package` ke komputer klien
   - Bisa via USB, network share, atau download

2. **Edit Konfigurasi**
   - Buka folder `ras_agent_package`
   - Edit file `config.json` dengan Notepad:
     ```json
     {
       "agent": {
         "device_id": "kantor-laptop-001",
         "hostname": "Laptop-Budi",
         "api_endpoint": "https://heathered-dortha-unparsed.ngrok-free.dev/RAS/admin/api/metrics.php",
         "api_key": "api-key-anda",
         "collect_interval": 60
       }
     }
     ```

3. **Jalankan Install**
   - Klik kanan `install.bat` → "Run as administrator"
   - Tunggu proses instalasi selesai

4. **Verifikasi**
   - Buka Command Prompt sebagai administrator
   - Jalankan: `ras_agent.exe test`
   - Harus muncul pesan "Connection successful"

---

## 🧪 Cara 3: Test Tanpa Install

Untuk testing tanpa menginstall service:

1. **Copy Folder**
   - Copy `ras_agent_package` ke komputer klien

2. **Edit Config**
   - Edit `config.json` sesuai kebutuhan

3. **Test Koneksi**
   ```bash
   cd ras_agent_package
   ras_agent.exe test
   ```

4. **Jalankan Manual**
   ```bash
   ras_agent.exe
   ```
   *Note: Agent akan berhenti saat Command Prompt ditutup*

---

## ✅ Verifikasi Instalasi

### Cek Windows Service:

**Method 1: Command Prompt**
```bash
sc query RASAgent
```
Status harus: `RUNNING`

**Method 2: Services Manager**
- Tekan `Win + R`
- Ketik: `services.msc`
- Cari: "RAS Monitoring Agent"
- Status harus: "Running"

### Cek Koneksi:

```bash
cd "C:\Program Files\RAS Agent"
ras_agent.exe test
```

Output harus:
```
✅ Connection test successful: Connection successful
```

### Cek Dashboard:

Buka dashboard di server:
```
http://localhost/RAS/admin/
```

Cek halaman **Devices** - device klien harus muncul!

---

## 🔧 Konfigurasi yang Diperlukan

### Minimal Config (config.json):

```json
{
  "agent": {
    "device_id": "unique-id-untuk-device-ini",
    "hostname": "Nama-Komputer",
    "api_endpoint": "URL-Server-Anda/RAS/admin/api/metrics.php",
    "api_key": "API-Key-dari-dashboard",
    "collect_interval": 60
  }
}
```

### Field Penting:

| Field | Contoh | Keterangan |
|-------|--------|------------|
| `device_id` | `"kantor-laptop-001"` | ID unik untuk device |
| `hostname` | `"Laptop-Budi"` | Nama komputer |
| `api_endpoint` | Full URL ke API | Server RAS |
| `api_key` | `"abc123..."` | Kunci autentikasi |
| `collect_interval` | `60` | Interval (detik) |

---

## 🎯 Troubleshooting

### Masalah: "Connection Timeout"

**Solusi:**
1. Cek internet di komputer klien
2. Cek URL `api_endpoint` di config.json
3. Test dari browser klien:
   ```
   https://heathered-dortha-unparsed.ngrok-free.dev/RAS/admin/
   ```

### Masalah: "Authentication Failed"

**Solusi:**
1. Cek API key di config.json
2. Pastikan API key sama dengan di server
3. API key ada di `config/config.php` di server

### Masalah: "Service Not Starting"

**Solusi:**
1. Buka Command Prompt sebagai administrator
2. Cek error:
   ```bash
   sc query RASAgent
   type "C:\Program Files\RAS Agent\ras_agent.log"
   ```
3. Reinstall service:
   ```bash
   cd "C:\Program Files\RAS Agent"
   ras_agent.exe remove
   ras_agent.exe install
   ras_agent.exe start
   ```

### Masalah: Device Tidak Muncul di Dashboard

**Solusi:**
1. Cek agent sudah running:
   ```bash
   sc query RASAgent
   ```
2. Test koneksi:
   ```bash
   ras_agent.exe test
   ```
3. Cek log:
   ```bash
   type "C:\Program Files\RAS Agent\ras_agent.log"
   ```
4. Refresh dashboard (F5)
5. Tunggu 1-2 menit untuk update

---

## 📋 Commands yang Berguna

### Service Management:

```bash
# Install service
ras_agent.exe install

# Start service
ras_agent.exe start

# Stop service
ras_agent.exe stop

# Restart service
ras_agent.exe restart

# Remove service
ras_agent.exe remove

# Check status
sc query RASAgent
```

### Testing:

```bash
# Test koneksi
ras_agent.exe test

# Cek version
ras_agent.exe version

# Run standalone (tanpa service)
ras_agent.exe
```

### Configuration:

```bash
# Buka config file
notepad "C:\Program Files\RAS Agent\config.json"

# Atau via Start Menu
Start → RAS Agent → Configuration
```

### Logs:

```bash
# View logs
type "C:\Program Files\RAS Agent\ras_agent.log"

# Atau via Start Menu
Start → RAS Agent → View Logs
```

---

## 🔄 Update Instalasi

### Cara Update Agent:

1. **Stop service lama:**
   ```bash
   ras_agent.exe stop
   ```

2. **Replace file:**
   - Copy `ras_agent.exe` baru ke `C:\Program Files\RAS Agent\`
   - Overwrite file lama

3. **Start service:**
   ```bash
   ras_agent.exe start
   ```

---

## ❓ FAQ

**Q: Apakah perlu internet di komputer klien?**
A: Ya, agent perlu internet untuk mengirim metrics ke server.

**Q: Berapa bandwidth yang digunakan?**
A: Sangat minimal (~1KB per 60 detik).

**Q: Apakah agent mengganggu performa komputer?**
A: Tidak, agent menggunakan <1% CPU dan <50MB memory.

**Q: Bagaimana cara uninstall?**
A: Jalankan `uninstall.bat` atau via Control Panel → Programs.

**Q: Apakah data saya aman?**
A: Ya, agent hanya mengirim metrics sistem (CPU, memory, disk), bukan data pribadi.

**Q: Berapa sering agent mengirim data?**
A: Sesuai config `collect_interval` (default: 60 detik).

**Q: Apakah bisa install tanpa admin?**
A: Tidak, butuh admin untuk install Windows service.

---

## 📞 Support

Jika ada masalah:
1. Cek log di `ras_agent.log`
2. Test koneksi dengan `ras_agent.exe test`
3. Hubungi admin IT dengan detail error

---

## ✅ Checklist Instalasi

Sebelum menganggap instalasi selesai:

- [ ] Installer sudah dijalankan sebagai administrator
- [ ] Service status: "Running"
- [ ] Config.json sudah diupdate dengan endpoint dan API key yang benar
- [ ] Test koneksi berhasil (`ras_agent.exe test`)
- [ ] Device muncul di dashboard RAS
- [ ] Log tidak ada error (hanya INFO)

---

**Selamat! RAS Agent sudah siap digunakan di komputer klien. 🎉**

*Agent sekarang akan mengirim metrics secara otomatis ke server RAS dan bisa dimonitor dari dashboard.*
