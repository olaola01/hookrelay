<?php

namespace Database\Factories;

use App\Models\WebhookDelivery;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<WebhookDelivery>
     */
    protected $model = WebhookDelivery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_event_id' => WebhookEvent::factory(),
            'attempt_number' => 1,
            'status' => fake()->randomElement(['success', 'failed']),
            'latency_ms' => fake()->numberBetween(1, 500),
            'error_message' => null,
            'processed_at' => Carbon::now(),
        ];
    }
}
