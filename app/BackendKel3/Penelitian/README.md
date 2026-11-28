# Rita Imut · Penelitian

**Hasil yang dituju:** dosen memilih kategori penelitian, mengisi form sesuai kategori, lalu menyimpan dan melihat kembali penelitiannya.

| Target API | Yang dikerjakan |
|---|---|
| `GET /dosen/penelitian` | ✅ Daftar penelitian milik atau yang diikuti dosen, dengan filter tahun dan status. |
| `GET /dosen/penelitian/{id}` | ✅ Detail kegiatan, skim, kelompok bidang, jawaban form, anggota, dokumen, status terakhir. |
| `POST /dosen/penelitian` | ✅ Simpan kegiatan, anggota, dan jawaban dinamis dalam satu transaksi; kembalikan ID kegiatan. |
| `PATCH /dosen/penelitian/{id}` | ✅ Pemilik memperbaiki isian selama tahap/status masih mengizinkan. |
| `DELETE /dosen/penelitian/{id}` | ✅ Pemilik dapat menghapus draft; kegiatan yang sudah diajukan tidak dapat dihapus. |
| `GET /dosen/kategori/{id}/isian` | ✅ Ambil definisi field untuk kategori yang dipilih. |

## Filter daftar

`GET /dosen/penelitian` mendukung:

- `q`: pencarian nama kegiatan;
- `tahun`: tahun kegiatan, diambil dari tanggal mulai dinamis atau kolom tabel;
- `status`: `draft`, `diajukan`, `direview`, `direvisi`, `ditolak`, `diterima`, atau `selesai`;
- `per_page`: 1–100, default 15.

Contoh: `GET /api/v1/dosen/penelitian?tahun=2026&status=diajukan&per_page=20`.

## Aturan status dan edit

- Data baru tanpa timeline berstatus `draft`.
- Pemilik dan anggota dapat membaca kegiatan; hanya pemilik dapat mengubah atau menghapus.
- Pemilik tidak dapat mengubah kegiatan berstatus `diterima` atau `selesai`.
- Kegiatan yang sudah pernah diajukan tidak dapat dihapus.
- Pengajuan baru hanya boleh dilakukan pemilik.
- Pengajuan memerlukan kategori, seluruh isian wajib kategori, dan paling sedikit satu lampiran.
- Pengajuan ulang hanya boleh dilakukan pada status `direvisi` dan kembali melewati validasi kelengkapan.

## Cakupan yang sudah selesai

Backend memakai tabel bersama `kegiatan`, `detail_kegiatan`, `anggota_kegiatan`, `dokumen`, dan `timeline`. Pemilik otomatis berasal dari akun yang login. Kategori harus berawalan `Penelitian`; setiap jawaban harus terdaftar pada kategori tersebut. Simpan jawaban, anggota, dan kegiatan dalam transaksi; tanggal mulai/selesai dibaca dari isian dinamis. Pemilik dapat mengubah draft atau kegiatan yang direvisi, sedangkan anggota hanya dapat membaca. Status tanpa timeline dikembalikan sebagai `draft`.

Pilihan kategori tersedia melalui `GET /api/v1/kategori-kegiatan?jenis=Kegiatan%20Dosen`; definisi form tersedia melalui `GET /api/v1/dosen/kategori/{id}/isian`. Pengajuan, perubahan status, dan pengelolaan lampiran memakai endpoint bersama modul Pengajuan dan Dokumen, bukan endpoint khusus penelitian.

Tes fitur ada di `tests/Feature/BackendKel3/IntegrasiKelompok3Test.php`, termasuk simpan/baca/update/hapus, filter, kategori/isian silang, dan batas akses antardosen. Jalankan `php artisan test tests/Feature/BackendKel3/IntegrasiKelompok3Test.php --filter=penelitian`.

**Batas cakupan:** penyelesaian PDF surat dan keputusan review dilakukan oleh modul lain; keduanya bukan bagian dari CRUD penelitian.

[Kembali ke pembagian tugas tim](../README.md)
