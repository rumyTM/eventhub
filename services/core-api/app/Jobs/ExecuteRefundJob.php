<?php

namespace App\Jobs;

use App\Enums\RefundStatus;
use App\Helpers\LogHelper;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Executes an approved refund against the payment-service, off the request path. Dispatched ONLY for a
 * newly-created, policy-approved refund (see RefundController) — never for a duplicate/ineligible request.
 *
 * IMPORTANT — Chunk A scope: this job is wired up now but **does not move money or write any reversal
 * ledger entry yet**. The actual payment-service call + ledger reversal land in Chunk C. The guard below
 * (no-op unless the refund is still `requested`) is the idempotency contract that Chunk C will build on:
 * a re-dispatch or a refund already past `requested` is a safe no-op.
 */
class ExecuteRefundJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** Hold the uniqueness lock across the gateway delay + a few retries. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $refundId,
    ) {}

    /** One in-flight execution per refund. */
    public function uniqueId(): string
    {
        return $this->refundId;
    }

    public function handle(RefundRepositoryInterface $refunds): void
    {
        $refund = $refunds->find($this->refundId);

        // Refund vanished or already moved past `requested` (executing/terminal) — idempotent no-op.
        if ($refund === null || $refund->status !== RefundStatus::Requested) {
            return;
        }

        // Chunk C will, in order: (1) flip requested→pending INSIDE a transaction BEFORE the outbound
        // call (so a worker crash mid-call can't re-call the gateway once the uniqueness lock lapses —
        // the pending guard makes a retry a no-op until the result lands), (2) call
        // PaymentServiceContract::refund() with an idempotency key, (3) on the signed result write the
        // reversal `ledger_entry` and flip the refund to completed/failed.
        // Until then we record that execution is queued — NO money is moved here.
        LogHelper::logEntry(LogHelper::LOG_INFO, 'Refund approved and queued; execution deferred to Chunk C', [
            'refund_id' => $refund->id,
            'amount' => $refund->amount,         // integer minor units — never card data
            'policy_applied' => $refund->policy_applied,
        ]);
    }
}
