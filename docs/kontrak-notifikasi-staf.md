# Kontrak Notifikasi Admin & Kaprodi

Acuan semua fase (lonceng, halaman, API). Kode: `app/Support/NotifikasiStaf.php`.
**Jangan ubah nama/tipe kunci tanpa kabar ke tim (Kurnia + Daffa).**

## Item notifikasi (bentuk yang dibaca halaman, lonceng, dan respons API Fase 2)

| Kunci | Tipe | Arti |
|---|---|---|
| `id` | string `[A-Za-z0-9_-]+` | Pengenal buram. `k-<id_timeline>` untuk kegiatan, `b-<id_berita>-<status>` untuk berita. Halaman tidak boleh menebak isinya. |
| `type` | `kegiatan` \| `berita` | Isi filter halaman. |
| `title` | string | Judul pendek. |
| `body` | string | Satu kalimat (boleh memuat "Catatan: ..."). |
| `time` | string | `14.05` (hari ini/kemarin) atau `3 Nov 2026`. Sudah jadi, tinggal ditampilkan. |
| `g` | `Hari ini` \| `Kemarin` \| `Lebih lama` | Kelompok tampilan. |
| `unread` | bool | Belum dibaca oleh akun ini. |
| `href` | string | Tujuan klik. `#` = tidak ada tujuan. |
| `waktu_iso` | string ISO 8601 | Untuk pengurutan. |

Urutan: terbaru di atas (sudah diurutkan sumbernya).

## Isi per peran

| Peran | Kegiatan | Berita |
|---|---|---|
| admin | pengajuan masuk / diajukan ulang; keputusan kaprodi (disetujui, dikembalikan, ditolak) | berita `diajukan` menunggu tinjauan |
| kaprodi | pengajuan `direview` (menunggu tanda tangan). Hanya yang sudah sampai tahap kaprodi | hasil tinjauan admin atas berita **milik kaprodi** (publish, direvisi, ditolak) |

Status "dibaca" **per akun**. Id milik peran lain = 404.

## Route (Fase 1, semua di dalam grup `admin` / `kaprodi`)

| Method | Path | Nama |
|---|---|---|
| GET | `/{peran}/notifikasi` | `{peran}.notifikasi` |
| POST | `/{peran}/notifikasi/baca-semua` | `{peran}.notifikasi.baca-semua` |
| POST | `/{peran}/notifikasi/{id}/baca` | `{peran}.notifikasi.baca` |

Respons `baca`: `{"ok":true,"data":{"id":"k-9001","belum_dibaca":false}}`.
Respons `baca-semua`: `{"ok":true,"data":{"jumlah_ditandai":5}}`. Id tidak dikenal: 404 `{"ok":false,"message":"..."}`.

## Lonceng

`<x-notifikasi-lonceng prefix="admin|kaprodi" />` di header kedua layout. Angka dari
`NotifikasiStaf::jumlahBelumDibaca($peran)`: `int` atau `null`. `null` = tampil lonceng **tanpa angka**,
halaman tidak boleh error. Angka ikut berubah lewat event window `notif-count` (sama seperti Kelompok 3).

## Rencana fase berikutnya (belum dikerjakan)

- Fase 2: migrasi tabel penanda dibaca + API Kelompok 1 `GET /api/v1/{admin|kaprodi}/notifikasi`, `.../jumlah`, `POST .../{id}/baca`, `.../baca-semua`. Bentuk item = tabel di atas.
- Fase 3: `NotifikasiStaf::daftar()` dan `jumlahBelumDibaca()` diganti panggilan API (+ cache 60 detik per akun), `NOTIF_DUMMY=false`.
