<?php

namespace App\Services\Payments;

use App\Enums\PayoutStatus;
use App\Enums\TransactionType;
use App\Exceptions\Payments\IdempotencyKeyConflictException;
use App\Gateways\GatewayManager;
use App\Jobs\DeliverPayoutResultJob;
use App\Models\Payout;
use App\Repositories\Contracts\IdempotencyKeyRepositoryInterface;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owns a payout execution end-to-end, mirroring {@see RefundService} exactly (CLAUDE.md §A.5/§D/§E/§G):
 *
 *   1. createPayout() — reserve the `pending` Payout idempotently (ADR-09). The Idempotency-Key maps
 *      to exactly one Payout; same key+body replays it, same key+different body is a 409.
 *   2. resolve()      — let the default gateway decide success/failure, persist the terminal status, and
 *      append ONE row to the `transactions` ledger with a NEGATIVE signed amount (money out to vendor).
 *      Runs inside the queued DeliverPayoutResultJob so the HTTP request returns `pending` immediately and
 *      the real result arrives via the signed webhook to core-api.
 *
 * resolve() is idempotent: a row-locked re-check short-circuits a payout that is already terminal, so a
 * job retry (or a duplicate dispatch) never rolls the gateway twice or writes a second ledger row.
 */
final class PayoutService
{
    public function __construct(
        private readonly PayoutRepositoryInterface $payouts,
        private readonly IdempotencyKeyRepositoryInterface $idempotencyKeys,
        private readonly TransactionRepositoryInterface $transactions,
        private readonly GatewayManager $gateways,
    ) {}

    /**
     * @param  array{payout_ref: string, vendor_id: string, amount: int, currency: string}  $payload
     */
    public function createPayout(string $idempotencyKey, array $payload): Payout
    {
        $requestHash = $this->requestHash($payload);

        // --- Idempotency replay / conflict (ADR-09) ---
        $seen = $this->idempotencyKeys->findByKey($idempotencyKey);
        if ($seen !== null) {
            if (! hash_equals($seen->request_hash, $requestHash)) {
                throw new IdempotencyKeyConflictException;
            }

            return $this->payouts->findOrFail($seen->response_payload['payout_id']);
        }

        try {
            return DB::transaction(fn (): Payout => $this->persist($idempotencyKey, $requestHash, $payload));
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request used the same key first — resolve as a replay rather than 500.
            $seen = $this->idempotencyKeys->findByKey($idempotencyKey);

            if ($seen !== null) {
                return $this->payouts->findOrFail($seen->response_payload['payout_id']);
            }

            throw $e;
        }
    }

    /**
     * Queue the asynchronous resolution + signed webhook. A payout that is already terminal (an
     * idempotent replay) needs no further work.
     */
    public function scheduleResolution(Payout $payout): void
    {
        if ($payout->status->isTerminal()) {
            return;
        }

        $delaySeconds = $this->gateways->default()->delaySeconds();

        DeliverPayoutResultJob::dispatch($payout->id)
            ->delay(Carbon::now()->addSeconds($delaySeconds));
    }

    /**
     * Resolve a pending payout: ask the default gateway for the outcome, persist the terminal status,
     * and append the ledger row. Returns the (possibly already-resolved) Payout.
     *
     * The gateway call runs OUTSIDE the transaction so the `payouts` row lock is held only for the
     * write. The lock plus the terminal-status re-check inside the transaction is the authoritative
     * single-write guard: a job retry or duplicate dispatch re-enters, finds the payout already
     * terminal, and short-circuits — never a second gateway roll, never a second ledger row.
     */
    public function resolve(string $payoutId): Payout
    {
        // 1. Optimistic read (no lock): nothing to do if already resolved.
        $payout = $this->payouts->findOrFail($payoutId);
        if ($payout->status->isTerminal()) {
            return $payout;
        }

        // 2. Gateway decision OUTSIDE the transaction (no row lock held while it runs).
        $result = $this->gateways
            ->default()
            ->payout($payout->amount, $payout->currency, $payout->payout_ref);

        $status = $result->succeeded ? PayoutStatus::Completed : PayoutStatus::Failed;

        // 3. Lock, re-check (authoritative guard), persist + append the ledger row atomically.
        return DB::transaction(function () use ($payoutId, $result, $status): Payout {
            $payout = $this->payouts->findForUpdate($payoutId);

            // A concurrent resolve already committed — discard this roll, never double-write.
            if ($payout->status->isTerminal()) {
                return $payout;
            }

            $payout = $this->payouts->markResolved($payout, $status, $result->reference);

            // Append-only ledger: a successful payout is money OUT to the vendor, recorded as a
            // NEGATIVE signed amount; a failed payout moved nothing, so it is 0 — SUM(amount) stays
            // an honest net position. Keyed to payout_id (no associated payment for a payout row).
            $this->transactions->create([
                'payment_id' => null,
                'payout_id' => $payout->id,
                'type' => TransactionType::Payout->value,
                'amount' => $result->succeeded ? -$payout->amount : 0,
                'currency' => $payout->currency,
                'gateway_ref' => $result->reference,
            ]);

            return $payout;
        });
    }

    /**
     * @param  array{payout_ref: string, vendor_id: string, amount: int, currency: string}  $payload
     */
    private function persist(string $idempotencyKey, string $requestHash, array $payload): Payout
    {
        $payout = $this->payouts->create([
            'payout_ref' => $payload['payout_ref'],
            'vendor_id' => $payload['vendor_id'],
            'status' => PayoutStatus::Pending->value,
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
            'idempotency_key' => $idempotencyKey,
        ]);

        $this->idempotencyKeys->create([
            'key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'response_payload' => ['payout_id' => $payout->id],
            'status' => 'completed',
        ]);

        return $payout;
    }

    /**
     * Stable hash of the money-relevant request fields, so the same key with a different body is
     * detectable as a conflict.
     *
     * @param  array{payout_ref: string, vendor_id: string, amount: int, currency: string}  $payload
     */
    private function requestHash(array $payload): string
    {
        return hash('sha256', (string) json_encode([
            'payout_ref' => $payload['payout_ref'],
            'vendor_id' => $payload['vendor_id'],
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
        ]));
    }
}
