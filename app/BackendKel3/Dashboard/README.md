# Ilham · Dashboard, notifikasi, dan integrasi

**Hasil yang dituju:** angka dashboard cocok dengan daftar kegiatan dosen yang sama, dan dosen dapat membuka perubahan status dari notifikasi.

| Target API | Yang dikerjakan |
|---|---|
| `GET /dosen/dashboard` | Jumlah penugasan mengajar, penelitian, pengabdian, pengajuan menunggu, dan grafik per tahun. |
| `GET /dosen/notifikasi` | Daftar perubahan status yang boleh dilihat dosen, berikut waktu, judul kegiatan, dan tautan detail. |

**Mulai dari** `DosenDashboardController.php` di sini yang sudah menyajikan halaman web. Untuk API JSON, ambil penugasan mengajar dari data Naya, penelitian/pengabdian dari `Kegiatan::untukUser()`, status dari Revo, serta tahun dari tanggal detail. Sepakati dengan Naya arti “jumlah pengajaran” sehingga kartu dan daftar menghitung penugasan yang sama. Pakai timeline sebagai sumber notifikasi awal. Fitur “tandai dibaca” baru memerlukan penyimpanan per pengguna jika tim memang memerlukannya.

**Integrasi:** Ilham menyatukan contoh request/response anggota, menyiapkan koleksi Postman dengan variabel URL dan token, lalu mencatat endpoint lintas kelompok yang sudah disepakati. Reuse `ApiClient` jika layanan memang terpisah. Siapkan satu demo dari login hingga notifikasi, termasuk tampilan ketika data kosong atau layanan mitra gagal.

**Selesai saat** kartu dan daftar cocok untuk dosen A, keduanya kosong untuk dosen baru, perubahan status milik dosen B tidak bocor, dan tautan notifikasi membuka pengajuan yang tepat.

**File kerja:** `DashboardApiController.php` untuk ringkasan JSON serta `NotifikasiController.php` untuk perubahan status. Dashboard web yang sudah berjalan ada di `DosenDashboardController.php`.

[Kembali ke pembagian tugas tim](../README.md)
