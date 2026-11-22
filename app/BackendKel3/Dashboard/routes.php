<?php

use App\BackendKel3\Dashboard\DashboardApiController;
use App\BackendKel3\Dashboard\NotifikasiController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', [DashboardApiController::class, 'index'])->name('dashboard');
Route::get('notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi');
