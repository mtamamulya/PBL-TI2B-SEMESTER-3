# Review ERD final (PBL TI-BISA)

Dibaca dari `ERD_PBL_TI-BISA (1).pdf`. Bandingkan juga dengan struktur tabel dari phpMyAdmin yang sudah dikirim
(prestasi, kegiatan, kategori, isian_kategori, detail_kegiatan, bukti_prestasi, jenis_dokumen). Tabel lain belum dicek
terhadap database.

## A. Selaras dengan database (tidak perlu tindakan)

`isian_kategori`, `detail_kegiatan`, `bukti_prestasi`, `jenis_dokumen`. Enum `timeline.status` sama persis dengan
kode status di `KegiatanWorkflow`. `mahasiswa.tahun_masuk` dipakai sebagai angkatan.

## B. ERD final berbeda dari database yang berjalan

| Tabel | ERD final | Database (phpMyAdmin) | Dampak |
|---|---|---|---|
| `prestasi` | `id_user`, `created_by`, `id_kegiatan` nullable, `nama_lomba`, `penyelenggara`, `lokasi`, `dosen_pembimbing`, `tingkat_kejuaraan`, `tanggal_mulai`, `tanggal_selesai`, `bentuk_partisipasi`, `deskripsi`, `capaian`, `jenis_prestasi`, `bidang_lomba` | `id_kegiatan` NOT NULL, `jenis_prestasi` (`akademik`, `non-akademik`), `bidang_lomba`, `catatan`, `capaian` | **BE-6 harus diubah.** Versi terakhir menulis ke `kegiatan` dan `detail_kegiatan`; ERD final meminta prestasi ditulis langsung di tabelnya. Butuh migrasi `alter` |
| `kegiatan` | `id_kategori`, `id_user`, `id_skim`, `id_kelompok_bidang`, `nama_kegiatan`, `nomor_sk`, `tanggal_sk` | Tambahan: `lokasi`, `deskripsi`, `tanggal_mulai`, `tanggal_selesai`, `id_kegiatan_sebelumnya`. Belum ada `id_skim`, `id_kelompok_bidang` | Di ERD tanggal dan lokasi hanya lewat `detail_kegiatan` (teks). Itu menyulitkan grafik per semester dan filter tanggal |
| `kategori` | `isi_surat` | `template_surat` | Nama kolom beda |

## C. Konflik antara ERD dan UI

| Temuan | Tindakan |
|---|---|
| `berita.status` hanya `publish`, `draft`; UI punya 5 status | Perluas enum (migrasi `extend_berita_status`) |
| `timeline` tidak punya kolom pelaku, padahal Blade menampilkan pelaku | Tambah `timeline.id_user` (migrasi `align_timeline_with_erd`) |
| `user` tidak punya kolom nama dan role (hanya `id_role`) | BE-1: accessor `nama_tampil` dan relasi `role` |
| `tahun_akademik` hanya `tahun_akademik` (teks, mis. "Ganjil 2025/2026") dan `status` | Dipakai sebagai sumber "berjalan" (`status = 1`), tetapi tidak ada tanggal mulai dan selesai. Usulan: tambah dua kolom tanggal (nullable) |
| Form berita punya `activity_name` (kegiatan baru) tanpa kolom penampung | Putuskan: berita membuat kegiatan, atau kolom teks |
| Prestasi ERD memakai `non akademik`, database `non-akademik` | Pilih satu ejaan, dan samakan dengan nilai yang dikirim filter UI |

## D. Celah: dibutuhkan jobdesk tetapi tidak ada di ERD

| Kebutuhan | Pemilik | Pilihan |
|---|---|---|
| `sertifikat`, `sertifikat_verifikasi` | BE-6 | Tambah ke ERD, atau keluarkan sertifikat dari lingkup |
| OTP ubah sandi dan "terakhir login" | BE-1 | Tabel `otp_codes` dan `login_logs`, atau kolom di `user` |
| Notifikasi | BE-4 | Tabel bawaan Laravel `notifications` (tidak perlu masuk ERD) |
| Nomor surat dan dokumen hasil | BE-5 | `nomor_sk`/`tanggal_sk` di `kegiatan` (sementara) dan baris `dokumen` untuk PDF hasil |
| Total SKS kelulusan ("dari 144 SKS") | BE-2 | Tambah kolom di `kurikulum`, atau konfigurasi |
| SKS ditempuh | BE-2 | Belum ada tabel nilai atau status lulus; definisikan sebagai jumlah SKS mata kuliah pada semester yang sudah lewat |

## E. Daftar permintaan perubahan ERD (untuk disepakati tim)

1. `timeline` + `id_user` (nullable).
2. `berita.status` + `diajukan`, `direvisi`, `ditolak`.
3. `tahun_akademik` + `tanggal_mulai`, `tanggal_selesai` (nullable).
4. `kegiatan`: pertahankan `tanggal_mulai`, `tanggal_selesai`, `lokasi` sebagai kolom (bukan hanya isian dinamis).
5. `prestasi`: selaraskan database ke ERD final lewat migrasi `alter`; tetapkan ejaan `jenis_prestasi`.
6. Putuskan nasib `sertifikat`, `sertifikat_verifikasi`, OTP, dan riwayat login.
7. `kurikulum` + total SKS kelulusan.
