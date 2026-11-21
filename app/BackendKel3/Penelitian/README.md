# Rita Imut · Penelitian

**Hasil yang dituju:** dosen memilih kategori penelitian, mengisi form sesuai kategori, lalu menyimpan dan melihat kembali penelitiannya.

| Target API | Yang dikerjakan |
|---|---|
| `GET /dosen/penelitian` | Daftar penelitian milik atau yang diikuti dosen, dengan filter tahun dan status. |
| `GET /dosen/penelitian/{id}` | Detail kegiatan, skim, kelompok bidang, jawaban form, anggota, dokumen, status terakhir. |
| `POST /dosen/penelitian` | Simpan kegiatan, anggota, dan jawaban dinamis dalam satu transaksi. Kembalikan ID kegiatan ke frontend. |
| `PATCH /dosen/penelitian/{id}` | Pemilik memperbaiki isian sesuai tahap pengajuan yang disepakati dengan Revo. |

**Mulai dari** `Kegiatan`, `Kategori`, `IsianKategori`, `DetailKegiatan`, `Skim`, `KelompokBidang`, dan `AnggotaKegiatan`. Validasi bahwa kategori benar-benar penelitian dan tiap jawaban milik kategori itu. Simpan ID skim dan kelompok bidang di kegiatan. Tanggal kegiatan diambil dari detail. Untuk kegiatan tim, gunakan aturan anggota yang sudah ada.

**Bagi pekerjaan dengan Pandu:** Rita menyiapkan validasi/simpan jawaban dinamis yang juga bisa dipanggil modul Pengabdian; Pandu menyiapkan endpoint pilihan kategori dan isian untuk kedua form. Revo menangani pengiriman dan perubahan status, Ridho menangani berkas.

**Selesai saat** dosen A dapat membuat penelitian lalu membaca kembali nilai yang sama; jawaban kategori pengabdian ditolak dengan 422; jika salah satu jawaban gagal, tidak ada data kegiatan setengah tersimpan. Uji juga bahwa data dosen B yang tidak terkait tidak muncul.

**File kerja:** `PenelitianController.php` untuk endpoint penelitian. Rita dan Pandu dapat menaruh logika penyimpanan yang dipakai keduanya di `app/BackendKel3/Kegiatan/SimpanKegiatan.php` saat implementasi.

[Kembali ke pembagian tugas tim](../README.md)
