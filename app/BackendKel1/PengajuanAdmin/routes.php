<?php

use App\BackendKel1\PengajuanAdmin\PengajuanAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('admin/pengajuan')->group(function () {
    Route::get('/', [PengajuanAdminController::class, 'index'])->name('api.admin.pengajuan.index');
    Route::get('/{id}', [PengajuanAdminController::class, 'show'])->whereNumber('id')->name('api.admin.pengajuan.show');
    Route::post('/{id}/keputusan', [PengajuanAdminController::class, 'keputusan'])->whereNumber('id')->name('api.admin.pengajuan.keputusan');
});
