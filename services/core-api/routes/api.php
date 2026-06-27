<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes — prefix `api/v1` (set in bootstrap/app.php).
| AssignLogTraceId is prepended to this group; role gating via `role:<role>`.
| Feature routes (events, ticket-types) are added below as controllers land.
|--------------------------------------------------------------------------
*/

Route::get('health', fn () => ApiResponse::success(
    data: ['service' => 'core-api', 'status' => 'ok'],
    message: __('api.health.ok'),
))->name('health');

// --- Auth (public; rate-limited by the named `auth` limiter) ---
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:auth')->name('register');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:auth')->name('login');
});

// --- Authenticated (any role) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
    });

    // --- Admin area (role-gated). Real admin endpoints join this group as they land. ---
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('ping', fn () => ApiResponse::success(
            data: ['area' => 'admin'],
            message: 'ok',
        ))->name('ping');
    });
});
