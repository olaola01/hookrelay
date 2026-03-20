<?php

use App\Domain\Webhooks\Jobs\ProcessWebhookEventJob;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;

it('accepts an allowed webhook source and stores the event', function () {
    Queue::fake();

    $payload = [
        'id' => 'slack_evt_12345',
        'type' => 'message.created',
        'data' => ['object' => ['customer' => 'cus_123']],
    ];

    $response = $this->postJson('/webhooks/slack', $payload);

    $response->assertAccepted()
        ->assertJsonPath('message', 'Webhook received.')
        ->assertJsonPath('data.source', 'slack')
        ->assertJsonPath('data.status', 'received');

    $event = WebhookEvent::query()->first();

    expect($event)->not->toBeNull();
    expect($event->source)->toBe('slack');
    expect($event->event_id)->toBe('slack_evt_12345');
    expect($event->signature)->toBeNull();
    expect($event->status)->toBe('received');
    expect($event->headers)->toBeArray()->toHaveKey('content-type');

    $this->assertDatabaseHas('webhook_events', [
        'id' => $event->id,
        'source' => 'slack',
        'event_id' => 'slack_evt_12345',
        'status' => 'received',
    ]);

    Queue::assertPushed(ProcessWebhookEventJob::class, function (ProcessWebhookEventJob $job) use ($event): bool {
        expect($job->webhookEventId)->toBe($event->id);
        expect($job->connection)->toBe(config('hookrelay.queue.connection'));
        expect($job->queue)->toBe(config('hookrelay.queue.name'));

        return true;
    });
});

it('returns not found for unsupported webhook source', function () {
    $this->postJson('/webhooks/not-a-real-source', [
        'id' => 'evt_invalid',
    ])->assertNotFound();

    $this->assertDatabaseCount('webhook_events', 0);
});
