# Rencana Implementasi: Perbaikan Layout Penyimpanan & Ekspor Snapshot Perangkat

Rencana ini bertujuan untuk menyelaraskan halaman detail perangkat di dashboard RAS Admin dengan spesifikasi di [SARAN_FITUR.md](file:///d:/xampp/htdocs/RAS/docs/SARAN_FITUR.md), khususnya untuk visualisasi penyimpanan ala Windows Disk Management dan ekspor System Snapshot ke CSV.

## User Review Required

> [!IMPORTANT]
> - Perubahan layout penyimpanan memerlukan penambahan kode CSS baru pada stylesheet utama (`admin.css`). Kode CSS dirancang responsif agar tetap nyaman dilihat di perangkat desktop maupun seluler.
> - Ekspor System Snapshot akan diimplementasikan sebagai unduhan file CSV langsung dengan menargetkan standalone script [device_detail.php](file:///d:/xampp/htdocs/RAS/admin/pages/device_detail.php?export=csv).

## Proposed Changes

Kami akan melakukan modifikasi pada komponen halaman detail perangkat (baik yang terintegrasi di dashboard admin maupun file standalone) dan stylesheet.

---

### Component: Admin Dashboard Frontend

#### [MODIFY] [admin.css](file:///d:/xampp/htdocs/RAS/admin/assets/css/admin.css)
Menambahkan class styling baru untuk mendukung layout Disk Management horizontal yang proporsional:
*   `.disk-management-layout`: Mengatur tata letak satu unit disk fisik sebagai kartu.
*   `.physical-disk-header`: Mengatur informasi judul disk fisik, model, dan total kapasitas.
*   `.partition-list`: Mengatur container partisi dengan `display: flex; flex-direction: row;`.
*   `.partition-row`: Mengatur blok partisi dengan padding, warna latar belakang, dan border agar tampak seperti blok partisi disk.
*   `.partition-drive`, `.partition-capacity`: Mengatur tipografi nama drive dan keterangannya.
*   `.partition-usage`: Mengatur visual progress bar persentase pemakaian di dalam partisi.

#### [MODIFY] [device_detail_content.php](file:///d:/xampp/htdocs/RAS/admin/pages/device_detail_content.php)
*   Menambahkan tombol **"Ekspor System Snapshot"** di bagian atas halaman detail. Tombol ini akan mengarah ke `pages/device_detail.php?id={device_id}&export=csv`.
*   Merapikan visualisasi partisi yang dikelompokkan oleh Disk Fisik agar class CSS yang baru ditambahkan di `admin.css` dapat bekerja dengan benar (menggunakan flexbox row secara horizontal berbasis persentase ukuran volume).

#### [MODIFY] [device_detail.php](file:///d:/xampp/htdocs/RAS/admin/pages/device_detail.php)
*   Menyelaraskan struktur HTML/CSS dengan `device_detail_content.php` agar layout penyimpanan horizontal juga berfungsi jika diakses langsung secara standalone.
*   Menambahkan logika penanganan ekspor CSV di bagian paling atas file (sebelum output HTML dimulai):
    *   Jika parameter `export=csv` terdeteksi, panggil data perangkat, lakukan parsing JSON `additional_info`, buat struktur data snapshot lengkap, tulis ke stream `php://output` menggunakan `fputcsv()`, set headers untuk file download, dan hentikan ekspor.
*   Menambahkan tombol ekspor di header halaman standalone.

---

## Verification Plan

### Automated Tests
Karena tidak ada framework testing visual otomatis, verifikasi dilakukan dengan menjalankan server PHP lokal XAMPP atau built-in PHP server dan melakukan inspeksi manual.

### Manual Verification
1.  **Menjalankan Server**:
    *   Menjalankan server pengembangan PHP lokal dengan perintah:
        ```bash
        d:\xampp\php\php.exe -S localhost:8000 -t d:\xampp\htdocs\RAS
        ```
2.  **Verifikasi Layout Penyimpanan**:
    *   Membuka halaman detail perangkat di browser: `http://localhost:8000/admin/index.php?page=devices&device_id={id}`
    *   Memastikan bahwa partisi ditampilkan secara horizontal berdampingan di dalam satu baris (row) per disk fisik.
    *   Memastikan lebar setiap partisi proporsional terhadap ukuran kapasitas total disk fisik.
3.  **Verifikasi Ekspor Snapshot**:
    *   Mengklik tombol **"Ekspor System Snapshot (CSV)"** di halaman detail.
    *   Memastikan file CSV berhasil terunduh dengan nama `snapshot_{hostname}_{tanggal}.csv`.
    *   Membuka file CSV dan memverifikasi kelengkapan datanya (Sistem Operasi, CPU, Memori, Disk fisik beserta partisi, dan daftar Alerts).
