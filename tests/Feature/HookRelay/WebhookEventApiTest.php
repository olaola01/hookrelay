<?php

use App\Domain\Webhooks\Jobs\ProcessWebhookEventJob;
use App\Models\FailedWebhookDelivery;
use App\Models\WebhookDelivery;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;

it('lists webhook events with source and status filters', function () {
    $matchingEvent = WebhookEvent::factory()->create([
        'source' => 'stripe',
        'status' => 'processed',
        'event_id' => 'evt_matching',
    ]);

    WebhookDelivery::factory()->create([
        'webhook_event_id' => $matchingEvent->id,
        'attempt_number' => 1,
        'status' => 'success',
    ]);

    WebhookEvent::factory()->create([
        'source' => 'github',
        'status' => 'failed',
        'event_id' => 'evt_other',
    ]);

    $response = $this->getJson('/api/events?source=stripe&status=processed');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $matchingEvent->id)
        ->assertJsonPath('data.0.source', 'stripe')
        ->assertJsonPath('data.0.status', 'processed')
        ->assertJsonPath('data.0.latest_delivery.status', 'success');
});

it('lists failed webhook events', function () {
    $failedEvent = WebhookEvent::factory()->create([
        'source' => 'shopify',
        'status' => 'failed',
        'event_id' => 'evt_failed',
    ]);

    FailedWebhookDelivery::factory()->create([
        'webhook_event_id' => $failedEvent->id,
        'final_attempts' => 5,
        'last_error' => 'Retries exhausted.',
    ]);

    WebhookEvent::factory()->create([
        'source' => 'stripe',
        'status' => 'processed',
        'event_id' => 'evt_ok',
    ]);

    $response = $this->getJson('/api/events/failed');

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $failedEvent->id)
        ->assertJsonPath('data.0.dead_letter.last_error', 'Retries exhausted.');
});

it('returns webhook analytics stats', function () {
    $processedEvent = WebhookEvent::factory()->create([
        'status' => 'processed',
        'event_id' => 'evt_stats_processed',
    ]);

    WebhookDelivery::factory()->create([
        'webhook_event_id' => $processedEvent->id,
        'attempt_number' => 1,
        'status' => 'success',
        'latency_ms' => 120,
    ]);

    $failedEvent = WebhookEvent::factory()->create([
        'status' => 'failed',
        'event_id' => 'evt_stats_failed',
    ]);

    WebhookDelivery::factory()->create([
        'webhook_event_id' => $failedEvent->id,
        'attempt_number' => 1,
        'status' => 'failed',
        'latency_ms' => 240,
        'error_message' => 'Retries exhausted.',
    ]);

    FailedWebhookDelivery::factory()->create([
        'webhook_event_id' => $failedEvent->id,
        'final_attempts' => 5,
        'last_error' => 'Retries exhausted.',
    ]);

    $retriedEvent = WebhookEvent::factory()->create([
        'status' => 'processed',
        'event_id' => 'evt_stats_retried',
    ]);

    WebhookDelivery::factory()->create([
        'webhook_event_id' => $retriedEvent->id,
        'attempt_number' => 2,
        'status' => 'success',
        'latency_ms' => 60,
    ]);

    $response = $this->getJson('/api/events/stats');

    $response->assertOk()
        ->assertJsonPath('data.total_events', 3)
        ->assertJsonPath('data.total_deliveries', 3)
        ->assertJsonPath('data.successful_deliveries', 2)
        ->assertJsonPath('data.failed_deliveries', 1)
        ->assertJsonPath('data.retry_deliveries', 1)
        ->assertJsonPath('data.dead_letter_events', 1)
        ->assertJsonPath('data.success_rate', 66.67)
        ->assertJsonPath('data.failure_rate', 33.33)
        ->assertJsonPath('data.average_latency_ms', 140);
});

it('replays a webhook event and clears dead letter state', function () {
    Queue::fake();

    $event = WebhookEvent::factory()->create([
        'source' => 'stripe',
        'status' => 'failed',
        'event_id' => 'evt_replay',
    ]);

    FailedWebhookDelivery::factory()->create([
        'webhook_event_id' => $event->id,
    ]);

    $response = $this->postJson("/events/{$event->id}/replay");

    $response->assertAccepted()
        ->assertJsonPath('message', 'Webhook replay queued.')
        ->assertJsonPath('data.id', $event->id)
        ->assertJsonPath('data.status', 'received');

    expect($event->fresh()->status)->toBe('received');
    expect($event->fresh()->replayed_at)->not->toBeNull();

    $this->assertDatabaseMissing('failed_webhook_deliveries', [
        'webhook_event_id' => $event->id,
    ]);

    Queue::assertPushed(ProcessWebhookEventJob::class, fn (ProcessWebhookEventJob $job): bool => $job->webhookEventId === $event->id);
});
