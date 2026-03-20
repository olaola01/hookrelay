<?php

namespace App\Domain\Webhooks\Jobs;

use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWebhookEventJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $webhookEventId,
    ) {
        $this->onConnection(config('hookrelay.queue.connection'));
        $this->onQueue(config('hookrelay.queue.name'));
    }

    public function handle(): void
    {
        $event = WebhookEvent::query()->find($this->webhookEventId);

        if ($event === null) {
            return;
        }

        $event->forceFill([
            'status' => 'processing',
        ])->save();

        $event->forceFill([
            'status' => 'processed',
        ])->save();
    }
}
