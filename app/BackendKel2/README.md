# BE-7 · Berita, Dashboard, dan Laporan

Implementasi BE-7 dipisah menurut modul frontend:

| Folder | Isi |
|---|---|
| [Berita](Berita/README.md) | Pengajuan, edit, kategori, dan review berita |
| [Dashboard](Dashboard/README.md) | Statistik kategori/semester dan kegiatan selesai |
| [Laporan](Laporan/README.md) | Rekap admin dan ekspor Excel/PDF |

Setiap modul mempunyai `routes.php` sendiri dan dimuat oleh file ini. Endpoint memakai prefix `/api/v1` dan autentikasi Sanctum.
