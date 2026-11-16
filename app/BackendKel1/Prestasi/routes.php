<?php

use App\BackendKel1\Prestasi\PrestasiController;
use Illuminate\Support\Facades\Route;

Route::get('/prestasi', [PrestasiController::class, 'index']);
Route::get('/prestasi/{id}', [PrestasiController::class, 'show'])->whereNumber('id');
