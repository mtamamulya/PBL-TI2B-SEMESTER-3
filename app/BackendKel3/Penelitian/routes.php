<?php

use App\BackendKel3\Penelitian\DosenPenelitianController;
use Illuminate\Support\Facades\Route;

Route::apiResource('penelitian', DosenPenelitianController::class)
    ->only(['index', 'store', 'show', 'update', 'destroy'])
    ->names('api.penelitian');
