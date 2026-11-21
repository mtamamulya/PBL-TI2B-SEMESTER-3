# Lia · Prestasi dan berita dosen

**Hasil yang dituju:** dosen mencatat prestasi mandiri atau dari kegiatan yang memenuhi syarat, lalu menyusun berita kegiatan.

| Target API | Yang dikerjakan |
|---|---|
| `GET /dosen/prestasi` dan `GET /dosen/prestasi/{id}` | Daftar dan detail prestasi yang menjadi hak dosen, termasuk bukti dan penerima. |
| `POST /dosen/prestasi` dan `PATCH /dosen/prestasi/{id}` | Validasi capaian, pemilik, tanggal, bukti, dan asal kegiatan saat membuat atau memperbarui prestasi. |
| `GET /dosen/berita` dan `GET /dosen/berita/{id}` | Daftar dan detail berita yang dikelola dosen, termasuk draft miliknya. |
| `POST /dosen/berita` dan `PATCH /dosen/berita/{id}` | Simpan/edit judul, isi, kegiatan terkait, dan sampul draft. Sepakati alur publikasi dengan pengelola berita. |

**Mulai dari** model `Prestasi`, `BuktiPrestasi`, `Berita`, `AnggotaKegiatan` dan `Kegiatan::untukPrestasi()`. Yang dicek ialah **pemilik prestasi**. Ia harus merupakan pemilik atau anggota kegiatan dan status **terakhir** kegiatan harus `diterima`. Prestasi mandiri boleh tanpa `id_kegiatan`. Prestasi dari kegiatan memakai daftar anggota kegiatan; prestasi mandiri bertim memakai anggota yang terhubung melalui `id_prestasi`. Atur `created_by` dari pengguna login.

**Selesai saat** peserta bisa menautkan kegiatan diterima, orang luar ditolak, status terbaru direvisi ditolak meskipun dulu diterima, dan bukti memiliki nama file. Draft dosen B tidak terlihat oleh dosen A. Periksa dengan Ridho bagaimana menguji tautan bukti dan dengan Ilham bagaimana berita terbit ditampilkan.

**File kerja:** `PrestasiController.php` untuk pencatatan capaian dan `BeritaController.php` untuk draft berita. Daftarkan keduanya di `routes.php` milik modul ini.

[Kembali ke pembagian tugas tim](../README.md)
