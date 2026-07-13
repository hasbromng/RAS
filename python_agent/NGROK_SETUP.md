# Testing RAS Agent dengan ngrok

## Setup di Server Lokal

### 1. Pastikan XAMPP Running

```bash
# Start Apache
# XAMPP Control Panel → Apache → Start
```

### 2. Jalankan ngrok

```bash
ngrok http 80
```

**Catat URL yang muncul:**
```
https://heathered-dortha-unparsed.ngrok-free.dev
```

### 3. Cek API Key

Buka file [`config/config.php`](../config/config.php) dan catat API key:
```php
define('API_KEY', 'your-api-key-here');
```

---

## Setup di Komputer Klien

### 1. Copy Python Agent ke Klien

Copy seluruh folder `python_agent` ke komputer klien:
- Via USB
- Via network share
- Via download dari repo

### 2. Install Python Agent

```bash
cd python_agent

# Windows
install.bat

# Linux
sudo ./install.sh
```

### 3. Edit config.json

Buka `config.json` dan update:

```json
{
  "agent": {
    "device_id": "laptop-john-001",
    "hostname": "John-Laptop",
    "api_endpoint": "https://heathered-dortha-unparsed.ngrok-free.dev/RAS/admin/api/metrics.php",
    "api_key": "your-actual-api-key",
    "collect_interval": 60
  }
}
```

**PENTING:**
- `device_id`: Unique ID untuk device ini
- `hostname`: Nama komputer klien
- `api_endpoint`: URL ngrok + path ke API
- `api_key`: API key dari server (bukan default!)

### 4. Test Koneksi

```bash
# Test koneksi dulu
python test_ngrok_connection.py

# Jika test berhasil, jalankan agent
python -m ras_agent.agent
```

### 5. Start Service (Windows)

```bash
python service/windows_service.py start
```

Atau via Service Manager:
```bash
services.msc
# Cari "RAS Monitoring Agent" → Start
```

---

## Verifikasi di Dashboard

Buka dashboard lokal:
```
http://localhost/RAS/admin/
```

Cek halaman **Devices** - device klien seharusnya muncul dalam 1-2 menit!

---

## Troubleshooting

### ❌ Connection Timeout

**Masalah:** Agent tidak bisa connect ke ngrok

**Solusi:**
1. Pastikan ngrok running di server
2. Cek URL ngrok benar: `https://heathered-dortha-unparsed.ngrok-free.dev`
3. Test koneksi dari browser klien:
   ```
   https://heathered-dortha-unparsed.ngrok-free.dev/RAS/admin/
   ```

### ❌ Authentication Failed (401)

**Masalah:** API key salah

**Solusi:**
1. Buka `config/config.php` di server
2. Copy API_KEY yang sebenarnya
3. Update di `config.json` klien

### ❌ Device Tidak Muncul di Dashboard

**Masalah:** Metrics terkirim tapi tidak muncul

**Solusi:**
1. Cek log agent: `ras_agent.log`
2. Pastikan response dari API adalah `success: true`
3. Cek dashboard → Devices page
4. Refresh dashboard (Ctrl+F5)

### ❌ ngrok URL Berubah

**Masalah:** Setelah restart ngrok, URL berubah

**Solusi:**
1. Update config.json di semua klien dengan URL baru
2. Atau upgrade ke ngrok paid untuk domain statis

---

## Quick Start untuk Testing

### Di Server (Lokal):

```bash
# 1. Start XAMPP (Apache)
# 2. Start ngrok
ngrok http 80

# 3. Catat URL dan API key
# URL: https://heathered-dortha-unparsed.ngrok-free.dev
# API Key: (dari config/config.php)
```

### Di Klien:

```bash
# 1. Copy python_agent folder
# 2. Edit config.json:
#    - api_endpoint: https://heathered-dortha-unparsed.ngrok-free.dev/RAS/admin/api/metrics.php
#    - api_key: (dari server)
#    - device_id: unique-id
#    - hostname: nama-komputer

# 3. Test
python test_ngrok_connection.py

# 4. Jika berhasil, install
install.bat

# 5. Start service
python service/windows_service.py start
```

