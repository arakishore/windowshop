# Third-Party Integrations

## Integration Standard

External providers must be accessed through an application-owned service contract. Controllers and models must not depend directly on a provider SDK.

Each integration must define:

- purpose and data exchanged
- configuration and required environment variables
- authentication method and secret rotation
- timeout and retry policy
- idempotency behavior
- webhook verification
- failure and fallback behavior
- logging and redaction rules
- test or sandbox strategy

## HTTP Client Rules

- Set explicit connection and response timeouts.
- Retry only safe or idempotent operations, using bounded backoff.
- Treat all provider responses as untrusted input.
- Do not expose raw provider errors to end users.
- Attach correlation identifiers where supported.

## Webhooks

- Verify signatures before processing payloads.
- Reject stale or replayed events when provider support permits.
- Store provider event identifiers and process idempotently.
- Acknowledge promptly and queue expensive processing.
- Log outcome metadata without retaining unnecessary secrets or personal data.

## Planned Categories

Likely integrations include payments, SMS/OTP, email, social login, maps/location, object storage, push notifications, shipping, analytics, and AI services. A provider must not be selected or schema added until its concrete requirements are approved.

## Integration Register

| Provider/category | Purpose | State |
|---|---|---|
| Razorpay | Payments and refunds | Future |
| PhonePe | Payments and refunds | Future |
| Shiprocket | Shipping, labels, and tracking | Future |
| Google Login | Federated identity | Future |
| Facebook Login | Federated identity | Future |
| WhatsApp | Transactional/support messaging | Future |
| SMS Gateway | OTP and transactional messages | Future |
| AI APIs | Assisted content and operations | Future |
| Email providers | Transactional email and events | Future |

Each provider requires an ADR or integration design. Payments additionally require signature verification, idempotency, server-side amount checks, reconciliation, and no storage of card credentials.
