# Ringkasan Proyek: RAS (Remote Assistance Support System)

## 📊 Gambaran Umum
**RAS** adalah sistem pemantauan berbasis web yang digunakan untuk memantau metrik performa perangkat (klien) dan mengelola peringatan (alerts) secara *real-time*. Proyek ini dirancang menggunakan arsitektur *client-server*, di mana agen (klien) mengirimkan data metrik ke server sentral yang menyediakan *dashboard* pemantauan.

## 🛠️ Teknologi yang Digunakan
*   **Backend:** PHP 7.4+
*   **Database:** MySQL/MariaDB 5.7+
*   **Frontend:** HTML5, CSS3, JavaScript (Materialize CSS & Chart.js)
*   **Klien / Agen:** Python (mengirim data metrik melalui REST API)

## 🧩 Komponen Utama Sistem
1.  **Database (`database/ras_schema.sql`)**: 
    Terdapat tabel utama seperti `devices` (perangkat terdaftar), `metrics` (riwayat CPU, RAM, Disk), `alerts` (peringatan sistem), dan `settings` (konfigurasi).
2.  **API Endpoints (`admin/api/`)**:
    Menyediakan REST API (JSON) dengan autentikasi API Key untuk menerima pengiriman metrik dari agen Python, serta menyuplai data ke *dashboard*.
3.  **Admin Dashboard (`admin/`)**:
    Menampilkan antarmuka monitoring:
    *   Status perangkat secara langsung (Online, Offline, Warning, Critical).
    *   Grafik performa historis (CPU, Memori, Disk).
    *   Manajemen *Alerts* dan pelaporan (termasuk ekspor CSV).

## ✅ Status Proyek (MVP)
Tahap MVP (Minimum Viable Product) untuk bagian **Admin Dashboard** dan sistem pusat telah selesai. Fitur yang sudah berjalan:
*   Dashboard *real-time* dengan antarmuka Material Design.
*   Penyimpanan metrik ke MySQL.
*   REST API siap pakai.
*   Sistem peringatan berdasarkan *threshold* (ambang batas) otomatis.
*   Manajemen daftar perangkat klien.

## 🚀 Langkah Selanjutnya (Next Steps)
1.  **Implementasi Agen Python:** Membangun *script* klien Python yang membaca sumber daya lokal komputer klien dan mengirimkannya ke API server secara berkala.
2.  **Uji Coba Integrasi:** Menghubungkan *dashboard* langsung dengan program klien Python.
3.  **Konfigurasi Lanjutan:** Mengatur sistem SMTP untuk mengirimkan notifikasi peringatan (*alerts*) melalui email.
4.  **Deployment:** Memindahkan dan menyiapkan lingkungan produksi untuk peluncuran resmi.
