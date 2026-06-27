<?php

namespace Database\Factories;

use App\Enums\KycStatus;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 *
 * KYC/PII fields use demo-safe [PLACEHOLDER] values only — never real NID/TIN/bank data. These columns
 * are encrypted at rest by the model casts.
 */
class VendorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->vendor(),
            'business_name' => fake()->company(),
            'legal_name' => fake()->company().' Ltd.',
            'trade_license_no' => 'TL-[PLACEHOLDER]',
            'tin_bin' => '[PLACEHOLDER-TIN]',
            'representative_nid' => '[PLACEHOLDER-NID]',
            'contact_phone' => '+8801[PLACEHOLDER]',
            'address' => fake()->city().', Bangladesh',
            'kyc_status' => KycStatus::Pending,
            'payout_account' => ['bank' => '[PLACEHOLDER-BANK]', 'account' => '[PLACEHOLDER-ACC]'],
            'webhook_url' => null,
            'webhook_secret' => null,
            'commission_rate' => null,
        ];
    }

    /** A KYC-verified vendor (eligible to publish events / receive payouts). */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'kyc_status' => KycStatus::Verified,
            'submitted_at' => now()->subDays(2),
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now()->subDay(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'kyc_status' => KycStatus::Rejected,
            'submitted_at' => now()->subDays(2),
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now()->subDay(),
            'rejection_reason' => 'Documents illegible ([PLACEHOLDER]).',
        ]);
    }
}
