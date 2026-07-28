# Tax Engine Implementation

## Purpose

Build a generic tax engine for WindowShop that supports India GST by default while remaining usable for other tax systems such as UK VAT, USA sales tax, and merchants with tax disabled.

Tax logic must be centralized in services. Controllers, Blade files, POS screens, receipts, refunds, exchanges, and reports should consume resolved tax/pricing results instead of recalculating tax independently.

## Design Principles

- Use generic tax names in schema and code. Avoid GST-specific table names.
- Treat India GST records as default seed data, not hardcoded business rules.
- Support tax-disabled merchants cleanly by hiding product and POS tax fields.
- Store historical tax snapshots on orders and order items.
- Refunds and exchanges must use the original order-item tax snapshot, not the current tax master.
- Keep parent product categories as grouping-only. Apply selectable tax defaults to leaf categories.

## Recommended Execution Order

Codex should complete and report after every step:

1. Tax database foundation
2. India seed data
3. Merchant settings
4. Category default tax
5. Product override UX
6. Tax/Pricing services
7. POS and order integration
8. Receipt, returns, exchanges, reports, and tests

## Step 1: Tax Database Foundation

Status: Implemented

Generic tax master tables:

- `tax_classes`
- `tax_rates`
- `tax_components`

Responsibilities:

| Table | Purpose |
|---|---|
| `tax_classes` | User-facing tax class such as Exempt, GST 5%, VAT Standard, or Sales Tax |
| `tax_rates` | Effective-dated rate records for each tax class and country/tax system |
| `tax_components` | Optional breakdown such as CGST, SGST, IGST, state tax, city tax |

Minimum behavior:

- Tax classes can be active or inactive.
- Tax classes have `sort_order` so admin lists and tax dropdowns can show slabs in a merchant-friendly order.
- Tax rates support effective date ranges.
- A tax class can have one active rate for a merchant country/system at a given date.
- Component rates must sum to the total rate when components are used.
- Schema must not assume India-only taxes.

## Step 2: India Seed Data

Status: Implemented

Seed default India GST slab tax classes. A product/category/default tax selection points to the slab tax class, not to one generic GST class.

Hierarchy:

```text
Product
    |
Tax Class (GST 5%)
    |
Effective Tax Rate
    |
Components
    |- CGST
    `- SGST
