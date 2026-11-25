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

## Memasang pada sebuah model

```php
use App\BackendKel2\Workflow\{HasWorkflow, Workflowable};

class Kegiatan extends Model implements Workflowable
{
    use HasWorkflow;

    public static function workflowKey(): string { return 'kegiatan'; }
    public function workflowOwnerId(): int|string|null { return $this->id_user; }
}
```

## Cara pakai

```php
$engine = app(WorkflowEngine::class);

// 1. Saat data dibuat (di dalam transaksi pembuatan data)
$engine->start($kegiatan, $user);                     // berita: $engine->start($berita, $user, 'draft') atau 'diajukan'

// 2. Saat reviewer/pemilik bertindak
$engine->transition($kegiatan, 'direview', $reviewer);
$engine->transition($kegiatan, 'direvisi', $reviewer, 'Lengkapi lampiran.');   // catatan wajib
$engine->transition($kegiatan, 'diajukan', $pemilik);                          // ajukan ulang

// 3. Untuk UI
$kegiatan->statusLabel();                        // "Perlu revisi"
$kegiatan->statusTerkini?->status;               // "direvisi" (dipakai Blade dashboard; bisa with('statusTerkini'))
$kegiatan->bolehDiedit();                        // untuk 'editable' di halaman detail
$kegiatan->timeline();                           // [['title','at','actor','note'], ...] untuk $steps
$engine->availableTransitions($kegiatan, $user); // tombol aksi yang boleh dilihat pelaku
Kegiatan::whereStatus('direview')->paginate();   // filter daftar
$engine->countByLabel(Kegiatan::where('id_user', $id));   // ['Disetujui' => 4, ...] untuk kartu
```

`start()` mengembalikan baris `timeline` (untuk kegiatan) sehingga BE-5 bisa mengaitkan dokumen lewat
`bukti_history` (`id_dokumen`, `id_timeline`).

## Mendengarkan perubahan status

```php
Event::listen(StatusChanged::class, function (StatusChanged $e) {
    if ($e->became('kegiatan', 'selesai')) {
        // buat prestasi otomatis, kirim notifikasi, dst
    }
});
```

## Error yang bisa muncul

| Exception | Arti | Respons |
|---|---|---|
| `InvalidTransition` | Perpindahan status tidak ada di definisi | 422 (JSON) atau kembali ke form dengan error `status` |
| `TransitionForbidden` | Pelaku tidak berhak | 403 |
| `ValidationException` | Catatan wajib tetapi kosong | 422, error pada `note` |

## Pelaku

`owner` (pemilik data), `staff` (lolos Gate `review-workflow`), `system` (tanpa pengguna, mis. scheduler).
Gate `review-workflow` sementara memeriksa `$user->role->nama_role` terhadap `['admin', 'reviewer']`
di `WorkflowServiceProvider`. BE-1 menimpanya dengan aturan resmi.

## Yang disediakan modul lain

- **BE-1:** relasi `role` pada User (menuju tabel `role`, kolom `nama_role`) dan accessor `nama_tampil`
  (dari `mahasiswa.nama` atau `dosen.nama`). Selama accessor belum ada, nama pelaku di timeline memakai
  kolom `name` bila ada, lalu email.
- **Perubahan skema:** lihat `docs/review-erd.md` (`timeline.id_user`, perluasan enum `berita.status`).

## Menambah alur baru

1. Buat `XxxWorkflow.php` berisi `definition()` (salin pola `BeritaWorkflow`).
2. Pilih store (`TimelineStore` bila perlu riwayat, `ColumnStore` bila cukup satu kolom).
3. Daftarkan di `WorkflowServiceProvider::register()`.
4. Tambah tes di `WorkflowDefinitionTest`, lalu perbarui dokumen ini dan ADR bila ada keputusan baru.

## Pemasangan awal

1. Salin file ke repo, lalu daftarkan provider di `bootstrap/providers.php`:
   `App\BackendKel2\Workflow\WorkflowServiceProvider::class`
2. Baca `docs/review-erd.md`, lalu cek tabel `timeline` dan `berita` yang ada di database sebelum `php artisan migrate`.
3. `php artisan test --filter=Workflow`
