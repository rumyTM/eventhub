<?php

namespace Tests\Feature\Payments;

use App\Enums\RefundStatus;
use App\Enums\TransactionType;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Transaction;
use App\Services\Payments\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Refund resolution (CLAUDE.md §B/§G), mirroring charge resolution: the configured simulator decides the
 * outcome deterministically (forced) and the result is written once to the append-only `transactions`
 * ledger. A successful refund is money OUT (NEGATIVE signed amount); a failed one moved nothing and is
 * recorded as 0. Re-resolving (a job retry) never rolls the gateway or writes the ledger twice.
 */
class RefundResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function pendingRefund(int $amount = 25_000): Refund
    {
        $charge = Payment::factory()->succeeded()->create([
            'gateway' => 'stripe_sim', 'amount' => $amount, 'currency' => 'BDT',
        ]);

        return Refund::factory()->create([
            'payment_id' => $charge->id,
            'order_id' => $charge->order_id,
            'gateway' => 'stripe_sim',
            'status' => RefundStatus::Pending->value,
            'amount' => $amount,
            'currency' => 'BDT',
            'gateway_ref' => null,
        ]);
    }

    private function forceGateway(string $outcome): void
    {
        config(['gateways.gateways.stripe_sim.force' => $outcome]); // 'succeed' | 'fail'
    }

    public function test_a_forced_success_marks_the_refund_completed_and_writes_a_negative_ledger_row(): void
    {
        $this->forceGateway('succeed');
        $refund = $this->pendingRefund(25_000);

        $resolved = app(RefundService::class)->resolve($refund->id);

        $this->assertSame(RefundStatus::Completed, $resolved->status);
        $this->assertNotNull($resolved->gateway_ref); // a clearly-fake simulated ref — never card data

        $this->assertSame(1, Transaction::count());
        $ledger = Transaction::first();
        $this->assertSame(TransactionType::Refund, $ledger->type);
        $this->assertSame(-25_000, $ledger->amount); // money OUT — negative signed amount
        $this->assertSame('BDT', $ledger->currency);
        $this->assertSame($refund->payment_id, $ledger->payment_id); // keyed to the original charge
    }

    public function test_a_forced_failure_marks_the_refund_failed_and_writes_a_zero_ledger_row(): void
    {
        $this->forceGateway('fail');
        $refund = $this->pendingRefund(25_000);

        $resolved = app(RefundService::class)->resolve($refund->id);

        $this->assertSame(RefundStatus::Failed, $resolved->status);

        $this->assertSame(1, Transaction::count());
        $ledger = Transaction::first();
        $this->assertSame(TransactionType::Refund, $ledger->type);
        $this->assertSame(0, $ledger->amount); // a failed refund moved no money
    }

    public function test_resolving_twice_does_not_roll_the_gateway_or_write_the_ledger_again(): void
    {
        $this->forceGateway('succeed');
        $refund = $this->pendingRefund();

        $first = app(RefundService::class)->resolve($refund->id);
        $second = app(RefundService::class)->resolve($refund->id);

        $this->assertSame($first->gateway_ref, $second->gateway_ref); // same outcome, not re-rolled
        $this->assertSame(RefundStatus::Completed, $second->status);
        $this->assertSame(1, Transaction::count()); // exactly one ledger row across both calls
    }
}
