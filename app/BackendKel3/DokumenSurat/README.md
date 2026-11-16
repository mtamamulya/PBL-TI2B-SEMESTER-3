# Ridho · Dokumen dan surat

**Hasil yang dituju:** dosen dapat melampirkan bukti pada kegiatan yang benar dan mengunduh surat final ketika tersedia.

| Target API | Yang dikerjakan |
|---|---|
| `GET /dosen/kegiatan/{id}/dokumen` | Daftar jenis, nama, dan lokasi berkas/tautan yang boleh diakses. |
| `POST /dosen/kegiatan/{id}/dokumen` | Validasi jenis dokumen, ukuran/tipe unggahan atau format tautan, serta kecocokan detail dan kegiatan. |
| `DELETE /dosen/kegiatan/{id}/dokumen/{dokumen}` | Hapus lampiran milik pengaju hanya pada tahap yang masih boleh diubah. |
| `GET /dosen/pengajuan/{id}/surat` | Sajikan surat dari pengajuan yang boleh diakses; tangani surat belum terbit dan kategori tanpa template. |

**Mulai dari** `Dokumen`, `JenisDokumen`, `BuktiHistory`, `kategori.isi_surat`, `lampiran_peserta`, dan `dokumen.is_lampiran`. Nomor surat, tanda tangan, dan hak terbit dikoordinasikan dengan admin/kaprodi. Untuk arsip pengajaran, periksa dengan Naya asal `id_kegiatan` karena dokumen mewajibkan ID itu. Jangan membuat kegiatan fiktif untuk memenuhi relasi.

**Selesai saat** unggahan bisa dibaca kembali, dokumen dari kegiatan lain ditolak, penghapusan detail menghapus baris dokumen terkait, dan pengguna lain tidak memperoleh isi surat dari URL tebakan. Bedakan penghapusan baris database dan penghapusan berkas fisik. Serahkan contoh hasil unduh/tautan ke Naya dan Ilham.

**File kerja:** `DokumenController.php` untuk daftar/unggah/hapus dan `SuratController.php` untuk mengakses surat kegiatan yang sudah tersedia.

[Kembali ke pembagian tugas tim](../README.md)
