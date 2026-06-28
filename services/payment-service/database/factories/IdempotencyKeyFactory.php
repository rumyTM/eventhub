<?php

namespace Database\Factories;

use App\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', (string) Str::random(32)),
            'response_payload' => ['payment_id' => (string) Str::ulid()],
            'status' => 'completed',
        ];
    }
}
