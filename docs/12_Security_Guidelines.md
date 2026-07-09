# Security Guidelines

## Authentication

- Use Laravel hashing and authentication APIs; never implement password cryptography manually.
- Regenerate sessions after login and privilege changes.
- Invalidate relevant sessions after password changes or account suspension.
- Rate-limit login, password reset, OTP, and verification endpoints.
- Require stronger verification for destructive or high-value actions.

## Input and Output

- Validate all external input with Form Requests or equivalent validators.
- Authorize independently of validation.
- Use Eloquent or parameter binding; never concatenate untrusted SQL.
- Escape output by default and review every use of unescaped HTML.
- Restrict uploads by size, MIME type, extension, and storage location.

## Secrets and Privacy

- Keep secrets in environment configuration or an approved secret manager.
- Never commit `.env`, credentials, tokens, private keys, or production data.
- Never log passwords, OTPs, session IDs, authorization headers, or payment secrets.
- Minimize stored personal and location data.
- Encrypt sensitive values when confidentiality is required beyond database access controls.

## Web and API Controls

- Keep CSRF protection enabled for session-authenticated web routes.
- Configure cookies as secure, HTTP-only, and appropriately SameSite in production.
- Use HTTPS in every non-local environment.
- Apply least-privilege CORS rules.
- Return generic authentication errors and avoid account enumeration.

## Dependency and Release Security

- Pin dependencies through lock files.
- Review dependency advisories before releases.
- Disable debug mode in production.
- Restrict production diagnostics, queues, schedulers, and administrative tools.
- Back up data and test restoration procedures.

## Password Policy

- Require at least 12 characters for privileged accounts.
- Permit password managers and long passphrases.
- Reject known-compromised passwords where practical.
- Hash through Laravel and never store or log plaintext passwords.
- Require reset after compromise; avoid arbitrary periodic rotation.

## Two-Factor Authentication

- Require 2FA for owners and super admins before production launch.
- Store recovery codes hashed and show them only once.
- Rate-limit and audit challenges, recovery, enablement, and disablement.
- Require recent password confirmation before changing 2FA settings.

## Session Timeout

- Configure idle and absolute lifetimes.
- Use shorter limits for privileged contexts.
- Record timed-out sessions with `timeout`.
- Re-authenticate sensitive operations where appropriate.

## CSRF, XSS, and Uploads

- Keep CSRF middleware enabled for state-changing browser requests.
- Escape Blade output by default and allowlist supported rich HTML.
- Validate upload size, MIME type, extension, and content where feasible.
- Generate server-side filenames and store uploads outside executable paths.
- Serve private files only through authorized endpoints.

## Audit Requirements

Authentication, permission changes, admin actions, exports, refunds, session revocation, and destructive operations must record actor, action, target, time, origin, outcome, and safe changes.
