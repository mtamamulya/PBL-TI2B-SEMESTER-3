# Revo · Pengajuan dan alur status

**Hasil yang dituju:** kegiatan yang sudah disimpan bisa diajukan dan dosen dapat melihat kapan pengajuan diterima, dikembalikan, atau ditolak.

| Target API | Yang dikerjakan |
|---|---|
| `GET /dosen/pengajuan` | Daftar pengajuan dosen login dengan filter status. |
| `GET /dosen/pengajuan/{id}/timeline` | Semua tahap beserta tanggal dan catatan revisi. |
| `POST /dosen/pengajuan/{id}/ajukan` | Pemilik mengajukan kegiatan yang belum memiliki tahap pengajuan. Periksa kelengkapan kategori dan lampiran. |
| `POST /dosen/pengajuan/{id}/ajukan-ulang` | Setelah direvisi dan dilengkapi, tambah tahap diajukan berikutnya. |

**Mulai dari** `Kegiatan::timeline()`, `statusTerkini()`, dan model `Timeline`. ID pada endpoint adalah `id_kegiatan`. Satu perubahan status menambah satu baris. Skema mengenal `diajukan`, `direview`, `direvisi`, `ditolak`, `diterima`, dan `selesai`. Susun matriks aktor dan transisi bersama pemilik modul admin/kaprodi. Endpoint dosen hanya menyediakan aksi dari sisi pengaju.

**Selesai saat** pengajuan pertama menghasilkan satu baris; klik ganda atau request bersamaan tidak menghasilkan dua tahap yang sama; revisi dan pengajuan ulang mempertahankan catatan lama; dosen tidak bisa menyetujui pengajuannya sendiri. Kirim contoh timeline ke Ridho, Lia, dan Ilham.

**File kerja:** `PengajuanController.php` untuk API dan `AlurPengajuan.php` untuk aturan transisi, transaksi, dan pencegahan kirim ganda.

[Kembali ke pembagian tugas tim](../README.md)
