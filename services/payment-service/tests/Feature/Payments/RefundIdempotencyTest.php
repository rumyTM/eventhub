<?php

namespace Tests\Feature\Payments;

use App\Enums\RefundStatus;
use App\Exceptions\Payments\IdempotencyKeyConflictException;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Payments\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Idempotency is the core money guarantee for refunds too (CLAUDE.md §D/§H), mirroring the charge path:
 * the same Idempotency-Key must create exactly one refund. A replay with the same body returns the SAME
 * record (no second refund); a replay with a different body is a 409 conflict.
 */
class RefundIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function service(): RefundService
    {
        return app(RefundService::class);
    }

    private function charge(int $amount = 25_000): Payment
    {
        return Payment::factory()->succeeded()->create([
            'gateway' => 'stripe_sim', 'amount' => $amount, 'currency' => 'BDT',
        ]);
    }

    /**
     * @return array{payment_ref: string, amount: int, currency: string, reason: string|null}
     */
    private function payload(Payment $charge, array $overrides = []): array
    {
        return array_merge([
            'payment_ref' => $charge->id,
            'amount' => 10_000,
            'currency' => 'BDT',
            'reason' => 'attendee_requested',
        ], $overrides);
    }

    public function test_a_refund_is_created_pending_copying_gateway_and_order_from_the_charge(): void
    {
        $charge = $this->charge();

        $refund = $this->service()->createRefund('refund-idem-1', $this->payload($charge));

        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame(10_000, $refund->amount);
        $this->assertSame('BDT', $refund->currency);
        $this->assertSame($charge->id, $refund->payment_id);
        $this->assertSame($charge->order_id, $refund->order_id);        // copied from the original charge
        $this->assertSame($charge->gateway->value, $refund->gateway->value);
        $this->assertNull($refund->gateway_ref);                        // resolved only when the gateway reports back
        $this->assertSame(1, Refund::count());
    }

    public function test_same_key_and_body_returns_the_same_record_without_a_second_refund(): void
    {
        $charge = $this->charge();

        $first = $this->service()->createRefund('refund-idem-2', $this->payload($charge));
        $second = $this->service()->createRefund('refund-idem-2', $this->payload($charge));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Refund::count()); // no second refund
    }

    public function test_same_key_with_a_different_body_is_a_409_conflict(): void
    {
        $charge = $this->charge();
        $this->service()->createRefund('refund-idem-3', $this->payload($charge));

        try {
            $this->service()->createRefund('refund-idem-3', $this->payload($charge, ['amount' => 5_000]));
            $this->fail('Expected IdempotencyKeyConflictException.');
        } catch (IdempotencyKeyConflictException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertSame(1, Refund::count()); // the conflicting request created nothing
    }
}
