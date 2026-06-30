<?php

namespace App\Services\Payouts;

use App\Helpers\LogHelper;

/**
 * Daily payout batch entry point (CLAUDE.md §G — ProcessPayoutBatch).
 *
 * Delegates all eligibility checks, commission math, threshold enforcement, and idempotency
 * to {@see PayoutBuildService}. The no-double-pay guarantee comes from there:
 *   - batchId = YYYY-MM-DD (today) → deterministic idempotency key `payout:{vendorId}:{batchId}`
 *   - Re-running on the same day returns the existing payout row, never a second one.
 *   - A mid-batch crash leaves already-created rows untouched; re-run skips them.
 */
final class ProcessPayoutBatchService
{
    public function __construct(
        private readonly PayoutBuildService $buildService,
    ) {}

    /**
     * Build pending payout records for all eligible vendors in the given batch window.
     *
     * @return array{batch_id: string, built: int}
     */
    public function handle(string $batchId): array
    {
        LogHelper::logEntry(LogHelper::LOG_INFO, 'ProcessPayoutBatch started', ['batch_id' => $batchId]);

        $payouts = $this->buildService->buildAll($batchId);

        LogHelper::logEntry(LogHelper::LOG_INFO, 'ProcessPayoutBatch finished', [
            'batch_id' => $batchId,
            'built' => count($payouts),
        ]);

        return ['batch_id' => $batchId, 'built' => count($payouts)];
    }
}
