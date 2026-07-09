# File and Folder Structure

## Application Layout

```text
app/
├── Enums/                 PHP backed enums
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   ├── Merchant/
│   │   └── Customer/
│   ├── Middleware/
│   ├── Requests/          Validation and authorization
│   └── Resources/         API response resources
├── Models/                Eloquent models and relationships
├── Policies/              Model authorization policies
├── Services/              Business workflows and integrations
├── Actions/               Small single-purpose application actions
├── Jobs/                  Queueable background work
├── Events/
├── Listeners/
└── Providers/
```

Create folders only when the first real class needs them. Do not add empty architectural scaffolding.

## Supporting Layout

```text
database/
├── factories/
├── migrations/
└── seeders/

resources/
├── css/
├── js/
└── views/
    ├── admin/
    ├── merchant/
    ├── customer/
    └── components/

routes/
├── web.php
├── api.php
├── admin.php
├── merchant.php
└── customer.php

tests/
├── Feature/
└── Unit/
```

Route files beyond Laravel's defaults must be registered explicitly in `bootstrap/app.php`.

## Placement Rules

- Controllers coordinate HTTP concerns; they do not contain large business workflows.
- Form Requests own input validation and request authorization.
- Policies and gates own authorization decisions.
- Services and actions own reusable business behavior.
- Models own relationships, casts, scopes, and small domain helpers.
- External provider code belongs behind an application-owned service interface.
- Tests mirror the feature or domain they verify.

## Standard Application Folders

- `Actions/`: focused, single-use-case operations.
- `Enums/`: PHP 8.2 backed enums for finite values.
- `Helpers/`: small stateless cross-cutting helpers; no domain workflows.
- `Http/`: controllers, middleware, requests, and API resources.
- `Models/`: persistence, relationships, casts, scopes, and small model behavior.
- `Services/`: reusable workflows and provider boundaries.
- `Traits/`: narrow reusable behavior when composition adds no value.

Create a folder only when its first real class is needed. Avoid vague catch-all classes such as `Common`, `Utility`, or `Manager`.
