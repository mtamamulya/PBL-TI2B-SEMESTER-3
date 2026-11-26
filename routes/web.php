<?php

use App\BackendKel2\Mahasiswa\ProfilMahasiswaController;
use App\BackendKel3\Autentikasi\PastikanDosenAktif;
use App\BackendKel3\Dashboard\DosenDashboardController;
use App\BackendKel3\Dashboard\NotifikasiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DosenPengabdianController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\MahasiswaController;
use App\Http\Controllers\PengajuanController;
use App\Http\Middleware\MahasiswaOnly;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Landing Page TRK
Route::get('/', [HomeController::class, 'index'])->name('home');

$redirectByRole = static function (string $dosen, string $mahasiswa) {
    return static function (Request $request, ?string $id = null) use ($dosen, $mahasiswa) {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        $role = strtolower($request->user()->role?->nama_role ?? '');
        if (in_array($role, ['admin', 'kaprodi'], true)) {
            return redirect()->route($role.'.dashboard');
        }
        abort_unless(in_array($role, ['dosen', 'mahasiswa'], true), 403);
        $route = $role === 'dosen' ? $dosen : $mahasiswa;

        return redirect()->route($route, $id === null ? [] : ['id' => $id]);
    };
};

Route::get('/dashboard', $redirectByRole('dosen.dashboard', 'mahasiswa.dashboard'))->name('dashboard');
Route::middleware(['auth', PastikanDosenAktif::class])->group(function () {
    Route::get('/dosen/dashboard', [DosenDashboardController::class, 'index'])->name('dosen.dashboard');

    Route::get('/dosen/kegiatan', [KegiatanController::class, 'index'])->name('dosen.kegiatan.index');
    Route::view('/dosen/kegiatan/create', 'dosen.kegiatan.create')->name('dosen.kegiatan.create');
    Route::get('/dosen/kegiatan/{id}', [KegiatanController::class, 'show'])->whereNumber('id')->name('dosen.kegiatan.show');
    Route::get('/dosen/kegiatan/{id}/edit', [KegiatanController::class, 'edit'])->whereNumber('id')->name('dosen.kegiatan.edit');
    Route::put('/dosen/kegiatan/{id}', [KegiatanController::class, 'update'])->whereNumber('id')->name('dosen.kegiatan.update');
    Route::put('/dosen/kegiatan/{id}/dokumen/{dokumenId}', [KegiatanController::class, 'gantiLampiran'])->whereNumber(['id', 'dokumenId'])->name('dosen.kegiatan.dokumen.update');

    Route::view('/prestasi', 'dosen.prestasi.index')->name('prestasi.index');
    Route::view('/prestasi/create', 'dosen.prestasi.create')->name('prestasi.create');
    Route::view('/prestasi/{id}', 'dosen.prestasi.show')->whereNumber('id')->name('prestasi.show');
    Route::view('/prestasi/{id}/edit', 'dosen.prestasi.edit')->whereNumber('id')->name('prestasi.edit');
    Route::view('/verifikasi/{id}', 'pengajuan.show', ['asal' => 'dosen'])->whereNumber('id')->name('verifikasi.show');

    Route::view('/profil', 'dosen.profil.show')->name('profil');
    Route::get('/notifikasi', [NotifikasiController::class, 'web'])->name('notifikasi');
    Route::redirect('/dosen/notifikasi', '/notifikasi');
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'tandaiSemuaDibaca'])->name('notifikasi.baca-semua');
    Route::post('/notifikasi/{idTimeline}/baca', [NotifikasiController::class, 'tandaiDibaca'])->whereNumber('idTimeline')->name('notifikasi.baca');

    Route::view('/penelitian', 'dosen.penelitian.index')->name('penelitian.index');
    Route::view('/penelitian/create', 'dosen.penelitian.create')->name('penelitian.create');
    Route::view('/penelitian/{id}', 'dosen.penelitian.show')->whereNumber('id')->name('penelitian.show');
    Route::view('/penelitian/{id}/edit', 'dosen.penelitian.edit')->whereNumber('id')->name('penelitian.edit');

    Route::get('/pengabdian', [DosenPengabdianController::class, 'index'])->name('pengabdian.index');
    Route::get('/pengabdian/create', [DosenPengabdianController::class, 'create'])->name('pengabdian.create');
    Route::post('/pengabdian', [DosenPengabdianController::class, 'store'])->name('pengabdian.store');
    Route::get('/pengabdian/{id}', [DosenPengabdianController::class, 'show'])->whereNumber('id')->name('pengabdian.show');
    Route::get('/pengabdian/{id}/edit', [DosenPengabdianController::class, 'edit'])->whereNumber('id')->name('pengabdian.edit');
    Route::put('/pengabdian/{id}', [DosenPengabdianController::class, 'update'])->whereNumber('id')->name('pengabdian.update');

    // KODE BARU (Menggunakan Route::view karena data diambil via API):
    Route::view('/pengajaran', 'dosen.pengajaran.index')->name('pengajaran.index');
    Route::view('/pengajaran/create', 'dosen.pengajaran.create')->name('pengajaran.create');
    Route::view('/pengajaran/{id}', 'dosen.pengajaran.show')->whereNumber('id')->name('pengajaran.show');
    Route::view('/pengajaran/{id}/edit', 'dosen.pengajaran.edit')->whereNumber('id')->name('pengajaran.edit');

    Route::get('/pengajuan/dosen/baru', [PengajuanController::class, 'createDosen'])->name('pengajuan.dosen.create');
    Route::post('/pengajuan/dosen', [PengajuanController::class, 'storeDosen'])->name('pengajuan.dosen.store');
    Route::view('/pengajuan/dosen', 'dosen.pengajuan.dosen')->name('pengajuan.dosen');
    Route::view('/pengajuan/dosen/{id}', 'dosen.pengajuan.show', ['asal' => 'dosen'])->whereNumber('id')->name('pengajuan.dosen.show');
    Route::view('/pengajuan/mahasiswa', 'pengajuan.mahasiswa')->name('pengajuan.mahasiswa');
    Route::view('/pengajuan/mahasiswa/{id}', 'dosen.pengajuan.show', ['asal' => 'mahasiswa'])->whereNumber('id')->name('pengajuan.mahasiswa.show');

    Route::view('/berita', 'dosen.berita.index')->name('berita.index');
    Route::view('/berita/create', 'dosen.berita.create')->name('berita.create');
    Route::view('/berita/{id}', 'dosen.berita.show')->whereNumber('id')->name('berita.show');
    Route::view('/berita/{id}/edit', 'dosen.berita.edit')->whereNumber('id')->name('berita.edit');
});

