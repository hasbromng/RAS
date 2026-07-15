# MVP Client: Python Monitoring Agent (Updated)

## Tujuan
Membangun agen monitoring yang efisien, handal, dan aman yang berjalan di perangkat klien (*background process*) untuk mengumpulkan metrik sistem secara komprehensif dan mentransmisikannya ke server melalui API yang terautentikasi.

## Scope MVP (Diperbarui)
- Agen berjalan sebagai *Windows Service* atau *Linux Daemon* yang *silent*.
- Pengumpulan metrik terperinci mencakup CPU, RAM, Disk, dan konektivitas.
- Komunikasi dengan server menggunakan *API Key Authentication* di *header* HTTP.
- Mekanisme *retry*, *backoff*, dan *local buffering* jika server tidak dapat diakses.

## Teknologi
- **Bahasa:** Python 3.x
- **Library Inti:** `psutil` (untuk metrik sistem), `requests` (komunikasi HTTP), `schedule` (penjadwalan tugas).
- **Deployment:** *Windows Service* (menggunakan `pywin32`) atau setara untuk sistem lain.

## Fitur Utama
1. **Pengumpulan Metrik Sistem Lanjutan**
   - Penggunaan CPU (%) secara keseluruhan dan per-*core*.
   - Analisis Memori: Total RAM, penggunaan (angka dan persentase), *swap*.
   - Analisis Storage: Kapasitas disk total, penggunaan disk (dalam Bytes dan %), status IO dasar.
   - Jaringan: Alamat IP aktual dan status konektivitas internet.

2. **Pengiriman Data Terautentikasi**
   - Format payload JSON yang terstruktur.
   - Menyertakan header `X-API-Key` pada setiap *request*.
   - Interval pengiriman dapat dikonfigurasi secara lokal (misal: setiap 1-5 menit).

3. **Ketahanan & Failover (Resiliency)**
   - Jika koneksi API gagal, agen akan menyimpan sementara (buffer) payload di penyimpanan lokal.
   - Menggunakan algoritma pengiriman ulang otomatis (*retry*) ketika koneksi kembali normal.

4. **Operasi Mode *Silent***
   - Beban penggunaan CPU agen kurang dari 2% dan penggunaan memori kurang dari 50MB.
   - *Log rotation* untuk pencatatan *error* lokal agar tidak menghabiskan *storage*.

## Format Payload JSON (Contoh)
```json
{
    "device_id": "UUID-1234-5678",
    "hostname": "LAPTOP-MARKETING",
    "ip_address": "192.168.1.55",
    "cpu_usage": 45.5,
    "memory_used": 4294967296,
    "memory_total": 8589934592,
    "disk_used": 120000000000,
    "disk_total": 500000000000,
    "disk_usage": 24.0,
    "storage_health": "healthy",
    "network_status": "good"
}
```

## Langkah Pengembangan (Status: Tahap Berikutnya)
1. Penyusunan modul ekstraktor data menggunakan `psutil`.
2. Implementasi modul HTTP Client dengan otorisasi `X-API-Key`.
3. Pembangunan fitur antrian lokal (*queueing*) untuk *offline state*.
4. Pembungkusan *script* menjadi *executable* atau *Windows Service*.
5. Pengujian beban (*stress test*) untuk memastikan efisiensi *resource*.
