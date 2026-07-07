<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AwardsController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TacticController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

// Public — auth endpoints are rate-limited.
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Everything else requires a valid bearer token.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/matches', [MatchController::class, 'index']);
    Route::post('/matches', [MatchController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/matches/{match}', [MatchController::class, 'show']);
    Route::get('/matches/{match}/kill-positions', [MatchController::class, 'killPositions']);
    Route::get('/matches/{match}/clutches', [MatchController::class, 'clutches']);
    Route::get('/matches/{match}/team-stats', [MatchController::class, 'teamStats']);
    Route::get('/matches/{match}/demo', [MatchController::class, 'demo']);
    Route::post('/matches/{match}/reparse', [MatchController::class, 'reparse'])->middleware('throttle:30,1');
    Route::delete('/matches/{match}', [MatchController::class, 'destroy']);

    Route::get('/players', [PlayerController::class, 'index']);
    Route::get('/players/{steamId}/clutches', [PlayerController::class, 'clutches']);

    Route::get('/teams', [TeamController::class, 'index']);
    Route::post('/teams', [TeamController::class, 'store']);
    Route::get('/teams/{team}', [TeamController::class, 'show']);
    Route::get('/teams/{team}/stats', [TeamController::class, 'stats']);
    Route::post('/teams/{team}/members', [TeamController::class, 'addMember']);
    Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
    Route::post('/teams/{team}/players', [TeamController::class, 'addPlayer']);
    Route::delete('/teams/{team}/players/{steamId}', [TeamController::class, 'removePlayer']);

    Route::get('/tactics', [TacticController::class, 'index']);
    Route::post('/tactics', [TacticController::class, 'store']);
    Route::get('/tactics/{tactic}', [TacticController::class, 'show']);
    Route::put('/tactics/{tactic}', [TacticController::class, 'update']);
    Route::delete('/tactics/{tactic}', [TacticController::class, 'destroy']);

    Route::get('/awards', [AwardsController::class, 'index']);
    Route::get('/awards/kills', [AwardsController::class, 'kills']);

    Route::get('/search/kills', [SearchController::class, 'kills']);
    Route::get('/search/rounds', [SearchController::class, 'rounds']);
    Route::get('/search/clutch-sizes', [SearchController::class, 'clutchSizes']);
});
