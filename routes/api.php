<?php

declare(strict_types=1);

use App\Http\Controllers\DiagnoseController;
use Illuminate\Support\Facades\Route;

Route::post('/diagnose', DiagnoseController::class);
