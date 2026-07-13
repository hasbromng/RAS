# Saran Fitur RAS

Dokumen ini mencatat arah pengembangan Remote Assistance Support System agar informasi perangkat mudah dipindai, lengkap, dan dapat ditindaklanjuti.

## Desain halaman perangkat

- Gunakan header ringkas berisi hostname, status koneksi, IP, pengguna aktif, dan waktu sinkron terakhir.
- Kelompokkan informasi menjadi bagian Ringkasan, Hardware, Storage, Sistem Operasi, Network, Riwayat, dan Alerts.
- Tampilkan storage seperti Windows Disk Management: satu kartu untuk setiap disk fisik, dengan partisi sebagai blok proporsional di dalamnya.
- Gunakan warna status secara konsisten: biru untuk normal, kuning untuk peringatan, dan merah untuk kritis.
- Sediakan tombol ekspor System Snapshot ke CSV/PDF.

## Data perangkat yang dikumpulkan

### Sistem operasi

- Windows edition, display version, OS build, dan tanggal instalasi.
- Uptime, arsitektur sistem, serta versi Windows.

### Hardware

- Motherboard: manufacturer, product/model, serial number.
- BIOS: versi dan tanggal rilis.
- CPU: model, core fisik, logical processor, dan pemakaian per core.
- Memori: kapasitas total; tahap berikutnya menambahkan slot, module, manufacturer, dan speed.
- Storage: nomor disk, model, serial, kapasitas, volume, dan ruang kosong.

### Jaringan dan keamanan

- Adapter, alamat IP, MAC address, gateway, DNS, dan kecepatan link.
- Tahap berikutnya: firewall, BitLocker, antivirus/Defender, serta Windows Update terakhir.

## Status implementasi

- [x] Tampilan detail partisi berbasis disk fisik.
- [x] Pengiriman nomor disk, model storage, serial, dan pemetaan drive letter dari Windows agent.
- [x] Ringkasan Windows edition, version, OS build, install date, motherboard, dan BIOS.
- [ ] SMART/health dan temperatur storage.
- [ ] Detail RAM per slot dan GPU.
- [ ] Riwayat perubahan hardware serta ekspor System Snapshot.

## Catatan deployment

Data fisik storage, motherboard, dan BIOS menggunakan PowerShell/CIM atau cara lain yang lebih efisien. Agent sebaiknya dijalankan sebagai Windows Service dengan hak administrator. Setelah pembaruan kode, executable agent perlu dibangun dan dipasang ulang pada client agar payload baru dikirim ke server.
