# ADR-0006: Soft-Delete Policy

- Status: Accepted
- Date: 2026-07-09

## Problem

Hard deletion damages recovery, investigation, and historical references.

## Decision

Use soft deletes where recovery, auditability, or history matters. Append-only logs use retention policies instead. Hard deletion is a separate privileged operation.

## Alternatives Considered

- Hard-delete every record.
- Never delete records and use status alone.
- Archive deleted data into separate tables.

## Reason

Soft deletes provide practical recovery and historical continuity while retaining familiar Laravel query behavior.

## Consequences

Queries and uniqueness rules must account for deleted rows, and restoration and deletion require authorization and audit records.
