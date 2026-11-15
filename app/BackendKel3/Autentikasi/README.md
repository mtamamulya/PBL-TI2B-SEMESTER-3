# Boby · Autentikasi dan middleware

**Hasil yang dituju:** setiap API privat dosen mengetahui siapa yang login, apakah akunnya aktif, dan apakah ia berhak memakai modul dosen.

| Endpoint yang sudah tersedia | Pekerjaan Boby |
|---|---|
| `POST /login` | Uji email dan sandi, token Sanctum, akun nonaktif, batas percobaan. Bagikan contoh login kepada tim. |
| `GET /me` | Pastikan token menghasilkan identitas akun yang benar. Berikan contoh cara membaca `id_user` dan mendapatkan profil `dosen`. |
| `POST /logout` | Pastikan token yang dipakai dicabut dan ditolak pada permintaan berikutnya. |

**Mulai dari** `app/Http/Controllers/Api/AuthController.php`, `routes/api.php`, dan `tests/Feature/Api/AuthApiTest.php`. Buat middleware khusus dosen di folder ini. Login dan token tetap memakai layanan bersama. Grup API tim sudah memakai `auth:sanctum`; pasang pemeriksaan role/akun aktif di `app/BackendKel3/routes.php`, termasuk pada opsi pengajaran bersama Naya.

**Selesai saat** dosen aktif mendapat 200, tanpa token mendapat 401, akun mahasiswa atau akun yang dinonaktifkan setelah login mendapat 403, dan token yang dicabut tidak bisa dipakai. Berikan ke Lukman dan Ilham satu contoh header `Authorization: Bearer <token>` memakai placeholder.

**File kerja:** `PastikanDosenAktif.php` untuk pemeriksaan role dan akun aktif. Mulai tes di `tests/Feature/BackendKel3/AksesDosenTest.php`.

[Kembali ke pembagian tugas tim](../README.md)
