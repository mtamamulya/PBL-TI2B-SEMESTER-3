<?php

use App\BackendKel1\Autentikasi\PeranApi;
use App\BackendKel1\MataKuliah\MataKuliahController;
use Illuminate\Support\Facades\Route;

Route::get('mata-kuliah', [MataKuliahController::class, 'index'])->name('api.mata-kuliah.index');
Route::get('mata-kuliah/{id}', [MataKuliahController::class, 'show'])->whereNumber('id')->name('api.mata-kuliah.show');

// Tulis: token Sanctum dan peran admin (1) atau kaprodi (2).
Route::middleware(['auth:sanctum', PeranApi::class.':1,2'])->group(function () {
    Route::post('kurikulum/{id}/mata-kuliah', [MataKuliahController::class, 'store'])->whereNumber('id')->name('api.mata-kuliah.store');
    Route::put('mata-kuliah/{id}', [MataKuliahController::class, 'update'])->whereNumber('id')->name('api.mata-kuliah.update');
    Route::delete('mata-kuliah/{id}', [MataKuliahController::class, 'destroy'])->whereNumber('id')->name('api.mata-kuliah.destroy');
});
