# Backend Dashboard

Endpoint dashboard memberikan jumlah aktivitas, ringkasan berdasarkan kategori dan semester, serta daftar kegiatan selesai untuk mahasiswa dan dosen.

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/mahasiswa/dashboard` | Mahasiswa |
| GET | `/dosen/dashboard/kegiatan` | Dosen/admin |
| GET | `/mahasiswa/dashboard/contoh` | Data contoh lokal untuk menguji card dan tabel |

Tanggal aktivitas diambil dari detail kegiatan; semester akademik dimulai pada Agustus.

Di lingkungan `local`, dashboard mahasiswa dapat menampilkan data contoh tanpa
mengubah database dengan membuka `/mahasiswa/mahasiswa/dashboard?contoh=1`.
Data contoh yang sama tersedia di `/api/v1/mahasiswa/dashboard/contoh`; endpoint ini
mengembalikan ringkasan card dan daftar mata kuliah dalam format respons API
standar. Mode dan endpoint contoh tidak tersedia di production.
