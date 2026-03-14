<?php

it('uses hookrelay bootstrap defaults', function () {
    expect(config('hookrelay.sources'))
        ->toBeArray()
        ->toContain('stripe', 'github', 'shopify', 'slack');

    expect(config('hookrelay.queue.connection'))->toBe(env('QUEUE_CONNECTION'));
    expect(config('hookrelay.queue.name'))->toBe(env('HOOKRELAY_QUEUE', 'webhooks'));

    expect(config('hookrelay.retries.max_attempts'))->toBe(5);
    expect(config('hookrelay.retries.backoff_seconds'))->toBe([10, 30, 90, 270, 810]);
});
