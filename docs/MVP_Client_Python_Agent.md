# MVP Client: Python Monitoring Agent

## Tujuan
Membangun agen monitoring ringan yang berjalan di background pada perangkat target untuk mengumpulkan metrik sistem dan mengirimkannya ke server monitoring.

## Scope MVP
- Agen harus berjalan sebagai proses/service ringan
- Mengumpulkan metrik penting secara berkala
- Mengirim data ke server PHP melalui API
- Menyimpan sementara data lokal saat server tidak tersedia
- Menggunakan sumber daya minimal agar tidak mengganggu pengguna

## Teknologi
- Bahasa: Python 3.x
- Library inti: `psutil`, `requests`, `schedule` atau `APScheduler`
- Deployment: sebagai service Windows (Task Scheduler/Windows Service) atau daemon Linux

## Fitur Utama
1. Pengumpulan Metrik Sistem
   - Penggunaan CPU per core dan rata-rata
   - Penggunaan memori fisik dan swap
   - Penggunaan disk, ruang bebas, I/O dasar
   - Kesehatan storage: status disk, SMART (opsional jika tersedia)
   - Status jaringan: pengukuran konektivitas dan alamat IP
   - Ketersediaan perangkat (heartbeat)

2. Pengiriman Data
   - Mengirim paket JSON ke endpoint PHP
   - Interval pengiriman konfigurabel (misalnya setiap 60 detik)
   - Retry dan backoff jika koneksi gagal
   - Buffer lokal sementara saat server tidak tersedia

3. Monitoring Background
   - Low CPU dan memori
   - Tidak mengganggu aktivitas pengguna
   - Ukuran proses kecil
   - Mode silent dan tanpa UI

4. Deteksi Masalah Kritis
   - Threshold awal untuk CPU tinggi, memori rendah, disk penuh
   - Log sederhana di file lokal
   - Prioritas: kirim notifikasi ke server ketika kondisi kritis terdeteksi

## Struktur Arsitektur
- Agent Python
  - Modul pengumpulan metrik
  - Modul pengiriman ke API
  - Modul penyimpanan sementara
  - Konfigurasi threshold dan endpoint
- Endpoint PHP/MySQL
  - Menerima data dari agent
  - Menyimpan metrik dan status perangkat

## Rincian Data yang Dikirim
- `device_id`
- `hostname`
- `timestamp`
- `cpu_usage`
- `memory_total`, `memory_used`, `memory_free`
- `disk_total`, `disk_used`, `disk_free`, `disk_usage`
- `storage_health` (jika tersedia)
- `network_status`
- `online_status`

## Definisi MVP Minimal
- Agent Python capable collecting CPU, memory, disk, network, and heartbeat
- Data sent to PHP endpoint reliably
- Configurable send interval
- Local queueing when offline

## Langkah Pengembangan
1. Buat script Python untuk membaca metrik dasar dengan `psutil`
2. Tambahkan fungsi pengiriman data ke API PHP
3. Tambahkan mekanisme retry dan buffer lokal
4. Siapkan agent sebagai service/daemon
5. Uji di Windows dan Linux dengan pekerjaan latar belakang

## Catatan Khusus
- Fokus pada stabilitas background dan efisiensi
- Hindari polling terlalu sering; gunakan interval 30-60 detik
- Simpan log kecil agar tidak memenuhi storage
- Versi awal tidak perlu SMART kompleks kecuali sistem mendukung
