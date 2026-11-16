<?php

use App\BackendKel1\JenisDokumen\JenisDokumenController;
use Illuminate\Support\Facades\Route;

Route::get('jenis-dokumen', [JenisDokumenController::class, 'index'])->name('api.jenis-dokumen.index');
Route::post('jenis-dokumen', [JenisDokumenController::class, 'store'])->name('api.jenis-dokumen.store');
Route::get('jenis-dokumen/{id}', [JenisDokumenController::class, 'show'])->whereNumber('id')->name('api.jenis-dokumen.show');
Route::put('jenis-dokumen/{id}', [JenisDokumenController::class, 'update'])->whereNumber('id')->name('api.jenis-dokumen.update');
Route::delete('jenis-dokumen/{id}', [JenisDokumenController::class, 'destroy'])->whereNumber('id')->name('api.jenis-dokumen.destroy');