```

Seeded tax classes:

| Sort | Code | Tax class | Active rate |
|---:|---|---|---:|
| 10 | GST_0 | GST 0% | 0% |
| 20 | GST_025 | GST 0.25% | 0.25% |
| 30 | GST_15 | GST 1.5% | 1.5% |
| 40 | GST_3 | GST 3% | 3% |
| 50 | GST_5 | GST 5% | 5% |
| 60 | GST_18 | GST 18% | 18% |
| 70 | GST_28 | GST 28% | 28% |
| 80 | GST_40 | GST 40% | 40% |

Each GST slab tax class has exactly one active seed tax rate. Keep `tax_rates` because future rate changes are represented as new effective-dated rate rows under the same tax class.

Example future history:

| Tax class | Tax rate | Effective from | Effective to |
|---|---:|---|---|
| GST 5% | 5% | 2017-07-01 | 2027-03-31 |
| GST 5% | 6% | 2027-04-01 | null |

Seed component structure:

| Tax class | CGST | SGST |
|---|---:|---:|
| GST 0% | 0% | 0% |
| GST 0.25% | 0.125% | 0.125% |
| GST 1.5% | 0.75% | 0.75% |
| GST 3% | 1.5% | 1.5% |
| GST 5% | 2.5% | 2.5% |
| GST 18% | 9% | 9% |
| GST 28% | 14% | 14% |
| GST 40% | 20% | 20% |

Required fields:

- Country: India
- Tax system: GST
- Effective start date
- Optional effective end date
- Active status
- Sort order

Important rules:

- These rows are default data only. The application must not hardcode GST rates or GST-specific table names into business logic.
- Do not model India GST as one generic `GST` tax class with many active rates. Product-level selection needs the slab identity, for example `GST_5`.
- Product/category/merchant tax dropdowns should display slab tax classes such as `GST 5%`, not one generic `Goods and Services Tax` option.

## Step 3: Merchant Tax Settings

Status: Implemented

Merchant-level tax settings are stored separately from products and categories.

Settings support:

- Country
- Tax enabled: yes/no
- Tax system
- Price mode: inclusive/exclusive
- Optional merchant default tax class

Fields:

| Field | Meaning |
|---|---|
| `tax_country_id` or `tax_country_code` | Merchant tax country |
| `tax_enabled` | Whether tax applies for this merchant |
| `tax_system` | GST, VAT, sales_tax, none, or future configured values |
| `tax_price_mode` | `inclusive` or `exclusive` |
| `default_tax_class_id` | Fallback tax class when category/product has none |

UI behavior:

- When tax is disabled, hide tax fields from product forms and other areas where they no longer apply.
- When tax is enabled, show tax status and price mode clearly in merchant settings.
- Validate that selected tax classes match the merchant country/tax system.
- Default tax class dropdowns display slab labels with rate/component context, for example `GST 5% (5.0000% (CGST 2.5000% + SGST 2.5000%))`.
- Default tax class options follow `tax_classes.sort_order`.

Out of scope for this step:

- POS tax calculation
- Order tax snapshots
- Resolver behavior

## Step 4: Category Default Tax

Status: Implemented

Nullable default tax class support exists on `product_categories`.

Field:

- `default_tax_class_id nullable foreign key`

Behavior:

- Admin users configure sensible category defaults.
- Parent categories remain grouping-only.
- Selectable leaf categories can receive a default tax class.
- Category default tax should be nullable so merchants/categories can fall back to merchant defaults or no tax.
- Category default tax labels include the slab rate and component summary where available.
- Category tax class options follow `tax_classes.sort_order`.

Validation:

- Tax class must be active when selected.
- Soft-deleted tax classes are rejected when assigning or changing category defaults.
- Parent/grouping categories cannot store a default tax class.
- Existing inactive or soft-deleted assignments are preserved on unrelated category edits so old records can still be inspected safely.
- Referenced tax classes cannot be force deleted.

## Step 5: Product Tax Override UX

Status: Implemented

Product-level tax preference fields exist on `products`:

- `tax_mode enum('inherit', 'override', 'exempt') default 'inherit'`
- `tax_class_id nullable foreign key`

Why both fields:

| tax_mode | tax_class_id | Meaning |
|---|---:|---|
| inherit | null | Use default tax determined from category or merchant settings |
| override | selected slab tax class | Always use selected tax class, for example GST 5% |
| exempt | null | Never charge tax |

Product form behavior:

```text
Tax Configuration

( ) Use Default Tax
( ) Tax Exempt
( ) Override Tax Class

