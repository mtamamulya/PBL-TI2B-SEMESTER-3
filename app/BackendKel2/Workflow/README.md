# Core Workflow · Mesin Status Bersama

Satu mesin untuk semua alur persetujuan (pengajuan kegiatan, berita, dan alur lain ke depan).
Pemilik: Lead. Modul lain **memakai** mesin ini, tidak menulis ke tabel `timeline` atau kolom status langsung.

## Prinsip

- **Aturan sama, penyimpanan berbeda.** Aturan (status, transisi, pelaku, catatan wajib) ada di
  `WorkflowDefinition`. Tempat menyimpan status dipilih per alur lewat `StatusStore`:
  - `TimelineStore`: riwayat di tabel `timeline` (kegiatan). Hanya ditambah, tidak diubah atau dihapus.
  - `ColumnStore`: kolom `berita.status` + `berita.catatan_revisi` (berita, tanpa riwayat).
- **Kode ≠ label.** Database menyimpan kode (`direvisi`, `publish`), UI menampilkan label ("Perlu revisi", "Dipublikasikan").
- **Satu pintu.** Semua perubahan lewat `WorkflowEngine` (satu transaksi, baris data dikunci).
- **Event setelah commit.** `StatusChanged` dikirim setelah transaksi berhasil. Notifikasi, pembuatan prestasi,
  dan lainnya cukup mendengarkan event ini.

## Alur yang tersedia

| Kunci | Store | Status (kode) | Definisi |
|---|---|---|---|
| `kegiatan` | `timeline` | diajukan, direview, direvisi, diterima, ditolak, selesai | `KegiatanWorkflow.php` |
| `berita` | kolom `status` | draft, diajukan, direvisi, publish, ditolak | `BeritaWorkflow.php` |

Prestasi tidak memakai alur status (langsung tersimpan). Pengajuan surat adalah kegiatan dengan kategori
berkebutuhan `surat`, jadi memakai alur `kegiatan`.

## Kegiatan tercatat vs pengajuan

Kegiatan punya dua asal, dibedakan oleh ada tidaknya baris di `timeline`:

| | Pengajuan | Tercatat |
|---|---|---|
| Dibuat lewat | Form pengajuan (dengan surat dan persetujuan) | "Tambah Kegiatan" (riwayat langsung) |
| Baris `timeline` | Ada (`start()` dipanggil) | **Tidak ada**, mesin tidak dipanggil |
| Label | Diajukan, Direview, ... | `Tercatat` |
| Edit / hapus | Menurut status | Bebas oleh pemilik |
| Muncul di | Halaman Pengajuan | Halaman Kegiatan |

Halaman Kegiatan menampilkan **tercatat + pengajuan yang selesai** (usulan, perlu dikonfirmasi):

```php
Kegiatan::where('id_user', $id)->tercatatAtauStatus('selesai');   // daftar dan kartu halaman Kegiatan
Kegiatan::where('id_user', $id)->pengajuan();                     // daftar halaman Pengajuan
Kegiatan::where('id_user', $id)->tercatat();                      // hanya yang dicatat langsung$kegiatan->tercatat();                                            // true bila tanpa timeline