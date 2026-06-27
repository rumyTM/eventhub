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
     * Named rate limiters. Each returns the canonical envelope + retry_after (in data) on 429.
     */
    private function configureRateLimiters(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user()?->id ?: $request->ip();

        $tooMany = fn (Request $request, array $headers) => ApiResponse::error(
            message: __('api.errors.too_many_requests'),
            status: 429,
            data: ['retry_after' => (int) ($headers['Retry-After'] ?? 60)],
        );

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)
            ->by($request->ip())
            ->response($tooMany));

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(20)
            ->by($byUserOrIp($request))
            ->response($tooMany));

        RateLimiter::for('refund', fn (Request $request) => Limit::perMinute(10)
            ->by($byUserOrIp($request))
            ->response($tooMany));
    }
}
