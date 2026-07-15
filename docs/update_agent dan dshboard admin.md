update cara kerja agent: 
- kirim data engkap saat pertama kali connect/ didaftarkan melalui install
- kirim update lengkap saat komputer baru on, atau user baru login; 
  - mekanismenya , cek intrenet jika ada kirim data ke api 
  - jika tidak ada internet, simpan data di log local storage dan kirim saat ada internet
- data yang sudah dikirim jangan dikirim lagi
- kirim data lengkap saat ada triger dari dashboard admin (audit sekarang) 
  - 

## dashboard admin 
mekanisme audit sekarang :
- jika agent tidak mengirimkan balasan informasikan bahwa agent sedang offline / tidak terhubung  (terlihat dari waktu terakhir kali agent mengirimkan data)
- 