<?php

use App\Models\WebhookEvent;

it('accepts an allowed webhook source and stores the event', function () {
    $payload = [
        'id' => 'evt_12345',
        'type' => 'invoice.paid',
        'data' => ['object' => ['customer' => 'cus_123']],
    ];

    $response = $this->postJson('/webhooks/stripe', $payload, [
        'Stripe-Signature' => 't=1,v1=testsignature',
    ]);

    $response->assertAccepted()
        ->assertJsonPath('message', 'Webhook received.')
        ->assertJsonPath('data.source', 'stripe')
        ->assertJsonPath('data.status', 'received');

    $event = WebhookEvent::query()->first();

    expect($event)->not->toBeNull();
    expect($event->source)->toBe('stripe');
    expect($event->event_id)->toBe('evt_12345');
    expect($event->signature)->toBe('t=1,v1=testsignature');
    expect($event->status)->toBe('received');
    expect($event->headers)->toBeArray()->toHaveKey('stripe-signature');

    $this->assertDatabaseHas('webhook_events', [
        'id' => $event->id,
        'source' => 'stripe',
        'event_id' => 'evt_12345',
        'status' => 'received',
    ]);
});

it('returns not found for unsupported webhook source', function () {
    $this->postJson('/webhooks/not-a-real-source', [
        'id' => 'evt_invalid',
    ])->assertNotFound();

    $this->assertDatabaseCount('webhook_events', 0);
});
