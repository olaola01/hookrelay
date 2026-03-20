<?php

use App\Domain\Webhooks\Jobs\ProcessWebhookEventJob;
use App\Models\WebhookEvent;

it('marks a queued webhook event as processed', function () {
    $event = WebhookEvent::factory()->create([
        'status' => 'received',
    ]);

    $job = new ProcessWebhookEventJob($event->id);
    $job->handle();

    expect($event->fresh()->status)->toBe('processed');
});
