<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\PayoutController;
use App\Http\Controllers\Api\V1\PayoutWebhookController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\RefundWebhookController;
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

// --- Internal: payment-service → core-api signed webhook callback (NOT user-authenticated). ---
// Gated by `webhook.signature` (shared-secret bearer + HMAC-SHA256 over the raw body). Never public.
Route::post('internal/payments/webhook', [PaymentWebhookController::class, 'handle'])
    ->middleware('webhook.signature')
    ->name('internal.payments.webhook');

// payment-service → core-api signed REFUND result callback (same bearer + raw-body HMAC guard).
Route::post('internal/payments/refund-webhook', [RefundWebhookController::class, 'handle'])
    ->middleware('webhook.signature')
    ->name('internal.payments.refund-webhook');

// payment-service → core-api signed PAYOUT result callback (same bearer + raw-body HMAC guard).
Route::post('internal/payments/payout-webhook', [PayoutWebhookController::class, 'handle'])
    ->middleware('webhook.signature')
    ->name('internal.payments.payout-webhook');

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

// --- Attendee checkout (order + 15-min holds, idempotent) ---
Route::middleware(['auth:sanctum', 'role:attendee', 'throttle:checkout'])->group(function () {
    Route::post('orders', [OrderController::class, 'store'])->name('orders.store');
});

// --- Attendee refund request (own paid order; policy-derived amount, idempotent) ---
Route::middleware(['auth:sanctum', 'role:attendee', 'throttle:refund'])->group(function () {
    Route::post('orders/{order}/refund', [RefundController::class, 'store'])->name('orders.refund');
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

        // Admin-initiated refund (e.g. event-cancellation 100% refund). Policy-derived amount; idempotent.
        Route::post('orders/{order}/refund', [RefundController::class, 'initiate'])
            ->middleware('throttle:refund')->name('orders.refund');

        // Payout management.
        Route::get('payouts', [PayoutController::class, 'index'])
            ->middleware('throttle:read')->name('payouts.index');
        Route::post('payouts/build', [PayoutController::class, 'build'])
            ->middleware('throttle:write')->name('payouts.build');
        Route::post('payouts/{payout}/execute', [PayoutController::class, 'execute'])
            ->middleware('throttle:write')->name('payouts.execute');
    });
});
