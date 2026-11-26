# Kontrak data per halaman (dibaca dari Blade saat ini)

Variabel yang **diharapkan** tiap Blade. Pemilik modul menyediakan data persis dalam bentuk ini
(controller web boleh memanggil Service yang sama dengan endpoint API).
Status memakai **label** dari `statusLabel()`. Halaman yang belum dibaca ditandai "belum dipetakan".

| Halaman (Blade) | Pemilik | Variabel |
|---|---|---|
| Dashboard pengajuan (`dashboard`) | BE-7 | `$user` (`foto_profil`), `$nama`, `$role`, `$labelIdentitas`, `$nomorIdentitas`, `$angkatan`, `$statusAkun`, `$totalPengajuan`, `$totalKegiatan`, `$totalPrestasi`, `$pengajuanRoute`, `$pengajuanTerbaru` (tiap item: key, `nama_kegiatan`, `deskripsi`, `statusTerkini->status`, `created_at`) |
| Dashboard mahasiswa (`dashboard-mhs`) | BE-2 | `$nama`, `$ringkasan` (`tahun_akademik`, `jenis_semester`, `sks_total`, `sks_tempuh`, `semester_ke`, `jumlah_mk`, `sks_semester`, `pengajuan_aktif`, `dokumen_siap`), `$filter` (`q`, `semester`, `tingkat`, `tahun`), `$tahunList`, `$mataKuliah` (paginator; item `mk`, `dosen`, `tingkat`, `semester`, `tahun`) |
| Mata kuliah (`mata-kuliah`) | BE-2 | `$tab` (`berjalan`, `katalog`, `semua`), `$rows` (collection; `mk`, `dosen`, `tingkat`, `periode`, `sks`, `tahun`), `$ringkasan` (`tahun_akademik`, `semester_ke`, `jumlah_berjalan`, `sks_diambil`), `$filter`, `$tahunList` |
| Daftar pengajuan (`pengajuan.index`) | BE-3 | `$all`, `$counts` (kunci = label status), `$rows` (paginator), `$from`, `$to` |
| Form pengajuan/kegiatan (`create`) | BE-3 | `$item` (`id`, `data`: kategori, judul, penyelenggara, tingkat, lokasi, tanggal mulai/selesai, peran, durasi, tautan info, dosen pembimbing, partisipasi, anggota; `documents`: nama, jenis, url), `$advisors`, `$isActivityHistory` |
| Detail pengajuan (`pengajuan.show`) | BE-3 + Lead | `$row` (`id`, `status` label, `title`, `type`, `submitted_at` Carbon, `editable`), `$student` (`name`, `nim`, `prodi`), `$steps` dari `timeline()`, `$files` (`name`, `meta`, `url`) |
| Pengajuan berita (`berita.index`) | BE-7 | `$items` (`id`, `title`, `category`, `submitted_at`, `status`), `$counts` (kunci = label), `$status`, `$query` |
| Detail berita (`berita.show`) | BE-7 | `$item` (`id`, `status`, `title`, `category`, `submitted_at`, `cover`, `source_type`, `previous_activity`, `activity_name`, `content`) |
| Form berita (`form`) | BE-7 | `$item`, `$activities` (`title`), `$categories`. Field kirim: `source_type`, `previous_activity`, `activity_name`, `category`, `title`, `content`, `cover`, `intent` (`draft` atau `submit`) |
| Kegiatan (`kegiatan.index`) | BE-7 | `$allActivities`, `$semesters`. Kartu kategori dan grafik saat ini masih angka tetap |
| Prestasi, tambah prestasi, profil | BE-6, BE-1 | Belum dipetakan |

Catatan: form berita mengirim judul kegiatan sebelumnya (`previous_activity`) sebagai teks, bukan id.
Sebaiknya diganti id agar tidak ambigu.
