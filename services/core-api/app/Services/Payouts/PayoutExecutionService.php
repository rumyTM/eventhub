<?php

namespace App\Services\Payouts;

use App\Contracts\PaymentServiceContract;
use App\Enums\PayoutStatus;
use App\Helpers\LogHelper;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Initiates an approved payout against the payment-service, off the request path (CLAUDE.md §F/§H).
 * Mirrors {@see RefundExecutionService} for the refund path. Runs from the queued
 * {@see ExecutePayoutJob}, so it is written to be safe to re-run:
 *
 *   - **Flip `pending` → `processing` BEFORE the call**, inside a row-locked transaction. Once
 *     processing, the only thing that resolves the payout is the signed payout webhook (or a 4xx
 *     fast-fail). This never marks the payout `paid` locally — money movement is confirmed by the
 *     gateway result via the webhook, not here. (ADR-09/ADR-13)
 *   - **Deterministic Idempotency-Key** (`payout-exec:{id}`): a job retry reuses the same key, so
 *     the payment-service de-dupes and never double-pays (ADR-09).
 *   - **5xx/timeout → the call throws**, bubbling to the job for a backed-off retry; the payout stays
 *     `processing`. **4xx → the job fast-fails** and calls {@see markExecutionFailed()} (no webhook
 *     will arrive for a request the payment-service rejected, so we resolve it locally as failed — no
 *     ledger, no money moved).
 */
final class PayoutExecutionService
{
    public function __construct(
        private readonly PayoutRepositoryInterface $payouts,
        private readonly PaymentServiceContract $paymentService,
    ) {}

    public function execute(string $payoutId): void
    {
        $payout = $this->payouts->find($payoutId);

        // Vanished or already resolved (paid/failed) — idempotent no-op.
        if ($payout === null || in_array($payout->status, [PayoutStatus::Paid, PayoutStatus::Failed], true)) {
            return;
        }

        // Flip pending|approved → processing atomically. `Approved` skips the flip without this guard,
        // leaving the audit trail with no in-flight state and no crash-recovery signal (H-2).
        if (in_array($payout->status, [PayoutStatus::Pending, PayoutStatus::Approved], true)) {
            DB::transaction(function () use ($payoutId): void {
                $locked = $this->payouts->findForUpdate($payoutId);
                if ($locked !== null && in_array($locked->status, [PayoutStatus::Pending, PayoutStatus::Approved], true)) {
                    $this->payouts->markProcessing($locked);
                }
            });
            $payout = $this->payouts->find($payoutId);
        }

        if ($payout === null) {
            return;
        }

        // Returns a pending ack; the terminal result arrives via the signed payout webhook. A non-2xx
        // throws (RequestException) — handled by the job (4xx fast-fail, 5xx retry).
        $result = $this->paymentService->executePayout(
            payoutId: $payout->id,
            vendorId: $payout->vendor_id,
            amount: $payout->payable,           // integer minor units — the disbursable amount, never card data
            currency: $payout->currency,
            idempotencyKey: $this->idempotencyKey($payout->id),
        );

        LogHelper::logEntry(LogHelper::LOG_INFO, 'Payout execution initiated; awaiting signed webhook', [
            'payout_id' => $payout->id,
            'payout_ref' => $result->ref,       // payment-service payout ref — never card data
        ]);
    }

    /**
     * Resolve a payout as `failed` locally — used only when the payment-service permanently rejects
     * the request (4xx) so no webhook will ever arrive. No ledger is written (failure moves no money).
     */
    public function markExecutionFailed(string $payoutId): void
    {
        DB::transaction(function () use ($payoutId): void {
            $payout = $this->payouts->findForUpdate($payoutId);
            if ($payout === null || in_array($payout->status, [PayoutStatus::Paid, PayoutStatus::Failed], true)) {
                return;
            }
            // H-4: if the payout is already Processing, the payment-service may have accepted the call
            // and the webhook is still in-flight. Marking failed here could conflict with a late webhook
            // success. Log an alert for manual review; the terminal-status guard in the webhook handler
            // will absorb the webhook if it arrives after this (no double ledger write), but the vendor
            // will NOT be marked paid. A reconciliation cron should query the payment-service for the
            // outcome of payouts stuck in Processing beyond a timeout.
            if ($payout->status === PayoutStatus::Processing) {
                LogHelper::logEntry(LogHelper::LOG_ERROR, 'Marking Processing payout as failed — gateway may have already disbursed; manual reconciliation required', [
                    'payout_id' => $payoutId,
                ]);
            }
            $this->payouts->markFailed($payout);
        });
    }

    /** Deterministic per payout: a retry reuses the key so the payment-service de-dupes (ADR-09). */
    private function idempotencyKey(string $payoutId): string
    {
        return "payout-exec:{$payoutId}";
    }
}
