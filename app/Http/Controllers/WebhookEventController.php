<?php

namespace App\Http\Controllers;

use App\Domain\Webhooks\Jobs\ProcessWebhookEventJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = WebhookEvent::query()
            ->with(['latestDelivery', 'failedDelivery'])
            ->when($request->string('source')->toString() !== '', fn ($query) => $query->where('source', $request->string('source')->toString()))
            ->when($request->string('status')->toString() !== '', fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->date('from') !== null, fn ($query) => $query->where('received_at', '>=', $request->date('from')))
            ->when($request->date('to') !== null, fn ($query) => $query->where('received_at', '<=', $request->date('to')->endOfDay()))
            ->orderByDesc('received_at')
            ->paginate($this->resolvePerPage($request));

        return response()->json($this->paginatedEvents($events));
    }

    public function failed(Request $request): JsonResponse
    {
        $events = WebhookEvent::query()
            ->with(['latestDelivery', 'failedDelivery'])
            ->where(function ($query): void {
                $query->where('status', 'failed')
                    ->orWhereHas('failedDelivery');
            })
            ->orderByDesc('updated_at')
            ->paginate($this->resolvePerPage($request));

        return response()->json($this->paginatedEvents($events));
    }

    public function replay(WebhookEvent $webhookEvent): JsonResponse
    {
        $webhookEvent->failedDelivery()?->delete();

        $webhookEvent->forceFill([
            'status' => 'received',
            'replayed_at' => now(),
        ])->save();

        ProcessWebhookEventJob::dispatch($webhookEvent->id);

        Log::info('Webhook event replay queued.', [
            'webhook_event_id' => $webhookEvent->id,
            'source' => $webhookEvent->source,
        ]);

        return response()->json([
            'message' => 'Webhook replay queued.',
            'data' => [
                'id' => $webhookEvent->id,
                'source' => $webhookEvent->source,
                'status' => $webhookEvent->status,
                'replayed_at' => $webhookEvent->replayed_at?->toISOString(),
            ],
        ], 202);
    }

    public function stats(): JsonResponse
    {
        $totalEvents = WebhookEvent::query()->count();
        $totalDeliveries = WebhookDelivery::query()->count();
        $successCount = WebhookDelivery::query()->where('status', 'success')->count();
        $failureCount = WebhookDelivery::query()->where('status', 'failed')->count();
        $retryCount = WebhookDelivery::query()->where('attempt_number', '>', 1)->count();
        $averageLatency = round((float) (WebhookDelivery::query()->avg('latency_ms') ?? 0), 2);
        $deadLetterCount = WebhookEvent::query()->whereHas('failedDelivery')->count();

        return response()->json([
            'data' => [
                'total_events' => $totalEvents,
                'processed_events' => WebhookEvent::query()->where('status', 'processed')->count(),
                'failed_events' => WebhookEvent::query()->where('status', 'failed')->count(),
                'dead_letter_events' => $deadLetterCount,
                'total_deliveries' => $totalDeliveries,
                'successful_deliveries' => $successCount,
                'failed_deliveries' => $failureCount,
                'retry_deliveries' => $retryCount,
                'success_rate' => $this->resolveRate($successCount, $totalDeliveries),
                'failure_rate' => $this->resolveRate($failureCount, $totalDeliveries),
                'average_latency_ms' => $averageLatency,
            ],
        ]);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    private function paginatedEvents(LengthAwarePaginator $events): array
    {
        return [
            'data' => $events->getCollection()->map(fn (WebhookEvent $event) => $this->transformEvent($event))->all(),
            'meta' => [
                'current_page' => $events->currentPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'last_page' => $events->lastPage(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformEvent(WebhookEvent $event): array
    {
        return [
            'id' => $event->id,
            'source' => $event->source,
            'event_id' => $event->event_id,
            'status' => $event->status,
            'signature' => $event->signature,
            'headers' => $event->headers,
            'payload' => $this->decodePayload($event->payload),
            'received_at' => $event->received_at?->toISOString(),
            'replayed_at' => $event->replayed_at?->toISOString(),
            'latest_delivery' => $event->latestDelivery === null ? null : [
                'attempt_number' => $event->latestDelivery->attempt_number,
                'status' => $event->latestDelivery->status,
                'latency_ms' => $event->latestDelivery->latency_ms,
                'error_message' => $event->latestDelivery->error_message,
                'processed_at' => $event->latestDelivery->processed_at?->toISOString(),
            ],
            'dead_letter' => $event->failedDelivery === null ? null : [
                'final_attempts' => $event->failedDelivery->final_attempts,
                'last_error' => $event->failedDelivery->last_error,
                'failed_at' => $event->failedDelivery->failed_at?->toISOString(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|string|null
     */
    private function decodePayload(?string $payload): array|string|null
    {
        if ($payload === null) {
            return null;
        }

        $decoded = json_decode($payload, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $payload;
    }

    private function resolvePerPage(Request $request): int
    {
        return max(1, min(100, $request->integer('per_page', 15)));
    }

    private function resolveRate(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }
}
