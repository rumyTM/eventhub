<?php

namespace App\Providers;

use App\Contracts\NotificationPublisherContract;
use App\Contracts\PaymentServiceContract;
use App\Repositories\Contracts\AttendeeRepositoryInterface;
use App\Repositories\Contracts\DisputeRepositoryInterface;
use App\Repositories\Contracts\EventReminderRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\IdempotencyKeyRepositoryInterface;
use App\Repositories\Contracts\LedgerEntryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Repositories\Contracts\SalesReportRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\TicketHoldRepositoryInterface;
use App\Repositories\Contracts\TicketRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Repositories\Contracts\WaitlistRepositoryInterface;
use App\Repositories\Eloquent\AttendeeRepository;
use App\Repositories\Eloquent\DisputeRepository;
use App\Repositories\Eloquent\EventReminderRepository;
use App\Repositories\Eloquent\EventRepository;
use App\Repositories\Eloquent\IdempotencyKeyRepository;
use App\Repositories\Eloquent\LedgerEntryRepository;
use App\Repositories\Eloquent\OrderRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\PayoutRepository;
use App\Repositories\Eloquent\RefundRepository;
use App\Repositories\Eloquent\SalesReportRepository;
use App\Repositories\Eloquent\SettingRepository;
use App\Repositories\Eloquent\TicketHoldRepository;
use App\Repositories\Eloquent\TicketRepository;
use App\Repositories\Eloquent\TicketTypeRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\VendorRepository;
use App\Repositories\Eloquent\WaitlistRepository;
use App\Services\Notification\RedisNotificationPublisher;
use App\Services\Payments\PaymentClient;
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
        OrderRepositoryInterface::class => OrderRepository::class,
        TicketHoldRepositoryInterface::class => TicketHoldRepository::class,
        IdempotencyKeyRepositoryInterface::class => IdempotencyKeyRepository::class,
        SettingRepositoryInterface::class => SettingRepository::class,
        PaymentRepositoryInterface::class => PaymentRepository::class,
        PayoutRepositoryInterface::class => PayoutRepository::class,
        RefundRepositoryInterface::class => RefundRepository::class,
        DisputeRepositoryInterface::class => DisputeRepository::class,
        TicketRepositoryInterface::class => TicketRepository::class,
        LedgerEntryRepositoryInterface::class => LedgerEntryRepository::class,
        EventReminderRepositoryInterface::class => EventReminderRepository::class,
        SalesReportRepositoryInterface::class => SalesReportRepository::class,
        WaitlistRepositoryInterface::class => WaitlistRepository::class,

        // Inter-service clients (CLAUDE.md §H) — fakeable in tests via the contract.
        PaymentServiceContract::class => PaymentClient::class,
        NotificationPublisherContract::class => RedisNotificationPublisher::class,
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