Route::get('/kegiatan', $redirectByRole('dosen.kegiatan.index', 'kegiatan.index'));
Route::get('/kegiatan/create', $redirectByRole('dosen.kegiatan.create', 'kegiatan.create'));
Route::middleware(MahasiswaOnly::class)->group(function () {
    Route::get('/mahasiswa/kegiatan', [KegiatanController::class, 'index'])->name('kegiatan.index');
    Route::get('/mahasiswa/kegiatan/create', [PengajuanController::class, 'createActivity'])->name('kegiatan.create');
    Route::post('/mahasiswa/kegiatan', [PengajuanController::class, 'storeActivity'])->name('kegiatan.store');

    // Mengirimkan semua variabel dummy yang dibutuhkan kegiatan.blade.php
    Route::view('/mahasiswa/kegiatan/{id}/edit', 'kegiatan', [
        'allActivities' => [],
        'categories' => [],
        'activities' => [],
    ])->whereNumber('id')->name('kegiatan.edit');

    Route::get('/mahasiswa/kegiatan/{id}', [KegiatanController::class, 'show'])->whereNumber('id')->name('kegiatan.show');
    Route::get('/mahasiswa/pengajuan/{id}/surat', [PengajuanController::class, 'surat'])
        ->whereNumber('id')
        ->name('pengajuan.surat');
    Route::get('/mahasiswa/pengajuan/{id}/surat/ttd', [PengajuanController::class, 'ttdSurat'])
        ->whereNumber('id')
        ->name('pengajuan.surat.ttd');
});

Route::view('/kegiatan-page', 'kegiatan')->name('kegiatan');
Route::view('/header', 'header')->name('header');
Route::view('/navbar', 'navbar')->name('navbar');

Route::get('/mahasiswa/pengajuan/dashboard', [PengajuanController::class, 'dashboard'])
    ->middleware(MahasiswaOnly::class)
    ->name('pengajuan.dashboard');
Route::redirect('/mahasiswa', '/mahasiswa/mahasiswa/dashboard');
Route::redirect('/mahasiswa/akademik', '/mahasiswa/mahasiswa/dashboard');
Route::get('/mahasiswa/mahasiswa/dashboard', [MahasiswaController::class, 'dashboard'])
    ->middleware(MahasiswaOnly::class)
    ->name('mahasiswa.index');

Route::get('/mahasiswa/dashboard', [DashboardController::class, 'index'])
    ->middleware(MahasiswaOnly::class)
    ->name('mahasiswa.dashboard');

