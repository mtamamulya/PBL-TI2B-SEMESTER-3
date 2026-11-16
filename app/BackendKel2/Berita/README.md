# Backend Berita

Mengelola berita milik dosen/mahasiswa, lampiran sampul, dan proses review admin.

| Method | Endpoint | Akses |
|---|---|---|
| GET | `/berita/kategori` | Pengguna login |
| GET, POST | `/dosen/berita` | Dosen/mahasiswa |
| GET, PUT, PATCH | `/dosen/berita/{id}` | Pemilik berita |
| GET | `/admin/berita` | Admin |
| PATCH | `/admin/berita/{id}/review` | Admin |

Field form menerima `judul`, `isi`, `kategori` atau `id_kategori`, `kegiatan_id` opsional, `foto` PDF/JPG/PNG hingga 5 MB, dan `status` draft/publish.
