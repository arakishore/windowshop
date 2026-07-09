# Testing Checklist

## Before Every Merge

- [ ] Code is formatted and static checks pass.
- [ ] Relevant unit and feature tests pass.
- [ ] New behavior has success, validation, authorization, and failure coverage.
- [ ] Migrations run from a clean database and roll back safely.
- [ ] No secrets, debug calls, or sensitive fixtures are committed.

## Authentication and Authorization

- [ ] Correct and incorrect credentials are tested.
- [ ] Suspended, inactive, deleted, and unverified states are tested.
- [ ] Session regeneration and logout invalidation are verified.
- [ ] Login successes and failures create safe audit records.
- [ ] Each guard context is isolated.
- [ ] Policies reject cross-tenant and unauthorized access.
- [ ] Rate limiting is covered for sensitive endpoints.

## Database

- [ ] Foreign keys and deletion behavior match the domain.
- [ ] Unique, single-column, and composite indexes support known queries.
- [ ] Nullable fields have intentional meaning.
- [ ] Enum-like comments match PHP enum cases.
- [ ] Soft-delete behavior and restoration are tested where supported.

## API and UI

- [ ] Validation errors use the expected response format.
- [ ] Public responses expose UUIDs rather than internal IDs where required.
- [ ] Pagination, filtering, sorting, and empty states are tested.
- [ ] CSRF, escaping, uploads, and authorization are verified.
- [ ] Important flows work on supported screen sizes and browsers.

## Integrations and Operations

- [ ] Provider success, timeout, malformed response, and retry behavior are tested.
- [ ] Webhooks verify signatures and process duplicate events safely.
- [ ] Queued jobs are idempotent where retries are possible.
- [ ] Logs contain enough context without secrets or unnecessary personal data.
- [ ] Production configuration uses safe debug, cache, queue, and cookie settings.

## Release Areas

### Authentication

- [ ] Login, logout, reset, Remember Me, OTP/2FA, expiry, and revocation pass.
- [ ] Guard and role isolation passes.

### Merchant and Customer

- [ ] Merchant onboarding, status, staff permissions, and tenant isolation pass.
- [ ] Customer registration, profile, addresses, cart, checkout, and history pass.

### Orders, Payments, and Refunds

- [ ] Pricing, tax, transitions, cancellation, and concurrent updates pass.
- [ ] Success, failure, pending, duplicate callbacks, signatures, reconciliation, and refunds pass.

### Inventory

- [ ] Adjustments, reservations, release, overselling prevention, and audit history pass.

### SEO and Performance

- [ ] Metadata, canonical URLs, robots, sitemap, and structured data pass.
- [ ] Queries, indexes, caching, queues, pagination, assets, and critical latency are reviewed.

### Security

- [ ] CSRF, XSS, SQL injection, IDOR, uploads, throttles, secrets, headers, advisories, and audit coverage pass.
