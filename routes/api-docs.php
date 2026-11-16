<?php

use App\Http\Controllers\ApiDokumentasiController;
use Illuminate\Support\Facades\Route;

Route::get('/api/docs', [ApiDokumentasiController::class, 'ui'])->name('api.docs');
Route::get('/api/docs/openapi.yaml', [ApiDokumentasiController::class, 'spec'])->name('api.docs.spec');
