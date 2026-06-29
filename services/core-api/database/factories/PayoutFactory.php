<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gross = fake()->numberBetween(50_000, 500_000);
        $commission = (int) ($gross * 0.1);
        $net = $gross - $commission;

        return [
            'vendor_id' => Vendor::factory()->verified(),
            'gross' => $gross,
            'commission' => $commission,
            'net' => $net,
            'payable' => $net,   // no adjustments by default
            'reserved_refund' => 0,
            'currency' => 'BDT',
            'status' => PayoutStatus::Pending,
            'batch_id' => (string) Str::ulid(),
            'idempotency_key' => 'payout:'.Str::ulid().':batch-'.Str::random(8),
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => PayoutStatus::Paid]);
    }

    public function failed(): static
    {
        return $this->state(['status' => PayoutStatus::Failed]);
    }

    public function processing(): static
    {
        return $this->state(['status' => PayoutStatus::Processing]);
    }
}