---

## File-file yang Relevant

| File | Deskripsi |
|------|-----------|
| [`test_ngrok_connection.py`](test_ngrok_connection.py) | Script test koneksi |
| [`config.ngrok.json`](config.ngrok.json) | Template config untuk ngrok |
| [`config.json.template`](config.json.template) | Template config umum |
| [`config/config.php`](../config/config.php) | API key di server |

---

## Catatan Penting

1. **Untuk Testing Only** - Setup ini untuk development/testing
2. **Production** - Gunakan VPS dengan domain dan SSL proper
3. **Security** - Jangan share API key secara publik
4. **ngrok Free** - URL berubah setiap restart
5. **Monitor** - Cek dashboard secara regular untuk incoming data

---

## Checklist Sebelum Testing

- [ ] XAMPP Apache running
- [ ] ngrok running dan tercatat URL-nya
- [ ] API key sudah dicatat dari config.php
- [ ] Python agent sudah diinstall di klien
- [ ] config.json sudah diupdate dengan:
  - [ ] URL ngrok yang benar
  - [ ] API key yang benar
  - [ ] device_id unique
  - [ ] hostname yang sesuai
- [ ] Test koneksi berhasil (test_ngrok_connection.py)
- [ ] Agent service started
- [ ] Dashboard menampilkan device klien

---

## Contoh Output yang Sukses

### Test Connection Script:

```
============================================================
RAS Agent - ngrok Connection Test
============================================================

ngrok URL: https://heathered-dortha-unparsed.ngrok-free.dev
API Endpoint: https://heathered-dortha-unparsed.ngrok-free.dev/RAS/admin/api/metrics.php
============================================================

🔍 Testing connection to ngrok endpoint...
   URL: https://heathered-dortha-unparsed.ngrok-free.dev/RAS/admin/api/metrics.php

✅ Connection successful!
   Status Code: 200
   Response: {"success":false,"message":"Method not allowed. Use POST."}...

🔐 Testing API authentication...
   API Key: change...ecure
   Status Code: 200

✅ API authentication successful!
   Message: Metrics received successfully
   Device ID: test-connection
   Status: online
   Alerts Created: 0

============================================================
✅ All tests passed!
============================================================

Your ngrok tunnel is working properly!
You can now install and run the Python agent.
```

### Agent Running:

```
[2025-01-11 10:30:00] [INFO] ras_agent.agent - ============================================================
[2025-01-11 10:30:00] [INFO] ras_agent.agent - RAS Monitoring Agent Starting
[2025-01-11 10:30:00] [INFO] ras_agent.agent - Device ID: laptop-john-001
[2025-01-11 10:30:00] [INFO] ras_agent.agent - Hostname: John-Laptop
[2025-01-11 10:30:00] [INFO] ras_agent.agent - API Endpoint: https://heathered-dortha-unparsed.ngrok-free.dev/RAS/admin/api/metrics.php
[2025-01-11 10:30:00] [INFO] ras_agent.agent - ============================================================
[2025-01-11 10:30:01] [INFO] ras_agent.agent - Testing connection to API endpoint...
[2025-01-11 10:30:02] [INFO] ras_agent.agent - Connection test successful: Connection successful
[2025-01-11 10:30:05] [INFO] ras_agent.agent - Collecting system metrics...
[2025-01-11 10:30:05] [INFO] ras_agent.agent - Metrics sent successfully. Device status: online, Alerts created: 0
```

---

## Selamat Testing! 🚀

Dengan setup ini, Anda bisa:
- Test agent dari komputer manapun dengan internet
- Verifikasi bahwa metrics dikirim dengan benar
- Lihat data real-time di dashboard lokal
- Development tanpa perlu VPS/Domain

**Ready to test?** Jalankan `test_ngrok_connection.py` untuk memulai!
