# Backend dosen · Kelompok 3

## Koreksi database yang sudah terpasang · 22 November 2026

Jalankan `php artisan migrate` setelah mengambil develop terbaru. Migration `2026_11_22_000001_reconcile_existing_otp_codes` memperbaiki instalasi lama yang sudah mencatat migration OTP tetapi belum memiliki `tujuan`. Baris lama diberi tujuan `lupa_sandi`; waktu OTP memakai DATETIME agar perubahan jumlah percobaan tidak menggeser waktu kedaluwarsa secara otomatis di MariaDB. Data OTP dan indeks pencarian dipertahankan. Rollback migration ini sengaja tidak menghapus kolom koreksi yang dibutuhkan kode aplikasi.

Riwayat migration yang lengkap belum menjamin struktur database lama sama dengan kode, karena sebagian migration awal pernah diedit. Bandingkan kolom, indeks, dan foreign key sebelum memakai database lama. Jangan menjalankan `migrate:fresh` pada database berisi data. Tabel arsip atau tabel dokumen paralel lama tidak perlu dihapus untuk menjalankan aplikasi; rekonsiliasi datanya dilakukan terpisah.

## Integrasi branch anggota · 22 November 2026

Integrasi mencakup `feature/API-penelitian`, `feature/kel3-pengabdian`, `rhafihairy/dashboard-notifikasi`, `feature/kel3/PrestasiBerita-lia`, dan `feature/dokumen-surat-ridho`. Riwayat commit masing-masing branch dipertahankan melalui merge.

- API privat berada di `/api/v1/dosen`, memakai token atau sesi Sanctum serta pemeriksaan dosen aktif. Router tiap modul dimuat satu kali. Endpoint berita dosen ditangani Kelompok 3; endpoint kategori berita dan review admin tetap di Kelompok 2.
- Penelitian dan pengabdian memakai `KegiatanRisetController`, tabel `kegiatan`, `detail_kegiatan`, dan `anggota_kegiatan`. Kirim `id_kategori`, `nama_kegiatan`, opsional `id_skim`, `id_kelompok_bidang`, serta `detail: [{id_isian_kategori, isian}]`. Tanggal menggunakan definisi isian kategori. Nama master pada form lama harus cocok dengan data database; data contoh bukan otomatis menjadi data master.
- Daftar penelitian mendukung filter `q`, `tahun`, `status`, dan `per_page`. Status `draft` berarti kegiatan belum memiliki timeline. Pengajuan menolak kegiatan yang isian wajibnya belum lengkap atau belum memiliki lampiran.
- Lampiran memakai tabel `dokumen` yang sama dengan alur pengajuan Revo. URL `kegiatan/{id}/dokumen` dan alias `pengajuan/{id}/dokumen` menerima `file`, `dokumen`, `berkas`, `files[]`, atau tautan HTTP(S). Hanya pemilik boleh mengubah lampiran pada draft atau status `direvisi`. `ajukan_kembali=true` menjalankan validasi alur pengajuan ulang dalam transaksi yang sama. Mengunggah tanpa opsi itu tidak mengubah status.
- Penghapusan lampiran menghapus relasi aktif; berkas fisik tetap diarsipkan. Bukti yang sudah ditautkan ke riwayat tidak dapat dihapus. Tabel paralel `dokumen_surats`, `dokumen_terlampirs`, `pengajuans`, dan `riwayat_statuses` dari branch lama tidak dipakai dan migration baru untuk tabel tersebut tidak disertakan. Tidak ada perintah penghapusan tabel pada database anggota yang sudah menggunakannya.
- `GET pengajuan/{id}/surat` mengembalikan metadata/template dan lampiran terpilih setelah kegiatan diterima dan bernomor SK. Endpoint ini belum menghasilkan PDF surat bertanda tangan.
- Dashboard membaca status timeline paling baru; kegiatan tanpa timeline merupakan draft. Notifikasi hanya menampilkan timeline kegiatan milik pengguna.
- Berita dosen mendukung draft dan pengajuan review; publikasi tetap melalui pengelola. Prestasi tetap memakai formulir update lengkap dan hanya pemilik yang boleh mengubah atau menghapus.

Pengujian lintas modul ada di `tests/Feature/BackendKel3/IntegrasiKelompok3Test.php`. Halaman yang masih memakai data contoh dan penyelesaian PDF surat tetap merupakan pekerjaan lanjutan anggota; merge tidak menyatakan seluruh jobdesk selesai.

Folder kerja tim ada di `app/BackendKel3`. Klik nama anggota untuk membuka jobdesk, daftar endpoint, rekan yang perlu diajak koordinasi, dan contoh uji penerimaan.

| Anggota | Modul | Hasil pertama yang bisa didemokan |
|---|---|---|
| [Boby](Autentikasi/README.md) | Autentikasi dan middleware | Token dosen bisa membuka API privat; token mahasiswa ditolak |
| [Lukman](Profil/README.md) | Profil dan riwayat dosen | Dosen melihat profil dan daftar SK miliknya |
| [Naya](Pengajaran/README.md) | Pengajaran | Dosen menambah penugasan kelas dan membuka detailnya |
| [Rita Imut](Penelitian/README.md) | Penelitian | Form penelitian tersimpan lengkap dan dapat dibuka kembali |
| [Pandu](Pengabdian/README.md) | Pengabdian | Form berubah sesuai kategori pengabdian |
| [Revo](Pengajuan/README.md) | Pengajuan dan alur status | Pengajuan yang direvisi bisa diajukan ulang dan riwayat tetap lengkap |
| [Ridho](DokumenSurat/README.md) | Dokumen dan surat | Dosen membuka lampiran dan surat pengajuannya |
| [Lia](PrestasiBerita/README.md) | Prestasi dan berita dosen | Kegiatan diterima bisa ditautkan ke prestasi pemilik atau anggotanya |
| [Ilham](Dashboard/README.md) | Dashboard, notifikasi, dan integrasi | Angka dashboard cocok dengan daftar dan notifikasi status |

