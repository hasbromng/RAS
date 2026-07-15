# MVP Admin: PHP + MySQL Dashboard (Updated)

## Tujuan
Membangun dashboard admin yang komprehensif untuk menampilkan status perangkat secara real-time, visualisasi riwayat metrik menggunakan grafik interaktif, dan manajemen notifikasi/peringatan dengan konfigurasi dinamis berbasis antarmuka web.

## Scope MVP (Diperbarui)
- Tampilan dashboard interaktif dengan fitur *auto-refresh* dan visualisasi data.
- Penyimpanan metrik historis yang aman dari client Python dengan autentikasi API Key.
- Sistem *Alert* cerdas dengan tingkatan (*severity levels*) dan manajemen penyelesaian (*acknowledgment*).
- Konfigurasi sistem dinamis (SMTP, *Threshold*) yang dapat diatur langsung dari dashboard tanpa mengubah kode.
- Installer berbasis web (`install.php`) untuk kemudahan *deployment*.

## Teknologi
- **Backend:** PHP 7.4+
- **Database:** MySQL / MariaDB 5.7+
- **Frontend:** HTML5, CSS3, JavaScript (ES6)
- **UI Framework:** Materialize CSS (Material Design)
- **Data Visualization:** Chart.js
- **API:** REST API endpoint berbasis JSON.

## Fitur Utama
1. **Dashboard Real-Time & Interaktif**
   - Daftar perangkat dengan status *online/offline/warning/critical*.
   - Ringkasan rata-rata metrik CPU, memori, dan disk dengan *auto-refresh*.
   - Visualisasi grafik *real-time*.

2. **Manajemen Perangkat Mendalam**
   - Halaman detail per perangkat yang menampilkan grafik historis (*historical charts*).
   - Log peringatan khusus untuk setiap perangkat.
   - Deteksi *heartbeat* otomatis.

3. **Sistem Notifikasi dan Insiden Tingkat Lanjut**
   - Pengiriman email otomatis via SMTP saat metrik mencapai ambang batas.
   - Pengelompokan tingkat peringatan (*Info, Warning, Critical*).
   - Fitur *Acknowledgment* (tandai selesai/dibaca) untuk insiden.
   - Konfigurasi batas *threshold* dinamis (CPU, Memory, Disk) via dashboard.

4. **Pelaporan & Ekspor**
   - Ringkasan performa harian/mingguan/kustom.
   - Grafik interaktif untuk memantau tren performa.
   - Ekspor laporan metrik langsung ke format CSV.

5. **Sistem Keamanan & Konfigurasi**
   - Autentikasi berbasis API Key untuk pengiriman data agen.
   - Manajemen konfigurasi SMTP dan pengaturan aplikasi langsung dari UI.
   - Proteksi injeksi SQL (menggunakan *prepared statements*).

## Struktur Database
- `devices`: Menyimpan data profil perangkat yang terhubung.
- `metrics`: Mencatat data deret waktu (*time-series*) dari resource perangkat (CPU, RAM, Disk, Network).
- `alerts`: Menyimpan log peringatan dengan status penanganan.
- `settings`: Menyimpan konfigurasi aplikasi (Threshold, kredensial SMTP, API Key).

## Langkah Pengembangan (Status: Selesai)
1. Perancangan skema database dinamis.
2. Pembuatan Web Installer (`install.php`).
3. Endpoint API terlindungi dengan validasi dan API Key.
4. Dashboard berbasis Material Design dengan integrasi Chart.js.
5. Manajemen Settings & Alerts interaktif.
