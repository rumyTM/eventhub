<?php

namespace App\Providers;

use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use App\Repositories\Eloquent\EventRepository;
use App\Repositories\Eloquent\TicketTypeRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds each repository interface to its Eloquent implementation. Services depend on the
 * interface only; this is the single place those bindings live.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        EventRepositoryInterface::class => EventRepository::class,
        TicketTypeRepositoryInterface::class => TicketTypeRepository::class,
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
