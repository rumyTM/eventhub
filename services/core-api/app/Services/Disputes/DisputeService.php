<?php

namespace App\Services\Disputes;

use App\Enums\DisputeStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Jobs\ExecuteRefundJob;
use App\Models\Dispute;
use App\Models\User;
use App\Repositories\Contracts\DisputeRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\RefundRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Admin-only dispute resolution (ADR-11). Two outcomes:
 *   - resolve: admin approves a refund override (bypasses the time-based policy), creates the
 *     refund row, dispatches ExecuteRefundJob, marks the dispute resolved with the refund linked.
 *   - reject: admin denies the request, marks the dispute rejected with a resolution note.
 *
 * Both operations are idempotent: calling resolve/reject on an already-terminal dispute returns
 * the existing dispute unchanged rather than throwing.
 */
final class DisputeService
{
    public function __construct(
        private readonly DisputeRepositoryInterface $disputes,
        private readonly PaymentRepositoryInterface $payments,
        private readonly RefundRepositoryInterface $refunds,
    ) {}

    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->disputes->listOpen($perPage);
    }

    /**
     * Approve the dispute: create a full-remaining-balance refund (admin override, no policy window)
     * and mark the dispute resolved.
     */
    public function resolve(Dispute $dispute, User $admin, ?string $resolution = null): Dispute
    {
        if ($dispute->status !== DisputeStatus::Open) {
            return $dispute; // idempotent
        }

        $dispute->loadMissing('order');
        $order = $dispute->order;

        $payment = $this->payments->succeededForOrder($order->id)
            ?? throw new HttpException(422, 'No completed payment found for this order.');

        return DB::transaction(function () use ($dispute, $payment, $order, $admin, $resolution): Dispute {
            $alreadyRefunded = $this->refunds->refundedTotalForOrder($order->id);
            $remaining = max(0, $payment->amount - $alreadyRefunded);

            if ($remaining <= 0) {
                throw new HttpException(422, 'This order has already been fully refunded.');
            }

            $refund = $this->refunds->create([
                'payment_id' => $payment->id,
                'amount' => $remaining,
                'policy_applied' => 'admin_override',
                'status' => RefundStatus::Requested->value,
                'reason' => RefundReason::AttendeeRequested->value,
            ]);

            ExecuteRefundJob::dispatch($refund->id)->afterCommit();

            return $this->disputes->update($dispute, [
                'status' => DisputeStatus::Resolved->value,
                'refund_id' => $refund->id,
                'resolved_by' => $admin->id,
                'resolution' => $resolution,
            ]);
        });
    }

    /**
     * Reject the dispute: no refund is issued; the dispute is closed with a reason note.
     */
    public function reject(Dispute $dispute, User $admin, string $resolution): Dispute
    {
        if ($dispute->status !== DisputeStatus::Open) {
            return $dispute; // idempotent
        }

        return $this->disputes->update($dispute, [
            'status' => DisputeStatus::Rejected->value,
            'resolved_by' => $admin->id,
            'resolution' => $resolution,
        ]);
    }
}
