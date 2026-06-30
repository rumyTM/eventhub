<?php

namespace App\Services\Notification;

use App\Contracts\NotificationPublisherContract;
use App\Helpers\LogHelper;
use Illuminate\Support\Facades\Redis;

/**
 * Publishes notification jobs to Redis lists that the notification-service reads
 * via BLPOP (CLAUDE.md §3 inter-service communication).
 *
 * Each payload carries `trace_id` (from the current request's Laravel Context)
 * so every log line in the Node service is correlated with the originating HTTP request.
 *
 * Never include secrets, tokens, or PII beyond what the notification needs to render.
 */
class RedisNotificationPublisher implements NotificationPublisherContract
{
    private const NOTIFICATIONS_QUEUE = 'eventhub:notifications';

    private const WEBHOOKS_QUEUE = 'eventhub:webhooks';

    public function publishEmail(
        string $type,
        array $recipient,
        array $data,
        string $idempotencyKey,
    ): void {
        $payload = $this->buildPayload($type, $recipient, $data, $idempotencyKey);
        Redis::connection('notifications')->lpush(self::NOTIFICATIONS_QUEUE, json_encode($payload, JSON_THROW_ON_ERROR));

        LogHelper::logEntry(LogHelper::LOG_INFO, 'Notification job enqueued', [
            'type' => $type,
            'idempotency_key' => $idempotencyKey,
            'queue' => self::NOTIFICATIONS_QUEUE,
        ]);
    }

    public function publishWebhook(
        string $type,
        string $url,
        array $recipient,
        array $data,
        string $idempotencyKey,
    ): void {
        $payload = $this->buildPayload($type, $recipient, $data, $idempotencyKey);
        $payload['url'] = $url;

        Redis::connection('notifications')->lpush(self::WEBHOOKS_QUEUE, json_encode($payload, JSON_THROW_ON_ERROR));

        LogHelper::logEntry(LogHelper::LOG_INFO, 'Webhook notification job enqueued', [
            'type' => $type,
            'idempotency_key' => $idempotencyKey,
            'queue' => self::WEBHOOKS_QUEUE,
        ]);
    }

    /** @param array<string,mixed> $recipient @param array<string,mixed> $data @return array<string,mixed> */
    private function buildPayload(
        string $type,
        array $recipient,
        array $data,
        string $idempotencyKey,
    ): array {
        return [
            'type' => $type,
            'idempotencyKey' => $idempotencyKey,
            'trace_id' => LogHelper::traceId(),
            'recipient' => $recipient,
            'data' => $data,
        ];
    }
}
