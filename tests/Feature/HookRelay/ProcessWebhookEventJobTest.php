<?php

use App\Domain\Webhooks\Jobs\ProcessWebhookEventJob;
use App\Domain\Webhooks\Services\WebhookEventProcessor;
use App\Models\FailedWebhookDelivery;
use App\Models\WebhookDelivery;
use App\Models\WebhookEvent;

it('marks a queued webhook event as processed and records a delivery attempt', function () {
    $event = WebhookEvent::factory()->create([
        'status' => 'received',
    ]);

    $job = new ProcessWebhookEventJob($event->id);
    $job->handle();

    expect($event->fresh()->status)->toBe('processed');

    $delivery = WebhookDelivery::query()->where('webhook_event_id', $event->id)->sole();

    expect($delivery->attempt_number)->toBe(1);
    expect($delivery->status)->toBe('success');
    expect($delivery->latency_ms)->toBeGreaterThanOrEqual(0);
    expect($delivery->processed_at)->not->toBeNull();
});

it('increments the attempt number for subsequent delivery records', function () {
    $event = WebhookEvent::factory()->create([
        'status' => 'received',
    ]);

    WebhookDelivery::factory()->create([
        'webhook_event_id' => $event->id,
        'attempt_number' => 1,
        'status' => 'failed',
    ]);

    $job = new ProcessWebhookEventJob($event->id);
    $job->handle();

    $delivery = WebhookDelivery::query()
        ->where('webhook_event_id', $event->id)
        ->orderByDesc('attempt_number')
        ->firstOrFail();

    expect($delivery->attempt_number)->toBe(2);
    expect($delivery->status)->toBe('success');
});

it('uses the configured retry metadata', function () {
    config([
        'hookrelay.retries.max_attempts' => 5,
        'hookrelay.retries.backoff_seconds' => [10, 30, 90, 270, 810],
    ]);

    $job = new ProcessWebhookEventJob(123);

    expect($job->tries())->toBe(5);
    expect($job->backoff())->toBe([10, 30, 90, 270, 810]);
});

it('marks the event failed and records a failed delivery when processing throws', function () {
    app()->bind(WebhookEventProcessor::class, fn () => new class extends WebhookEventProcessor
    {
        public function process(WebhookEvent $event): void
        {
            throw new \RuntimeException('Processing failed.');
        }
    });

    $event = WebhookEvent::factory()->create([
        'status' => 'received',
    ]);

    $job = new ProcessWebhookEventJob($event->id);

    expect(fn () => $job->handle())->toThrow(\RuntimeException::class, 'Processing failed.');

    expect($event->fresh()->status)->toBe('failed');

    $delivery = WebhookDelivery::query()->where('webhook_event_id', $event->id)->sole();

    expect($delivery->attempt_number)->toBe(1);
    expect($delivery->status)->toBe('failed');
    expect($delivery->error_message)->toBe('Processing failed.');
    expect($delivery->latency_ms)->toBeGreaterThanOrEqual(0);
});

it('moves the event to dead letter storage after final failure', function () {
    config([
        'hookrelay.retries.max_attempts' => 5,
    ]);

    $event = WebhookEvent::factory()->create([
        'status' => 'failed',
    ]);

    foreach (range(1, 5) as $attempt) {
        WebhookDelivery::factory()->create([
            'webhook_event_id' => $event->id,
            'attempt_number' => $attempt,
            'status' => 'failed',
            'error_message' => 'Processing failed.',
        ]);
    }

    $job = new ProcessWebhookEventJob($event->id);
    $job->failed(new \RuntimeException('Retries exhausted.'));

    $deadLetter = FailedWebhookDelivery::query()->where('webhook_event_id', $event->id)->sole();

    expect($deadLetter->final_attempts)->toBe(5);
    expect($deadLetter->last_error)->toBe('Retries exhausted.');
    expect($deadLetter->failed_at)->not->toBeNull();
});
