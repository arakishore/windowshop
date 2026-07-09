# Logging and Audit System

## Purpose

Logging supports troubleshooting, security monitoring, accountability, and historical investigation. Logs are not a substitute for primary business records.

## Login Records

`user_sessions` tracks login sessions, device metadata, activity, logout time, and logout reason. Active-session lookups use the composite `user_id, is_active` index.

`user_login_logs` permanently records successful and failed attempts. Its nullable user reference uses `nullOnDelete()` so historical events survive hard deletion of an identity.

## Database Separation

The planned deployment separates data by purpose:

- Main database: transactional and business data.
- Logs database: high-volume activity, audit, API, notification, AI usage, and diagnostic history.

Moving a log table to the logs database requires explicit connection configuration, retention planning, and cross-database relationship review.

## Event Requirements

Audit events should capture:

- event name and outcome
- actor identity and authentication context
- affected resource type and public identifier
- timestamp
- request or correlation identifier
- IP and device metadata where justified
- safe before/after changes for audited fields

## Safety

- Never log secrets or raw credentials.
- Redact sensitive request and response fields.
- Treat user agent and network-location data as untrusted input.
- Logs should be append-only to normal application users.
- Clock synchronization and a consistent application timezone are required.

## Retention

Define retention by event class, legal need, operational value, storage cost, and privacy impact. Purging must be scheduled, observable, and excluded from records under an active retention hold.

## Required Audit Questions

| Question | Recorded concept |
|---|---|
| Who? | Actor user/profile and guard |
| Did what? | Event/action and outcome |
| To what? | Target type and public identifier |
| When? | Immutable timestamp |
| From where? | IP, request ID, and justified device/location data |
| What changed? | Redacted old and new values |

Old/new values contain only changed auditable fields. Passwords, OTPs, tokens, payment secrets, and unnecessary personal data are excluded.

## Required Event Categories

- Login success/failure and session lifecycle
- Admin and support actions
- Role, permission, status, and 2FA changes
- Merchant/shop configuration changes
- Orders, payments, refunds, and inventory corrections
- Data exports and sensitive record access

The future audit-log module will persist admin actions. Until its schema is approved, security-relevant admin events use structured application logs.
