<?php

namespace App\Domain\Webhooks\Jobs;

use App\Domain\Webhooks\Services\WebhookEventProcessor;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWebhookEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $webhookEventId,
    ) {
        $this->onConnection(config('hookrelay.queue.connection'));
        $this->onQueue(config('hookrelay.queue.name'));
    }

    public function tries(): int
    {
        return (int) config('hookrelay.retries.max_attempts', 1);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return config('hookrelay.retries.backoff_seconds', []);
    }

    public function handle(): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null) {
            return;
        }

        $attemptNumber = $event->deliveries()->count() + 1;
        $startedAt = microtime(true);

        $event->forceFill([
            'status' => 'processing',
        ])->save();

        try {
            app(WebhookEventProcessor::class)->process($event);

            $event->forceFill([
                'status' => 'processed',
            ])->save();

            $event->deliveries()->create([
                'attempt_number' => $attemptNumber,
                'status' => 'success',
                'latency_ms' => $this->resolveLatency($startedAt),
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' => 'failed',
            ])->save();

            $event->deliveries()->create([
                'attempt_number' => $attemptNumber,
                'status' => 'failed',
                'latency_ms' => $this->resolveLatency($startedAt),
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function resolveLatency(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }

    public function failed(Throwable $exception): void
    {
        $event = WebhookEvent::query()->with('deliveries')->find($this->webhookEventId);

        if ($event === null) {
            return;
        }

        $event->forceFill([
            'status' => 'failed',
        ])->save();

        $finalAttempts = max(
            $event->deliveries->max('attempt_number') ?? 0,
            $this->tries()
        );

        $event->failedDelivery()->updateOrCreate(
            [],
            [
                'final_attempts' => $finalAttempts,
                'last_error' => $exception->getMessage(),
                'failed_at' => now(),
            ]
        );

        Log::error('Webhook event moved to dead letter storage.', [
            'webhook_event_id' => $event->id,
            'source' => $event->source,
            'final_attempts' => $finalAttempts,
            'error' => $exception->getMessage(),
        ]);
    }
}
