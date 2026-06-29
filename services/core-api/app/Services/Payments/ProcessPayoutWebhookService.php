<?php

namespace App\Services\Payments;

use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Exceptions\Payments\PayoutWebhookMismatchException;
use App\Jobs\SendPayoutNotificationJob;
use App\Repositories\Contracts\LedgerEntryRepositoryInterface;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Applies a payment-service PAYOUT webhook result (CLAUDE.md §F/§H; ADR-09/10/13/20). Runs in ONE
 * transaction with the payout row locked FOR UPDATE; every step is idempotent on replay.
 *
 * Order of guards is deliberate:
 *   1. unknown payout_ref       → no-op (stale/foreign callback never errors);
 *   2. already terminal (paid/failed) → no-op (replay — so a re-delivered success never writes a
 *                                        second ledger row or double-pays the vendor);
 *   3. amount mismatch          → reject (422) and mutate nothing (tamper guard).
 *
 * On SUCCESS (`completed`): mark payout `paid`; mark PayoutItems settled; write ONE `payout` ledger
 * entry (NEGATIVE per ADR-13 — a payout debits the vendor's balance in the ledger). Enqueue the
 * vendor notification after commit (publish-only, fire-and-forget).
 *
 * On FAILURE (`failed`): mark payout `failed`; NO ledger row, NO items change — failure moves no money.
 */
final class ProcessPayoutWebhookService
{
    public function __construct(
        private readonly PayoutRepositoryInterface $payouts,
        private readonly LedgerEntryRepositoryInterface $ledger,
    ) {}

    /**
     * @param  array{event: string, payout_ref: string, vendor_id: string, status: array{value: string}, amount: int, currency: string}  $payload
     */
    public function handle(array $payload): void
    {
        $resolvedPayoutId = null;
        $resolvedStatus = null;

        DB::transaction(function () use ($payload, &$resolvedPayoutId, &$resolvedStatus): void {
            // The payload's `payout_ref` IS the core-api Payout ID (sent as `payout_ref` to payment-service).
            $payout = $this->payouts->findForUpdate($payload['payout_ref']);

            // (1) Unknown payout — idempotent no-op (stale or foreign callback).
            if ($payout === null) {
                return;
            }

            // (2) Already terminal — replay. The idempotent guard prevents a second ledger write.
            if (in_array($payout->status, [PayoutStatus::Paid, PayoutStatus::Failed], true)) {
                return;
            }

            // (3) Vendor ID must match — cheap tamper guard in addition to the HMAC (N-3).
            if ($payout->vendor_id !== $payload['vendor_id']) {
                throw new PayoutWebhookMismatchException;
            }

            // (4) Amount must match what core-api sent. A mismatch means the webhook is tampered or
            //     mis-routed — reject the entire transaction, move no money.
            if ((int) $payload['amount'] !== $payout->payable) {
                throw new PayoutWebhookMismatchException;
            }

            // payment-service vocabulary: 'completed' means success; 'failed' means failure.
            // Core-api maps 'completed' → PayoutStatus::Paid (its own `paid` status).
            if ($payload['status']['value'] === 'failed') {
                $this->payouts->markFailed($payout); // no money moved → no ledger

                $resolvedPayoutId = $payout->id;
                $resolvedStatus = PayoutStatus::Failed->value;

                return;
            }

            // --- SUCCESS ('completed' from payment-service → 'paid' in core-api) ---
            $this->payouts->markPaid($payout);
            $this->payouts->markItemsSettled($payout->id);

            // One NEGATIVE payout ledger entry (ADR-13): a payout debits the vendor balance in the
            // append-only `ledger_entries` table. Written ONLY on confirmed success — never on failure.
            $this->ledger->create([
                'vendor_id' => $payout->vendor_id,
                'subject_type' => 'payout',
                'subject_id' => $payout->id,
                'entry_type' => LedgerEntryType::Payout->value,
                'amount' => -$payout->payable,   // NEGATIVE — debit vendor balance (ADR-13)
                'currency' => $payout->currency,
            ]);

            $resolvedPayoutId = $payout->id;
            $resolvedStatus = PayoutStatus::Paid->value;
        });

        // Off the request path, after the transaction commits. Enqueue notification only when the
        // payout actually resolved (not on an idempotent replay or no-op).
        if ($resolvedPayoutId !== null && $resolvedStatus !== null) {
            SendPayoutNotificationJob::dispatch($resolvedPayoutId, $resolvedStatus);
        }
    }
}
