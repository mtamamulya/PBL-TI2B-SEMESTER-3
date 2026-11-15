<?php

use App\BackendKel1\Kurikulum\KurikulumController;
use Illuminate\Support\Facades\Route;

Route::get('kurikulum', [KurikulumController::class, 'index'])->name('api.kurikulum.index');
Route::get('kurikulum/{id}', [KurikulumController::class, 'show'])->whereNumber('id')->name('api.kurikulum.show');
Route::get('kurikulum/{id}/mata-kuliah', [KurikulumController::class, 'mataKuliah'])->whereNumber('id')->name('api.kurikulum.mata-kuliah');
