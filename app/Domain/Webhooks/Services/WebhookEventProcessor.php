<?php

namespace App\Domain\Webhooks\Services;

use App\Models\WebhookEvent;

class WebhookEventProcessor
{
    public function process(WebhookEvent $event): void
    {
        // Processing hooks will be expanded in later slices.
    }
}
