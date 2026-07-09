# ADR-0008: Audit Logging

- Status: Accepted
- Date: 2026-07-09

## Problem

Security investigations and operational accountability require durable answers to who changed what, when, where, and from which values.

## Decision

Record structured audit events for authentication, privileged actions, permissions, status changes, payments, refunds, exports, and destructive operations. Capture actor, action, target, origin, outcome, and redacted changes.

## Alternatives Considered

- Rely only on application text logs.
- Audit every database write automatically.
- Record only failed or exceptional actions.

## Reason

Purpose-built events are searchable and meaningful without collecting excessive secrets or noisy implementation details.

## Consequences

Sensitive fields require redaction, retention and access policies are mandatory, and critical workflows must test audit emission.

