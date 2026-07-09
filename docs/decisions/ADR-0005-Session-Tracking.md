# ADR-0005: Session Tracking

- Status: Accepted
- Date: 2026-07-09

## Problem

WindowShop needs device visibility, forced logout, concurrent-session policy, and durable security history beyond Laravel's session storage.

## Decision

`user_sessions` tracks authenticated sessions. `user_login_logs` records every successful and failed attempt and survives hard user deletion through a nullable reference.

## Alternatives Considered

- Rely only on Laravel's session storage.
- Record only successful logins.
- Use application log files without queryable session records.

## Reason

Dedicated records support device visibility, forced logout, security investigations, and future concurrent-login policy.

## Consequences

Authentication flows must write safe records, retention rules are required, and stale active sessions need cleanup.
