<?php

use App\BackendKel1\ImportExcel\ImportMataKuliahController;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\KaprodiOnly;
use Illuminate\Support\Facades\Route;

/*
 * Route WEB (bukan API). Dimuat dari routes/web.php:
 *     require app_path('BackendKel1/ImportExcel/routes.php');
 * Admin dan kaprodi sama-sama boleh mengimpor mata kuliah.
 */
foreach (['admin' => AdminOnly::class, 'kaprodi' => KaprodiOnly::class] as $peran => $penjaga) {
    Route::prefix($peran.'/kurikulum/mata-kuliah/import')->middleware($penjaga)->group(function () use ($peran) {
        $nama = $peran.'.kurikulum.mata-kuliah.import';

        Route::get('/', [ImportMataKuliahController::class, 'form'])->name($nama);
        Route::get('template', [ImportMataKuliahController::class, 'template'])->name($nama.'.template');
        Route::post('pratinjau', [ImportMataKuliahController::class, 'pratinjau'])->name($nama.'.pratinjau');
        Route::post('simpan', [ImportMataKuliahController::class, 'simpan'])->name($nama.'.simpan');
        Route::post('batal', [ImportMataKuliahController::class, 'batal'])->name($nama.'.batal');
    });
}
