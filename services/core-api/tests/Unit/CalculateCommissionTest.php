<?php

namespace Tests\Unit;

use App\Actions\Payouts\CalculateCommission;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CalculateCommissionTest extends TestCase
{
    public function test_applies_the_rate_with_integer_math(): void
    {
        $action = new CalculateCommission;

        // 10% of 100000 minor units = 10000; pure integer, no float.
        $this->assertSame(10_000, $action->handle(100_000, '0.1000'));
        $this->assertSame(3_000, $action->handle(30_000, '0.1000'));
    }

    public function test_rounds_half_up(): void
    {
        $action = new CalculateCommission;

        // 12.5% of 125 = 15.625 → 16 (half-up), never truncated to 15 or drifted via float.
        $this->assertSame(16, $action->handle(125, '0.1250'));
    }

    public function test_throws_on_a_blank_rate_instead_of_silently_charging_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CalculateCommission)->handle(100_000, '');
    }

    public function test_throws_on_a_non_numeric_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CalculateCommission)->handle(100_000, 'abc');
    }
}
