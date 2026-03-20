<?php

use App\Domain\Webhooks\Jobs\ProcessWebhookEventJob;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Queue;

it('accepts stripe webhook when signature is valid', function () {
    Queue::fake();

    config(['hookrelay.signatures.stripe.secret' => 'whsec_test_secret']);

    $payload = json_encode([
        'id' => 'evt_stripe_valid',
        'type' => 'invoice.paid',
    ], JSON_THROW_ON_ERROR);

    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

    $response = $this->call(
        'POST',
        '/webhooks/stripe',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ],
        $payload
    );

    $response->assertAccepted()
        ->assertJsonPath('data.source', 'stripe')
        ->assertJsonPath('data.status', 'received');

    $this->assertDatabaseHas('webhook_events', [
        'source' => 'stripe',
        'event_id' => 'evt_stripe_valid',
        'status' => 'received',
    ]);

    Queue::assertPushed(ProcessWebhookEventJob::class);
});

it('rejects stripe webhook when signature is invalid', function () {
    config(['hookrelay.signatures.stripe.secret' => 'whsec_test_secret']);

    $payload = json_encode([
        'id' => 'evt_stripe_invalid',
        'type' => 'invoice.payment_failed',
    ], JSON_THROW_ON_ERROR);

    $timestamp = now()->timestamp;

    $response = $this->call(
        'POST',
        '/webhooks/stripe',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=notavalidsignature",
        ],
        $payload
    );

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid webhook signature.');

    $this->assertDatabaseCount('webhook_events', 0);
});
