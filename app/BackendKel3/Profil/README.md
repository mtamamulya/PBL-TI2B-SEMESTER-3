# Lukman · Profil dan riwayat dosen

**Hasil yang dituju:** dosen melihat profil miliknya dan riwayat SK pangkat/golongan yang benar.

| Target API | Yang dikerjakan |
|---|---|
| `GET /dosen/profil` | Dari akun login, ambil nama, NIP, email, foto profil, dan pangkat terakhir. |
| `PATCH /dosen/profil` | Awali dengan perubahan nama. Buat daftar field yang boleh diubah. Pisahkan perubahan email, NIP, role, dan pangkat dari form ini. |
| `GET /dosen/riwayat-pangkat` | Kembalikan pangkat, golongan, nomor SK, dan tanggal SK, terbaru lebih dulu. |

**Mulai dari** model `User`, `Dosen`, `HistoriPangkatGolongan`. Hubungannya `users.id_user → dosen.id_user`, lalu `dosen.id_dosen → histori_pangkat_golongan.id_dosen`. Pakai relasi `historiPangkatGolongan()`. Pilih field respons secara eksplisit. API publik daftar dosen milik modul lain punya tujuan berbeda.

**Selesai saat** perubahan nama terlihat setelah profil dimuat ulang; riwayat kosong menghasilkan daftar kosong; akun tanpa profil mendapat pesan yang jelas; mengirim ID dosen lain tidak mengganti sasaran. Serahkan contoh respons ke Boby dan Ilham.

**File kerja:** `ProfilController.php` untuk profil dan riwayat, serta `UpdateProfilRequest.php` untuk field yang boleh diedit.

[Kembali ke pembagian tugas tim](../README.md)
