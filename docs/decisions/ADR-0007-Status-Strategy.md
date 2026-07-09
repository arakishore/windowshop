# ADR-0007: Status Strategy

- Status: Accepted
- Date: 2026-07-09

## Problem

Status values span application logic, migrations, operations, and reports. Undocumented strings drift, while database-native enums can be cumbersome to evolve.

## Decision

Use length-bounded status strings, list allowed values in database comments, and mirror them with PHP 8.2 backed enums. Native database enums may represent stable event outcomes such as `success` and `failed`.

## Alternatives Considered

- Database enums for every status.
- Unconstrained strings without PHP enums.
- Numeric status codes.

## Reason

Strings remain readable and migration-friendly, PHP enums provide type safety, and database comments keep the schema self-documenting.

## Consequences

Enums, validation, casts, tests, and migration comments must change together.
