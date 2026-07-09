# Database Standards

## General Rules

- Define schema changes through Laravel migrations.
- Never edit an already deployed migration; create a forward migration instead.
- During initial development, existing migrations may be refined only when no shared or production data depends on them.
- Use InnoDB, `utf8mb4`, and `utf8mb4_unicode_ci`.
- Use Laravel schema methods instead of raw SQL unless the schema builder cannot express the requirement.

## Naming

- Tables and columns use `snake_case`.
- Table names are plural.
- Foreign keys use `<singular>_id`.
- Indexes use Laravel's conventional names unless a shorter explicit name is required by the database.

## Keys and Relationships

- Use `$table->id()` for internal primary keys.
- Business entities exposed publicly use a unique UUID.
- Use `foreignId()->constrained()` for relationships.
- Choose deletion behavior deliberately: cascade dependent records, set nullable historical references to null, or restrict deletion.
- Index foreign keys and columns used frequently for filtering or sorting.
- Add composite indexes for established multi-column query patterns.

## Column Documentation

- Every business column whose meaning is not obvious must have a database comment.
- Every enum-like string column must list its allowed values in its comment.
- PHP 8.2 backed enums are the application source of type safety.
- Migration comments and PHP enum cases must remain synchronized.
- Obvious structural columns such as `id`, `created_at`, and `updated_at` do not need comments.

Example:

```php
$table->string('status', 30)
    ->default('active')
    ->comment('active,inactive,suspended,deleted');
```

## Data Types

- Store IP addresses in `VARCHAR(45)` to support IPv4 and IPv6.
- Store money as fixed-point decimal or integer minor units; never use float.
- Use timestamps for Laravel lifecycle fields and choose date/time types consistently for business events.
- Make nullability explicit and meaningful.
- Prefer enum-like strings plus PHP enums when values may evolve across releases.

## Audit and Deletion

- Business records that require recovery or history use soft deletes.
- Audited business tables may include `created_by`, `updated_by`, and `deleted_by`.
- Historical logs must not be cascade-deleted with a user; nullable references should use `nullOnDelete()`.

## Business Table Constitution

Every business table should contain these fields unless an approved ADR documents why a field does not apply:

```text
id
uuid
status
created_by
updated_by
deleted_by
created_at
updated_at
deleted_at
```

Join tables, framework infrastructure tables, append-only logs, and immutable events may use a smaller intentional shape.

## UUID Rules

- Use a unique Laravel UUID column.
- Generate UUIDs in application code before insert.
- Expose UUIDs in URLs and APIs; keep numeric IDs internal.
- Continue using numeric IDs for most foreign-key joins.
- Never change a UUID after creation.

## Indexing Guidelines

- Index unique identifiers, foreign keys, common filters, and common sort fields.
- Order composite indexes by the leading filters in real queries.
- Avoid redundant indexes already covered by a unique or composite index.
- Index low-cardinality values only for a demonstrated query pattern, often in a composite index.
- Review query plans for important or slow queries.

## Audit Field Usage

- `created_by` is assigned once.
- `updated_by` identifies the latest actor.
- `deleted_by` is assigned immediately before soft deletion.
- System jobs use a documented system-actor strategy, not a fabricated user.
- Restore and hard-delete operations require authorization and audit records.
