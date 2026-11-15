<?php

use App\BackendKel1\MataKuliah\MataKuliahController;
use Illuminate\Support\Facades\Route;

Route::get('mata-kuliah', [MataKuliahController::class, 'index'])->name('api.mata-kuliah.index');
Route::get('mata-kuliah/{id}', [MataKuliahController::class, 'show'])->whereNumber('id')->name('api.mata-kuliah.show');
