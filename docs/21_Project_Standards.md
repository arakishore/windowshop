# WindowShop Project Standards

## Purpose

This document is the project-wide rulebook for WindowShop. It defines default conventions for implementation, review, and architecture.

Detailed subject documents remain authoritative within their scope. If standards conflict, resolve the conflict explicitly and record a significant decision in an Architecture Decision Record (ADR).

## 1. Naming Conventions

### PHP

- Classes, enums, traits, and interfaces use `PascalCase`.
- Methods, properties, and variables use `camelCase`.
- Constants and enum case names use `UPPER_SNAKE_CASE` unless framework conventions require otherwise.
- Names describe business intent; avoid vague terms such as `data`, `helper`, `manager`, or `common`.
- Boolean names begin with `is`, `has`, `can`, or `should`.

### Database

- Tables and columns use `snake_case`.
- Table names are plural.
- Foreign keys use `<singular>_id`.
- Authentication tables use the `auth_` prefix, except Laravel's conventional `users` table.
- Index and constraint names follow Laravel conventions unless an explicit shorter name is needed.
- Public business identifiers use `uuid`; numeric `id` remains internal.

### Routes and APIs

- Route names use dot notation, such as `admin.users.index`.
- URI segments use lowercase kebab-case and plural resources.
- JSON field names use `snake_case`.
- Permission names use `<resource>.<action>`, such as `orders.refund`.

## 2. Laravel Standards

### Controllers

- Controllers coordinate HTTP concerns and remain thin.
- They delegate validation to Form Requests, authorization to policies/gates, and workflows to actions or services.
- Controllers do not contain reusable business logic or direct provider integrations.
- Resource controllers use conventional method names where appropriate.

### Models

- Models define relationships, casts, scopes, fillable/guarded policy, and small persistence-aware behavior.
- Models do not orchestrate large workflows or call external services.
- Enum-like columns may cast to PHP 8.2 backed enums after the enum layer is introduced; the current authentication foundation uses documented strings.
- Public route binding should use UUIDs where required.

### Actions and Services

- Actions represent focused use cases with clear inputs and outcomes.
- Services coordinate reusable multi-step workflows or isolate external integrations.
- Dependencies are injected through constructors.
- Avoid static service locators and oversized “god services.”

### Repositories

- Do not add repositories around Eloquent by default.
- Introduce a repository only when it provides a real boundary, such as multiple persistence sources, complex reusable queries, or replaceable infrastructure.
- Repository interfaces belong to the application/domain boundary; implementations belong to infrastructure code.

### Form Requests and Resources

- Form Requests own request validation and request-level authorization.
- API Resources define external response shapes.
- Do not return unrestricted Eloquent models directly from public APIs.
- Normalize and validate input before passing it to business workflows.

### Events, Listeners, and Jobs

- Events describe completed domain facts in past tense.
- Listeners handle independent reactions to events.
- Queue slow, retryable, or external work.
- Jobs must be idempotent when retries are possible.
- Configure explicit timeout, retry, backoff, and failure behavior.
- Do not keep database transactions open during network calls.

## 3. Database Standards

- Follow [09_Database_Standards.md](09_Database_Standards.md) as the database constitution.
- Use Laravel migrations for every schema change.
- Business tables follow the documented identifier, status, timestamp, audit, and soft-delete baseline unless an ADR approves an exception.
- Define foreign-key deletion behavior deliberately.
- Index foreign keys, unique values, and proven query patterns.
- Comment non-obvious business fields.
- Database comments on enum-like fields list their allowed values.
- When PHP enums are introduced, keep enums, validation, casts, tests, migrations, and the master data dictionary synchronized.
- Never use floating-point fields for money.

## 4. Authentication Standards

- Follow [08_Authentication_Architecture.md](08_Authentication_Architecture.md).
- Use one canonical `users` identity table for every user type.
- Prefix authentication and authorization tables with `auth_`; `users` is the Laravel-convention exception.
- Include a UUID on business and authentication records that may need a public identifier.
- Public APIs and URLs use UUIDs instead of internal numeric IDs.
- Use soft deletes where authentication records require lifecycle history or recovery.
- Store allowed status/channel/reason values in database column comments.
- Do not introduce PHP enum classes during the current database-foundation phases.
- Foreign-key columns use the `*_id` convention.
- Every `user_id` authentication reference points to `users.id`.
- Roles and profiles determine context; do not duplicate credential tables per role.
- Authorization uses users → roles → permissions through explicit pivot tables.
- Use Laravel's authentication, hashing, password broker, remember-token, session, and CSRF facilities.
- Regenerate session IDs after login and revoke applicable sessions after security-sensitive identity changes.
- Track active/multi-device sessions in `auth_user_sessions`.
- Permanently retain login-attempt history in `auth_user_login_history`; hard user deletion sets its user reference to null.
- Rate-limit login, reset, OTP, and verification endpoints.
- Persist reset and verification tokens only as hashes.
- Persist OTP values only as hashes.
- Record successful and failed login attempts without logging secrets.
- Authentication proves identity; authorization separately decides access.

## 5. API Standards

- APIs are versioned when backward compatibility becomes a release concern.
- Use resource-oriented endpoints and appropriate HTTP methods/status codes.
- Return consistent success, error, validation, and pagination structures.
- Expose UUIDs rather than internal numeric IDs where required.
- Use ISO 8601 timestamps with an explicit timezone.
- Use strings for decimal money in JSON unless a documented minor-unit integer contract is chosen.
- Paginate all potentially unbounded collections.
- Support idempotency for retried payment and other critical write operations.
- Do not expose stack traces, SQL errors, provider payloads, or internal exception details.

