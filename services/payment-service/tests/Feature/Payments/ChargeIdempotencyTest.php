<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\Payments\IdempotencyKeyConflictException;
use App\Models\IdempotencyKey;
use App\Models\Payment;
use App\Repositories\Contracts\IdempotencyKeyRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Payments\ChargeService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Idempotency is the core money guarantee (CLAUDE.md §D/§H): the same Idempotency-Key must create
 * exactly one charge. A replay with the same body returns the SAME record (no second charge); a
 * replay with a different body is a 409 conflict.
 */
class ChargeIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ChargeService
    {
        return app(ChargeService::class);
    }

    /**
     * @return array{order_id: string, gateway: string, amount: int, currency: string}
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'order_id' => '01J9Z0ORDER0000000000000001',
            'gateway' => 'stripe_sim',
            'amount' => 25_000,
            'currency' => 'BDT',
        ], $overrides);
    }

    public function test_a_charge_is_created_pending_with_the_given_amount(): void
    {
        $payment = $this->service()->createCharge('idem-key-1', $this->payload());

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(25_000, $payment->amount);
        $this->assertSame('BDT', $payment->currency);
        $this->assertSame('stripe_sim', $payment->gateway->value);
        $this->assertNull($payment->gateway_ref); // resolved only when the gateway reports back
        $this->assertSame(1, Payment::count());
    }

    public function test_same_key_and_body_returns_the_same_record_without_a_second_charge(): void
    {
        $first = $this->service()->createCharge('idem-key-2', $this->payload());
        $second = $this->service()->createCharge('idem-key-2', $this->payload());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::count());          // no second charge
        $this->assertSame(1, IdempotencyKey::count());
    }

    public function test_same_key_with_a_different_body_is_a_409_conflict(): void
    {
        $this->service()->createCharge('idem-key-3', $this->payload());

        try {
            $this->service()->createCharge('idem-key-3', $this->payload(['amount' => 99_999]));
            $this->fail('Expected IdempotencyKeyConflictException.');
        } catch (IdempotencyKeyConflictException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $this->assertSame(1, Payment::count()); // the conflicting request created nothing
    }

    public function test_a_concurrent_duplicate_key_is_resolved_as_a_replay_not_a_double_charge(): void
    {
        // The "winner" of the race: a payment that already committed under this key.
        $winner = Payment::factory()->create();

        $winnerKey = new IdempotencyKey([
            'key' => 'race-key',
            'request_hash' => 'unused-on-the-rescue-path',
            'response_payload' => ['payment_id' => $winner->id],
            'status' => 'completed',
        ]);

        // Simulate the race: our request saw NO key in the pre-transaction check (null), then lost
        // the unique-key insert INSIDE the transaction (UniqueConstraintViolationException — which
        // rolls the whole transaction back, including our just-created Payment), then on rescue
        // finds the winner's key and replays it. Never a 500, never a second charge.
        $idem = Mockery::mock(IdempotencyKeyRepositoryInterface::class);
        $idem->shouldReceive('findByKey')->with('race-key')->once()->ordered()->andReturnNull();
        $idem->shouldReceive('create')->once()->andThrow(
            new UniqueConstraintViolationException('sqlite', 'insert into idempotency_keys', [], new \Exception('duplicate key'))
        );
        $idem->shouldReceive('findByKey')->with('race-key')->once()->ordered()->andReturn($winnerKey);

        $service = new ChargeService(app(PaymentRepositoryInterface::class), $idem);

        $result = $service->createCharge('race-key', $this->payload());

        $this->assertSame($winner->id, $result->id); // replayed the winner, not a fresh charge
        $this->assertSame(1, Payment::count());        // the losing transaction's Payment was rolled back
    }
}
