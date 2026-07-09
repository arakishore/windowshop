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

