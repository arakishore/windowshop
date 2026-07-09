# ADR-0001: Authentication Strategy

- Status: Accepted
- Date: 2026-07-09

## Problem

WindowShop requires administrator, merchant, customer, and future role contexts without duplicating credential security.

## Decision

Use Laravel authentication with a canonical users provider and context-specific guards/middleware. Keep Laravel password, reset, remember-token, and session behavior unless an approved requirement changes it.

## Alternatives Considered

- Separate authentication applications and credential stores.
- A custom authentication framework.
- One undifferentiated guard with role checks only in controllers.

## Reason

Laravel provides reviewed security primitives, while explicit contexts and policies keep domain boundaries understandable.

## Consequences

New contexts require coordinated guard, middleware, route, profile, session tracking, authorization, and test changes.