Tax Class
[ GST 5% ]
```

UX requirements:

- Tax selection is hidden when merchant tax is disabled.
- Use Default Tax hides the tax class dropdown.
- Override Tax Class shows the tax class dropdown and requires an active, non-deleted tax class.
- Tax Exempt hides the tax class dropdown.
- Inherit and exempt forcibly store `tax_class_id = null`.
- The product form shows current category and a default-tax hint without performing final tax resolution.
- Until Step 6, default/inherited effective tax is shown as a checkout-resolution preview instead of pretending the final resolver already exists.
- Product list labels use merchant-facing text: `Default`, `Tax Exempt`, or the selected slab name such as `GST 18%`.
- Override dropdown options display slab tax classes with their active rate/component summary.
- Override dropdown options follow `tax_classes.sort_order`.
- This step stores preference only. It does not calculate tax or resolve merchant/category fallback.

Save behavior:

- Missing tax fields preserve the existing product configuration. This supports hidden tax UI and older submit flows.
- Quick Create always creates products with `tax_mode = inherit` and `tax_class_id = null`.
- Product duplication preserves the original product's tax configuration.

Validation:

- `tax_mode = override` requires `tax_class_id`.
- Override tax class must be active.
- Override tax class must not be soft deleted.
- `tax_mode = inherit` forces `tax_class_id = null`.
- `tax_mode = exempt` forces `tax_class_id = null`.

Implemented tests:

- Product saved with inherit.
- Product saved with override.
- Product saved with exempt.
- Override requires tax class.
- Inactive tax class is rejected.
- Deleted tax class is rejected.
- Inherit clears `tax_class_id`.
- Exempt clears `tax_class_id`.
- Product edit preserves existing configuration.
- Product tax configuration is hidden when merchant tax is disabled.
- Product list shows the tax column.
- Product tax dropdown displays seeded GST slab classes in sort order.

Future rule:

- Keep `tax_mode` small. Customer-specific exemptions, tax holidays, location rules, and other conditional behavior should be handled by the Step 6 resolver instead of adding product-level modes.

## Step 6: Tax And Pricing Services

Status: Implemented

Centralized internal services:

- `TaxResolver`
- `MerchantTaxContext`
- `EffectiveTaxRateResolver`
- `TaxCalculator`
- `PricingEngine`

Responsibilities:

| Service | Responsibility |
|---|---|
| `TaxResolver` | Build merchant tax context and resolve merchant tax status, product override/exemption, category default, merchant default, and no-tax fallback |
| `MerchantTaxContext` | Carry merchant tax settings such as enabled flag, default tax class, price mode, and business country through one pricing operation |
| `EffectiveTaxRateResolver` | Select exactly one active effective-dated `tax_rates` row for the resolved tax class |
| `TaxCalculator` | Calculate inclusive/exclusive line tax, taxable amount, component amounts, and line total |
| `PricingEngine` | Compose resolver, effective rate resolver, and calculator into one product-line pricing result |

Required behavior:

- Resolve tax as of a specific date/time.
- Support inclusive and exclusive prices.
- Return consistent rounded totals.
- Return component-level tax amounts when components exist.
- Return a no-tax result when merchant tax is disabled.
- Return a no-tax result when product `tax_mode = exempt`.
- Return a no-tax result when no product/category/merchant tax class exists.
- Avoid tax calculation logic in controllers, Blade files, or JavaScript-only business logic.
- Financial calculations use integer-scaled decimal arithmetic in the tax service layer, not authoritative PHP floating-point arithmetic.
- Step 6 DTOs carry immutable scalar values and component DTOs, not Eloquent models.
- `PricingResult` is an internal service result. Step 7 should introduce a dedicated order snapshot/export DTO instead of reusing nested internal objects as the storage/API contract.

Resolution order:

1. Merchant tax settings missing or `tax_enabled = false` -> no tax, source `tax_disabled`.
2. Product `tax_mode = exempt` -> no tax, source `product_exempt`.
3. Product `tax_mode = override` -> product tax class, source `product_override`.
4. Product `tax_mode = inherit` with category default -> category tax class, source `category_default`.
5. Product `tax_mode = inherit` with merchant default -> merchant tax class, source `merchant_default`.
6. Nothing found -> no tax, source `no_tax_class`.

Invalid configuration policy:

- Invalid stored tax classes throw domain exceptions instead of silently becoming no-tax.
- Deleted tax classes are rejected.
- Inactive tax classes are rejected.
- Tax classes from another merchant business country are rejected.
- Missing active effective tax rates throw `TaxRateNotFoundException`.
- Multiple active applicable tax rates for the same date throw `OverlappingTaxRatesException`.
- Tax-rate components must sum exactly to the total rate at runtime, otherwise `TaxComponentMismatchException` is thrown.

Effective rate selection:

- Compare `effective_from` and `effective_to` using the calendar date from `effective_at`.
- `effective_from <= effective_at date`.
- `effective_to is null` or `effective_to >= effective_at date`.
- Inactive and soft-deleted rates are ignored.
- If zero rates are configured, their components must still total `0.0000`.

Calculation formulas:

```text
exclusive:
line_subtotal = unit_price * quantity
discount_amount = min(discount_amount, line_subtotal)
taxable_amount = max(line_subtotal - discount_amount, 0)
tax_amount = taxable_amount * total_rate / 100
line_total = taxable_amount + tax_amount

