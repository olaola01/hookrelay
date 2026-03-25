<?php

namespace App\Http\Controllers;

use App\Domain\Webhooks\Jobs\ProcessWebhookEventJob;
use App\Domain\Webhooks\Services\SignatureVerifierResolver;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class WebhookIngestionController extends Controller
{
    public function __invoke(Request $request, string $source): JsonResponse
    {
        if (! in_array($source, config('hookrelay.sources', []), true)) {
            abort(404, 'Unsupported webhook source.');
        }

        $payload = $request->getContent();
        $decodedPayload = json_decode($payload, true);
        $eventId = is_array($decodedPayload) ? Arr::get($decodedPayload, 'id') : null;
        $signatureVerifier = app(SignatureVerifierResolver::class)->forSource($source);

        if (! $signatureVerifier->verify($request, $payload)) {
            Log::warning('Webhook signature verification failed.', [
                'source' => $source,
                'event_id' => $eventId,
            ]);

            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        if (is_string($eventId) && $eventId !== '') {
            $existingEvent = WebhookEvent::query()
                ->where('source', $source)
                ->where('event_id', $eventId)
                ->first();

            if ($existingEvent !== null) {
                Log::info('Duplicate webhook event ignored.', [
                    'webhook_event_id' => $existingEvent->id,
                    'source' => $source,
                    'event_id' => $eventId,
                ]);

                return response()->json([
                    'message' => 'Webhook already received.',
                    'data' => [
                        'id' => $existingEvent->id,
                        'source' => $existingEvent->source,
                        'status' => $existingEvent->status,
                        'duplicate' => true,
                    ],
                ], 202);
            }
        }

        $event = WebhookEvent::query()->create([
            'source' => $source,
            'event_id' => $eventId,
            'signature' => $this->resolveSignatureHeader($request),
            'headers' => $request->headers->all(),
            'payload' => $payload,
            'status' => 'received',
            'received_at' => now(),
        ]);

        ProcessWebhookEventJob::dispatch($event->id);

        Log::info('Webhook event accepted.', [
            'webhook_event_id' => $event->id,
            'source' => $event->source,
            'event_id' => $event->event_id,
        ]);

        return response()->json([
            'message' => 'Webhook received.',
            'data' => [
                'id' => $event->id,
                'source' => $event->source,
                'status' => $event->status,
            ],
        ], 202);
    }

    private function resolveSignatureHeader(Request $request): ?string
    {
        return $request->header('Stripe-Signature')
            ?? $request->header('X-Shopify-Hmac-Sha256')
            ?? $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Hub-Signature');
    }
}
