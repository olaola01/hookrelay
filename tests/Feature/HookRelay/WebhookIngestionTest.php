<?php

use App\Models\WebhookEvent;

it('accepts an allowed webhook source and stores the event', function () {
    $payload = [
        'id' => 'gh_evt_12345',
        'type' => 'push',
        'data' => ['object' => ['customer' => 'cus_123']],
    ];

    $response = $this->postJson('/webhooks/github', $payload, [
        'X-Hub-Signature' => 'sha1=testsignature',
    ]);

    $response->assertAccepted()
        ->assertJsonPath('message', 'Webhook received.')
        ->assertJsonPath('data.source', 'github')
        ->assertJsonPath('data.status', 'received');

    $event = WebhookEvent::query()->first();

    expect($event)->not->toBeNull();
    expect($event->source)->toBe('github');
    expect($event->event_id)->toBe('gh_evt_12345');
    expect($event->signature)->toBe('sha1=testsignature');
    expect($event->status)->toBe('received');
    expect($event->headers)->toBeArray()->toHaveKey('x-hub-signature');

    $this->assertDatabaseHas('webhook_events', [
        'id' => $event->id,
        'source' => 'github',
        'event_id' => 'gh_evt_12345',
        'status' => 'received',
    ]);
});

it('returns not found for unsupported webhook source', function () {
    $this->postJson('/webhooks/not-a-real-source', [
        'id' => 'evt_invalid',
    ])->assertNotFound();

    $this->assertDatabaseCount('webhook_events', 0);
});
