<?php

use App\Http\Controllers\AdminAkreditasiController;
use App\Http\Controllers\AdminAuthController;
// ---------- Controller Publik & Landing ----------
use App\Http\Controllers\AdminBeritaController;
use App\Http\Controllers\AdminKegiatanController;
use App\Http\Controllers\AdminKegiatanDetailShowController;
use App\Http\Controllers\AdminKegiatanTambahController;
use App\Http\Controllers\AdminKurikulumController;
use App\Http\Controllers\AdminMataKuliahController;
use App\Http\Controllers\AdminPengajuanController;
use App\Http\Controllers\AdminPrestasiController;
use App\Http\Controllers\AdminProfilController;
// ---------- Controller Admin ----------
use App\Http\Controllers\AdminTinjauPengajuanController;
use App\Http\Controllers\AkreditasiController;
use App\Http\Controllers\DosenController;
use App\Http\Controllers\FasilitasController;
use App\Http\Controllers\JenisDokumenController;
use App\Http\Controllers\KaprodiBeritaController;
use App\Http\Controllers\KaprodiDashboardController;
use App\Http\Controllers\KaprodiDosenController;
use App\Http\Controllers\KaprodiJenisDokumenController;
use App\Http\Controllers\KaprodiKategoriKegiatanController;
use App\Http\Controllers\KaprodiKegiatanController;
use App\Http\Controllers\KaprodiKegiatanDosenDetailShowController;
use App\Http\Controllers\KaprodiKegiatanMahasiswaDetailShowController;
use App\Http\Controllers\KaprodiKurikulumController;
use App\Http\Controllers\KaprodiMahasiswaController;
use App\Http\Controllers\KaprodiMataKuliahController;
use App\Http\Controllers\KaprodiPengajuanController;
use App\Http\Controllers\KaprodiPrestasiController;
use App\Http\Controllers\KaprodiProfilController;
use App\Http\Controllers\KaprodiTandaTanganController;
// ---------- Controller Kaprodi ----------
use App\Http\Controllers\KategoriKegiatanController;
use App\Http\Controllers\KegiatanHapusController;
use App\Http\Controllers\KegiatanLaporanController;
use App\Http\Controllers\KegiatanMahasiswaController;
use App\Http\Controllers\KelompokBidangController;
use App\Http\Controllers\KurikulumController;
use App\Http\Controllers\LandingDashboardController;
use App\Http\Controllers\LandingMahasiswaController;
use App\Http\Controllers\MataKuliahController;
use App\Http\Controllers\NotifikasiStafController;
use App\Http\Controllers\PemulihanController;
use App\Http\Controllers\PrestasiController;
use App\Http\Controllers\PrestasiLaporanController;
use App\Http\Controllers\ProfilProdiController;
use App\Http\Controllers\PublicBeritaController;
use App\Http\Controllers\SkimController;
use App\Http\Controllers\TahunAkademikController;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\KaprodiOnly;
use Illuminate\Support\Facades\Route;

// ---------- Publik ----------
Route::get('/mata-kuliah', [MataKuliahController::class, 'index'])->name('mata-kuliah');
Route::get('/mata-kuliah/data', [MataKuliahController::class, 'data'])->name('mata-kuliah.data');
Route::get('/akreditasi', [AkreditasiController::class, 'index'])->name('akreditasi');
Route::get('/kurikulum', [KurikulumController::class, 'index'])->name('kurikulum');
Route::get('/fasilitas', [FasilitasController::class, 'index'])->name('fasilitas');
Route::get('/kabar', [PublicBeritaController::class, 'index'])->name('publik.berita.index');
Route::get('/kabar/{slug}', [PublicBeritaController::class, 'show'])->name('publik.berita.show');
Route::get('/prestasi-trk', [PrestasiController::class, 'index'])->name('publik.prestasi.index');
Route::get('/prestasi-trk/{prestasi}', [PrestasiController::class, 'show'])
    ->whereNumber('prestasi')
    ->name('publik.prestasi.show');

