<?php

use App\Http\Controllers\Api\V1\PaymentController;
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
