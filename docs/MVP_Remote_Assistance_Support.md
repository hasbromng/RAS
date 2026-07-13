# MVP: Aplikasi Monitoring Support Perangkat

## Tujuan Utama
Membangun aplikasi monitoring perangkat yang memungkinkan tim support memantau komputer dan laptop secara real-time, mendeteksi masalah dini, dan mengirim notifikasi untuk tindakan cepat.

## Sasaran MVP
- Memantau performa perangkat dengan metrik kunci
- Menampilkan status koneksi jaringan dan internet
- Mengirim notifikasi real-time saat terjadi masalah kritis
- Menyediakan laporan performa berkala
- Menjaga antarmuka sederhana dan kompatibilitas lintas OS

## Teknologi yang Digunakan
- Dashboard admin: PHP dengan MySQL sebagai basis data
- Backend: PHP untuk penerimaan data, penyimpanan, dan logika notifikasi
- Database: MySQL untuk menyimpan metrik perangkat, riwayat notifikasi, dan konfigurasi threshold
- Agen monitoring client: modul ringan di perangkat target menggunakan Python yang berjalan di background dan mengirim data ke server PHP

## Dokumen Terpisah MVP
- `MVP_Client_Python_Agent.md`: detail rancangan client Python
- `MVP_Admin_PHP_MySQL.md`: detail rancangan admin PHP + MySQL

## Dua MVP Terpisah
- MVP Client: agen monitoring Python yang berjalan di komputer/laptop target
- MVP Admin: dashboard admin berbasis PHP + MySQL untuk memantau perangkat dan mengelola notifikasi

## MVP Client (Python Agent)
- Berjalan di background sebagai proses/service ringan
- Mengumpulkan metrik sistem: CPU, memori, disk, kesehatan storage, status jaringan
- Mengirim data secara berkala ke server PHP melalui API endpoint
- Melakukan deteksi dasar online/offline
- Tidak membebani CPU atau memori perangkat target
- Menyimpan sementara data lokal jika sambungan ke server terputus

## MVP Admin (PHP + MySQL)
- Menyediakan dashboard admin untuk melihat status perangkat secara real-time
- Menyimpan dan menampilkan metrik perangkat dari client Python
- Menyediakan notifikasi email saat metrik kritis terdeteksi
- Menyajikan laporan performa harian atau mingguan
- Menyediakan daftar perangkat, pencarian, dan status online/offline

## Fitur Utama MVP
1. Dashboard Real-Time
   - Tampilan status perangkat secara langsung
   - Ringkasan CPU, memori, disk, dan jaringan
   - Indikator cepat untuk perangkat yang bermasalah

2. Pengumpulan Metrik Perangkat
   - Penggunaan CPU (%)
   - Penggunaan memori (RAM)
   - Penggunaan disk dan I/O dasar
   - Kesehatan storage dan kapasitas disk
   - Status koneksi jaringan
   - Ketersediaan perangkat (online/offline)

3. Notifikasi Insiden
   - Pemberitahuan saat metrik melebihi ambang batas kritis
   - Notifikasi via email atau pesan instan (integrasi awal dapat memakai email)
   - Log insiden sederhana untuk tindak lanjut

4. Laporan Berkala
   - Ringkasan performa harian atau mingguan
   - Data penggunaan CPU, memori, disk, dan kesehatan storage
   - Informasi tren koneksi jaringan
   - Ekspor laporan ke PDF/CSV (opsional tahap awal)

5. Kemudahan Penggunaan dan Kompatibilitas
   - Interface sederhana dan mudah dipahami
   - Dukungan dasar untuk Windows dan Linux (macOS opsional)
   - Agen ringan atau klien monitoring yang mudah dipasang pada perangkat

## Prioritas Fungsional
- Prioritas Tinggi
  - Dashboard real-time
  - Pengumpulan metrik CPU, memori, disk, dan kesehatan storage
  - Deteksi status online/offline
  - Notifikasi kritis

- Prioritas Menengah
  - Laporan berkala
  - Pengiriman notifikasi email
  - Filter perangkat dan pencarian cepat

- Prioritas Rendah
  - Integrasi pesan instan tambahan
  - Analisis mendalam dan tren lanjutan
  - Dukungan macOS penuh

## Definisi MVP Minimal
- Client Python yang berjalan di background untuk pengumpulan metrik
- Server PHP + MySQL untuk penyimpanan dan tampilan dashboard
- Endpoint API PHP untuk menerima data dari client Python
- Notifikasi email untuk masalah kritis
- Laporan ringkas harian

## Langkah Pengembangan MVP
1. Rancang arsitektur sederhana: server + agen monitoring
2. Buat modul pengumpulan metrik dasar
3. Bangun dashboard tampilan status
4. Implementasikan mekanisme notifikasi kritis
5. Tambahkan laporan berkala dasar

## Catatan Tambahan
- Fokus pada stabilitas dan performa agen monitoring
- Batasi jumlah metrik awal agar implementasi lebih cepat
- Siapkan mekanisme threshold yang dapat disesuaikan di tahap berikutnya

## Roadmap Pengembangan

### Fase 1: MVP dan Peluncuran Awal
- Bangun arsitektur server + agen monitoring
- Kumpulkan metrik: CPU, memori, disk, kesehatan storage, status jaringan
- Implementasikan dashboard real-time sederhana
- Tambahkan deteksi online/offline perangkat
- Siapkan notifikasi email untuk masalah kritis
- Buat laporan performa harian dasar

### Fase 2: Penyempurnaan dan Skalabilitas
- Tambahkan filter perangkat, pencarian, dan grup perangkat
- Perluas notifikasi ke pesan instan seperti Telegram/WhatsApp
- Tingkatkan laporan berkala dengan tren grafik dan ringkasan per pengguna
- Tambahkan konfigurasi threshold yang dapat disesuaikan
- Optimalkan performa agen dan konsumsi bandwidth

### Fase 3: Analisis Lanjutan dan Integrasi
- Tambahkan analisis tren jangka panjang dan prediksi potensi kegagalan
- Integrasi dengan sistem tiket/service desk
- Tambahkan dukungan macOS dan platform lain jika diperlukan
- Sediakan akses mobile atau notifikasi seluler
- Tambahkan eksport laporan ke PDF/CSV dan dashboard user-friendly

### Fase 4: Otomasi dan Proaktif
- Tambahkan rekomendasi tindakan otomatis berdasarkan kondisi perangkat
- Buat rule-based atau AI-driven deteksi anomali
- Siapkan pemantauan patch dan update perangkat lunak dasar
- Tambahkan fitur audit dan riwayat perbaikan
