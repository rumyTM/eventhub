<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Jobs\DeliverChargeResultJob;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\Payments\ChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Charge resolution (CLAUDE.md §B/§G): the configured simulator decides the outcome deterministically
 * (forced) and the result is written once to the append-only `transactions` ledger. A successful
 * charge is positive money-in; a failed one moved nothing and is recorded as 0. Re-resolving (a job
 * retry) never rolls the gateway or writes the ledger twice.
 */
class ChargeResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function pendingPayment(): Payment
    {
        return Payment::factory()->create([
            'gateway' => 'stripe_sim',
            'status' => PaymentStatus::Pending->value,
            'amount' => 25_000,
            'currency' => 'BDT',
            'gateway_ref' => null,
        ]);
    }

    private function forceGateway(string $outcome): void
    {
        config(['gateways.gateways.stripe_sim.force' => $outcome]); // 'succeed' | 'fail'
    }

    public function test_a_forced_success_marks_the_payment_succeeded_and_writes_a_positive_ledger_row(): void
    {
        $this->forceGateway('succeed');
        $payment = $this->pendingPayment();

        $resolved = app(ChargeService::class)->resolve($payment->id);

        $this->assertSame(PaymentStatus::Succeeded, $resolved->status);
        $this->assertNotNull($resolved->gateway_ref); // a clearly-fake simulated ref — never card data

        $this->assertSame(1, Transaction::count());
        $ledger = Transaction::first();
        $this->assertSame(TransactionType::Charge, $ledger->type);
        $this->assertSame(25_000, $ledger->amount); // positive money-in
        $this->assertSame('BDT', $ledger->currency);
        $this->assertSame($payment->id, $ledger->payment_id);
    }

    public function test_a_forced_failure_marks_the_payment_failed_and_writes_a_zero_ledger_row(): void
    {
        $this->forceGateway('fail');
        $payment = $this->pendingPayment();

        $resolved = app(ChargeService::class)->resolve($payment->id);

        $this->assertSame(PaymentStatus::Failed, $resolved->status);

        $this->assertSame(1, Transaction::count());
        $ledger = Transaction::first();
        $this->assertSame(TransactionType::Charge, $ledger->type);
        $this->assertSame(0, $ledger->amount); // a failed charge moved no money
    }

    public function test_resolving_twice_does_not_roll_the_gateway_or_write_the_ledger_again(): void
    {
        $this->forceGateway('succeed');
        $payment = $this->pendingPayment();

        $first = app(ChargeService::class)->resolve($payment->id);
        $second = app(ChargeService::class)->resolve($payment->id);

        $this->assertSame($first->gateway_ref, $second->gateway_ref); // same outcome, not re-rolled
        $this->assertSame(PaymentStatus::Succeeded, $second->status);
        $this->assertSame(1, Transaction::count()); // exactly one ledger row across both calls
    }

    public function test_an_idempotent_replay_after_resolution_returns_the_updated_terminal_payment(): void
    {
        $this->forceGateway('succeed');
        $charges = app(ChargeService::class);

        $payload = [
            'order_id' => '01J9Z0ORDER0000000000000001',
            'gateway' => 'stripe_sim',
            'amount' => 25_000,
            'currency' => 'BDT',
        ];

        $created = $charges->createCharge('replay-key', $payload);
        $charges->resolve($created->id); // charge resolves to succeeded

        // A duplicate request with the same key arrives AFTER resolution — it must see the live
        // terminal state (status + gateway_ref), not the stale pending snapshot.
        $replay = $charges->createCharge('replay-key', $payload);

        $this->assertSame($created->id, $replay->id);
        $this->assertSame(PaymentStatus::Succeeded, $replay->status);
        $this->assertNotNull($replay->gateway_ref);
        $this->assertSame(1, Payment::count());
    }

    public function test_scheduling_resolution_for_an_already_terminal_payment_queues_no_job(): void
    {
        Queue::fake();

        $payment = Payment::factory()->succeeded()->create(['gateway' => 'stripe_sim']);

        app(ChargeService::class)->scheduleResolution($payment);

        Queue::assertNotPushed(DeliverChargeResultJob::class); // nothing to resolve — no duplicate work
    }
}
