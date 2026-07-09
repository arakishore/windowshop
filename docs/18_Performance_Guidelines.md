# Performance Guidelines

## Database Queries

- Select only required columns in application queries; avoid unbounded `SELECT *`.
- Paginate every potentially large list; use cursor pagination for suitable high-volume feeds.
- Eager-load known relationships and prevent N+1 queries.
- Use `withCount`, aggregates, joins, or dedicated queries instead of loops that query.
- Process large datasets with chunks, lazy collections, or queued jobs.
- Keep transactions short and lock only what the workflow requires.

## Indexes and Query Plans

- Index foreign keys, public identifiers, frequent filters, and proven sort patterns.
- Design composite indexes around actual query order.
- Run `EXPLAIN` for important new queries and before merging query-sensitive features.
- Investigate slow-query logs in staging and production.
- Avoid duplicate or speculative indexes; writes also pay their cost.

## Caching

- Cache stable product pages, catalogue fragments, settings, reference data, and expensive aggregates when beneficial.
- Define cache key ownership, scope, version, lifetime, and invalidation before adding a cache.
- Include tenant/shop, locale, currency, and permission scope where relevant.
- Never cache private responses under a shared key.
- Prefer event-driven invalidation for correctness-sensitive data.

## HTTP and Frontend

- Paginate APIs and keep response resources intentionally small.
- Compress and version static assets through the build pipeline.
- Optimize images, use appropriate formats/sizes, and lazy-load below-the-fold media.
- Avoid blocking third-party scripts.
- Set cache headers deliberately for public and private content.

## Queues and External Services

- Queue email, messages, imports, exports, image work, webhooks, and other slow tasks.
- Jobs must be idempotent when retries are possible.
- Configure timeouts, bounded retries, backoff, and failure handling.
- Do not keep database transactions open during network calls.

## Measurement and Merge Gate

- Establish a baseline before optimizing.
- Measure response time, query count/time, memory, queue latency, cache hit rate, and error rate.
- Add performance tests for critical high-volume workflows.
- A performance-sensitive change should include query evidence, `EXPLAIN` output where relevant, and before/after measurements.

