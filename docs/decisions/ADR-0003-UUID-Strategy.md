# ADR-0003: UUID Strategy

- Status: Accepted
- Date: 2026-07-09

## Problem

Sequential IDs are efficient relational keys but expose predictable identifiers publicly.

## Decision

Business entities use numeric IDs internally and unique, immutable UUIDs in URLs and APIs. Relationships normally reference numeric IDs.

## Alternatives Considered

- Expose sequential numeric IDs.
- Use UUIDs as primary and foreign keys everywhere.
- Use another sortable public identifier such as ULID.

## Reason

Numeric keys keep joins compact and efficient, while UUIDs prevent predictable public identifiers and provide a stable external reference.

## Consequences

Application code generates UUIDs before insert, public route binding prefers UUIDs, and UUID lookups require unique indexes.
