<?php

it('accepts github webhook when sha256 signature is valid', function () {
    config(['hookrelay.signatures.github.secret' => 'github_test_secret']);

    $payload = json_encode([
        'id' => 'gh_evt_valid',
        'type' => 'push',
    ], JSON_THROW_ON_ERROR);

    $signature = hash_hmac('sha256', $payload, 'github_test_secret');

    $response = $this->call(
        'POST',
        '/webhooks/github',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => "sha256={$signature}",
        ],
        $payload
    );

    $response->assertAccepted()
        ->assertJsonPath('data.source', 'github')
        ->assertJsonPath('data.status', 'received');

    $this->assertDatabaseHas('webhook_events', [
        'source' => 'github',
        'event_id' => 'gh_evt_valid',
        'status' => 'received',
    ]);
});

it('accepts github webhook when sha1 signature is valid', function () {
    config(['hookrelay.signatures.github.secret' => 'github_test_secret']);

    $payload = json_encode([
        'id' => 'gh_evt_sha1_valid',
        'type' => 'issues',
    ], JSON_THROW_ON_ERROR);

    $signature = hash_hmac('sha1', $payload, 'github_test_secret');

    $response = $this->call(
        'POST',
        '/webhooks/github',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE' => "sha1={$signature}",
        ],
        $payload
    );

    $response->assertAccepted()
        ->assertJsonPath('data.source', 'github')
        ->assertJsonPath('data.status', 'received');

    $this->assertDatabaseHas('webhook_events', [
        'source' => 'github',
        'event_id' => 'gh_evt_sha1_valid',
        'status' => 'received',
    ]);
});

it('rejects github webhook when signature is invalid', function () {
    config(['hookrelay.signatures.github.secret' => 'github_test_secret']);

    $payload = json_encode([
        'id' => 'gh_evt_invalid',
        'type' => 'push',
    ], JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/webhooks/github',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=notavalidsignature',
        ],
        $payload
    );

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid webhook signature.');

    $this->assertDatabaseCount('webhook_events', 0);
});
