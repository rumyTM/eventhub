<?php

namespace App\Providers;

use App\Repositories\Contracts\AttendeeRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Repositories\Eloquent\AttendeeRepository;
use App\Repositories\Eloquent\EventRepository;
use App\Repositories\Eloquent\TicketTypeRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\VendorRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Binds each repository interface to its Eloquent implementation. Services depend on the
 * interface only; this is the single place those bindings live.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        VendorRepositoryInterface::class => VendorRepository::class,
        AttendeeRepositoryInterface::class => AttendeeRepository::class,
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
