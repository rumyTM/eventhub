<?php

namespace Tests\Feature\Notifications;

use App\Contracts\NotificationPublisherContract;
use App\Enums\KycStatus;
use App\Enums\PayoutStatus;
use App\Jobs\SendKycDecisionEmailJob;
use App\Jobs\SendVendorOrderWebhookJob;
use App\Jobs\SendVendorPayoutWebhookJob;
use App\Jobs\SendVendorSoldOutWebhookJob;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payout;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\PayoutRepositoryInterface;
use App\Repositories\Contracts\TicketTypeRepositoryInterface;
use App\Repositories\Contracts\VendorRepositoryInterface;
use App\Services\Vendors\VendorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Asserts that each domain event dispatches the correct notification job with the correct notification
 * type. Two layers of assertion per notification:
 *   1. Service layer  — Queue::assertPushed confirms the job is dispatched.
 *   2. Job layer      — mock publisher confirms publishEmail/publishWebhook is called with the right type.
 */
class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    // ── vendor.kyc_decision ────────────────────────────────────────────────────

    public function test_verifying_vendor_dispatches_kyc_decision_job_with_approved(): void
    {
        Queue::fake();

        $vendor = Vendor::factory()->create([
            'kyc_status' => KycStatus::Pending,
            'submitted_at' => now(),
        ]);
        $admin = User::factory()->admin()->create();

        app(VendorService::class)->verify($vendor, $admin);

        Queue::assertPushed(SendKycDecisionEmailJob::class, function (SendKycDecisionEmailJob $job) use ($vendor) {
            return $job->vendorId === $vendor->id && $job->decision === 'approved';
        });
    }

    public function test_rejecting_vendor_dispatches_kyc_decision_job_with_rejected(): void
    {
        Queue::fake();

        $vendor = Vendor::factory()->create([
            'kyc_status' => KycStatus::Pending,
            'submitted_at' => now(),
        ]);
        $admin = User::factory()->admin()->create();

        app(VendorService::class)->reject($vendor, $admin, 'Documents unclear');

        Queue::assertPushed(SendKycDecisionEmailJob::class, function (SendKycDecisionEmailJob $job) use ($vendor) {
            return $job->vendorId === $vendor->id
                && $job->decision === 'rejected'
                && $job->rejectionReason === 'Documents unclear';
        });
    }

    public function test_kyc_decision_job_publishes_vendor_kyc_decision_type(): void
    {
        $vendor = Vendor::factory()->create();

        $publisher = $this->mock(NotificationPublisherContract::class);
        $publisher->shouldReceive('publishEmail')
            ->once()
            ->withArgs(function (string $type, array $recipient, array $data, string $idempotencyKey) use ($vendor) {
                return $type === 'vendor.kyc_decision'
                    && $data['decision'] === 'approved'
                    && $recipient['vendorId'] === $vendor->id
                    && $idempotencyKey === "notif:vendor.kyc_decision:{$vendor->id}:approved";
            });

        (new SendKycDecisionEmailJob($vendor->id, 'approved'))
            ->handle($publisher, app(VendorRepositoryInterface::class));
    }

    // ── order.created (vendor webhook) ─────────────────────────────────────────

    public function test_vendor_order_webhook_job_publishes_order_created_type(): void
    {
        $vendor = Vendor::factory()->create([
            'webhook_url' => 'https://vendor.example.com/hook',
            'kyc_status' => KycStatus::Verified,
        ]);
        $event = Event::factory()->completed()->forVendor($vendor)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create(['price' => 50_000]);
        $order = Order::factory()->paid()->create();

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'unit_price' => 50_000,
        ]);

        $publisher = $this->mock(NotificationPublisherContract::class);
        $publisher->shouldReceive('publishWebhook')
            ->once()
            ->withArgs(function (string $type, string $url, array $recipient, array $data, string $idempotencyKey) use ($vendor, $order) {
                return $type === 'order.created'
                    && $url === 'https://vendor.example.com/hook'
                    && $recipient['vendorId'] === $vendor->id
                    && $data['orderId'] === $order->id
                    && $idempotencyKey === "notif:order.created:{$order->id}:{$vendor->id}";
            });

        (new SendVendorOrderWebhookJob($order->id))
            ->handle($publisher, app(OrderRepositoryInterface::class));
    }

    public function test_vendor_order_webhook_job_skips_vendor_without_webhook_url(): void
    {
        $vendor = Vendor::factory()->create(['webhook_url' => null]);
        $event = Event::factory()->completed()->forVendor($vendor)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create();
        $order = Order::factory()->paid()->create();

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 10_000,
        ]);

        $publisher = $this->mock(NotificationPublisherContract::class);
        $publisher->shouldNotReceive('publishWebhook');

        (new SendVendorOrderWebhookJob($order->id))
            ->handle($publisher, app(OrderRepositoryInterface::class));
    }

    // ── event.sold_out (vendor webhook) ────────────────────────────────────────

    public function test_sold_out_webhook_job_publishes_event_sold_out_when_at_capacity(): void
    {
        $vendor = Vendor::factory()->create([
            'webhook_url' => 'https://vendor.example.com/hook',
            'kyc_status' => KycStatus::Verified,
        ]);
        $event = Event::factory()->completed()->forVendor($vendor)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'quantity_total' => 10,
            'quantity_sold' => 10, // fully sold out
        ]);
        $order = Order::factory()->paid()->create();

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 10_000,
        ]);

        $publisher = $this->mock(NotificationPublisherContract::class);
        $publisher->shouldReceive('publishWebhook')
            ->once()
            ->withArgs(function (string $type, string $url, array $recipient, array $data, string $idempotencyKey) use ($vendor, $ticketType) {
                return $type === 'event.sold_out'
                    && $url === 'https://vendor.example.com/hook'
                    && $recipient['vendorId'] === $vendor->id
                    && $data['ticketTypeId'] === $ticketType->id
                    && $idempotencyKey === "notif:event.sold_out:{$ticketType->id}";
            });

        (new SendVendorSoldOutWebhookJob($order->id))
            ->handle($publisher, app(OrderRepositoryInterface::class), app(TicketTypeRepositoryInterface::class));
    }

    public function test_sold_out_webhook_job_skips_when_not_at_capacity(): void
    {
        $vendor = Vendor::factory()->create([
            'webhook_url' => 'https://vendor.example.com/hook',
            'kyc_status' => KycStatus::Verified,
        ]);
        $event = Event::factory()->completed()->forVendor($vendor)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'quantity_total' => 100,
            'quantity_sold' => 9, // not sold out
        ]);
        $order = Order::factory()->paid()->create();

        OrderItem::create([
            'order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 10_000,
        ]);

        $publisher = $this->mock(NotificationPublisherContract::class);
        $publisher->shouldNotReceive('publishWebhook');

        (new SendVendorSoldOutWebhookJob($order->id))
            ->handle($publisher, app(OrderRepositoryInterface::class), app(TicketTypeRepositoryInterface::class));
    }

    // ── payout.sent (vendor webhook) ────────────────────────────────────────────

    public function test_payout_webhook_job_publishes_payout_sent_type(): void
    {
        $vendor = Vendor::factory()->create([
            'webhook_url' => 'https://vendor.example.com/hook',
            'kyc_status' => KycStatus::Verified,
        ]);

        $payout = Payout::create([
            'vendor_id' => $vendor->id,
            'gross' => 100_000,
            'commission' => 10_000,
            'net' => 90_000,
            'payable' => 90_000,
            'reserved_refund' => 0,
            'currency' => 'BDT',
            'status' => PayoutStatus::Paid->value,
            'batch_id' => '2026-06-30',
            'idempotency_key' => 'payout:'.$vendor->id.':2026-06-30',
        ]);

        $publisher = $this->mock(NotificationPublisherContract::class);
        $publisher->shouldReceive('publishWebhook')
            ->once()
            ->withArgs(function (string $type, string $url, array $recipient, array $data, string $idempotencyKey) use ($vendor, $payout) {
                return $type === 'payout.sent'
                    && $url === 'https://vendor.example.com/hook'
                    && $recipient['vendorId'] === $vendor->id
                    && $data['payoutId'] === $payout->id
                    && $idempotencyKey === "notif:payout.sent:{$payout->id}";
            });

        (new SendVendorPayoutWebhookJob($payout->id))
            ->handle($publisher, app(PayoutRepositoryInterface::class));
    }

    public function test_payout_webhook_job_skips_vendor_without_webhook_url(): void
    {
        $vendor = Vendor::factory()->verified()->create(['webhook_url' => null]);

        $payout = Payout::create([
            'vendor_id' => $vendor->id,
            'gross' => 100_000,
            'commission' => 10_000,
            'net' => 90_000,
            'payable' => 90_000,
            'reserved_refund' => 0,
            'currency' => 'BDT',
            'status' => PayoutStatus::Paid->value,
            'batch_id' => '2026-06-30',
            'idempotency_key' => 'payout:'.$vendor->id.':2026-06-30-skip',
        ]);

        $publisher = $this->mock(NotificationPublisherContract::class);
        $publisher->shouldNotReceive('publishWebhook');

        (new SendVendorPayoutWebhookJob($payout->id))
            ->handle($publisher, app(PayoutRepositoryInterface::class));
    }
}
