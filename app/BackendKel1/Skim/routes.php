<?php

use App\BackendKel1\Autentikasi\PeranApi;
use App\BackendKel1\Skim\SkimController;
use Illuminate\Support\Facades\Route;

// Baca: semua pengguna login. Tulis: admin (1) atau kaprodi (2).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('skim', [SkimController::class, 'index'])->name('api.skim.index');
    Route::get('skim/{id}', [SkimController::class, 'show'])->whereNumber('id')->name('api.skim.show');
});

Route::middleware(['auth:sanctum', PeranApi::class.':1,2'])->group(function () {
    Route::post('skim', [SkimController::class, 'store'])->name('api.skim.store');
    Route::put('skim/{id}', [SkimController::class, 'update'])->whereNumber('id')->name('api.skim.update');
    Route::delete('skim/{id}', [SkimController::class, 'destroy'])->whereNumber('id')->name('api.skim.destroy');
});
