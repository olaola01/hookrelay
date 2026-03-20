<?php

namespace App\Http\Controllers;

use App\Domain\Webhooks\Jobs\ProcessWebhookEventJob;
use App\Domain\Webhooks\Services\SignatureVerifierResolver;
use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class WebhookIngestionController extends Controller
{
    public function __invoke(Request $request, string $source): JsonResponse
    {
        if (! in_array($source, config('hookrelay.sources', []), true)) {
            abort(404, 'Unsupported webhook source.');
        }

        $payload = $request->getContent();
        $signatureVerifier = app(SignatureVerifierResolver::class)->forSource($source);

        if (! $signatureVerifier->verify($request, $payload)) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $decodedPayload = json_decode($payload, true);

        $event = WebhookEvent::query()->create([
            'source' => $source,
            'event_id' => is_array($decodedPayload) ? Arr::get($decodedPayload, 'id') : null,
            'signature' => $this->resolveSignatureHeader($request),
            'headers' => $request->headers->all(),
            'payload' => $payload,
            'status' => 'received',
            'received_at' => now(),
        ]);

        ProcessWebhookEventJob::dispatch($event->id);

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
