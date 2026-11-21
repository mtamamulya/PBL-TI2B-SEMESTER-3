<?php

use App\BackendKel1\Autentikasi\PeranApi;
use App\BackendKel1\Kurikulum\KurikulumController;
use Illuminate\Support\Facades\Route;

Route::get('kurikulum', [KurikulumController::class, 'index'])->name('api.kurikulum.index');
Route::get('kurikulum/{id}', [KurikulumController::class, 'show'])->whereNumber('id')->name('api.kurikulum.show');
Route::get('kurikulum/{id}/mata-kuliah', [KurikulumController::class, 'mataKuliah'])->whereNumber('id')->name('api.kurikulum.mata-kuliah');

// Tulis: token Sanctum dan peran admin (1) atau kaprodi (2).
Route::middleware(['auth:sanctum', PeranApi::class.':1,2'])->group(function () {
    Route::post('kurikulum', [KurikulumController::class, 'store'])->name('api.kurikulum.store');
    Route::put('kurikulum/{id}', [KurikulumController::class, 'update'])->whereNumber('id')->name('api.kurikulum.update');
    Route::delete('kurikulum/{id}', [KurikulumController::class, 'destroy'])->whereNumber('id')->name('api.kurikulum.destroy');
    Route::patch('kurikulum/{id}/status', [KurikulumController::class, 'status'])->whereNumber('id')->name('api.kurikulum.status');
});
