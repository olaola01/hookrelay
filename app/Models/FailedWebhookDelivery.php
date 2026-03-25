<?php

namespace App\Models;

use Database\Factories\FailedWebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedWebhookDelivery extends Model
{
    /** @use HasFactory<FailedWebhookDeliveryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'webhook_event_id',
        'final_attempts',
        'last_error',
        'failed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failed_at' => 'datetime',
        ];
    }

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class);
    }
}
