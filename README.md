# HookRelay

HookRelay is a reliable webhook ingestion and processing service built with Laravel.

Many SaaS systems struggle with webhooks because they can be unreliable, bursty, and difficult to debug. HookRelay focuses on making webhook delivery durable, observable, and easy to operate.

## Why HookRelay
- Reliable webhook intake
- Source-specific signature verification
- Durable event storage
- Asynchronous queue processing
- Retry strategy with dead letter handling
- Replay support for failed events
- Delivery analytics for operational visibility

## Status
HookRelay is currently in active development.

This repository tracks the implementation of:
- Ingestion endpoint (`POST /webhooks/{source}`)
- Signature verification (Stripe, Shopify, GitHub, Slack)
- Event processing jobs
- Retry and dead letter flow
- Replay endpoint (`POST /events/{id}/replay`)
- Analytics endpoints

## Proposed Request Pipeline
Webhook Sender  
-> Webhook API  
-> Signature Verification  
-> Store Event  
-> Queue Job  
-> Worker Processing  
-> Retry / Dead Letter Queue

## Tech Stack
- PHP 8.2+
- Laravel 12
- Redis (queues/cache)
- MySQL or Postgres
- Pest (testing)

## Local Development
1. Install dependencies:
```bash
composer install
npm install
```
2. Configure environment:
```bash
cp .env.example .env
php artisan key:generate
```
3. Configure database + Redis in `.env`, then run:
```bash
php artisan migrate
```
4. Start development services:
```bash
composer run dev
```

`composer run dev` starts the app server, queue listener, logs, and Vite concurrently.

## Testing
Run all checks:
```bash
composer test
```

Run tests only:
```bash
php artisan test
```

## API Surface (Target)
- `POST /webhooks/{source}`
- `POST /events/{id}/replay`
- `GET /api/events`
- `GET /api/events/failed`
- `GET /api/events/stats`

## Documentation
- Architecture: [docs/architecture.md](docs/architecture.md)

## Project Goal
Build a production-style webhook platform that demonstrates strong backend engineering patterns:
- Async processing
- Queue reliability
- Retry and failure handling
- Observability and analytics
