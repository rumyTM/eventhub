<?php

namespace App\Services\Orders;

use App\Actions\Orders\ResolveTicketPrice;
use App\Enums\HoldStatus;
use App\Enums\OrderStatus;
use App\Exceptions\Orders\EventNotPurchasableException;
use App\Exceptions\Orders\IdempotencyKeyConflictException;
use App\Exceptions\Orders\LockUnavailableException;
use App\Exceptions\Orders\MixedCurrencyException;
use App\Exceptions\Orders\SalesWindowClosedException;
use App\Exceptions\Orders\TicketsUnavailableException;
use App\Models\Attendee;
use App\Models\Order;
use App\Models\TicketType;
use App\Repositories\Contracts\IdempotencyKeyRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\TicketHoldRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Checkout: turns a cart into a `pending` order + 15-minute `ticket_holds` + `order_items`. No tickets
 * are issued and `quantity_sold` is NOT touched here — holds reserve inventory via the active-holds count;
 * `quantity_sold` only moves on payment success (a later slice).
 *
 * Oversell prevention is the hybrid lock of ADR-07: a short-lived per-ticket_type cache lock (the
 * "distributed front", Redis in prod / array store in tests) fronts an authoritative DB row lock
 * (SELECT ... FOR UPDATE) taken inside the transaction. The DB row lock is the correctness guard —
 * oversell is impossible even if the cache lock is unavailable.
 *
 * Idempotency is DB-backed (ADR-09): the Idempotency-Key maps to the created order; a replay with the same
 * body returns that order with no new side effects, a replay with a different body is a 409 conflict.
 */
final class CheckoutService
{
    private const HOLD_MINUTES = 15;

    /** Platform default when no `platform_commission_rate` setting row exists (matches .env default). */
    private const DEFAULT_COMMISSION_RATE = '0.10';

    private const LOCK_PREFIX = 'checkout:ticket_type:';

    private const LOCK_TTL_SECONDS = 10;

