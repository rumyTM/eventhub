<?php

namespace App\Providers;

use App\Support\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * Named rate limiters. Calls here come from core-api (a trusted service), so the limit is a
     * backstop against a runaway retry loop, keyed by IP. Returns the canonical envelope +
     * retry_after (in data) on 429 — mirrors core-api.
     */
    private function configureRateLimiters(): void
    {
        $tooMany = fn (Request $request, array $headers) => ApiResponse::error(
            message: __('api.errors.too_many_requests'),
            status: 429,
            data: ['retry_after' => (int) ($headers['Retry-After'] ?? 60)],
        );

        RateLimiter::for('payments', fn (Request $request) => Limit::perMinute(120)
            ->by($request->ip())
            ->response($tooMany));

        RateLimiter::for('refunds', fn (Request $request) => Limit::perMinute(120)
            ->by($request->ip())
            ->response($tooMany));

        RateLimiter::for('payouts', fn (Request $request) => Limit::perMinute(60)
            ->by($request->ip())
            ->response($tooMany));
    }
}
