<?php

namespace Database\Factories;

use App\Models\Attendee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendee>
 */
class AttendeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->attendee(),
            'phone' => '+8801[PLACEHOLDER]',
        ];
    }
}
