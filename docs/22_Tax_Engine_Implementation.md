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

Status: Planned

Create generic tax master tables:

- `tax_classes`
- `tax_rates`
- `tax_components` if component-level tax splitting is needed as normalized data

Suggested responsibilities:

| Table | Purpose |
|---|---|
| `tax_classes` | User-facing tax class such as Exempt, GST 5%, VAT Standard, or Sales Tax |
| `tax_rates` | Effective-dated rate records for each tax class and country/tax system |
| `tax_components` | Optional breakdown such as CGST, SGST, IGST, state tax, city tax |

Minimum behavior:

- Tax classes can be active or inactive.
- Tax rates support effective date ranges.
- A tax class can have one active rate for a merchant country/system at a given date.
- Component rates must sum to the total rate when components are used.
- Schema must not assume India-only taxes.

## Step 2: India Seed Data

Status: Planned

Seed default India GST tax classes:

| Tax class | Total rate |
|---|---:|
| Exempt | 0% |
| GST 5% | 5% |
| GST 12% | 12% |
| GST 18% | 18% |
| GST 28% | 28% |

Seed component structure:

| Tax class | CGST | SGST | IGST |
|---|---:|---:|---:|
| Exempt | 0% | 0% | 0% |
| GST 5% | 2.5% | 2.5% | 5% |
| GST 12% | 6% | 6% | 12% |
| GST 18% | 9% | 9% | 18% |
| GST 28% | 14% | 14% | 28% |

Required fields:

- Country: India
- Tax system: GST
- Effective start date
- Optional effective end date
- Active status

Important rule:

- These rows are default data only. The application must not hardcode GST rates or GST-specific table names into business logic.

## Step 3: Merchant Tax Settings

Status: Planned

Add merchant-level tax settings.

Settings should support:

- Country
- Tax enabled: yes/no
- Tax system
- Price mode: inclusive/exclusive
- Optional merchant default tax class

Suggested fields:

| Field | Meaning |
|---|---|
| `tax_country_id` or `tax_country_code` | Merchant tax country |
| `tax_enabled` | Whether tax applies for this merchant |
| `tax_system` | GST, VAT, sales_tax, none, or future configured values |
| `tax_price_mode` | `inclusive` or `exclusive` |
| `default_tax_class_id` | Fallback tax class when category/product has none |

UI behavior:

- When tax is disabled, hide tax fields from product forms, POS, and merchant tax configuration areas where they no longer apply.
- When tax is enabled, show tax status and price mode clearly in merchant settings.
- Validate that selected tax classes match the merchant country/tax system.

## Step 4: Category Default Tax

Status: Planned

Add nullable default tax class support to `product_categories`.

Suggested field:

- `default_tax_class_id nullable foreign key`

Behavior:

- Admin users configure sensible category defaults.
- Merchant category selection automatically loads and displays the category tax.
- Parent categories remain grouping-only.
- Selectable leaf categories receive the primary default tax class.
- Category default tax should be nullable so merchants/categories can fall back to merchant defaults or no tax.

Validation:

- Tax class must be active when selected.
- Tax class must be compatible with the relevant merchant country/tax system when used in merchant workflows.

## Step 5: Product Tax Override UX

Status: Planned

Add nullable `tax_class_id` to `products`.

Resolution rule:

```text
products.tax_class_id = null
=> use category default tax class

products.tax_class_id has value
=> use product override
```

Merchant product form behavior:

```text
Tax: GST 5%
Automatically selected from category

[ ] Use a different tax for this product
```

UX requirements:

- Tax selection is hidden when merchant tax is disabled.
- Category-derived tax is shown as read-only until override is enabled.
- Product override must be nullable so removing the override returns the product to category default behavior.
- Product list/detail screens should indicate whether tax is inherited or overridden.

## Step 6: Tax And Pricing Services

Status: Planned

Create centralized services such as:

- `TaxResolver`
- `TaxCalculator`
- `PricingEngine`

Responsibilities:

| Service | Responsibility |
|---|---|
| `TaxResolver` | Resolve merchant tax status, merchant tax system, product override, category default, and fallback tax class |
| `TaxCalculator` | Calculate inclusive/exclusive tax amounts, component amounts, taxable amount, and totals |
| `PricingEngine` | Produce consistent line totals and order totals for POS/order flows |

Required behavior:

- Resolve tax as of a specific date/time.
- Support inclusive and exclusive prices.
- Return consistent rounded totals.
- Return component-level tax amounts when components exist.
- Return a no-tax result when merchant tax is disabled.
- Avoid tax calculation logic in controllers, Blade files, or JavaScript-only business logic.

Suggested result shape:

```text
tax_enabled
tax_class_id
tax_class_name
tax_rate_id
total_rate
price_mode
unit_price
quantity
taxable_amount
tax_amount
component_amounts
line_subtotal
line_total
```

## Step 7: POS And Order Integration

Status: Planned

Update POS calculation and order creation to use the centralized tax/pricing services.

Store historical tax snapshots in:

- `order_items`
- `order_totals`

Snapshots should include:

- Tax class name
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
- India GST defaults are seeded with CGST, SGST, IGST, and effective dates.
- Merchant settings support country, enabled flag, tax system, price mode, and default tax class.
- Product categories support nullable default tax class.
- Products support nullable tax override.
- Product form displays inherited tax and optional override workflow.
- Tax/Pricing services centralize all calculations.
- POS and order creation use the centralized services.
- Order items and totals store immutable tax snapshots.
- Receipts, refunds, exchanges, and reports read historical tax snapshots.
- Automated tests cover disabled tax, inherited tax, overrides, inclusive/exclusive pricing, snapshots, refunds, and exchanges.
