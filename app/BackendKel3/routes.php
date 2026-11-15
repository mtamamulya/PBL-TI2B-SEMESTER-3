<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
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
