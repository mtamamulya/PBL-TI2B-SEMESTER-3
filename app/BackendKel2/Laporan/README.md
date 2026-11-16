# Backend Laporan

Laporan agregat kegiatan untuk admin dengan filter tahun, semester, kategori, dan rentang tanggal.

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/admin/laporan` | Ringkasan dan daftar terpaginasikan |
| GET | `/admin/laporan/export?format=xls` | Unduh Excel |
| GET | `/admin/laporan/export?format=pdf` | Unduh PDF |

Semester laporan 1 adalah Januari–Juni, semester 2 Juli–Desember.
