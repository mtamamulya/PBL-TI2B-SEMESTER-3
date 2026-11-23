<?php

use App\BackendKel3\Autentikasi\PastikanDosenAktif;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::middleware([EnsureFrontendRequestsAreStateful::class, 'auth:sanctum', PastikanDosenAktif::class])->group(function () {
    require __DIR__.'/Pengajaran/routes.php';

    Route::prefix('dosen')->name('api.dosen.')->group(function () {
        require __DIR__.'/Profil/routes.php';
        require __DIR__.'/Penelitian/routes.php';
        require __DIR__.'/Pengabdian/routes.php';
        require __DIR__.'/Pengajuan/routes.php';
        require __DIR__.'/DokumenSurat/routes.php';
        require __DIR__.'/PrestasiBerita/routes.php';
        require __DIR__.'/Dashboard/routes.php';
    });
});
