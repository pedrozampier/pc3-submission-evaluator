<?php

use App\Http\Controllers\ResultsController;
use App\Http\Controllers\StoreLabelController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/results', ResultsController::class);
Route::post('/results/label', StoreLabelController::class);
