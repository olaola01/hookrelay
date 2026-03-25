<?php

namespace Database\Factories;

use App\Models\FailedWebhookDelivery;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<FailedWebhookDelivery>
 */
class FailedWebhookDeliveryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<FailedWebhookDelivery>
     */
    protected $model = FailedWebhookDelivery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_event_id' => WebhookEvent::factory(),
            'final_attempts' => 5,
            'last_error' => fake()->sentence(),
            'failed_at' => Carbon::now(),
        ];
    }
}
