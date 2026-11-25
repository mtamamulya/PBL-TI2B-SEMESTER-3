# ADR 0001 · Mesin status bersama (Core Workflow)

- Status: **Diusulkan** (menunggu persetujuan tim dan Manajer Proyek)
- Pemilik: Lead Backend
- Revisi: 2 (menyesuaikan ERD final)

## Konteks

Pengajuan kegiatan dan berita punya status dan aturan perpindahan masing-masing. Blade yang ada membaca
`statusTerkini->status`, menampilkan label seperti "Perlu revisi" dan "Disetujui", dan butuh timeline
(`title`, `at`, `actor`, `note`), flag `editable`, dan hitungan per status.

ERD final sudah menetapkan: tabel `timeline` (id_kegiatan, status, tanggal_status, catatan_revisi) untuk kegiatan,
kolom `berita.status` (publish, draft) + `catatan_revisi` untuk berita, dan enum status kegiatan
(diajukan, direvisi, ditolak, direview, diterima, selesai).

## Keputusan

1. **Satu mesin bersama** (`WorkflowEngine`). Definisi alur ditulis sebagai kode (`KegiatanWorkflow`, `BeritaWorkflow`).
2. **Penyimpanan mengikuti ERD, dipilih per alur** lewat `StatusStore`: `TimelineStore` untuk kegiatan,
   `ColumnStore` untuk berita. Tidak ada tabel riwayat baru.
3. **Kode dan label dipisah.** Kode mengikuti ERD (`direvisi`, `diterima`, `publish`), label mengikuti UI.
4. **Tiga jenis pelaku:** `owner`, `staff` (Gate `review-workflow`), `system`.
5. **Catatan wajib** untuk revisi dan penolakan. `ditolak`, `selesai`, dan `publish` adalah status akhir.
6. **Event `StatusChanged` dikirim setelah commit.** Modul lain bereaksi lewat listener, tidak saling memanggil.
7. **Prestasi tidak memakai alur status.** Pembuatannya dipicu event kegiatan `selesai`.

## Perubahan ERD yang diminta

| # | Perubahan | Alasan |
|---|---|---|
| 1 | `timeline` + kolom `id_user` (pelaku, nullable) | Halaman detail menampilkan siapa yang mengubah status |
| 2 | `berita.status` ditambah `diajukan`, `direvisi`, `ditolak` (kode `publish` tetap) | UI berita punya lima status |

Rincian lengkap perbedaan ERD dan database ada di `docs/review-erd.md`.

## Yang perlu dikonfirmasi

| Hal | Usulan awal |
|---|---|
| Edit dan hapus pengajuan kegiatan | Hanya saat `diajukan` dan `direvisi` |
| Hapus berita | Hanya saat `draft` |
| Kegiatan bisa langsung `diterima` tanpa `direview` | Tidak |
| `diterima` menjadi `selesai` | Oleh reviewer/admin atau proses sistem |
| Batas jumlah revisi | Tidak dibatasi |
| Nama role reviewer di tabel `role` | `admin`, `reviewer` (perlu dicek) |

## Konsekuensi

- (+) Mengikuti ERD yang sudah disepakati, tanpa tabel riwayat ganda; jejak audit kegiatan lengkap.
- (+) Mengubah label tidak perlu migrasi data.
- (−) Berita tidak punya riwayat (hanya status dan catatan terakhir). Cukup untuk UI saat ini; bila nanti dibutuhkan,
  ganti `ColumnStore` dengan `TimelineStore` versi berita tanpa mengubah aturan alur.
- (−) Status terkini kegiatan butuh subquery. Mitigasi: indeks `(id_kegiatan, id_timeline)` dan scope `whereStatus`.

## Alternatif yang ditolak

- Tabel riwayat generik baru (`status_histories`): menduplikasi `timeline` yang sudah ada di ERD.
- Kolom `status` di tabel kegiatan: ERD memakai timeline, dan riwayat tetap harus dibuat terpisah.
- Paket pihak ketiga untuk model state: menambah ketergantungan padahal kebutuhan kita kecil dan spesifik.
