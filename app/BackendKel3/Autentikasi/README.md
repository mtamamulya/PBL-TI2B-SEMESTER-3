# Boby · Autentikasi dan middleware

Semua path di bawah memakai prefix `/api/v1`. Login, identitas dan logout memakai layanan Sanctum bersama. Router Kelompok 3 memeriksa token, status akun saat permintaan masuk, lalu role dosen sebelum menjalankan controller.

| Endpoint | Cara pakai |
|---|---|
| `POST /login` | Kirim JSON berisi `email`, `password`, dan opsional `device_name`. Ambil `data.token` dan `data.expires_at` dari respons 200. |
| `GET /me` | Kirim token untuk membaca `data.id_user` dan `data.email` milik akun yang login. |
| `GET /pengajaran/opsi` | Contoh API privat yang bisa dicoba sekarang. Akun dosen aktif mendapat 200. |
| `POST /logout` | Kirim token perangkat yang ingin dikeluarkan. Token perangkat lain tetap bisa dipakai. |

## Contoh untuk frontend dan Postman

1. Kirim `POST /api/v1/login` dengan header `Accept: application/json` dan `Content-Type: application/json`. Ganti nilai contoh dengan akun uji lokal.

   ```json
   {"email":"dosen@example.test","password":"<sandi akun uji>","device_name":"postman"}
   ```

2. Salin nilai `data.token`, lalu kirim dua header berikut saat memanggil `GET /api/v1/me` atau `GET /api/v1/pengajaran/opsi`.

   ```http
   Accept: application/json
   Authorization: Bearer <data.token>
   ```

3. Untuk keluar, kirim `POST /api/v1/logout` dengan header yang sama. Jika token itu dipakai lagi untuk membuka opsi pengajaran, responsnya 401.

## Hasil yang perlu ditangani frontend

- **401** saat kredensial login salah atau token API privat kosong, kedaluwarsa, atau sudah dicabut.
- **403** saat akun nonaktif mencoba login, atau ketika pemegang token membuka API privat dosen dengan role lain atau akun yang sudah dinonaktifkan.
- **422** saat isian login gagal validasi. **429** saat batas lima permintaan login per menit terlampaui. Tunggu sesuai header `Retry-After`.

## Untuk pembuat modul

Daftarkan route privat lewat `app/BackendKel3/routes.php` agar mewarisi `auth:sanctum` dan `PastikanDosenAktif`. Di controller, ambil identitas dari `$request->user()`. Untuk profil gunakan `$request->user()->dosen`, yang bisa bernilai `null` jika profil belum dibuat. Gunakan `id_dosen` dari profil itu untuk membatasi data milik dosen sesuai aturan modul.

Jalankan `php artisan test --filter=AksesDosenTest` untuk menguji akses privat dengan token asli dan `php artisan test --filter=AuthApiTest` untuk layanan login bersama.

[Kembali ke pembagian tugas tim](../README.md)
