# MVP: Aplikasi Monitoring Support Perangkat (Updated)

## Tujuan Utama
Membangun platform pemantauan dan pengelolaan perangkat (komputer/laptop) secara terpusat untuk tim IT Support. Sistem ini dirancang untuk mendeteksi anomali performa secara otomatis, memberikan visualisasi data historis, serta menyediakan sistem peringatan (*alerting*) yang dikonfigurasi secara dinamis melalui antarmuka web.

## Sasaran MVP (Telah Diperluas & Diperbarui)
- **Monitoring Holistik:** Pemantauan *real-time* dan penyimpanan data historis untuk CPU, RAM, Disk, dan konektivitas jaringan.
- **Sistem *Alerting* Cerdas:** Klasifikasi peringatan (Info, Warning, Critical) dengan fitur *Acknowledgment* dan notifikasi email SMTP otomatis.
- **Visualisasi Interaktif:** Menampilkan data tren menggunakan grafik responsif (Chart.js) pada *dashboard* admin.
- **Konfigurasi Web:** Pengaturan dinamis untuk ambang batas (*threshold*), API Key, dan SMTP dari antarmuka pengguna tanpa memodifikasi kode.
- **Ekspor Data:** Kemampuan mengekspor laporan performa dalam format CSV untuk keperluan audit.
- **Keamanan:** Autentikasi agen menggunakan API Key dan proteksi database dari SQL Injection.

## Arsitektur Sistem Terkini
1. **Admin Dashboard (PHP + MySQL):** 
   - Berfungsi sebagai server pusat (*Backend* API) dan antarmuka pemantauan (*Frontend*).
   - Dilengkapi *Web Installer* untuk instalasi pertama kali.
2. **Agen Monitoring (Python):** 
   - *Script* ringan yang berjalan di komputer target (*background service*).
   - Mengumpulkan data dari *hardware* OS, lalu mengirimkannya ke server via koneksi terenkripsi/terautentikasi (API Key).

## Fitur Utama Keseluruhan
1. **Real-Time Data Center:** 
   - Daftar semua perangkat terdaftar yang melakukan *polling* otomatis secara konstan.
2. **Device Insight:** 
   - Halaman detail untuk memantau performa spesifik per-mesin dengan grafik *time-series*.
3. **Manajemen Insiden:** 
   - Dashboard log peringatan khusus untuk melacak masalah dan memastikan tim support mengambil tindakan (melalui status penanganan insiden).
4. **Resiliency:**
   - Fitur penyimpanan lokal *buffer* pada Agen Python jika jaringan pusat terputus sementara.

## Dokumen Turunan Terperinci
- Silakan lihat [MVP_Admin_PHP_MySQL.md](MVP_Admin_PHP_MySQL.md) untuk rincian modul web server.
- Silakan lihat [MVP_Client_Python_Agent.md](MVP_Client_Python_Agent.md) untuk rincian agen sistem di klien.

## Status Saat Ini
- ✅ **MVP Admin (Dashboard & API Server):** Selesai diimplementasikan dan siap diintegrasikan (Material Design UI, Chart.js, Sistem Alerting, Database, Web Installer).
- 🔄 **MVP Klien (Agen Python):** Masuk ke tahap pengembangan dan pengujian integrasi selanjutnya.
