# RAS Agent - Configuration Guide

## 📋 Overview

File `config.json` adalah konfigurasi utama untuk Python Agent. File ini fleksibel dan dapat digunakan untuk berbagai skenario deployment.

---

## 🎯 Skenario Deployment

### 1️⃣ Development (Localhost)

**Gunakan ketika:** Agent berjalan di mesin yang sama dengan dashboard

**File:** `config.json` atau `config.local.json`

```json
{
  "agent": {
    "api_endpoint": "http://localhost/RAS/admin/api/metrics.php"
  }
}
```

**Setup:**
```bash
# Copy template
cp config.json.template config.json

# Edit jika perlu
nano config.json
```

---

### 2️⃣ Local Network (LAN)

**Gunakan ketika:** Agent di komputer lain dalam jaringan yang sama

**File:** `config.local.json`

```json
{
  "agent": {
    "api_endpoint": "http://192.168.1.100/RAS/admin/api/metrics.php"
  }
}
```

**Setup:**
1. Cari IP server lokal:
   ```bash
   ipconfig   # Windows
   ifconfig   # Linux
   ```

2. Pastikan firewall mengizinkan koneksi:
   - Windows Firewall: Allow port 80
   - Linux Firewall: `sudo ufw allow 80`

3. Test dari klien:
   ```bash
   curl http://192.168.1.100/RAS/admin/
   ```

---

### 3️⃣ Remote Testing (ngrok)

**Gunakan ketika:** Testing agent dari luar jaringan tanpa VPS

**File:** `config.ngrok.json`

```json
{
  "agent": {
    "api_endpoint": "https://heathered-dortha-unparsed.ngrok-free.dev/RAS/admin/api/metrics.php"
  }
}
```

**Setup:**
1. Install ngrok di server
2. Jalankan tunnel:
   ```bash
   ngrok http 80
   ```
3. Copy URL ngrok ke config
4. Test koneksi:
   ```bash
   python test_ngrok_connection.py
   ```

**Catatan:** URL ngrok berubah setiap restart (free tier)

---

### 4️⃣ Production (Domain + HTTPS)

**Gunakan ketika:** Deployment production dengan proper domain

**File:** `config.production.json`

```json
{
  "agent": {
    "api_endpoint": "https://monitoring.company.com/RAS/admin/api/metrics.php"
  }
}
```

