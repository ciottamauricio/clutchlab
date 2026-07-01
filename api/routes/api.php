<?php

use App\Http\Controllers\MatchController;
use Illuminate\Support\Facades\Route;

Route::get('/matches', [MatchController::class, 'index']);
Route::post('/matches', [MatchController::class, 'store'])->middleware('throttle:30,1');
Route::get('/matches/{match}', [MatchController::class, 'show']);