    private const LOCK_WAIT_SECONDS = 3;

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly TicketTypeRepositoryInterface $ticketTypes,
        private readonly TicketHoldRepositoryInterface $holds,
        private readonly IdempotencyKeyRepositoryInterface $idempotencyKeys,
        private readonly SettingRepositoryInterface $settings,
        private readonly ResolveTicketPrice $resolvePrice,
    ) {}

    /**
     * @param  list<array{ticket_type_id: string, quantity: int}>  $items
     */
    public function checkout(Attendee $attendee, string $idempotencyKey, array $items): Order
    {
        $normalized = $this->normalize($items);            // [ticket_type_id => total qty], sorted by id
        $requestHash = $this->requestHash($attendee->id, $normalized);

        // --- Idempotency replay / conflict (ADR-09) ---
        $seen = $this->idempotencyKeys->findByKey($idempotencyKey);
        if ($seen !== null) {
            if (! hash_equals($seen->request_hash, $requestHash)) {
                throw new IdempotencyKeyConflictException;
            }

            return $this->orders->findWithLines($seen->response_payload['order_id']);
        }

        $ids = array_keys($normalized);
        $ticketTypes = $this->ticketTypes->findManyForCheckout($ids)->keyBy('id');
        $currency = $this->assertPurchasable($ticketTypes, $ids);

        try {
            $order = $this->withTicketTypeLocks($ids, fn (): Order => DB::transaction(
                fn (): Order => $this->reserve($attendee, $idempotencyKey, $requestHash, $currency, $normalized)
            ));
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request used the same key first — treat as a replay rather than 500.
            $seen = $this->idempotencyKeys->findByKey($idempotencyKey);

            if ($seen !== null) {
                return $this->orders->findWithLines($seen->response_payload['order_id']);
            }

            throw $e;
        }

        return $this->orders->findWithLines($order->id);
    }

    /**
     * The locked critical section: per-line availability check + hold/item creation + order, all atomic.
     *
     * @param  array<string, int>  $normalized
     */
    private function reserve(
        Attendee $attendee,
        string $idempotencyKey,
        string $requestHash,
        string $currency,
        array $normalized,
    ): Order {
        $lines = [];
        $total = 0;

        foreach ($normalized as $ticketTypeId => $quantity) {
            $locked = $this->ticketTypes->lockForUpdate($ticketTypeId); // authoritative FOR UPDATE row lock

            // Re-validate purchasability on the FRESH locked row — the event could have been cancelled or
            // the sales window could have closed between the pre-lock check and acquiring the lock (H-1).
            $locked->loadMissing('event');
            if ($locked->event === null || ! $locked->event->status->isPurchasable()) {
                throw new EventNotPurchasableException;
            }
            if (! $this->salesWindowOpen($locked)) {
                throw new SalesWindowClosedException;
            }

            $available = $locked->quantity_total
                - $locked->quantity_sold
                - $this->holds->sumActiveQuantityForTicketType($ticketTypeId);

            if ($quantity > $available) {
                throw new TicketsUnavailableException(requested: $quantity, available: max(0, $available));
            }

            $unitPrice = $this->resolvePrice->handle($locked, $quantity);
            $lines[] = ['ticket_type_id' => $ticketTypeId, 'quantity' => $quantity, 'unit_price' => $unitPrice];
            $total += $quantity * $unitPrice;
        }

        $order = $this->orders->create([
            'attendee_id' => $attendee->id,
            'status' => OrderStatus::Pending->value,
            'total' => $total,
            'currency' => $currency,
            'commission_rate' => $this->commissionRate(), // snapshot at sale time
            'idempotency_key' => $idempotencyKey,
        ]);

        $expiresAt = now()->addMinutes(self::HOLD_MINUTES);

        foreach ($lines as $line) {
            $this->orders->addItem($order, [
                'ticket_type_id' => $line['ticket_type_id'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'], // locked at hold creation
            ]);

            $this->holds->create([
                'order_id' => $order->id,
                'ticket_type_id' => $line['ticket_type_id'],
                'quantity' => $line['quantity'],
                'status' => HoldStatus::Active->value,
                'expires_at' => $expiresAt,
            ]);
        }

        $this->idempotencyKeys->create([
            'key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'response_payload' => ['order_id' => $order->id],
            'status' => 'completed',
        ]);

        return $order;
    }

    /**
     * Validate every line's event is purchasable and its sales window is open, and that the whole cart
     * shares one currency. Returns the cart currency.
     *
     * @param  Collection<int|string, TicketType>  $ticketTypes  keyed by id
     * @param  list<string>  $ids
     */
    private function assertPurchasable(Collection $ticketTypes, array $ids): string
    {
        $currencies = [];

        foreach ($ids as $id) {
            /** @var TicketType|null $ticketType */
            $ticketType = $ticketTypes->get($id);

            // Missing or soft-deleted ticket type — not buyable.
            if ($ticketType === null || $ticketType->event === null || ! $ticketType->event->status->isPurchasable()) {
                throw new EventNotPurchasableException;
            }

            if (! $this->salesWindowOpen($ticketType)) {
                throw new SalesWindowClosedException;
            }

            $currencies[$ticketType->currency] = true;
        }

        if (count($currencies) > 1) {
            throw new MixedCurrencyException;
        }

        return (string) array_key_first($currencies);
    }

    /** A sales window is open when now is within [sales_start, sales_end] (either bound may be null = unbounded). */
    private function salesWindowOpen(TicketType $ticketType): bool
    {
        $now = now();

        if ($ticketType->sales_start !== null && $ticketType->sales_start->greaterThan($now)) {
            return false;
        }

        if ($ticketType->sales_end !== null && $ticketType->sales_end->lessThan($now)) {
            return false;
        }

        return true;
    }

    /**
     * Acquire the per-ticket_type cache locks in a deterministic (sorted) order to avoid deadlocks, run
     * the callback, then always release them.
     *
     * @param  list<string>  $ids
     */
    private function withTicketTypeLocks(array $ids, Closure $callback): mixed
    {
        $ordered = $ids;
        sort($ordered);

        $locks = [];

        try {
            foreach ($ordered as $id) {
                $lock = Cache::lock(self::LOCK_PREFIX.$id, self::LOCK_TTL_SECONDS);

                try {
                    // block() waits up to N seconds, then THROWS LockTimeoutException (it does not return false).
                    $lock->block(self::LOCK_WAIT_SECONDS);
                } catch (LockTimeoutException) {
                    throw new LockUnavailableException;
                }

                $locks[] = $lock;
            }

            return $callback();
        } finally {
            foreach ($locks as $lock) {
                $lock->release();
            }
        }
    }

    /**
     * Merge duplicate ticket_type_id lines (summing quantity) so a cart with two lines of the same type
     * is checked against availability once, and sort by id for stable lock ordering + a stable hash.
     *
     * @param  list<array{ticket_type_id: string, quantity: int}>  $items
     * @return array<string, int>
     */
    private function normalize(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            $id = (string) $item['ticket_type_id'];
            $merged[$id] = ($merged[$id] ?? 0) + (int) $item['quantity'];
        }

        ksort($merged);

        return $merged;
    }

    /**
     * @param  array<string, int>  $normalized
     */
    private function requestHash(string $attendeeId, array $normalized): string
    {
        return hash('sha256', (string) json_encode([
            'attendee_id' => $attendeeId,
            'items' => $normalized,
        ]));
    }

    /**
     * Commission rate snapshot, as an exact decimal STRING (never a float) so the value written to the
     * decimal(5,4) column carries no IEEE-754 drift (C-1). Read from settings at sale time, else default.
     */
    private function commissionRate(): string
    {
        return $this->settings->get('platform_commission_rate') ?? self::DEFAULT_COMMISSION_RATE;
    }
}
