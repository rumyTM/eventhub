<?php

use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes — prefix `api/v1` (set in bootstrap/app.php).
| AssignLogTraceId is prepended to this group; role gating via `role:<role>`.
| Feature routes (auth, events, ticket-types) are added below as controllers land.
|--------------------------------------------------------------------------
*/

Route::get('health', fn () => ApiResponse::success(
    data: ['service' => 'core-api', 'status' => 'ok'],
    message: __('api.health.ok'),
))->name('health');
