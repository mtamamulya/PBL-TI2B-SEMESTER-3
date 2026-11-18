<?php

use App\BackendKel1\Berita\BeritaController;
use Illuminate\Support\Facades\Route;

Route::get('/berita', [BeritaController::class, 'index']);
