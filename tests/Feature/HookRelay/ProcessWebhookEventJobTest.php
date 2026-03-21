<?php

use App\Domain\Webhooks\Jobs\ProcessWebhookEventJob;
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