inclusive:
line_subtotal = unit_price * quantity
discounted_total = max(line_subtotal - discount_amount, 0)
taxable_amount = discounted_total / (1 + total_rate / 100)
tax_amount = discounted_total - taxable_amount
line_total = discounted_total

no tax:
line_subtotal = unit_price * quantity
discount_amount = min(discount_amount, line_subtotal)
taxable_amount = max(line_subtotal - discount_amount, 0)
tax_amount = 0
line_total = taxable_amount
```

Rounding policy:

- Money values are returned at 2 decimal places.
- Tax rates are retained at database precision, currently 4 decimal places.
- Intermediate service calculations use scaled integers.
- Component amounts are rounded to 2 decimals.
- Any component rounding remainder is assigned to the last component in stable component order.
- Component amounts must sum exactly to `tax_amount`.

Input validation:

- Negative unit prices are rejected.
- Quantity must be greater than zero.
- Negative discounts are rejected.
- Discounts greater than line subtotal are capped to the subtotal, preserving the existing zero-taxable-amount behavior.

Implemented result shape:

```text
tax_enabled
resolution_source
tax_class_id
tax_class_code
tax_class_name
tax_rate_id
tax_rate_name
total_rate
price_mode
unit_price
quantity
line_subtotal
discount_amount
taxable_amount
tax_amount
component_amounts
line_total
calculated_at
```

Step 6 boundaries:

- No POS integration yet.
- No order creation changes yet.
- No receipt changes yet.
- No refund/exchange/report changes yet.
- No frontend JavaScript integration yet.

## Step 7: POS And Order Integration

Status: Planned

Update POS calculation and order creation to use the centralized tax/pricing services.

Store historical tax snapshots in:

- `order_items`
- `order_totals`

Snapshots should include:

- Tax class name, for example GST 5%
- Tax class ID where useful for traceability
- Tax rate ID where useful for traceability
- Total rate
- Component rates
- Taxable amount
- Tax amount
- Line subtotal
- Line total
- Price mode

Important rule:

- Order financial records must remain correct even if tax master data changes later.

POS behavior:

- Tax fields are hidden when merchant tax is disabled.
- Inclusive price mode should show customer-facing price without adding tax again.
- Exclusive price mode should add tax to the visible subtotal.
- Totals shown in POS must match the values saved to order tables.

## Step 8: Receipts, Refunds, Exchanges, Reports, And Tests

Status: Planned

Update affected surfaces:

- POS receipt
- Sales history
- Refund calculation
- Exchange calculation
- Reports
- Automated tests

Refund and exchange rule:

- Refunds and exchanges must use the original order-item tax snapshot.
- They must not recalculate tax using current tax master data.

Receipt/reporting requirements:

- Show tax total when tax is enabled.
- Show component breakdown where required by the tax system.
- Avoid showing tax sections for tax-disabled merchants unless a historical order contains tax.
- Reports should use saved order snapshots for historical accuracy.

Test coverage:

- Tax disabled merchant
- India GST seed data exists
- Category default tax resolution
- Product tax override resolution
- Inclusive price calculation
- Exclusive price calculation
- Component split calculation
- POS totals equal saved order totals
- Refund uses original tax snapshot
- Exchange uses original tax snapshot
- Tax master changes do not mutate historical orders

## Acceptance Checklist

- Generic tax schema exists and avoids GST-specific table names.
- India GST defaults are seeded as slab tax classes with CGST, SGST, and effective dates.
- Merchant settings support country, enabled flag, tax system, price mode, and default tax class.
- Product categories support nullable default tax class.
- Products support `tax_mode` with inherit, override, and exempt behavior.
- Product form displays inherited tax and optional override workflow.
- Tax/Pricing services centralize all calculations.
- POS and order creation use the centralized services.
- Order items and totals store immutable tax snapshots.
- Receipts, refunds, exchanges, and reports read historical tax snapshots.
- Automated tests cover disabled tax, inherited tax, overrides, inclusive/exclusive pricing, snapshots, refunds, and exchanges.
