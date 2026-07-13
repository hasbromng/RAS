# MVP Admin: PHP + MySQL Dashboard

## Tujuan
Membangun dashboard admin yang menampilkan status perangkat secara real-time, riwayat metrik, dan notifikasi kritis menggunakan PHP dan MySQL.

## Scope MVP
- Tampilan dashboard untuk administrator
- Menyimpan data dari client Python
- Menyediakan notifikasi email untuk kondisi kritis
- Menampilkan laporan performa sederhana
- Menyediakan manajemen perangkat dasar

## Teknologi
- Backend: PHP 7.x/8.x
- Database: MySQL atau MariaDB
- Frontend: HTML/CSS/JavaScript dengan desain modern Material UI
  - Gunakan library Material Design seperti Materialize, MDL, atau tema Material Bootstrap
  - UI harus bersih, responsif, dan mudah dinavigasi
- API: Endpoint PHP untuk menerima data JSON dari agent Python

## Fitur Utama
1. Dashboard Real-Time
   - Daftar perangkat dengan status online/offline
   - Ringkasan metrik CPU, memori, disk, kesehatan storage
   - Indikator masalah kritis dan notifikasi

2. Manajemen Perangkat
   - Daftar perangkat terdaftar
   - Informasi hostname dan IP
   - Status terakhir koneksi
   - Detail perangkat per unit

3. Notifikasi dan Insiden
   - Pengiriman email otomatis ketika threshold kritis terlampaui
   - Riwayat insiden sederhana
   - Konfigurasi notifikasi email dasar

4. Laporan Berkala
   - Ringkasan performa harian atau mingguan
   - Grafik sederhana untuk CPU, memori, disk
   - Ekspor laporan ke CSV (opsional)

## Arsitektur Sistem
- PHP Frontend: menampilkan dashboard, laporan, dan manajemen perangkat
- PHP API Endpoint: menerima data dari agent Python
- MySQL Database: menyimpan perangkat, metrik, dan log notifikasi

## Struktur Database Awal
- `devices`
  - `id`, `device_id`, `hostname`, `ip_address`, `last_seen`, `status`
- `metrics`
  - `id`, `device_id`, `timestamp`, `cpu_usage`, `memory_used`, `memory_total`, `disk_used`, `disk_total`, `disk_usage`, `storage_health`, `network_status`
- `alerts`
  - `id`, `device_id`, `timestamp`, `alert_type`, `message`, `status`

## Definisi MVP Minimal
- Dashboard device status working dengan data dari agent Python
- API endpoint PHP untuk menerima data JSON
- Penyimpanan MySQL yang baik untuk metrik dan alert
- Notifikasi email untuk kondisi kritis

## Langkah Pengembangan
1. Rancang database MySQL untuk device, metrics, dan alerts
2. Buat endpoint PHP untuk menerima dan menyimpan data JSON
3. Bangun dashboard viewing terakhir data perangkat
4. Implementasikan email notifikasi (SMTP sederhana)
5. Tambahkan laporan ringkas harian

## Catatan Khusus
- Pastikan validasi data di API PHP
- Gunakan prepared statements untuk keamanan MySQL
- Sederhanakan UI agar mudah dipakai admin
- Pisahkan logika API dan tampilan dashboard