// ---------- Login & Pemulihan ----------
Route::view('/login', 'login')->name('login');
Route::post('/login', [AdminAuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AdminAuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/lupa-sandi', [PemulihanController::class, 'show'])->name('password.request');
Route::post('/lupa-sandi/email', [PemulihanController::class, 'kirimOtp'])->name('password.email');
Route::post('/lupa-sandi/otp', [PemulihanController::class, 'verifikasi'])->name('password.otp');
Route::post('/lupa-sandi/kirim-ulang', [PemulihanController::class, 'kirimUlang'])->name('password.kirim-ulang');
Route::post('/lupa-sandi/ubah-email', [PemulihanController::class, 'ubahEmail'])->name('password.ubah-email');
Route::post('/lupa-sandi/sandi', [PemulihanController::class, 'simpan'])->name('password.simpan');
Route::post('/lupa-sandi/email', [PemulihanController::class, 'kirimOtp'])->middleware('throttle:5,1')->name('password.email');
Route::post('/lupa-sandi/otp', [PemulihanController::class, 'verifikasi'])->middleware('throttle:10,1')->name('password.otp');
Route::post('/lupa-sandi/kirim-ulang', [PemulihanController::class, 'kirimUlang'])->middleware('throttle:5,1')->name('password.kirim-ulang');
Route::get('/lupa-sandi/email', fn () => redirect()->route('password.request'));

// ---------- Admin (dijaga AdminOnly) ----------
Route::prefix('admin')->middleware(AdminOnly::class)->group(function () {
    Route::get('/dashboard', [LandingDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/notifikasi', [NotifikasiStafController::class, 'index'])->name('admin.notifikasi');
    Route::post('/notifikasi/baca-semua', [NotifikasiStafController::class, 'bacaSemua'])->name('admin.notifikasi.baca-semua');
    Route::post('/notifikasi/{id}/baca', [NotifikasiStafController::class, 'baca'])->where('id', '[A-Za-z0-9_-]+')->name('admin.notifikasi.baca');
    Route::post('/keluar', [AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::get('akreditasi', [AdminAkreditasiController::class, 'index'])->name('admin.akreditasi');
    Route::post('akreditasi', [AdminAkreditasiController::class, 'store'])->name('admin.akreditasi.store');
    Route::put('akreditasi/{id}', [AdminAkreditasiController::class, 'update'])->name('admin.akreditasi.update');
    Route::patch('akreditasi/{id}/status', [AdminAkreditasiController::class, 'status'])->name('admin.akreditasi.status');
    Route::delete('akreditasi/{id}', [AdminAkreditasiController::class, 'destroy'])->name('admin.akreditasi.destroy');

    // Kurikulum TRK
    Route::get('/kurikulum/trk', [AdminKurikulumController::class, 'index'])->name('admin.kurikulum.trk');
    Route::post('/kurikulum/trk', [AdminKurikulumController::class, 'store'])->name('admin.kurikulum.store');
    Route::put('/kurikulum/trk/{id}', [AdminKurikulumController::class, 'update'])->name('admin.kurikulum.update');
    Route::patch('/kurikulum/trk/{id}/status', [AdminKurikulumController::class, 'status'])->name('admin.kurikulum.status');
    Route::delete('/kurikulum/trk/{id}', [AdminKurikulumController::class, 'destroy'])->name('admin.kurikulum.destroy');

    // Mata Kuliah
    Route::get('/kurikulum/mata-kuliah', [AdminMataKuliahController::class, 'index'])->name('admin.kurikulum.mata-kuliah');
    Route::get('kurikulum/mata-kuliah/tambah', [AdminMataKuliahController::class, 'create'])->name('admin.kurikulum.mata-kuliah.create');
    Route::post('kurikulum/mata-kuliah', [AdminMataKuliahController::class, 'store'])->name('admin.kurikulum.mata-kuliah.store');

    // Tahun Akademik (Admin: Lihat & Tambah)
    Route::get('/kurikulum/tahun-akademik', [TahunAkademikController::class, 'index'])
        ->name('admin.kurikulum.tahun-akademik');
    Route::post('/kurikulum/tahun-akademik', [TahunAkademikController::class, 'store'])
        ->name('admin.kurikulum.tahun-akademik.store');

    // Data Dosen
    Route::get('/dosen', [DosenController::class, 'index'])->name('admin.dosen');
    Route::get('dosen/tambah', [DosenController::class, 'create'])->name('admin.dosen.create');
    Route::post('dosen', [DosenController::class, 'store'])->name('admin.dosen.store');
    Route::get('/dosen/{id}', [DosenController::class, 'show'])->name('admin.dosen.show');
    Route::get('/dosen/{id}/edit', [DosenController::class, 'edit'])->name('admin.dosen.edit');
    Route::put('/dosen/{id}', [DosenController::class, 'update'])->name('admin.dosen.update');
    Route::patch('/dosen/{id}/status', [DosenController::class, 'toggleStatus'])->name('admin.dosen.status');

    // Data Mahasiswa
    Route::get('/mahasiswa', [LandingMahasiswaController::class, 'index'])->name('admin.mahasiswa');
    Route::get('mahasiswa/tambah', [LandingMahasiswaController::class, 'create'])->name('admin.mahasiswa.create');
    Route::post('mahasiswa', [LandingMahasiswaController::class, 'store'])->name('admin.mahasiswa.store');
    Route::get('/mahasiswa/{id}', [LandingMahasiswaController::class, 'show'])->name('admin.mahasiswa.show');
    Route::get('/mahasiswa/{id}/edit', [LandingMahasiswaController::class, 'edit'])->name('admin.mahasiswa.edit');
    Route::put('/mahasiswa/{id}', [LandingMahasiswaController::class, 'update'])->name('admin.mahasiswa.update');
    Route::post('/mahasiswa/buat-kelas', [LandingMahasiswaController::class, 'buatKelasSemester'])->name('admin.mahasiswa.buat-kelas');
    Route::patch('/mahasiswa/{id}/toggle-status', [LandingMahasiswaController::class, 'toggleStatus'])->name('admin.mahasiswa.toggle-status');

    // Kegiatan (Menggunakan AdminKegiatanTambahController untuk Create & Store)
    Route::get('/kegiatan', [AdminKegiatanController::class, 'index'])->name('admin.kegiatan.index');
    Route::get('/kegiatan/tambah', [AdminKegiatanTambahController::class, 'create'])->name('admin.kegiatan.create');
    Route::get('/kegiatan/tambah/kategori/{id}', [AdminKegiatanTambahController::class, 'kategori'])->whereNumber('id')->name('admin.kegiatan.create.kategori');
    Route::post('/kegiatan', [AdminKegiatanTambahController::class, 'store'])->name('admin.kegiatan.store');
    // Nama lama untuk tautan/breadcrumb lama; daftar kegiatan kini satu halaman bertab (sama seperti sisi kaprodi).
    Route::redirect('/kegiatan/dosen', '/admin/kegiatan?tab=dosen')->name('admin.kegiatan.dosen');
    Route::redirect('/kegiatan/mahasiswa', '/admin/kegiatan?tab=mahasiswa')->name('admin.kegiatan.mahasiswa');

    Route::get('/kegiatan/mahasiswa', [KegiatanMahasiswaController::class, 'index'])->name('admin.kegiatan.mahasiswa');
    Route::get('/kegiatan/dosen/laporan', [KegiatanLaporanController::class, 'dosen'])->name('admin.kegiatan.dosen.laporan');
    Route::get('/kegiatan/mahasiswa/laporan', [KegiatanLaporanController::class, 'mahasiswa'])->name('admin.kegiatan.mahasiswa.laporan');
    Route::get('/kegiatan/prodi/laporan', [KegiatanLaporanController::class, 'prodi'])->name('admin.kegiatan.prodi.laporan');
    Route::get('/kegiatan/semua/laporan', [KegiatanLaporanController::class, 'semua'])->name('admin.kegiatan.semua.laporan');
    Route::get('/kegiatan/mahasiswa/{id}', [AdminKegiatanDetailShowController::class, 'show'])->defaults('jenis', 'mahasiswa')->name('admin.kegiatan.mahasiswa.detail.show');
    Route::get('/kegiatan/dosen/{id}', [AdminKegiatanDetailShowController::class, 'show'])->defaults('jenis', 'dosen')->name('admin.kegiatan.dosen.detail.show');
    Route::get('/kegiatan/prodi/{id}', [AdminKegiatanDetailShowController::class, 'show'])->defaults('jenis', 'prodi')->name('admin.kegiatan.prodi.detail.show');
    Route::delete('/kegiatan/dosen/{id}', [KegiatanHapusController::class, 'dosen'])->name('admin.kegiatan.dosen.destroy');
    Route::delete('/kegiatan/mahasiswa/{id}', [KegiatanHapusController::class, 'mahasiswa'])->name('admin.kegiatan.mahasiswa.destroy');
    // Jenis Dokumen
    Route::get('/kegiatan/jenis-dokumen', [JenisDokumenController::class, 'index'])->name('admin.kegiatan.jenis-dokumen');
    Route::post('/kegiatan/jenis-dokumen', [JenisDokumenController::class, 'store'])->name('admin.kegiatan.jenis-dokumen.store');
    Route::put('/kegiatan/jenis-dokumen/{id}', [JenisDokumenController::class, 'update'])->name('admin.kegiatan.jenis-dokumen.update');
    Route::delete('/kegiatan/jenis-dokumen/{id}', [JenisDokumenController::class, 'destroy'])->name('admin.kegiatan.jenis-dokumen.destroy');

    // Kategori Kegiatan
    Route::get('/kegiatan/kategori-kegiatan', [KategoriKegiatanController::class, 'index'])->name('admin.kegiatan.kategori-kegiatan');
    Route::get('/kegiatan/kategori-kegiatan/tambah', [KategoriKegiatanController::class, 'create'])->name('admin.kegiatan.kategori-kegiatan.create');
    Route::get('/kegiatan/kategori-kegiatan/{id}/edit', [KategoriKegiatanController::class, 'edit'])->name('admin.kegiatan.kategori-kegiatan.edit');
    Route::post('/kegiatan/kategori-kegiatan', [KategoriKegiatanController::class, 'store'])->name('admin.kegiatan.kategori-kegiatan.store');
    Route::put('/kegiatan/kategori-kegiatan/{id}', [KategoriKegiatanController::class, 'update'])->name('admin.kegiatan.kategori-kegiatan.update');
    Route::delete('/kegiatan/kategori-kegiatan/{id}', [KategoriKegiatanController::class, 'destroy'])->name('admin.kegiatan.kategori-kegiatan.destroy');

    // Kelompok Bidang
    Route::get('/kegiatan/kelompok-bidang', [KelompokBidangController::class, 'index'])->name('admin.kegiatan.kelompok-bidang');
    Route::post('/kegiatan/kelompok-bidang', [KelompokBidangController::class, 'store'])->name('admin.kegiatan.kelompok-bidang.store');
    Route::put('/kegiatan/kelompok-bidang/{id}', [KelompokBidangController::class, 'update'])->name('admin.kegiatan.kelompok-bidang.update');
    Route::delete('/kegiatan/kelompok-bidang/{id}', [KelompokBidangController::class, 'destroy'])->name('admin.kegiatan.kelompok-bidang.destroy');

    // Skim (admin + kaprodi: CRUD)
    Route::get('/kegiatan/skim', [SkimController::class, 'index'])->name('admin.kegiatan.skim');
    Route::post('/kegiatan/skim', [SkimController::class, 'store'])->name('admin.kegiatan.skim.store');
    Route::put('/kegiatan/skim/{id}', [SkimController::class, 'update'])->name('admin.kegiatan.skim.update');
    Route::delete('/kegiatan/skim/{id}', [SkimController::class, 'destroy'])->name('admin.kegiatan.skim.destroy');

    // Profil Prodi
    Route::get('/prodi/profil', [ProfilProdiController::class, 'index'])->name('admin.prodi.profil');
    Route::put('/prodi/profil', [ProfilProdiController::class, 'update'])->name('admin.prodi.profil.update');
    Route::post('/prodi/profil/logo', [ProfilProdiController::class, 'unggahLogo'])->name('admin.prodi.profil.logo.store');
    Route::delete('/prodi/profil/logo/{jenis}', [ProfilProdiController::class, 'hapusLogo'])
        ->where('jenis', 'utama|footer')
        ->name('admin.prodi.profil.logo.destroy');

    // Berita
    Route::get('berita', [AdminBeritaController::class, 'index'])->name('admin.berita');
    Route::get('berita/tambah', [AdminBeritaController::class, 'create'])->name('admin.berita.create');
    Route::post('berita/gambar-konten', [AdminBeritaController::class, 'uploadGambarKonten'])->name('admin.berita.upload-gambar');
    Route::get('berita/{id}', [AdminBeritaController::class, 'show'])->name('admin.berita.show');
    Route::post('berita', [AdminBeritaController::class, 'store'])->name('admin.berita.store');
    Route::get('berita/{id}/edit', [AdminBeritaController::class, 'edit'])->name('admin.berita.edit');
    Route::put('berita/{id}', [AdminBeritaController::class, 'update'])->name('admin.berita.update');
    Route::patch('berita/{id}/tinjau', [AdminBeritaController::class, 'tinjau'])->name('admin.berita.tinjau');
    Route::delete('berita/{id}', [AdminBeritaController::class, 'destroy'])->name('admin.berita.destroy');

    // Pengajuan
    Route::get('/pengajuan', [AdminPengajuanController::class, 'index'])->name('admin.pengajuan');
    Route::get('/pengajuan/{id}', [AdminTinjauPengajuanController::class, 'show'])->name('admin.pengajuan.show');
    Route::post('/pengajuan/{id}/keputusan', [AdminTinjauPengajuanController::class, 'keputusan'])->name('admin.pengajuan.keputusan');

    // Profil
    Route::get('/profil', [AdminProfilController::class, 'index'])->name('admin.profil');

    // Prestasi
    Route::get('/prestasi', [AdminPrestasiController::class, 'index'])->name('admin.prestasi');
    Route::get('/prestasi/tambah', [AdminPrestasiController::class, 'create'])->name('admin.prestasi.create');
    // Laporan Prestasi (PDF/Excel)
    Route::get('/prestasi/semua/laporan', [PrestasiLaporanController::class, 'semua'])->name('admin.prestasi.semua.laporan');
    Route::get('/prestasi/dosen/laporan', [PrestasiLaporanController::class, 'dosen'])->name('admin.prestasi.dosen.laporan');
    Route::get('/prestasi/mahasiswa/laporan', [PrestasiLaporanController::class, 'mahasiswa'])->name('admin.prestasi.mahasiswa.laporan');
    Route::post('/prestasi', [AdminPrestasiController::class, 'store'])->name('admin.prestasi.store');
    Route::get('/prestasi/{id}', [AdminPrestasiController::class, 'show'])->name('admin.prestasi.show');
    Route::get('/prestasi/{id}/edit', [AdminPrestasiController::class, 'edit'])->name('admin.prestasi.edit');
    Route::put('/prestasi/{id}', [AdminPrestasiController::class, 'update'])->name('admin.prestasi.update');
    Route::delete('/prestasi/{id}', [AdminPrestasiController::class, 'destroy'])->name('admin.prestasi.destroy');

    // Placeholder otomatis dari config/admin.php
    $sudahAda = [
        'admin.dashboard',
        'admin.kegiatan.index',
        'admin.kegiatan.create',
        'admin.kurikulum.trk',
        'admin.kurikulum.mata-kuliah',
        'admin.dosen',
        'admin.mahasiswa',
        'admin.kegiatan.mahasiswa',
        'admin.kegiatan.dosen',
        'admin.kegiatan.jenis-dokumen',
        'admin.kegiatan.kategori-kegiatan',
        'admin.kegiatan.kelompok-bidang',
        'admin.kegiatan.skim',
        'admin.prodi.profil',
        'admin.prodi.profil.update',
        'admin.prodi.profil.logo.store',
        'admin.prodi.profil.logo.destroy',
        'admin.pengajuan',
        'admin.berita',
        'admin.pengajuan.show',
        'admin.pengajuan.keputusan',
        'admin.akreditasi',
        'admin.profil',
        'admin.prestasi',
        'admin.prestasi.create',
        'admin.prestasi.show',
        'admin.prestasi.edit',
    ];

    foreach (config('admin.menu') as $m) {
        foreach ($m['children'] ?? [$m] as $c) {
            if (($c['route'] ?? null) && ! in_array($c['route'], $sudahAda)) {
                Route::get($c['url'], [LandingDashboardController::class, 'placeholder'])->name($c['route']);
            }
        }
    }
});

// ---------- Kaprodi (dijaga KaprodiOnly) ----------
Route::prefix('kaprodi')->middleware(KaprodiOnly::class)->group(function () {
    Route::get('/dashboard', [KaprodiDashboardController::class, 'index'])->name('kaprodi.dashboard');
    Route::get('/notifikasi', [NotifikasiStafController::class, 'index'])->name('kaprodi.notifikasi');
    Route::post('/notifikasi/baca-semua', [NotifikasiStafController::class, 'bacaSemua'])->name('kaprodi.notifikasi.baca-semua');
    Route::post('/notifikasi/{id}/baca', [NotifikasiStafController::class, 'baca'])->where('id', '[A-Za-z0-9_-]+')->name('kaprodi.notifikasi.baca');
    Route::post('/keluar', [AdminAuthController::class, 'logout'])->name('kaprodi.logout');

    Route::get('/kurikulum/trk', [KaprodiKurikulumController::class, 'index'])->name('kaprodi.kurikulum.trk');
    Route::post('/kurikulum/trk', [KaprodiKurikulumController::class, 'store'])->name('kaprodi.kurikulum.store');
    Route::put('/kurikulum/trk/{id}', [KaprodiKurikulumController::class, 'update'])->name('kaprodi.kurikulum.update');
    Route::patch('/kurikulum/trk/{id}/status', [KaprodiKurikulumController::class, 'status'])->name('kaprodi.kurikulum.status');
    Route::delete('/kurikulum/trk/{id}', [KaprodiKurikulumController::class, 'destroy'])->name('kaprodi.kurikulum.destroy');

    Route::get('/kurikulum/mata-kuliah', [KaprodiMataKuliahController::class, 'index'])->name('kaprodi.kurikulum.mata-kuliah');
    Route::get('/kurikulum/mata-kuliah/tambah', [KaprodiMataKuliahController::class, 'create'])->name('kaprodi.kurikulum.mata-kuliah.create');
    Route::post('/kurikulum/mata-kuliah', [KaprodiMataKuliahController::class, 'store'])->name('kaprodi.kurikulum.mata-kuliah.store');
    Route::get('/kurikulum/mata-kuliah/{id}/edit', [KaprodiMataKuliahController::class, 'edit'])->whereNumber('id')->name('kaprodi.kurikulum.mata-kuliah.edit');
    Route::put('/kurikulum/mata-kuliah/{id}', [KaprodiMataKuliahController::class, 'update'])->whereNumber('id')->name('kaprodi.kurikulum.mata-kuliah.update');
    Route::delete('/kurikulum/mata-kuliah/{id}', [KaprodiMataKuliahController::class, 'destroy'])->whereNumber('id')->name('kaprodi.kurikulum.mata-kuliah.destroy');
    // Tahun Akademik (Kaprodi: Read-Only, hanya GET)
    Route::get('/kurikulum/tahun-akademik', [TahunAkademikController::class, 'index'])->name('kaprodi.kurikulum.tahun-akademik');

    Route::get('/dosen', [KaprodiDosenController::class, 'index'])->name('kaprodi.dosen');
    Route::get('/mahasiswa', [KaprodiMahasiswaController::class, 'index'])->name('kaprodi.mahasiswa');
    Route::get('/dosen/{id}', [KaprodiDosenController::class, 'show'])->name('kaprodi.dosen.show');
    Route::get('/mahasiswa/{id}', [KaprodiMahasiswaController::class, 'show'])->name('kaprodi.mahasiswa.show');

    // Kegiatan gabungan (Dosen | Mahasiswa | Prodi). URL lama dialihkan ke tab yang sesuai.
    Route::get('/kegiatan', [KaprodiKegiatanController::class, 'index'])->name('kaprodi.kegiatan.index');
    Route::get('/kegiatan/tambah', [AdminKegiatanTambahController::class, 'create'])->name('kaprodi.kegiatan.create');
    Route::get('/kegiatan/tambah/kategori/{id}', [AdminKegiatanTambahController::class, 'kategori'])->whereNumber('id')->name('kaprodi.kegiatan.create.kategori');
    Route::post('/kegiatan', [AdminKegiatanTambahController::class, 'store'])->name('kaprodi.kegiatan.store');
    Route::redirect('/kegiatan/dosen', '/kaprodi/kegiatan?tab=dosen')->name('kaprodi.kegiatan.dosen');
    Route::redirect('/kegiatan/mahasiswa', '/kaprodi/kegiatan?tab=mahasiswa')->name('kaprodi.kegiatan.mahasiswa');

    Route::get('/kegiatan/dosen/laporan', [KegiatanLaporanController::class, 'dosen'])->name('kaprodi.kegiatan.dosen.laporan');
    Route::get('/kegiatan/mahasiswa/laporan', [KegiatanLaporanController::class, 'mahasiswa'])->name('kaprodi.kegiatan.mahasiswa.laporan');
    Route::get('/kegiatan/prodi/laporan', [KegiatanLaporanController::class, 'prodi'])->name('kaprodi.kegiatan.prodi.laporan');
    Route::get('/kegiatan/semua/laporan', [KegiatanLaporanController::class, 'semua'])->name('kaprodi.kegiatan.semua.laporan');
    Route::get('/kegiatan/mahasiswa/{id}', [KaprodiKegiatanMahasiswaDetailShowController::class, 'show'])->name('kaprodi.kegiatan.mahasiswa.show');

    Route::get('berita', [KaprodiBeritaController::class, 'index'])->name('kaprodi.berita');
    Route::get('berita/tambah', [KaprodiBeritaController::class, 'create'])->name('kaprodi.berita.create');
    Route::post('berita/gambar-konten', [KaprodiBeritaController::class, 'uploadGambarKonten'])->name('kaprodi.berita.upload-gambar');
    Route::get('berita/{id}', [KaprodiBeritaController::class, 'show'])->name('kaprodi.berita.show');
    Route::post('berita', [KaprodiBeritaController::class, 'store'])->name('kaprodi.berita.store');
    Route::get('berita/{id}/edit', [KaprodiBeritaController::class, 'edit'])->name('kaprodi.berita.edit');
    Route::put('berita/{id}', [KaprodiBeritaController::class, 'update'])->name('kaprodi.berita.update');
    Route::delete('berita/{id}', [KaprodiBeritaController::class, 'destroy'])->name('kaprodi.berita.destroy');

    Route::get('/kegiatan/kategori-kegiatan', [KaprodiKategoriKegiatanController::class, 'index'])->name('kaprodi.kegiatan.kategori-kegiatan');
    Route::get('/kegiatan/jenis-dokumen', [KaprodiJenisDokumenController::class, 'index'])->name('kaprodi.kegiatan.jenis-dokumen');

    // Kelompok Bidang
    Route::get('/kegiatan/kelompok-bidang', [KelompokBidangController::class, 'index'])->name('kaprodi.kegiatan.kelompok-bidang');
    Route::post('/kegiatan/kelompok-bidang', [KelompokBidangController::class, 'store'])->name('kaprodi.kegiatan.kelompok-bidang.store');
    Route::put('/kegiatan/kelompok-bidang/{id}', [KelompokBidangController::class, 'update'])->name('kaprodi.kegiatan.kelompok-bidang.update');
    Route::delete('/kegiatan/kelompok-bidang/{id}', [KelompokBidangController::class, 'destroy'])->name('kaprodi.kegiatan.kelompok-bidang.destroy');

    // Skim (admin + kaprodi: CRUD)
    Route::get('/kegiatan/skim', [SkimController::class, 'index'])->name('kaprodi.kegiatan.skim');
    Route::post('/kegiatan/skim', [SkimController::class, 'store'])->name('kaprodi.kegiatan.skim.store');
    Route::put('/kegiatan/skim/{id}', [SkimController::class, 'update'])->name('kaprodi.kegiatan.skim.update');
    Route::delete('/kegiatan/skim/{id}', [SkimController::class, 'destroy'])->name('kaprodi.kegiatan.skim.destroy');

    // Profil Prodi
    Route::get('/prodi/profil', [ProfilProdiController::class, 'index'])->name('kaprodi.prodi.profil');
    Route::put('/prodi/profil', [ProfilProdiController::class, 'update'])->name('kaprodi.prodi.profil.update');
    Route::post('/prodi/profil/logo', [ProfilProdiController::class, 'unggahLogo'])->name('kaprodi.prodi.profil.logo.store');
    Route::delete('/prodi/profil/logo/{jenis}', [ProfilProdiController::class, 'hapusLogo'])
        ->where('jenis', 'utama|footer')
        ->name('kaprodi.prodi.profil.logo.destroy');

    Route::get('/kegiatan/dosen/{id}', [KaprodiKegiatanDosenDetailShowController::class, 'show'])->name('kaprodi.kegiatan.dosen.show');
    Route::delete('/kegiatan/dosen/{id}', [KegiatanHapusController::class, 'dosen'])->name('kaprodi.kegiatan.dosen.destroy');
    Route::delete('/kegiatan/mahasiswa/{id}', [KegiatanHapusController::class, 'mahasiswa'])->name('kaprodi.kegiatan.mahasiswa.destroy');

    Route::get('/pengajuan', [KaprodiPengajuanController::class, 'index'])->name('kaprodi.pengajuan');
    Route::get('/pengajuan/{id}', [KaprodiPengajuanController::class, 'show'])->name('kaprodi.pengajuan.show');
    Route::post('/pengajuan/{id}/keputusan', [KaprodiPengajuanController::class, 'keputusan'])->name('kaprodi.pengajuan.keputusan');

    Route::get('/profil', [KaprodiProfilController::class, 'index'])->name('kaprodi.profil');

    Route::get('/prestasi', [KaprodiPrestasiController::class, 'index'])->name('kaprodi.prestasi');
    // Laporan Prestasi (PDF/Excel)
    Route::get('/prestasi/semua/laporan', [PrestasiLaporanController::class, 'semua'])->name('kaprodi.prestasi.semua.laporan');
    Route::get('/prestasi/dosen/laporan', [PrestasiLaporanController::class, 'dosen'])->name('kaprodi.prestasi.dosen.laporan');
    Route::get('/prestasi/mahasiswa/laporan', [PrestasiLaporanController::class, 'mahasiswa'])->name('kaprodi.prestasi.mahasiswa.laporan');
    Route::get('/prestasi/{id}', [KaprodiPrestasiController::class, 'show'])->name('kaprodi.prestasi.show');

    Route::get('/pengajuan/{id}/tanda-tangan', [KaprodiPengajuanController::class, 'ttdSurat'])->name('kaprodi.pengajuan.ttd');
    Route::get('/tanda-tangan/gambar', [KaprodiTandaTanganController::class, 'gambar'])->name('kaprodi.tanda-tangan.gambar');
    Route::post('/tanda-tangan', [KaprodiTandaTanganController::class, 'store'])->name('kaprodi.tanda-tangan.store');

    $sudahAdaKaprodi = [
        'kaprodi.dashboard',
        'kaprodi.kurikulum.trk',
        'kaprodi.kurikulum.mata-kuliah',
        'kaprodi.kurikulum.tahun-akademik',
        'kaprodi.dosen',
        'kaprodi.mahasiswa',
        'kaprodi.kegiatan.index',
        'kaprodi.kegiatan.create',
        'kaprodi.kegiatan.dosen',
        'kaprodi.kegiatan.mahasiswa',
        'kaprodi.kegiatan.kategori-kegiatan',
        'kaprodi.kegiatan.jenis-dokumen',
        'kaprodi.kegiatan.kelompok-bidang',
        'kaprodi.kegiatan.skim',
        'kaprodi.prodi.profil',
        'kaprodi.prodi.profil.update',
        'kaprodi.prodi.profil.logo.store',
        'kaprodi.prodi.profil.logo.destroy',
        'kaprodi.berita',
        'kaprodi.pengajuan',
        'kaprodi.pengajuan.show',
        'kaprodi.prestasi',
        'kaprodi.profil',
    ];

    foreach (config('kaprodi.menu') as $m) {
        foreach ($m['children'] ?? [$m] as $c) {
            if (($c['route'] ?? null) && ! in_array($c['route'], $sudahAdaKaprodi)) {
                Route::get($c['url'], [KaprodiDashboardController::class, 'placeholder'])->name($c['route']);
            }
        }
    }
});
