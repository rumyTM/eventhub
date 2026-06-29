<?php

namespace App\Services\Payouts;

use App\Actions\Payouts\CalculatePayout;
use App\Enums\PayoutStatus;
use App\Helpers\LogHelper;
use App\Models\Payout;
use App\Repositories\Contracts\LedgerEntryRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Builds pending payout records for one or all eligible vendors (Chunk D — DECIDE ONLY; no money moves).
 *
 * Idempotency contract (ADR-09):
 *   - Deterministic key: `payout:{vendorId}:{batchId}` (batchId = caller-supplied date string, e.g. today).
 *   - DB-unique guard on `payouts.idempotency_key` — a duplicate INSERT raises `UniqueConstraintViolationException`,
 *     caught and returned as the existing payout so a re-run is always a no-op.
 *   - Row lock inside the transaction (SELECT … FOR UPDATE on the key) collapses concurrent workers racing
 *     for the same key: the first writer wins; the second finds the locked row and returns it.
 *   - A payout in any non-failed status blocks rebuilding: `pending`, `approved`, `processing`, `paid` all
 *     return the existing row unchanged.
 *
 * No ledger entry is written here; the `payout` ledger row is written in Chunk E on execution success.
 */
final class PayoutBuildService
{
    /** Platform setting key for the minimum payout threshold (minor units). */
    private const THRESHOLD_KEY = 'payout_threshold';

    /** Default threshold when no setting exists: 100 BDT = 10 000 poisha. */
    private const DEFAULT_THRESHOLD = 10_000;

    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly LedgerEntryRepositoryInterface $ledger,
        private readonly PayoutRepositoryInterface $payouts,
        private readonly SettingRepositoryInterface $settings,
        private readonly CalculatePayout $calculatePayout,
    ) {}

    /**
     * Build a pending payout for a single vendor for the given batch window. Returns the created (or
     * existing) Payout, or null when there are no eligible orders or the balance is below threshold.
     */
    public function buildForVendor(string $vendorId, string $batchId): ?Payout
    {
        $idempotencyKey = "payout:{$vendorId}:{$batchId}";

        // Fast path: already built (no lock needed to detect existing rows).
        $existing = $this->payouts->findByIdempotencyKey($idempotencyKey);
        if ($existing !== null && $existing->status !== PayoutStatus::Failed) {
            LogHelper::logEntry(LogHelper::LOG_INFO, 'Payout already exists for vendor/batch; skipping build', [
                'vendor_id' => $vendorId, 'batch_id' => $batchId, 'payout_id' => $existing->id,
            ]);

            return $existing;
        }

        // Determine eligible orders + compute balance OUTSIDE the transaction (read-only, no lock needed).
        $eligibleOrderIds = $this->orders->eligibleOrderIdsForVendorPayout($vendorId);
        if ($eligibleOrderIds === []) {
            return null;
        }

        $amounts = $this->ledger->vendorPayoutAmounts($vendorId, $eligibleOrderIds);
        $threshold = (int) ($this->settings->get(self::THRESHOLD_KEY) ?? self::DEFAULT_THRESHOLD);

        $calc = $this->calculatePayout->handle(
            gross: $amounts['gross'],
            commission: $amounts['commission'],
            adjustments: $amounts['adjustments'],
            threshold: $threshold,
        );

        if (! $calc->meetsThreshold) {
            LogHelper::logEntry(LogHelper::LOG_INFO, 'Vendor payout below threshold; rolling into next cycle', [
                'vendor_id' => $vendorId, 'batch_id' => $batchId,
                'payable' => $calc->payable, 'threshold' => $threshold,
            ]);

            return null;
        }

        // Build the payout row + items inside a transaction with a row lock.
        try {
            return DB::transaction(function () use ($vendorId, $batchId, $idempotencyKey, $calc, $amounts, $eligibleOrderIds): Payout {
                // Row-lock guard: blocks a concurrent worker that reached this point at the same time.
                $locked = $this->payouts->lockByIdempotencyKey($idempotencyKey);
                if ($locked !== null && $locked->status !== PayoutStatus::Failed) {
                    return $locked;
                }

                $payout = $this->payouts->createPayout([
                    'vendor_id' => $vendorId,
                    'gross' => $calc->gross,
                    'commission' => $calc->commission,
                    'net' => $calc->net,           // H-2: accounting net = gross − commission
                    'payable' => $calc->payable,   // H-2: disbursable = net + adjustments, floored at 0
                    'reserved_refund' => 0,        // simplified; reserve estimation is a follow-up
                    'currency' => 'BDT',           // ADR-12: single-currency platform
                    'status' => PayoutStatus::Pending->value,
                    'batch_id' => $batchId,
                    'idempotency_key' => $idempotencyKey,
                ]);

                foreach ($eligibleOrderIds as $orderId) {
                    $this->payouts->createPayoutItem([
                        'payout_id' => $payout->id,
                        'order_id' => $orderId,
                        'settled_amount' => $amounts['per_order'][$orderId] ?? 0,
                    ]);
                }

                LogHelper::logEntry(LogHelper::LOG_INFO, 'Payout built for vendor', [
                    'vendor_id' => $vendorId, 'batch_id' => $batchId,
                    'payout_id' => $payout->id, 'net' => $calc->payable,
                    'order_count' => count($eligibleOrderIds),
                ]);

                return $payout;
            });
        } catch (UniqueConstraintViolationException) {
            // Concurrent INSERT hit the unique key — return the winning row.
            return $this->payouts->findByIdempotencyKey($idempotencyKey);
        }
    }

    /**
     * Build pending payouts for ALL vendors that have eligible funds in this batch window. Returns the
     * list of newly-created (or already-existing) Payout records; vendors with nothing eligible or
     * below-threshold are omitted.
     *
     * @return list<Payout>
     */
    public function buildAll(string $batchId): array
    {
        // Collect every vendor_id that has at least one completed-event, unsettled order.
        $vendorIds = $this->orders->eligibleVendorIdsForPayout();

        $payouts = [];
        foreach ($vendorIds as $vendorId) {
            $payout = $this->buildForVendor($vendorId, $batchId);
            if ($payout !== null) {
                $payouts[] = $payout;
            }
        }

        return $payouts;
    }
}
