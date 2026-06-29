<?php

namespace App\Providers;

use App\Repositories\Contracts\IdempotencyKeyRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Eloquent\IdempotencyKeyRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\PayoutRepository;
use App\Repositories\Eloquent\RefundRepository;
use App\Repositories\Eloquent\TransactionRepository;
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
        RefundRepositoryInterface::class => RefundRepository::class,
        PayoutRepositoryInterface::class => PayoutRepository::class,
        IdempotencyKeyRepositoryInterface::class => IdempotencyKeyRepository::class,
        TransactionRepositoryInterface::class => TransactionRepository::class,
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
