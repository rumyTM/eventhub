<?php

use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payment-service API v1 routes — prefix `api/v1`. All money routes (Day 3)
| sit behind EnsureServiceToken (shared-secret). Health is open for compose checks.
|--------------------------------------------------------------------------
*/

Route::get('health', fn () => ApiResponse::success(
    data: ['service' => 'payment-service', 'status' => 'ok'],
    message: 'payment-service is healthy.',
))->name('health');
