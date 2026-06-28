<?php

namespace App\Providers;

use App\Repositories\Contracts\IdempotencyKeyRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Eloquent\IdempotencyKeyRepository;
use App\Repositories\Eloquent\PaymentRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds each repository interface to its Eloquent implementation. Services depend on the interface
 * only; this is the single place those bindings live (mirrors core-api).
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        PaymentRepositoryInterface::class => PaymentRepository::class,
        IdempotencyKeyRepositoryInterface::class => IdempotencyKeyRepository::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
