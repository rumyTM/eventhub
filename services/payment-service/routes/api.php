<?php

use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PayoutController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment-service API v1 routes — prefix `api/v1`. Every money route sits
| behind EnsureServiceToken (the `service.token` shared-secret alias) — no
| endpoint here is publicly reachable. Health is open for compose checks.
|--------------------------------------------------------------------------
*/

Route::get('health', fn () => ApiResponse::success(
    data: ['service' => 'payment-service', 'status' => 'ok'],
    message: 'payment-service is healthy.',
))->name('health');

Route::middleware(['service.token', 'throttle:payments'])->group(function () {
    Route::post('payments', [PaymentController::class, 'store'])->name('payments.store');
});

Route::middleware(['service.token', 'throttle:refunds'])->group(function () {
    Route::post('refunds', [RefundController::class, 'store'])->name('refunds.store');
});

Route::middleware(['service.token', 'throttle:payouts'])->group(function () {
    Route::post('payouts', [PayoutController::class, 'store'])->name('payouts.store');
});
