<?php

namespace Database\Seeders;

use App\Models\FailedWebhookDelivery;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEvent;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $processedStripe = WebhookEvent::factory()->create([
            'source' => 'stripe',
            'status' => 'processed',
        ]);

        WebhookDelivery::factory()->create([
            'webhook_event_id' => $processedStripe->id,
            'attempt_number' => 1,
            'status' => 'success',
            'latency_ms' => 118,
        ]);

        $processedGitHub = WebhookEvent::factory()->create([
            'source' => 'github',
            'status' => 'processed',
            'replayed_at' => now()->subDay(),
        ]);

        WebhookDelivery::factory()->create([
            'webhook_event_id' => $processedGitHub->id,
            'attempt_number' => 1,
            'status' => 'failed',
            'latency_ms' => 201,
            'error_message' => 'Primary processing worker timed out.',
        ]);

        WebhookDelivery::factory()->create([
            'webhook_event_id' => $processedGitHub->id,
            'attempt_number' => 2,
            'status' => 'success',
            'latency_ms' => 94,
        ]);

        $failedShopify = WebhookEvent::factory()->create([
            'source' => 'shopify',
            'status' => 'failed',
        ]);

        foreach (range(1, 5) as $attempt) {
            WebhookDelivery::factory()->create([
                'webhook_event_id' => $failedShopify->id,
                'attempt_number' => $attempt,
                'status' => 'failed',
                'latency_ms' => 160 + $attempt,
                'error_message' => 'Retries exhausted while processing order update.',
            ]);
        }

        FailedWebhookDelivery::factory()->create([
            'webhook_event_id' => $failedShopify->id,
            'final_attempts' => 5,
            'last_error' => 'Retries exhausted while processing order update.',
        ]);

        WebhookEvent::factory()->create([
            'source' => 'slack',
            'status' => 'received',
        ]);
    }
}
