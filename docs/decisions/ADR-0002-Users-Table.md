# ADR-0002: Users Table

- Status: Accepted
- Date: 2026-07-09

## Problem

Administrators, merchants, customers, and future actors all require authentication. Separate credential stores duplicate security behavior.

## Decision

`users` is the canonical identity and credential table. Domain profile tables reference `users.id`; guard contexts do not create separate password stores.

## Alternatives Considered

- Separate credential tables for every user type.
- A polymorphic credentials table.
- Independent authentication systems per application area.

## Reason

A shared identity keeps password security, verification, recovery, and session policy consistent while domain tables retain role-specific data.

## Consequences

One identity may have multiple profiles, and authorization must verify both the requested profile and its scope.
