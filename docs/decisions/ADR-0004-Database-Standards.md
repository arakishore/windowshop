# ADR-0004: Database Standards

- Status: Accepted
- Date: 2026-07-09

## Problem

Without shared schema rules, naming, identifiers, audit fields, comments, relationships, and indexes drift between modules.

## Decision

Treat `09_Database_Standards.md` as the database constitution. New business schema follows its baseline unless another ADR records an exception.

## Alternatives Considered

- Let each module choose its own conventions.
- Depend only on framework defaults.
- Enforce conventions informally during review.

## Reason

Written standards improve consistency, review speed, DBA visibility, and long-term maintainability.

## Consequences

Schema reviews must check the standard, exceptions require justification, and the standard must evolve alongside accepted architecture decisions.