**Setup:**
1. Siapkan VPS dengan domain
2. Install SSL certificate (Let's Encrypt)
3. Deploy RAS dashboard ke VPS
4. Update config dengan domain HTTPS

**Keuntungan:**
- ✅ Secure (HTTPS)
- ✅ URL stabil (tidak berubah)
- ✅ Professional untuk production

---

### 5️⃣ Production (VPS IP)

**Gunakan ketika:** Production tanpa domain

**File:** `config.production.json` (custom)

```json
{
  "agent": {
    "api_endpoint": "http://203.0.113.10/RAS/admin/api/metrics.php"
  }
}
```

**Setup:**
1. Deploy ke VPS
2. Pastikan firewall mengizinkan port 80 dari luar
3. Gunakan IP public VPS

**Catatan:** Kurang secure karena HTTP (tanpa SSL)

---

## 🔧 Konfigurasi Detail

### Agent Settings

| Field | Default | Deskripsi |
|-------|---------|-----------|
| `device_id` | auto-generated | Unique ID untuk device |
| `hostname` | auto-detected | Nama hostname komputer |
| `api_endpoint` | required | URL API endpoint |
| `api_key` | required | Kunci autentikasi |
| `collect_interval` | 60 | Interval pengiriman (detik) |
| `buffer_max_size` | 1000 | Maksimal buffer saat offline |
| `buffer_file` | buffer.json | File buffer lokal |
| `log_file` | ras_agent.log | File log |
| `log_max_size_mb` | 10 | Maksimal ukuran log (MB) |
| `log_backup_count` | 5 | Jumlah backup log |

### Threshold Settings

| Field | Default | Deskripsi |
|-------|---------|-----------|
| `cpu_warning` | 80 | CPU % untuk warning |
| `cpu_critical` | 90 | CPU % untuk critical |
| `memory_warning` | 80 | Memory % untuk warning |
| `memory_critical` | 90 | Memory % untuk critical |
| `disk_warning` | 75 | Disk % untuk warning |
| `disk_critical` | 85 | Disk % untuk critical |

---

## 🚀 Quick Setup Commands

### Untuk Local Network:
```bash
# Copy template
cp config.local.json.template config.json

# Edit dengan IP server
nano config.json
```

### Untuk ngrok Testing:
```bash
# Copy template
cp config.ngrok.json config.json

# Edit dengan URL ngrok yang aktif
nano config.json
```

### Untuk Production:
```bash
# Copy template
cp config.production.json.template config.json

# Edit dengan domain production
nano config.json
```

---

## 🔐 Security Best Practices

### 1. **API Key Management**
- ❌ Jangan gunakan default API key
- ✅ Generate unique API key per deployment
- ✅ Simpan API key di environment variable:
  ```bash
  export RAS_API_KEY="your-secure-key"
  ```

### 2. **HTTPS di Production**
- ✅ Selalu gunakan HTTPS untuk production
- ✅ Gunakan SSL certificate yang valid
- ❌ Hindari HTTP untuk data sensitif

### 3. **Firewall Configuration**
- ✅ Buka hanya port yang diperlukan
- ✅ Batasi akses dari IP tertentu jika memungkinkan
- ✅ Monitor traffic untuk suspicious activity

### 4. **File Permissions**
```bash
# Linux: Restrict config file access
chmod 600 config.json
chown root:root config.json
```

---

## 🧪 Testing Configuration

### Test 1: Basic Connection
```bash
python test_ngrok_connection.py
```

### Test 2: Manual API Test
```bash
curl -X POST http://your-server/RAS/admin/api/metrics.php \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{"device_id":"test","hostname":"test","ip_address":"192.168.1.1"}'
```

### Test 3: Run Agent Once
```bash
python -m ras_agent.agent
```

---

## 📝 Troubleshooting

### ❌ "Connection Timeout"

**Masalah:** Agent tidak bisa connect ke API

**Solusi:**
1. Cek endpoint URL di config.json
2. Test koneksi dari browser:
   ```
   http://your-server/RAS/admin/
   ```
3. Cek firewall di server
4. Untuk ngrok: Pastikan ngrok running

### ❌ "Authentication Failed" (401)

**Masalah:** API key salah

**Solusi:**
1. Buka `config/config.php` di server
2. Copy `API_KEY` yang sebenarnya
3. Update di config.json

### ❌ "Device Not Showing in Dashboard"

**Masalah:** Metrics terkirim tapi device tidak muncul

**Solusi:**
1. Cek log agent: `ras_agent.log`
2. Pastikan response API adalah `success: true`
3. Refresh dashboard (Ctrl+F5)
4. Tunggu 1-2 menit untuk update

---

## 🎓 Best Practices

### Development Environment:
- ✅ Gunakan localhost atau local network
- ✅ Interval collection yang lebih pendek (30 detik)
- ✅ Log level DEBUG

### Testing Environment:
- ✅ Gunakan ngrok untuk remote testing
- ✅ Test dengan multiple device types
- ✅ Verifikasi buffer functionality

### Production Environment:
- ✅ Gunakan HTTPS dengan proper domain
- ✅ Interval collection yang optimal (60-120 detik)
- ✅ Log level WARNING atau ERROR
- ✅ Monitor resource usage
- ✅ Set up alerting

---

## 📚 Template Comparison

| Template | Use Case | Endpoint Type | Security |
|----------|----------|---------------|----------|
| [`config.json.template`](config.json.template) | Default | Flexible | Medium |
| [`config.ngrok.json`](config.ngrok.json) | Testing | HTTPS (ngrok) | High |
| [`config.local.json.template`](config.local.json.template) | LAN | HTTP | Low |
| [`config.production.json.template`](config.production.json.template) | Production | HTTPS (Domain) | High |

---

## 🔄 Migrasi Antar Environment

### Dari Testing ke Production:

1. **Backup config testing:**
   ```bash
   cp config.json config.testing.backup
   ```

2. **Setup config production:**
   ```bash
   cp config.production.json.template config.json
   nano config.json  # Update dengan production domain
   ```

3. **Test production connection:**
   ```bash
   python test_ngrok_connection.py
   ```

4. **Restart agent service:**
   ```bash
   # Windows
   python service/windows_service.py restart

   # Linux
   sudo systemctl restart ras-agent
   ```

---

## ✅ Configuration Checklist

Sebelum deploy agent, pastikan:

- [ ] Endpoint URL benar dan accessible
- [ ] API key valid dan sesuai dengan server
- [ ] Device ID unique (jika manual)
- [ ] Hostname sesuai dengan nama komputer
- [ ] Collect interval sesuai kebutuhan
- [ ] Log file location writable
- [ ] Buffer file location writable
- [ ] Threshold values sesuai dengan requirement
- [ ] Firewall mengizinkan koneksi
- [ ] Test koneksi berhasil

---

## 💡 Tips

1. **Gunakan environment variables** untuk sensitive data:
   ```bash
   export RAS_API_KEY="production-key"
   ```

2. **Version control config templates** bukan config dengan API keys

3. **Document setiap deployment** dengan environment dan endpoint yang digunakan

4. **Test setiap perubahan config** di development dulu

5. **Monitor logs** secara regular untuk troubleshooting

---

## 📞 Support

Untuk bantuan konfigurasi:
- Cek [`README.md`](README.md) untuk setup umum
- Cek [`NGROK_SETUP.md`](NGROK_SETUP.md) untuk setup ngrok
- Review logs di `ras_agent.log` untuk error messages

---

**Happy Monitoring! 🚀**