## Cara mulai

1. Dari folder repo masing-masing, ambil branch bersama dan buat branch pribadi. Contoh untuk Rita:

   ```bash
   git fetch origin
   git switch -c feature/kel3-penelitian origin/feature/backend-kelompok-3
   ```

2. Buka folder modulmu di `app/BackendKel3`. Letakkan controller, validasi request, dan kode khusus modul di situ. Contoh namespace Rita ialah `App\BackendKel3\Penelitian`. Setiap modul fitur punya `routes.php` sendiri. Boby mengerjakan middleware dan pemasangannya di router induk.
3. Gunakan model bersama dari `app/Models`. Tambahkan tes di `tests/Feature/BackendKel3/<Modul>Test.php` agar ditemukan PHPUnit. Untuk perubahan skema yang telah disepakati, gunakan migration baru di `database/migrations` sesuai urutan Laravel.
4. Jalankan perintah dari root repo pada environment development yang sudah disiapkan. Tes memakai database testing.

   ```bash
   php artisan route:list --path=api/v1
   php artisan test --filter=PengajaranTest
   php artisan test
   ```

5. Buat PR dari branch pribadi ke `feature/backend-kelompok-3`. Sertakan endpoint yang sudah bisa dipanggil, contoh payload/respons, dan hasil tes. Setelah alur antarmodul diuji bersama, tim dapat mengajukan PR integrasi ke `develop`.

## API yang sudah bisa diuji dan target yang akan dibangun

Semua path pada jobdesk relatif ke **`/api/v1`**. Contoh `GET /dosen/profil` berarti `GET /api/v1/dosen/profil`. Pada basis `develop` `183b538`, login, logout, me, dan API pengajaran sudah terdaftar. Halaman web dashboard juga sudah ada di `/dosen/dashboard`. Endpoint bertanda **target** merupakan pekerjaan anggota masing-masing. File route awal untuk target belum mendaftarkan endpoint.

Deklarasi `routes/api.php` memuat router tim satu kali. Router tim memasang `auth:sanctum`, mempertahankan URL pengajaran, dan memberi prefix `dosen` pada modul baru. Misalnya di `Profil/routes.php` cukup daftarkan path `profil`. Boby memasang pemeriksaan role dan akun aktif sebelum endpoint privat baru dipakai bersama. Kontrol kepemilikan objek tetap menjadi tugas penanggung jawab masing-masing endpoint.

**Contoh kontrak awal untuk Rita dan Pandu.** Frontend mengirim `id_kategori`, `nama_kegiatan`, serta daftar `detail`. Bila dipakai, sertakan `id_skim`, `id_kelompok_bidang`, dan anggota. Setiap `detail` berisi `id_isian_kategori` dari endpoint milik Pandu dan `isian`, misalnya tanggal `2026-11-15`. Pemilik diambil dari sesi/token. Setelah tersimpan, respons 201 mengembalikan `id_kegiatan`; ID inilah yang dipakai Revo dan Ridho.

**Kontrak respons.** Untuk API baru, mulai dari `App\Support\ApiResponse` dan `docs/API-CONTRACT.md`. Dokumentasikan contoh sukses, data kosong, kesalahan validasi (422), tanpa autentikasi (401), salah role (403), serta ID yang berada di luar akses pengguna (404). API pengajaran sudah memiliki konsumen dan format `status/message/data`; Naya dan Ilham memeriksa konsumennya sebelum mengubah kontrak.

## Urutan kerja dan serah terima

| Tahap | Yang dikerjakan bersama | Bukti siap lanjut |
|---|---|---|
| 1. Identitas dan contoh data | Boby menyiapkan cara memperoleh token. Lukman memetakan `id_user` ke `id_dosen`. Naya memeriksa API pengajaran. Ilham menyiapkan koleksi request. | Dua akun dosen dan satu akun mahasiswa dapat menguji akses berbeda di database pengujian. |
| 2. Data kegiatan | Pandu menyiapkan definisi form. Rita dan Pandu menyepakati penyimpanan jawaban serta anggota. Ridho dan Revo menyepakati syarat lampiran dan tahap kirim. | Satu penelitian dan satu pengabdian dapat disimpan dan ditampilkan beserta detail serta lampirannya. |
| 3. Status dan hasil | Revo menguji pengajuan ulang dan status terakhir bersama sisi verifikator. Lia memakai kegiatan diterima untuk prestasi. Ridho membaca surat yang sudah tersedia. | Kasus diterima, direvisi, dan ditolak menghasilkan respons sesuai aktor. |
| 4. Integrasi | Ilham membandingkan dashboard dengan daftar sumber dan menguji notifikasi. Semua anggota mencoba akses silang dosen A/B. | Demo login → isi pengabdian → unggah bukti → ajukan → tinjau status → buka notifikasi/surat. Untuk kegiatan diterima, lanjutkan ke pencatatan prestasi. |

**Koordinasi lintas kelompok.** Alur dosen adalah tanggung jawab Kelompok 3. Login, master identitas, master akademik, serta aksi persetujuan admin/kaprodi memiliki pemanggil lain. Gunakan kontrak yang sudah tersedia dan sepakati perubahan dengan pemiliknya. Kontrak antarkelompok dalam repo masih berupa rancangan. Ilham mencatat URL, pemilik layanan, ID yang dipertukarkan, dan contoh respons nyata sebelum menyambungkannya.
