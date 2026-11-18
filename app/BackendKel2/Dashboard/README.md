# Backend Dashboard

Endpoint dashboard memberikan jumlah aktivitas, ringkasan berdasarkan kategori dan semester, serta daftar kegiatan selesai untuk mahasiswa dan dosen.

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/mahasiswa/dashboard` | Mahasiswa |
| GET | `/dosen/dashboard/kegiatan` | Dosen/admin |

Tanggal aktivitas diambil dari detail kegiatan; semester akademik dimulai pada Agustus.
