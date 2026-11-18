<?php

use App\BackendKel1\Autentikasi\PeranApi;
use App\BackendKel1\KegiatanAdmin\KegiatanAdminController;
use Illuminate\Support\Facades\Route;

// Admin (role 1) dan kaprodi (role 2). Hapus kegiatan hanya admin (dicek di controller).
Route::middleware(['auth:sanctum', PeranApi::class.':1,2'])
    ->prefix('admin/kegiatan')
    ->name('api.admin.kegiatan.')
    ->group(function () {
        Route::get('/ringkasan', [KegiatanAdminController::class, 'ringkasan'])->name('ringkasan');
        Route::get('/', [KegiatanAdminController::class, 'index'])->name('index');
        Route::get('/{id}', [KegiatanAdminController::class, 'show'])->whereNumber('id')->name('show');
        Route::delete('/{id}', [KegiatanAdminController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });
