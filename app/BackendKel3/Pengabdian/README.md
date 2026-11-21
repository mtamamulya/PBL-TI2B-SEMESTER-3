# Pandu · Pengabdian

**Hasil yang dituju:** saat dosen memilih pengabdian, form menampilkan field kategori itu, misalnya mitra dan lokasi jika terdaftar di master isian.

| Target API | Yang dikerjakan |
|---|---|
| `GET /dosen/kategori/{id}/isian` | Kontrak bersama Rita. Kembalikan ID isian, label/input, jenis, aturan wajib/opsional, dan batas jumlah jawaban. |
| `GET /dosen/pengabdian` | Daftar kegiatan pengabdian milik atau yang diikuti dosen. |
| `GET /dosen/pengabdian/{id}` | Tampilkan jawaban dinamis, mitra jika tersedia, anggota, dokumen, dan timeline. |
| `POST /dosen/pengabdian` | Simpan kategori pengabdian, anggota, dan jawaban menggunakan alur transaksi bersama Rita. |
| `PATCH /dosen/pengabdian/{id}` | Perbarui jawaban kegiatan milik dosen sesuai status dari Revo. |

**Mulai dari** `kategori → isian_kategori` dan `kegiatan → detail_kegiatan`. Ambil skim dan kelompok bidang dari tabel referensi yang dipakai Rita. Pandu dan Rita menyepakati bentuk payload yang sama, misalnya `detail: [{id_isian_kategori, isian}]`. Tetapkan dengan frontend bagaimana memilih ID kategori penelitian/pengabdian dari daftar kategori yang tersedia.

**Selesai saat** memilih dua kategori dengan set isian berbeda menghasilkan dua form yang berbeda; mitra yang boleh berulang tersimpan sesuai batas; akses ke ID penelitian lewat endpoint pengabdian ditolak. Bagikan contoh form dan respons ke Rita serta Revo.

**File kerja:** `PengabdianController.php` untuk kegiatan pengabdian dan `IsianKategoriController.php` untuk kebutuhan form bersama Rita.

[Kembali ke pembagian tugas tim](../README.md)
