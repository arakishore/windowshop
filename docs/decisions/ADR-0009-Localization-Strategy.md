# ADR-0009: Localization Strategy for Version 1

- Status: Accepted
- Date: 2026-07-09

## Problem

WindowShop needs clear language, currency, timezone, and operating-location defaults. Version 1 targets only India, so dedicated localization master tables would add schema and maintenance without a current business requirement.

## Decision

WindowShop Version 1 targets:

- Country: India (`IN`)
- State: Maharashtra (`MH`)
- City: Nashik
- Language: English (`en`)
- Currency: Indian Rupee (`INR`)
- Timezone: `Asia/Kolkata`

Language, currency, and timezone defaults are stored in `system_settings`. Dedicated `system_languages`, `system_currencies`, and `system_timezones` tables are postponed.

## Alternatives Considered

- Create complete language, currency, and timezone master tables immediately.
- Hardcode localization values throughout application modules.
- Depend only on environment variables or static configuration.

## Reason

- Simpler Version 1 database
- Lower maintenance and seed-data burden
- Faster implementation
- No current multi-country business requirement
- Central settings remain changeable without scattering constants through business code

## Consequences

- Version 1 does not provide managed language, currency, or timezone catalogues.
- Business modules read defaults from the settings boundary.
- Currency conversion and per-user/per-tenant localization are out of scope.
- Existing location master tables remain available for geographic reference data.

## Future

When WindowShop expands to multiple countries or regions, introduce:

- `system_languages`
- `system_currencies`
- `system_timezones`

The new master tables should integrate through settings and localization services without requiring existing business modules to own localization data.

