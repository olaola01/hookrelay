# Webhooks Domain Structure

This folder keeps HookRelay webhook logic grouped by domain concern.

- `Contracts/`: interface contracts (for verification and processing behaviors)
- `DTOs/`: immutable data transfer objects for webhook payload/context
- `Enums/`: source/status enums
- `Jobs/`: queued jobs related to webhook processing
- `Services/`: orchestration and domain services
- `Support/`: shared helpers used inside the webhooks domain
