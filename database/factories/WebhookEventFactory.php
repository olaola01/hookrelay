<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<WebhookEvent>
     */
    protected $model = WebhookEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => fake()->randomElement(['stripe', 'github', 'shopify', 'slack']),
            'event_id' => 'evt_'.Str::lower(Str::random(16)),
            'signature' => 'sig_'.Str::lower(Str::random(32)),
            'headers' => [
                'content-type' => 'application/json',
                'user-agent' => fake()->userAgent(),
            ],
            'payload' => json_encode([
                'type' => fake()->randomElement(['invoice.paid', 'push', 'order.created']),
                'livemode' => false,
            ], JSON_THROW_ON_ERROR),
            'status' => 'received',
            'received_at' => Carbon::now(),
        ];
    }
}
