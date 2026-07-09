# Authentication Architecture

## Purpose

WindowShop uses Laravel authentication with one canonical `users` identity table. Domain profiles such as administrators, merchants, and customers reference `users.id` instead of storing passwords independently.

## Current State

- Laravel's default `web` guard and `users` provider remain active.
- Passwords and remember tokens use Laravel's standard behavior.
- The `admins` table provides administrator profile data.
- `auth_user_sessions` provides the database foundation for active and historical authenticated sessions.
- `auth_user_login_history` provides permanent successful and failed login-attempt history.
- Password reset, email verification, and mobile verification token tables are available; application flows are not implemented yet.
- Merchant and customer profile tables are not created yet.

## Target Guard Contexts

The application recognizes these authentication contexts:

- `admin`
- `merchant`
- `customer`

Guard names remain documented strings during the database-foundation phases. PHP enums may be introduced later with the application layer. Adding a guard requires matching provider, middleware, route, test, session-tracking, and authorization changes.

## Login Flow

1. Validate and normalize the submitted identifier.
2. Apply rate limiting before credential verification.
3. Find the canonical user and verify its status.
4. Verify the password, OTP, or approved social identity.
5. Verify that the requested domain profile is active.
6. Regenerate the Laravel session identifier.
7. Update `users.last_login_at` and `users.last_login_ip`.
8. Create or update the matching `auth_user_sessions` record.
9. Append an `auth_user_login_history` record.

Failed attempts must also be logged without storing passwords, OTPs, access tokens, or other secrets.

## Logout and Revocation

Logout invalidates the Laravel session, regenerates the CSRF token, and closes the matching `auth_user_sessions` record. Forced logout and password changes must revoke applicable sessions and record a documented `logout_reason`.

## Identifier Rules

- Numeric primary keys are internal database identifiers.
- UUIDs are public identifiers exposed in URLs and APIs.
- Email and mobile identifiers are unique when present.
- Authentication queries must not expose whether an account exists.

## Guards and Providers

All identities use the `users` provider. Guards describe context rather than separate credential stores.

| Guard/context | Purpose | State |
|---|---|---|
| `web` | Laravel browser session | Implemented |
| `admin` | Owner, super-admin, and admin access | Planned configuration |
| `merchant` | Merchant and merchant-staff access | Planned |
| `customer` | Customer access | Planned |

## Middleware

- `auth:<guard>` requires authentication.
- Domain middleware verifies the matching active profile.
- Policies and gates authorize resources and capabilities.
- Guest middleware protects login and reset pages.
- Throttling protects login, OTP, reset, and verification endpoints.

Middleware never replaces resource-level authorization.

## Password Reset

Use Laravel's password broker and hashed, expiring reset tokens. Responses must resist account enumeration. Requests are throttled. A successful reset revokes applicable sessions and records `password_changed` as the logout reason.

## Remember Me

Remember Me uses Laravel's standard remember-token behavior and requires explicit consent. Remembered sessions remain subject to status checks, revocation, maximum lifetime, and re-authentication for sensitive actions.

## Session Handling and Concurrent Login

- Regenerate the session ID after login and the CSRF token after logout.
- Track authenticated sessions in `auth_user_sessions`.
- Close sessions on logout, timeout, forced logout, password change, or suspension.
- Never log raw session IDs, cookies, or remember tokens.
- Initially permit multiple active devices and allow individual or authorized forced revocation.
- Treat per-role limits and single-session enforcement as configurable future policy.

## OTP

OTP authentication is planned. OTPs must be securely generated, short-lived, single-use, hashed at rest, purpose-bound, attempt-limited, and resend-throttled. Raw OTPs must never be logged.

## Social Login

Google is the first planned provider; Facebook and Apple may follow. Provider identities link to canonical users through verified provider IDs. Email alone must not silently merge accounts without a secure linking flow.

## Registration Flow

Registration is not implemented yet. The planned flow is:

1. Validate and normalize identity/profile input.
2. Enforce email/mobile uniqueness without exposing existing accounts.
3. Create the canonical `users` record with a hashed password when password authentication is used.
4. Assign the approved default role through `auth_user_roles`.
5. Create email or mobile verification records as required.
6. Dispatch verification through a future notification service.
7. Permit only actions allowed for the user's verification and status state.

Role-specific business profiles are created by their own modules and reference `users.id`; they do not duplicate credentials.

## Email Verification

- Store only hashed tokens or OTPs in `auth_email_verification_tokens`.
- Bind each record to its user and submitted email.
- Enforce expiration, single use, throttling, and attempt limits in the future application layer.
- On success, set the token record to `verified`, populate its `verified_at`, and update `users.email_verified_at`.
- Reissuing verification revokes or expires older active challenges according to the final policy.

## Mobile Verification

- Store only hashed tokens or OTPs in `auth_mobile_verification_tokens`.
- Bind each record to its user and submitted mobile number.
- Enforce expiration, single use, throttling, and attempt limits.
- On success, set the token record to `verified`, populate its `verified_at`, and update `users.mobile_verified_at`.
- Normalize mobile numbers consistently before storage or comparison.

## Password Reset Flow

1. Accept an email or mobile identifier without revealing whether it exists.
2. Apply request and delivery throttles.
3. Generate a cryptographically secure token or OTP.
4. Store only its hash in `auth_password_reset_tokens`, with channel, expiry, IP, and user agent.
5. Deliver the raw value once through an approved channel.
6. Verify the submitted value safely and atomically mark the record `used`.
7. Update the password through Laravel's hasher.
8. Revoke applicable sessions with `password_changed`.
9. Append the appropriate security history.

Laravel's default `password_reset_tokens` table remains available until application wiring explicitly moves the broker to the prefixed table.

## Multi-Device Login

Multiple active devices are initially allowed. Each successful login creates a distinct `auth_user_sessions` record with device, browser, platform, IP, and activity metadata where available. Users may later view and revoke individual devices. Per-role device limits require a separate approved policy.

## Forced Logout

Authorized security workflows may terminate one or all sessions. Termination must:

- invalidate the underlying Laravel session where possible;
- set `is_active` to false;
- set `logout_at`;
- record `forced`, `password_changed`, or `admin_terminated`;
- append an auditable history event;
- avoid exposing raw session identifiers.

## Role Assignment

Users receive roles through `auth_user_roles`. Role assignment and removal require dedicated authorization and audit records. User type is never inferred from a separate credential table. A user may have multiple roles if business policy permits it.

Default seeded role slugs are:

- `super_admin`
- `admin`
- `merchant`
- `customer`

## Permission Checking

Permissions are defined in `auth_permissions` and assigned to roles through `auth_role_permissions`. The future application layer will resolve effective permissions from active users, active roles, and active permissions, then apply resource ownership and tenant/shop scope through policies.

Authentication, role membership, permission checks, and business-resource scope remain separate decisions. UI visibility is never accepted as authorization.

## Future Authentication Capabilities

- Two-factor authentication and recovery codes
- API/personal access tokens
- Google, Facebook, and Apple login
- Configurable concurrent-session limits
- Session/device management UI
- Centralized forced-logout administration
