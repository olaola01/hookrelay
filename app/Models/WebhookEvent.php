<?php

namespace App\Models;

use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source',
        'event_id',
        'signature',
        'headers',
        'payload',
        'status',
        'received_at',
        'replayed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'received_at' => 'datetime',
            'replayed_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function latestDelivery(): HasOne
    {
        return $this->hasOne(WebhookDelivery::class)->latestOfMany('attempt_number');
    }

    public function failedDelivery(): HasOne
    {
        return $this->hasOne(FailedWebhookDelivery::class);
    }
}
