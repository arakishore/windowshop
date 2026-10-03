# Deployment Guide

## Environments

| Environment | Purpose | Data policy |
|---|---|---|
| Local | Individual development | Seeded or synthetic data |
| Development | Shared integration | Non-production data |
| Staging | Production-like verification | Sanitized or synthetic data |
| Production | Live service | Restricted production data |

Configuration differs by environment; committed application code does not.

## Environment Variables

- Start from `.env.example`; never commit `.env`.
- Manage production secrets through an approved secret store or protected deployment system.
- Configure app URL/key/environment, database, cache, queue, session, mail, storage, logging, and provider credentials.
- Rotate credentials and document ownership and expiry.
- Validate required variables during deployment without printing secrets.

## Standard Deployment

1. Put the application into maintenance mode when the release requires it.
2. Fetch the immutable release artifact.
3. Install production Composer and frontend dependencies from lock files.
4. Build frontend assets.
5. Run automated checks.
6. Back up data before risky schema changes.
7. Run `php artisan migrate --force`.
8. Cache production configuration, routes, events, and views as appropriate.
9. Restart queue workers so they load the new code.
10. Run smoke checks and monitor logs/metrics.
11. Leave maintenance mode.

Use zero-downtime, backward-compatible database changes when availability requires them.

## Scheduler and Cron

Configure one scheduler entry:

```cron
* * * * * cd /path/to/windowshop && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled commands must be overlap-safe and observable.

## Queues and Supervisor

- Use durable queue workers in non-local environments.
- Supervisor or an equivalent service manager restarts failed processes.
- Configure queue names, worker counts, timeouts, tries, memory limits, and stop wait time.
- Monitor failed jobs and provide an approved retry process.
- Run `php artisan queue:restart` after deployment.

### Shared-hosting transactional email queue

WindowShop business-event emails use Laravel's database queue on the dedicated `emails` queue. Production must keep `QUEUE_CONNECTION=database` and must have the standard `jobs`, `job_batches`, and `failed_jobs` migrations applied. SMTP credentials remain in the existing encrypted notification settings and are resolved only by the worker; they are not stored in queue payloads.

The existing once-per-minute `schedule:run` cron is sufficient for the shared-hosting V1 setup. The application scheduler starts a bounded `queue:work database --queue=emails --stop-when-empty` process every minute and prevents overlapping executions. A permanent Supervisor worker is therefore optional for this deployment model, not required.

Operational checks:

- Review pending work with the `jobs` table, filtering the queue column to `emails`.
- Review exhausted jobs with `php artisan queue:failed` and the `failed_jobs` table.
- After correcting a transient or configuration problem, retry a failed job with `php artisan queue:retry <job-uuid>` or all failed jobs with `php artisan queue:retry all` after confirming that replay is appropriate.
- Monitor notification delivery logs for entries that remain `queued` or `processing`, and investigate cron failures or worker timeouts before retrying them.
- Keep the worker timeout below the database queue `retry_after` value. The V1 scheduler uses a 60-second timeout against the default 90-second retry window.

Email jobs make three attempts with increasing backoff. SMTP failures are recorded on the existing notification delivery log before the job attempt fails, allowing Laravel retry and failed-job handling to remain visible. Because an SMTP server can accept a message before a connection failure is observed, a retry after an ambiguous transport failure can rarely produce a duplicate email.

## Redis

Redis may back cache, queues, rate limits, and sessions after environment review. Use distinct prefixes/databases, authentication, network restrictions, memory policy, persistence decisions, and monitoring. Redis must not become an undocumented single point of failure.

## Backups and Recovery

- Back up databases and required private files on an approved schedule.
- Encrypt backups, restrict access, and store copies outside the primary server.
- Define retention, recovery-point, and recovery-time objectives.
- Test restoration regularly; an untested backup is not a recovery plan.

## Production Safety

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Enforce HTTPS and secure cookie settings.
- Restrict phpMyAdmin, diagnostics, storage, logs, and administrative endpoints.
- Monitor health, errors, latency, queues, disk, database, Redis, and certificate expiry.
- Keep a tested rollback plan for code and a forward-recovery plan for migrations.