Suggested error shape:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email field is required."
    ]
  }
}
```

## 6. Validation Standards

- Treat every external value as untrusted.
- Validate through Form Requests or dedicated validators.
- Validation verifies shape and acceptable input; authorization verifies permission.
- Prefer allowlists over denylists.
- Normalize email, phone, identifiers, and enum values consistently before business processing.
- Validate database uniqueness with correct tenant and soft-delete scope.
- Validate again at the database layer through constraints where possible.
- Return safe, actionable validation messages without revealing protected records.

## 7. Logging Standards

- Follow [13_Logging_Audit_System.md](13_Logging_Audit_System.md).
- Use structured logs with event names and correlation/request IDs.
- Record actor, action, target, time, origin, outcome, and safe changes for auditable events.
- Use appropriate log levels consistently.
- Never log passwords, OTPs, tokens, cookies, authorization headers, payment credentials, or unnecessary personal data.
- Redact sensitive provider requests and responses.
- Append-only audit history is separate from transient diagnostic logging.

## 8. File Upload Standards

- Validate file size, extension, MIME type, and actual content where feasible.
- Use server-generated filenames and never trust the original filename.
- Store files outside executable paths.
- Separate public and private storage.
- Serve private files through authorized endpoints or short-lived signed URLs.
- Scan high-risk uploads before use.
- Process images and large files asynchronously when appropriate.
- Remove temporary and abandoned files through scheduled cleanup.

## 9. Security Standards

- Follow [12_Security_Guidelines.md](12_Security_Guidelines.md).
- Deny access by default and apply least privilege.
- Authorize every protected resource operation server-side.
- Keep CSRF protection and escaped Blade output enabled.
- Use parameterized queries/Eloquent and never concatenate untrusted SQL.
- Keep secrets in protected environment or secret-management systems.
- Require HTTPS, secure cookies, and production-safe error handling.
- Apply rate limits and stronger verification to sensitive operations.
- Review dependencies and security advisories before releases.

## 10. Git Standards

- Keep commits focused, reviewable, and written in imperative language.
- Use branches named by intent, such as `feature/merchant-approval`, `fix/session-timeout`, or `docs/database-standards`.
- Do not commit secrets, `.env`, generated dependencies, production data, logs, or editor-specific files.
- Update tests and documentation in the same change as behavior.
- Do not rewrite shared branch history without team agreement.
- Pull requests explain purpose, important decisions, migration impact, security impact, and verification performed.
- Squash noisy work-in-progress commits when the team's merge strategy requires it.

Recommended commit format:

```text
type(scope): concise imperative summary
```

Common types include `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, and `perf`.

## 11. Coding Standards

- Use PHP 8.2+ language features where they improve clarity.
- Follow PSR-12 and Laravel conventions.
- Format PHP with Laravel Pint.
- Use strict, meaningful parameter and return types.
- Prefer early returns and small cohesive methods.
- Prefer dependency injection and composition over global state and inheritance.
- Use PHP backed enums for finite business values after the enum layer is introduced; until then, use documented validated strings.
- Comments explain why, constraints, or risk—not obvious syntax.
- Remove dead code instead of commenting it out.
- Add tests proportional to behavior and risk.

## 12. Folder Structure

- Follow [10_File_Folder_Structure.md](10_File_Folder_Structure.md).
- Place code by responsibility, not by convenience.
- Create folders only when the first real class needs them.
- Keep transport, business workflow, persistence, and external integration concerns separated.
- Shared abstractions require more than one proven consumer.
- Avoid catch-all utility folders and circular dependencies between modules.

## 13. Localization Strategy

WindowShop Version 1 targets one operating region:

| Level | Default |
|---|---|
| Country | India (`IN`) |
| State | Maharashtra (`MH`) |
| City | Nashik |

The application stores these localization defaults in `system_settings`:

- `default_language` = `en`
- `default_currency` = `INR`
- `default_timezone` = `Asia/Kolkata`

Dedicated `system_languages`, `system_currencies`, and `system_timezones` master tables are intentionally deferred. They will be introduced only when the platform expands to multiple countries or regions and requires managed catalogues, conversions, or per-tenant localization.

Business modules must consume localization defaults through the settings boundary instead of hardcoding numeric database IDs.

## 14. Future Architecture Decisions

- Record significant decisions in `docs/decisions/` before or alongside implementation.
- Every ADR includes: Problem, Decision, Alternatives Considered, Reason, and Consequences.
- ADRs are immutable historical records once accepted; supersede them with a new ADR instead of rewriting history.
- Update this rulebook when an accepted ADR changes a project-wide default.

Likely future ADR topics include:

- Role and permission implementation
- Multi-tenancy and merchant/shop boundaries
- Payment provider and ledger strategy
- Order and inventory consistency
- Cache and Redis strategy
- Queue topology
- File/object storage
- Search architecture
- Notification channels
- Logs database separation
- API versioning and compatibility
- Data retention and privacy

## Standards Review Checklist

- Does the change follow naming and folder conventions?
- Are validation, authorization, and security boundaries explicit?
- Are schema fields, indexes, comments, and audit behavior correct?
- Are API contracts stable and intentionally shaped?
- Are logs useful and safely redacted?
- Are tests and documentation updated?
- Does the change introduce a decision that needs an ADR?
