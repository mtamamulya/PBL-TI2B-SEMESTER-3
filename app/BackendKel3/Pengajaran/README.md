# Naya · Pengajaran

**Hasil yang dituju:** dosen bisa mencatat dan melihat kelas serta mata kuliah yang diampu. Implementasi dan route yang sudah berjalan ada di folder ini.

| API aktif | Yang dicek atau dilanjutkan |
|---|---|
| `GET /pengajaran` | Daftar penugasan dosen login beserta kelas, mata kuliah, semester, dan tahun. |
| `GET /pengajaran/opsi` | Pilihan kelas dan mata kuliah dari database. Terapkan pembatasan dosen juga pada opsi. |
| `POST /pengajaran` | Validasi `id_enrollment_tingkat_kelas` dan `id_enrollment_mk`; ambil dosen dari akun login. |
| `GET /pengajaran/{id}` | Tampilkan detail penugasan sendiri beserta dokumen terkait. |
| `PUT` atau `PATCH /pengajaran/{id}` | Ubah penugasan sendiri dan periksa kembali kesesuaian tahun akademik. |

**Mulai dari** `DosenPengajaranController.php` dan `routes.php` di sini. ID pada URL adalah ID penugasan `enrollment_kelas_mata_kuliah`. Tabel `pengajaran` menautkan penugasan dengan dokumen. Koordinasikan lampiran dengan Ridho dan pasok jumlah penugasan ke Ilham. Perubahan master kelas, kurikulum, atau mata kuliah dikoordinasikan dengan pengelolanya.

**Selesai saat** daftar hanya berisi kelas dosen login, tahun kelas dan mata kuliah yang berbeda ditolak, satu kelas/mata kuliah tidak mendapat dua pengampu, dan dosen lain tidak bisa membuka detailnya. Jalankan `php artisan test --filter=PengajaranTest`.

**File kerja:** Lanjutkan `DosenPengajaranController.php` dan `routes.php` yang sudah terisi. Rujukan pengujian ada di `tests/Feature/PengajaranTest.php`.

[Kembali ke pembagian tugas tim](../README.md)
