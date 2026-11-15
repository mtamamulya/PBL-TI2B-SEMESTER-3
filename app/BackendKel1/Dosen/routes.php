<?php

use App\BackendKel1\Dosen\DosenController;
use Illuminate\Support\Facades\Route;

Route::get('/dosen', [DosenController::class, 'index']);
Route::get('/dosen/{id}', [DosenController::class, 'show'])->whereNumber('id');
