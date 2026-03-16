<?php

use App\Models\WebhookEvent;

it('creates a webhook event record from the factory', function () {
    $event = WebhookEvent::factory()->create([
        'source' => 'stripe',
        'status' => 'received',
    ]);

    expect($event->id)->not->toBeNull();
    expect($event->source)->toBe('stripe');
    expect($event->status)->toBe('received');
    expect($event->headers)->toBeArray();

    $this->assertDatabaseHas('webhook_events', [
        'id' => $event->id,
        'source' => 'stripe',
        'status' => 'received',
    ]);
});
