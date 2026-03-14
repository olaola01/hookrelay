# HookRelay Architecture

## Concept
HookRelay is a webhook ingestion and processing platform for unreliable, bursty, and failure-prone webhook traffic.

It provides:
- Reliable webhook intake
- Signature verification per source
- Durable event storage
- Async processing with queues
- Retry + dead letter handling
- Replay support
- Delivery analytics

## Tech Stack
- Laravel 12
- Redis (queue + cache)
- MySQL or Postgres
- Laravel Queues + Workers
- Pest for tests

## Core Request Pipeline
1. External sender calls `POST /webhooks/{source}`.
2. HookRelay validates source-specific signature headers.
3. Raw payload + headers are stored in `webhook_events`.
4. Event is queued to `ProcessWebhookEventJob`.
5. Worker processes the event and records delivery status.
6. Failed processing is retried with exponential backoff.
7. Permanently failed attempts are moved to dead letter storage.
8. Replay endpoint can re-queue an event.

## Data Model
### `webhook_events`
- `id`
- `source` (`stripe`, `github`, `shopify`, `slack`)
- `event_id` (provider event id when available)
- `signature`
- `headers` (json)
- `payload` (json or text)
- `status` (`received`, `processing`, `processed`, `failed`)
- `received_at`
- timestamps

### `webhook_deliveries`
- `id`
- `webhook_event_id`
- `attempt_number`
- `status` (`success`, `failed`)
- `latency_ms`
- `error_message` (nullable)
- `processed_at`
- timestamps

### `failed_webhook_deliveries`
- `id`
- `webhook_event_id`
- `final_attempts`
- `last_error`
- `failed_at`
- timestamps

## Queue + Retry Strategy
- Job: `ProcessWebhookEventJob`
- Retry backoff: exponential (`10s`, `30s`, `90s`, `270s`, `810s`)
- Max retries: `5`
- After max retries, write to `failed_webhook_deliveries` and mark event as failed

## API Surface
- `POST /webhooks/{source}`: ingest + verify + queue
- `POST /events/{id}/replay`: replay event
- `GET /api/events`: list events
- `GET /api/events/failed`: list failed events
- `GET /api/events/stats`: aggregate metrics

## Analytics Metrics
- Success rate = successful deliveries / total deliveries
- Failure rate = failed deliveries / total deliveries
- Retry rate = retried deliveries / total deliveries
- Average latency = mean(`latency_ms`)

## Risks to Watch
- Signature mismatch due to raw payload mutation
- Queue workers not running in local/dev
- Retry storms under high burst traffic
- Missing idempotency for duplicate provider events
