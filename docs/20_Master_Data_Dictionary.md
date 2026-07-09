# Master Data Dictionary

## Purpose

This document is the human-readable register for constants and workflow values shared across WindowShop. PHP 8.2 backed enums remain authoritative in code; database comments mirror allowed database values. Update code, validation, casts, tests, migrations, and this register together.

## Implemented Values

### Account and Profile Status

| Value | Meaning |
|---|---|
| `active` | Enabled for normal use |
| `inactive` | Disabled without a security suspension |
| `suspended` | Access blocked by an authorized action |
| `deleted` | Business-level deleted state where explicitly used |

### Authentication Guards

| Value | Meaning | State |
|---|---|---|
| `admin` | Platform administration context | Tracked/planned guard |
| `merchant` | Merchant context | Tracked/planned guard |
| `customer` | Customer context | Tracked/planned guard |

Laravel's current configured browser guard remains `web` until context guards are implemented.

### Login Methods

| Value | Meaning |
|---|---|
| `password` | Password authentication |
| `otp` | One-time-password authentication |
| `google` | Google identity |
| `facebook` | Facebook identity |
| `apple` | Apple identity |

### Login Outcomes

| Value | Meaning |
|---|---|
| `success` | Authentication completed |
| `failed` | Authentication rejected |

### Logout Reasons

| Value | Meaning |
|---|---|
| `manual` | User explicitly logged out |
| `timeout` | Session expired |
| `force_logout` | Authorized actor/system revoked session |
| `password_changed` | Password change revoked session |

### Device Types

| Value | Meaning |
|---|---|
| `desktop` | Desktop/laptop browser |
| `mobile` | Mobile handset |
| `tablet` | Tablet device |
| `bot` | Automated client |
| `unknown` | Could not classify safely |

## Planned Dictionaries

These categories require business approval before values become canonical:

- Payment methods and payment/refund statuses
- Order and fulfillment statuses
- Return and exchange statuses
- Shipping and delivery statuses
- Inventory movement types
- Notification channels and types
- Coupon, wallet, commission, and referral types
- AI providers and use cases
- Countries, states, languages, currencies, and tax categories

## Entry Template

```text
Category:
PHP enum:
Database fields:
Value:
Label:
Meaning:
Terminal state: yes/no
Allowed transitions:
Introduced:
Deprecated:
Related business rule or ADR:
```

