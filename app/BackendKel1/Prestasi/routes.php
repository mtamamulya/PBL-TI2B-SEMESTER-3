<?php

use App\BackendKel1\Prestasi\AdminPrestasiApiController;
use App\BackendKel1\Prestasi\PrestasiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->prefix('admin/prestasi')
    ->group(function () {
        Route::get('/', [AdminPrestasiApiController::class, 'index'])
            ->name('api.admin.prestasi.index');

        Route::get('/opsi', [AdminPrestasiApiController::class, 'opsi'])
            ->name('api.admin.prestasi.opsi');

        Route::post('/', [AdminPrestasiApiController::class, 'store'])
            ->name('api.admin.prestasi.store');

        Route::get('/{id}', [AdminPrestasiApiController::class, 'show'])
            ->whereNumber('id')
            ->name('api.admin.prestasi.show');

        Route::put('/{id}', [AdminPrestasiApiController::class, 'update'])
            ->whereNumber('id')
            ->name('api.admin.prestasi.update');

        Route::delete('/{id}', [AdminPrestasiApiController::class, 'destroy'])
            ->whereNumber('id')
            ->name('api.admin.prestasi.destroy');
    });

Route::get('/prestasi', [PrestasiController::class, 'index']);
Route::get('/prestasi/{id}', [PrestasiController::class, 'show'])->whereNumber('id');
