<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\TicketTypeController;
use App\Http\Controllers\Api\V1\VendorController;
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

// --- Events + ticket types: public reads (published only; owner/admin see more — enforced in policy) ---
Route::middleware('throttle:read')->group(function () {
    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('events/{event}/ticket-types', [TicketTypeController::class, 'index'])
        ->name('events.ticket-types.index');
    Route::get('events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'show'])
        ->scopeBindings()->name('events.ticket-types.show');
});

// --- Events + ticket types: vendor writes (ownership enforced in policy) ---
Route::middleware(['auth:sanctum', 'role:vendor', 'throttle:write'])->group(function () {
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    Route::post('events/{event}/ticket-types', [TicketTypeController::class, 'store'])
        ->name('events.ticket-types.store');
    Route::put('events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'update'])
        ->scopeBindings()->name('events.ticket-types.update');
    Route::delete('events/{event}/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy'])
        ->scopeBindings()->name('events.ticket-types.destroy');
});

// --- Vendor self-service: submit own KYC for review ---
Route::middleware(['auth:sanctum', 'role:vendor', 'throttle:write'])->group(function () {
    Route::post('vendor/kyc', [VendorController::class, 'submitKyc'])->name('vendor.kyc.submit');
});

// --- Authenticated (any role) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
    });

    // --- Admin area (role-gated). ---
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('ping', fn () => ApiResponse::success(
            data: ['area' => 'admin'],
            message: 'ok',
        ))->name('ping');

        // KYC review queue.
        Route::get('vendors', [VendorController::class, 'pending'])
            ->middleware('throttle:read')->name('vendors.pending');
        Route::post('vendors/{vendor}/verify', [VendorController::class, 'verify'])
            ->middleware('throttle:write')->name('vendors.verify');
        Route::post('vendors/{vendor}/reject', [VendorController::class, 'reject'])
            ->middleware('throttle:write')->name('vendors.reject');
    });
});