// Route Mahasiswa dari Backend Kelompok 2
Route::middleware([MahasiswaOnly::class])->group(function () {
    Route::get('/mahasiswa/dashboard/data', [App\BackendKel2\Dashboard\DashboardController::class, 'mahasiswa'])->name('mahasiswa.dashboard.data');
});

Route::middleware(MahasiswaOnly::class)->group(function () {
    Route::get('/mahasiswa/mata-kuliah', [MahasiswaController::class, 'mataKuliah'])->defaults('tab', 'berjalan')->name('mahasiswa.mata-kuliah');
    Route::get('/mahasiswa/mata-kuliah/semester-berjalan', [MahasiswaController::class, 'mataKuliah'])->defaults('tab', 'berjalan')->name('mahasiswa.mata-kuliah.berjalan');
    Route::get('/mahasiswa/mata-kuliah/katalog', [MahasiswaController::class, 'mataKuliah'])->defaults('tab', 'katalog')->name('mahasiswa.mata-kuliah.katalog');
    Route::get('/mahasiswa/mata-kuliah/riwayat', [MahasiswaController::class, 'mataKuliah'])->defaults('tab', 'semua')->name('mahasiswa.mata-kuliah.riwayat');
    Route::get('/mahasiswa/profil', [ProfilMahasiswaController::class, 'show'])->name('mahasiswa.profil');
    Route::prefix('/mahasiswa/profil/sandi')->name('mahasiswa.profil.sandi.')->group(function () {
        Route::post('/kirim-otp', [ProfilMahasiswaController::class, 'kirimOtp'])
            ->middleware('throttle:5,1')
            ->name('kirim-otp');
        Route::post('/verifikasi', [ProfilMahasiswaController::class, 'verifikasi'])
            ->middleware('throttle:10,1')
            ->name('verifikasi');
        Route::post('/simpan', [ProfilMahasiswaController::class, 'simpan'])
            ->middleware('throttle:10,1')
            ->name('simpan');
    });
    Route::prefix('mahasiswa')->name('mahasiswa.')->group(function () {
        Route::get('/prestasi', [MahasiswaController::class, 'prestasi'])
            ->name('prestasi');

        // Letakkan route statis sebelum route parameter prestasi.
        Route::get('/prestasi/tambah', [MahasiswaController::class, 'prestasiTambah'])
            ->name('prestasi.tambah');
        Route::post('/prestasi', [MahasiswaController::class, 'prestasiSimpan'])
            ->name('prestasi.simpan');

        Route::get('/prestasi/{prestasi}/edit', [MahasiswaController::class, 'prestasiEdit'])
            ->name('prestasi.edit');
        Route::put('/prestasi/{prestasi}', [MahasiswaController::class, 'prestasiUpdate'])
            ->name('prestasi.update');
        Route::delete('/prestasi/{prestasi}', [MahasiswaController::class, 'prestasiHapus'])
            ->name('prestasi.hapus');
    });
});

require __DIR__.'/pengajuan.php';

Route::get('/pengajuan', $redirectByRole('pengajuan.dosen', 'pengajuan.index'));
Route::get('/pengajuan/baru', $redirectByRole('pengajuan.dosen.create', 'pengajuan.create'));
Route::get('/pengajuan/dashboard', $redirectByRole('dosen.dashboard', 'pengajuan.dashboard'));
Route::get('/pengajuan/berita', $redirectByRole('berita.index', 'pengajuan.berita.index'));
Route::get('/pengajuan/berita/baru', $redirectByRole('berita.create', 'pengajuan.berita.create'));
Route::get('/pengajuan/berita/{id}/edit', $redirectByRole('berita.edit', 'pengajuan.berita.edit'))->whereNumber('id');
Route::get('/pengajuan/berita/{id}', $redirectByRole('berita.show', 'pengajuan.berita.show'))->whereNumber('id');
Route::get('/pengajuan/{id}/edit', $redirectByRole('pengajuan.dosen.show', 'pengajuan.edit'))->whereNumber('id');
Route::get('/pengajuan/{id}', $redirectByRole('pengajuan.dosen.show', 'pengajuan.show'))->whereNumber('id');
Route::get('/kegiatan/{id}/edit', $redirectByRole('dosen.kegiatan.edit', 'kegiatan.edit'))->whereNumber('id');
Route::get('/kegiatan/{id}', $redirectByRole('dosen.kegiatan.show', 'kegiatan.show'))->whereNumber('id');

require __DIR__.'/admin-kaprodi.php';
require __DIR__.'/api-docs.php';
require app_path('BackendKel1/ImportExcel/routes.php');
