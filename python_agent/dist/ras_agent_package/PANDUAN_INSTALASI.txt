Panduan Instalasi RAS Agent di Komputer Klien
=============================================

Instalasi RAS Agent kini dirancang agar sangat mudah, cepat, dan bekerja secara otomatis.

Langkah-langkah Instalasi:
--------------------------
1. Siapkan folder ini (yang berisi "ras_agent.exe" dan "config.json") di komputer klien.
   PENTING: Pastikan file config.json selalu berada di dalam folder yang sama dengan ras_agent.exe sebelum instalasi dimulai!

2. Klik ganda (Double-Click) pada file "ras_agent.exe".

3. Jika muncul layar peringatan dari Windows (User Account Control / UAC), klik "Yes" untuk memberikan izin Administrator. 
   (Izin ini wajib diberikan agar agen dapat terdaftar ke dalam sistem Windows).

4. Selesai! 
   Anda akan melihat notifikasi pop-up kecil yang menyatakan bahwa instalasi telah berhasil.

Apa yang terjadi di latar belakang?
-----------------------------------
- Agen akan otomatis menyalin dirinya sendiri ke sistem (C:\Program Files\RAS Agent).
- Agen otomatis berjalan sebagai Windows Service di latar belakang.
- Agen akan otomatis berjalan setiap kali komputer dihidupkan (Auto-start).

Cara Menghapus (Uninstall):
---------------------------
Jika suatu saat Anda perlu mencabut agen dari komputer ini:
1. Cari folder "RAS Agent" di C:\Program Files\
2. Jalankan file "uninstall.bat" dengan cara Klik Kanan -> Run as Administrator.
3. Agen akan otomatis mati dan terhapus dari sistem Anda tak bersisa.

---------------------------------------------
Terima kasih! Agen pemantauan kini sudah aktif!
