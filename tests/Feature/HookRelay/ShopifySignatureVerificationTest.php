<?php

it('accepts shopify webhook when signature is valid', function () {
    config(['hookrelay.signatures.shopify.secret' => 'shopify_test_secret']);

    $payload = json_encode([
        'id' => 'shopify_evt_valid',
        'topic' => 'orders/create',
    ], JSON_THROW_ON_ERROR);

    $signature = base64_encode(hash_hmac('sha256', $payload, 'shopify_test_secret', true));

    $response = $this->call(
        'POST',
        '/webhooks/shopify',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
        ],
        $payload
    );

    $response->assertAccepted()
        ->assertJsonPath('data.source', 'shopify')
        ->assertJsonPath('data.status', 'received');

    $this->assertDatabaseHas('webhook_events', [
        'source' => 'shopify',
        'event_id' => 'shopify_evt_valid',
        'status' => 'received',
    ]);
});

it('rejects shopify webhook when signature is invalid', function () {
    config(['hookrelay.signatures.shopify.secret' => 'shopify_test_secret']);

    $payload = json_encode([
        'id' => 'shopify_evt_invalid',
        'topic' => 'orders/updated',
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/webhooks/shopify',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SHOPIFY_HMAC_SHA256' => 'notavalidsignature',
        ],
        $payload
    );

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid webhook signature.');

    $this->assertDatabaseCount('webhook_events', 0);
});
