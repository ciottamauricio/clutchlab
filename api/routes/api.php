<?php

use App\Http\Controllers\AdminPermissionController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AnalystController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AwardsController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TacticController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TrainingAssignmentController;
use App\Http\Controllers\TrainingSessionController;
use Illuminate\Support\Facades\Route;

// Public — auth endpoints are rate-limited.
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Everything else requires a valid bearer token.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::get('/profile/stats', [ProfileController::class, 'stats']);
    Route::patch('/profile/password', [ProfileController::class, 'changePassword'])->middleware('throttle:10,1');

    Route::get('/matches', [MatchController::class, 'index']);
    Route::post('/matches', [MatchController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/matches/{match}', [MatchController::class, 'show']);
    Route::get('/matches/{match}/kill-positions', [MatchController::class, 'killPositions']);
    Route::get('/matches/{match}/clutches', [MatchController::class, 'clutches']);
    Route::get('/matches/{match}/team-stats', [MatchController::class, 'teamStats']);
    Route::get('/matches/{match}/demo', [MatchController::class, 'demo']);
    Route::post('/matches/{match}/reparse', [MatchController::class, 'reparse'])->middleware('throttle:30,1');
    Route::patch('/matches/{match}', [MatchController::class, 'update']);
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

    Route::get('/trainings', [TrainingSessionController::class, 'index']);
    Route::post('/trainings', [TrainingSessionController::class, 'store']);
    Route::get('/trainings/{training}', [TrainingSessionController::class, 'show']);
    Route::patch('/trainings/{training}', [TrainingSessionController::class, 'update']);
    Route::delete('/trainings/{training}', [TrainingSessionController::class, 'destroy']);
    Route::patch('/trainings/{training}/rsvp', [TrainingSessionController::class, 'rsvp']);
    Route::post('/trainings/{training}/assignments', [TrainingAssignmentController::class, 'store']);
    Route::patch('/trainings/{training}/assignments/{assignment}', [TrainingAssignmentController::class, 'update'])->scopeBindings();
    Route::delete('/trainings/{training}/assignments/{assignment}', [TrainingAssignmentController::class, 'destroy'])->scopeBindings();

    Route::middleware('can:tactics.view')->group(function () {
        Route::get('/tactics', [TacticController::class, 'index']);
        Route::post('/tactics', [TacticController::class, 'store']);
        Route::get('/tactics/{tactic}', [TacticController::class, 'show']);
        Route::put('/tactics/{tactic}', [TacticController::class, 'update']);
        Route::patch('/tactics/{tactic}', [TacticController::class, 'updateTeam']);
        Route::delete('/tactics/{tactic}', [TacticController::class, 'destroy']);
    });

    // The engineering study's docs Q&A. Deliberately outside every can: group: unlike the
    // analyst, this corpus is the repository's own markdown — identical for every caller,
    // scoped by nothing, and already readable by anyone who can read the repo. There is no
    // per-user data to gate, so authentication alone is the honest boundary. Still
    // throttled: each call spends local GPU time.
    Route::post('/docs/ask', [DocsController::class, 'ask'])->middleware('throttle:10,1');

    Route::middleware('can:awards.view')->group(function () {
        Route::get('/awards', [AwardsController::class, 'index']);
        Route::get('/awards/kills', [AwardsController::class, 'kills']);
    });

    Route::middleware('can:search.use')->group(function () {
        Route::get('/search/kills', [SearchController::class, 'kills']);
        Route::get('/search/rounds', [SearchController::class, 'rounds']);
        Route::get('/search/clutch-sizes', [SearchController::class, 'clutchSizes']);
        // Same corpus as search, so the same ability gates it. Each call is a paid
        // LLM request — throttled well below the generic limits.
        Route::post('/analyst/ask', [AnalystController::class, 'ask'])->middleware('throttle:10,1');
    });

    // Master-admin only: manage every user's global role and linked SteamID.
    Route::middleware('can:admin')->group(function () {
        Route::get('/admin/users', [AdminUserController::class, 'index']);
        Route::patch('/admin/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);
        Route::get('/admin/players', [AdminUserController::class, 'players']);
        Route::get('/admin/permissions', [AdminPermissionController::class, 'index']);
        Route::put('/admin/permissions', [AdminPermissionController::class, 'update']);
    });
});
