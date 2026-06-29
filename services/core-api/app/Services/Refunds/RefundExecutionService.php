<?php

namespace App\Services\Refunds;

use App\Contracts\PaymentServiceContract;
use App\Enums\RefundStatus;
use App\Helpers\LogHelper;
use App\Jobs\ExecuteRefundJob;
use App\Repositories\Contracts\RefundRepositoryInterface;
use App\Services\Payments\ChargeOrderService;
use Illuminate\Support\Facades\DB;

/**
 * Initiates an approved refund against the payment-service, off the request path (CLAUDE.md §F/§H).
 * Mirrors {@see ChargeOrderService} for the charge path. Runs from the queued
 * {@see ExecuteRefundJob}, so it is written to be safe to re-run:
 *
 *   - **Flip `requested` → `pending` BEFORE the call**, inside a row-locked transaction. Once pending,
 *     the only thing that resolves the refund is the signed refund webhook (or a 4xx fast-fail). This
 *     never marks the refund `completed` locally — money movement is confirmed by the gateway, not here.
 *   - **Deterministic Idempotency-Key** (`refund:{id}`): a job retry reuses the same key, so the
 *     payment-service de-dupes and never refunds twice (ADR-09).
 *   - **5xx/timeout → the call throws**, bubbling to the job for a backed-off retry; the refund stays
 *     `pending`. **4xx → the job fast-fails** and calls {@see markExecutionFailed()} (no webhook will
 *     arrive for a request the payment-service rejected, so we resolve it locally as failed — no ledger).
 */
final class RefundExecutionService
{
    public function __construct(
        private readonly RefundRepositoryInterface $refunds,
        private readonly PaymentServiceContract $paymentService,
    ) {}

    public function execute(string $refundId): void
    {
        $refund = $this->refunds->find($refundId);

        // Vanished or already resolved (completed/failed) — idempotent no-op.
        if ($refund === null || $refund->status->isTerminal()) {
            return;
        }

        // Flip requested → pending atomically (a retry that finds it already pending skips the flip and
        // re-POSTs with the same idempotency key — the payment-service de-dupes).
        if ($refund->status === RefundStatus::Requested) {
            DB::transaction(function () use ($refundId): void {
                $locked = $this->refunds->findForUpdate($refundId);
                if ($locked !== null && $locked->status === RefundStatus::Requested) {
                    $this->refunds->markPending($locked);
                }
            });
            $refund = $this->refunds->find($refundId);
        }

        // Resolve the original charge of record. external_ref (the payment-service charge ref) is set at
        // charge initiation/settlement; without it we cannot route the refund — fail locally (no webhook
        // will come) rather than leave it pending forever.
        $refund?->loadMissing('payment');
        $payment = $refund?->payment;

        if ($payment === null || $payment->external_ref === null) {
            LogHelper::logEntry(LogHelper::LOG_ERROR, 'Refund has no charge of record to execute against', [
                'refund_id' => $refundId,
            ]);
            $this->markExecutionFailed($refundId);

            return;
        }

        // Returns a pending ack; the terminal result arrives via the signed refund webhook. A non-2xx
        // throws (RequestException) — handled by the job (4xx fast-fail, 5xx retry).
        $result = $this->paymentService->refund(
            paymentRef: $payment->external_ref,
            amount: $refund->amount,         // integer minor units — never card data
            currency: $payment->currency,
            reason: $refund->reason?->value,
            idempotencyKey: $this->idempotencyKey($refund->id),
        );

        LogHelper::logEntry(LogHelper::LOG_INFO, 'Refund execution initiated; awaiting signed webhook', [
            'refund_id' => $refund->id,
            'refund_ref' => $result->ref,    // payment-service refund ref — never card data
        ]);
    }

    /**
     * Resolve a refund as `failed` locally — used only when the payment-service permanently rejects the
     * request (4xx) so no webhook will ever arrive. No ledger is written (failure moves no money).
     */
    public function markExecutionFailed(string $refundId): void
    {
        DB::transaction(function () use ($refundId): void {
            $refund = $this->refunds->findForUpdate($refundId);
            if ($refund !== null && ! $refund->status->isTerminal()) {
                $this->refunds->markFailed($refund);
            }
        });
    }

    /** Deterministic per refund: a retry reuses the key so the payment-service de-dupes (ADR-09). */
    private function idempotencyKey(string $refundId): string
    {
        return "refund:{$refundId}";
    }
}
