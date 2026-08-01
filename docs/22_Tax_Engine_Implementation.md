# Tax Engine Implementation

Production status: Ready and frozen through Step 8D.

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

## Step 7: Order Tax Snapshot And Integration

Status: Partially implemented

Step 7 is intentionally split so schema, snapshot mapping, order creation, POS calculation, and display changes can be reviewed separately.

### Step 7A: Order Tax Snapshot Schema

Status: Implemented

Historical tax snapshot columns now exist at order-item level. This is the primary snapshot location because different items in one order may resolve to different tax classes, rates, or exemption/default sources.

Existing order-item financial mappings:

| Step 6 pricing result | Existing `order_items` column |
|---|---|
| `line_subtotal` | `line_subtotal` |
| `discount_amount` | `line_discount` |
| `tax_amount` | `line_tax` |
| `line_total` | `line_total` |

Do not add duplicate financial columns for those values. Existing `order_items.line_subtotal`, `line_discount`, `line_tax`, and `line_total` remain the authoritative saved line totals.

Added `order_items` snapshot columns:

| Column | Meaning |
|---|---|
| `tax_enabled` | Whether tax was enabled for this line when the order was created |
| `tax_resolution_source` | Step 6 source such as `product_override`, `category_default`, or `tax_disabled` |
| `tax_class_id` | Historical traceability ID only |
| `tax_class_code` | Tax class code snapshot, for example `GST_5` |
| `tax_class_name` | Tax class display name snapshot, for example `GST 5%` |
| `tax_rate_id` | Historical traceability ID only |
| `tax_rate_name` | Resolved tax rate name snapshot |
| `tax_rate` | Resolved total rate from `EffectiveTaxRateResult.totalRate` |
| `price_mode` | `inclusive` or `exclusive` at calculation time |
| `taxable_amount` | Taxable amount used to calculate the line tax |

Master tax IDs are deliberately not foreign constrained:

- Historical orders must remain valid if tax classes, tax rates, or tax components are renamed, deleted, purged, or replaced.
- Snapshot IDs are traceability values, not live dependencies.
- Historical reads must use saved snapshot fields, not current tax master data.

Added `order_item_tax_components`:

| Column | Meaning |
|---|---|
| `order_item_id` | Required FK to `order_items`, cascades when an order item is deleted |
| `tax_component_id` | Historical traceability ID only; no FK to tax master |
| `component_code` | Component code snapshot, for example `CGST` |
| `component_name` | Component name snapshot |
| `jurisdiction_type` | Optional jurisdiction snapshot |
| `rate` | Component rate snapshot |
| `amount` | Component tax amount snapshot |
| `sort_order` | Stable display/report ordering |

Historical immutability rule:

- Pricing/tax results are copied into order snapshot columns at order creation.
- Existing orders must not be recalculated from current tax masters.
- Changing merchant defaults, category defaults, product tax mode, tax classes, rates, or components must not alter historical orders.

Step 7A boundaries:

- No POS calculation changes.
- No `OrderCreationService` changes.
- No receipt changes.
- No refund, exchange, or report changes.
- No frontend changes.

### Step 7B: Order Tax Snapshot DTO/Mapper

Status: Implemented

Dedicated immutable snapshot DTOs and a factory now map a completed Step 6 `PricingResult` into persistence-ready order tax snapshot values. This boundary keeps Step 6 internal pricing DTOs separate from the future order persistence contract.

Created snapshot objects:

- `OrderItemTaxSnapshot`
- `OrderItemTaxComponentSnapshot`
- `OrderTaxSnapshotFactory`

`OrderItemTaxSnapshot` fields:

- `taxEnabled`
- `resolutionSource`
- `taxClassId`
- `taxClassCode`
- `taxClassName`
- `taxRateId`
- `taxRateName`
- `totalRate`
- `priceMode`
- `lineSubtotal`
- `discountAmount`
- `taxableAmount`
- `taxAmount`
- `lineTotal`
- `components`

`OrderItemTaxComponentSnapshot` fields:

- `taxComponentId`
- `componentCode`
- `componentName`
- `jurisdictionType`
- `rate`
- `amount`
- `sortOrder`

Step 6 result to snapshot mappings:

| Step 6 value | Snapshot field |
|---|---|
| `PricingResult.resolution.taxEnabled` / calculation tax flag | `taxEnabled` |
| `PricingResult.resolution.resolutionSource` | `resolutionSource` |
| `PricingResult.resolution.taxClassId` | `taxClassId` |
| `PricingResult.resolution.taxClassCode` | `taxClassCode` |
| `PricingResult.resolution.taxClassName` | `taxClassName` |
| `PricingResult.effectiveRate.taxRateId` | `taxRateId` |
| `PricingResult.effectiveRate.taxRateName` | `taxRateName` |
| `PricingResult.effectiveRate.totalRate` | `totalRate` |
| `PricingResult.calculation.priceMode` | `priceMode` |
| `PricingResult.calculation.lineSubtotal` | `lineSubtotal` |
| `PricingResult.calculation.discountAmount` | `discountAmount` |
| `PricingResult.calculation.taxableAmount` | `taxableAmount` |
| `PricingResult.calculation.taxAmount` | `taxAmount` |
| `PricingResult.calculation.lineTotal` | `lineTotal` |
| `PricingResult.calculation.componentAmounts` | `components` |

Snapshot to `order_items` attribute mappings:

| Snapshot field | Database column |
|---|---|
| `taxEnabled` | `tax_enabled` |
| `resolutionSource` | `tax_resolution_source` |
| `taxClassId` | `tax_class_id` |
| `taxClassCode` | `tax_class_code` |
| `taxClassName` | `tax_class_name` |
| `taxRateId` | `tax_rate_id` |
| `taxRateName` | `tax_rate_name` |
| `totalRate` | `tax_rate` |
| `priceMode` | `price_mode` |
| `taxableAmount` | `taxable_amount` |
| `lineSubtotal` | `line_subtotal` |
| `discountAmount` | `line_discount` |
| `taxAmount` | `line_tax` |
| `lineTotal` | `line_total` |

Snapshot to `order_item_tax_components` attribute mappings:

| Snapshot field | Database column |
|---|---|
| `taxComponentId` | `tax_component_id` |
| `componentCode` | `component_code` |
| `componentName` | `component_name` |
| `jurisdictionType` | `jurisdiction_type` |
| `rate` | `rate` |
| `amount` | `amount` |
| `sortOrder` | `sort_order` |

Historical immutability rule:

- The factory preserves exact Step 6 string values.
- It does not query the database, resolve tax, recalculate tax, round values, or mutate the original `PricingResult`.
- DTOs contain scalar values and nested snapshot DTOs only; they do not expose Eloquent models.

Step 7B boundaries:

- No order item persistence yet.
- No order component persistence yet.
- No `OrderCreationService` changes.
- No POS, receipt, refund, exchange, report, frontend, or migration changes.

### Step 7C: OrderCreationService Integration

Status: Implemented

`OrderCreationService` now prices every newly created order line through the centralized Step 6 `PricingEngine`, converts the result through the Step 7B `OrderTaxSnapshotFactory`, and persists both item-level snapshot columns and `order_item_tax_components` rows.

Authoritative server calculation flow:

1. Accept only supported order item inputs from callers: `product_variant_id`, `quantity`, and existing item discount fields.
2. Load and lock the authoritative `ProductVariant` and related `Product` from the database.
3. Confirm the variant belongs to the requested shop and the product belongs to the same shop and merchant.
4. Use the database `selling_price` and `mrp`; submitted price, tax, subtotal, and total values are ignored.
5. Calculate the existing server-approved item discount with `DiscountService`.
6. Pass product, merchant, unit price, quantity, one order-level effective timestamp, and approved line discount to `PricingEngine`.
7. Convert the `PricingResult` with `OrderTaxSnapshotFactory`.
8. Create the `order_items` row from the existing product/variant/customer snapshot fields plus snapshot financial/tax attributes.
9. Create one related `order_item_tax_components` row for every component snapshot. Tax-disabled, exempt, and no-tax-class lines create no component rows.

Order item financial meanings after Step 7C:

| Column | Meaning |
|---|---|
| `line_subtotal` | Authoritative unit price multiplied by quantity before discount |
| `line_discount` | Existing approved item discount amount |
| `taxable_amount` | Tax base from Step 6 calculation |
| `line_tax` | Included or added line tax amount |
| `line_total` | Final customer-payable line amount after item discount and tax handling |

Order-total aggregation rules:

- `subtotal` is still the sum of saved `order_items.line_subtotal`.
- `discount_total` is still saved line discounts plus existing approved order-level discount/coupon rows.
- `tax_total` is still saved line tax plus any existing explicit tax total rows.
- `grand_total` is derived from saved `order_items.line_total`, then existing order-level discount, shipping, explicit tax rows, and rounding rows are applied.
- This preserves shipping rows, signed discount rows, cash rounding, `amount_paid`, `change_amount`, payment-status resolution, `order_totals` rows, and existing status history behavior.
- Inclusive tax is not added a second time to `grand_total`.
- Exclusive tax is included in saved `line_total` and therefore in `grand_total`.

Effective timestamp policy:

- One timestamp is captured at the start of order creation and passed to every line calculation.
- The same timestamp is used for completion/cancellation timestamps when those statuses are set during creation.

Transaction and rollback behavior:

- Order creation, item creation, component snapshot creation, order-total creation, status history creation, and stock deduction remain inside one database transaction.
- Tax configuration/rate/component failures stop order creation with a validation error.
- A failure before commit rolls back orders, items, component rows, totals, status history, and stock changes.

Historical immutability rule:

- Newly created orders read tax values from saved order-item and component snapshots.
- Later changes to tax classes, rates, components, category defaults, merchant defaults, or product tax modes must not mutate saved order tax snapshots.

Existing caller compatibility:

- POS checkout still calls `OrderCreationService`; POS UI calculation/display integration remains planned for Step 7D.
- Exchange replacement orders still call `OrderCreationService` and remain compatible.
- Exchange-return settlement and refund logic are not changed in Step 7C. They must be reviewed later because saved `line_total` now represents the final customer-payable line amount.

POS V1 aggregation behavior:

- `OrderCreationService` currently merges duplicate submitted variants into one order line before pricing.
- The merged line keeps the first submitted item discount inputs and sums the quantity.
- This is intentional for POS V1 because the POS cart consolidates identical items.
- If future workflows require independent lines, such as manual line notes, different discounts per duplicate variant, serial numbers, lot numbers, or warranty details, aggregation should become caller-configurable.

Error response note:

- Step 7C converts tax pricing/configuration failures into `ValidationException` responses so order creation stops clearly and rolls back.
- A later POS/API error-handling pass may introduce dedicated domain exceptions if callers need to distinguish tax configuration failures from user input validation.

Step 7C boundaries:

- No POS UI or frontend JavaScript changes.
- No receipt or order-detail display changes.
- No refund, exchange-return, or report calculation changes.
- No tax master, resolver, rate resolver, calculator, schema, DTO, or factory changes.

### Step 7D: POS Calculation Integration

Status: Implemented

POS live cart pricing now uses the same backend pricing stack as saved orders.

Implemented flow:

1. POS cart changes are sent to `POST /merchant/pos/pricing`.
2. The browser sends only supported inputs: variant ID, quantity, item discount inputs, order discount inputs, payment method, and amount paid for display.
3. The server loads authoritative product, variant, merchant tax settings, category defaults, and product tax overrides.
4. The server runs `PricingEngine`, maps the result through `OrderTaxSnapshotFactory`, and totals the cart through the same order-total rules used by Step 7C.
5. The POS UI consumes the returned line and summary values for display.
6. Checkout still uses `OrderCreationService`; the POS pricing endpoint does not create orders, deduct stock, write totals, or save snapshots.

POS display rules after Step 7D:

- Tax-disabled merchants receive `tax_display_enabled = false`; the POS hides line tax and tax summary rows.
- Inclusive-tax merchants display customer-facing line totals without adding tax again.
- Exclusive-tax merchants display separate tax amounts and line totals including added tax.
- Quantity, item discount, order discount, payment method, and held-cart restore changes trigger full-cart repricing.
- POS pricing requests are debounced, and newer pricing requests abort older in-flight requests.
- Checkout is disabled while pricing is pending or failed, including keyboard-triggered checkout attempts.
- Pricing failures keep the cart intact, show an error/retry state, and do not silently fall back to stale tax totals.
- The browser performs presentation and cart interaction only; it must not be treated as the pricing authority.
- Browser-submitted fake subtotal, tax total, grand total, taxable amount, or line tax values are ignored by the pricing endpoint and checkout.
- Browser cart storage keeps only variant IDs, quantities, and discount inputs; tax rates, classes, price mode, and calculated totals are always reloaded from the server.

Step 7D boundaries:

- No tax calculation service changes.
- No `OrderCreationService` changes.
- No snapshot schema, DTO, or factory changes.
- No receipt, refund, exchange-return, or report changes.

### Step 7E: Receipt And Order Display

Status: Implemented

Receipt and order-detail display now reads saved tax snapshots from orders, order items, and order-item tax components. These screens treat receipts and order details as historical documents.

Historical display rule:

- Never display current tax class names, current tax rates, current component rates, category defaults, merchant defaults, or product tax modes from master tables.
- Always display the saved `order_items.tax_class_name`, `order_items.tax_rate`, `order_items.price_mode`, `order_items.taxable_amount`, `order_items.line_tax`, `order_items.line_total`, and saved `order_item_tax_components` values.
- If an order was created with GST 18% and the tax master later changes to GST 20%, receipt reprints and order details must still show the saved GST 18% snapshot.
- If tax class or tax rate records are later renamed, disabled, or soft-deleted, receipt reprints and order details must still render from the saved order snapshot.

Implemented surfaces:

- POS printed receipt.
- Printable receipt page.
- Sales history order detail page.

Display behavior:

- Tax fields are hidden for tax-disabled orders with no saved historical tax.
- Historical taxed orders still show saved tax snapshot values even if merchant tax is disabled later.
- Inclusive price mode shows the customer-facing saved line total and labels saved tax as included. It does not add tax again in the receipt or detail display.
- Exclusive price mode shows saved taxable amount, saved tax amount, saved component rows when present, and saved line total.
- Component rows are displayed only when saved `order_item_tax_components` rows exist.
- If component rows are missing, the display falls back to the saved line tax amount and does not fabricate CGST, SGST, IGST, or other components.
- Totals shown in receipt and order detail use saved order totals, including shipping and cash rounding behavior already persisted on the order.

Step 7E boundaries:

- No tax calculation service changes.
- No `OrderCreationService` changes.
- No POS live-pricing endpoint or JavaScript changes.
- No refund, exchange-return, report calculation, or schema changes.

## Step 8: Refunds, Exchanges, Reports, And Tests

Status: Split into smaller planned phases

Step 8 is deliberately split so refund, exchange, reporting, and regression coverage can be reviewed independently. These workflows touch financial records and should not be bundled into one large change.

### Step 8A: Refund Snapshot Integration

Status: Planned

Update refund calculation to consume saved order-item tax snapshots.

Refund rules:

- Refunds must use the original saved `order_items.line_tax`, `order_items.line_total`, and component snapshots.
- Refunds must not recalculate tax from current tax classes, rates, merchant defaults, category defaults, or product tax modes.
- Partial refunds must prorate from the saved historical order line values.
- Tax component refund display or storage should preserve original component codes, rates, and names where needed.

Step 8A boundaries:

- No exchange logic changes.
- No report changes.
- No tax master or pricing-engine changes.

### Step 8B: Exchange Snapshot Integration

Status: Implemented

Exchange return calculation now consumes saved order-item tax snapshots and saved customer-payable line totals.

Implemented behavior:

- Exchange returned settlement now treats saved `order_items.line_total` as the authoritative customer-paid amount.
- Returned `settlement_line_total` is the prorated saved `line_total` only.
- Returned `line_tax` is still stored on exchange return items as the historical tax split, but it is not added again to returned settlement value.
- Partial exchange returns prorate directly from the saved original line amount before rounding.
- The last remaining exchange for an order line uses the remaining saved line value, so multiple partial exchanges cannot exceed or lose cents against the original saved line total.
- Returned tax snapshot values use the same proportional/remaining-value approach.
- Exchange replacement orders are marked operationally paid using the saved replacement `grand_total` after `OrderCreationService` prices them, so exclusive-tax replacement orders are not accidentally left partially paid by a selling-price-only estimate.
- Exclusive tax, inclusive tax, tax-disabled, partial quantity, full quantity, settlement amount, replacement-order paid status, and later tax-master-change regression tests cover this behavior.

Exchange rules:

- Exchange returns must use original saved order-item tax values.
- Exchange returns must not recalculate returned tax using current tax master data.
- Replacement orders continue to use current `OrderCreationService` pricing, because they are new orders created at exchange time.
- Replacement orders are operational records. Actual exchange collection, refund, or credit adjustment remains stored on `order_exchanges.amount_collected`, `order_exchanges.amount_refunded`, and `order_exchanges.credit_adjustment_amount`.

Step 8B boundaries:

- No refund logic changes.
- No report changes.
- No tax master or pricing-engine changes.

### Step 8C: Reports And Sales History

Status: Implemented

Historical display and reporting surfaces now read saved tax snapshots.

Reporting/display requirements:

- Every report must read from the same source as the financial ledger: `orders`, `order_items`, and `order_item_tax_components`.
- Sales history and order detail views should display saved order and order-item tax values.
- Reports should use saved `order_items.line_tax`, saved order totals, and component snapshot rows for historical accuracy.
- Reports must never derive historical financial totals by joining back to `products`, `product_variants`, tax classes, tax rates, merchant tax settings, category defaults, product tax modes, or any other current master-data tables.
- Tax master changes after order creation must not change historical report output.
- Avoid showing tax sections for tax-disabled merchants unless the historical order contains saved tax values.

Implemented reporting surfaces:

- Sales history list summary reads saved `orders.subtotal`, `orders.discount_total`, `orders.tax_total`, and `orders.grand_total`.
- Sales history tax summary groups saved `order_items.tax_class_name`, `order_items.tax_rate`, and `order_items.price_mode`, and sums saved `order_items.taxable_amount`, `order_items.line_tax`, and `order_items.line_total`.
- Sales history tax summary presents separate taxable sales and tax collected values, with saved inclusive/exclusive `price_mode` shown as a reporting badge.
- Sales history component summary groups saved `order_item_tax_components` rows by saved component identity and sums saved component `amount`.
- Sales history hides empty tax/component report sections when the filtered order set has no historical tax snapshots.
- Merchant dashboard revenue, today's tax, today's discount, and latest-order totals read saved `orders` values only.
- Exchange replacement orders remain excluded from sales and collection widgets because they are operational records; exchange settlement reporting continues to live on `order_exchanges`.
- Existing sales history filters continue to apply to sales, tax, and component summaries.

Future reporting enhancements:

- Sales history/report filters may later add saved tax class, saved price mode, and saved tax-enabled filters.
- Refund reporting should be handled as a separate reporting step with gross sales, refunds, net sales, tax collected, tax refunded, and net tax.
- Future exports must export the same saved snapshot values used by on-screen reports and must not recalculate historical tax.
- Monthly GST returns, HSN/SAC summaries, accountant reports, and CSV/Excel/PDF exports belong in a future reporting module, not the core tax engine.

Step 8C boundaries:

- No refund calculation changes.
- No exchange calculation changes.
- No tax master or pricing-engine changes.

### Step 8D: Tax Regression Tests

Status: Implemented

The final tax-engine audit and freeze pass is complete. The implemented regression suite covers the full tax lifecycle from tax resolution through saved order snapshots, receipts, refunds, exchanges, dashboard widgets, sales history reports, and immutable historical reprints.

Golden regression coverage:

- Tax-disabled merchant.
- India GST seed data exists.
- Category default tax resolution.
- Product tax override resolution.
- Merchant default fallback.
- Product exempt behavior.
- Inclusive price calculation.
- Exclusive price calculation.
- Component split calculation.
- POS displayed totals equal saved order totals.
- Browser-submitted fake totals remain ignored.
- Refund uses original tax snapshot.
- Exchange uses original tax snapshot.
- Reports use saved order snapshots.
- Tax master changes do not mutate historical orders.
- Product price changes do not mutate historical reports.
- Merchant tax disable after sale does not mutate historical receipts, order details, refunds, exchanges, dashboard totals, or reports.
- Exchange replacement orders are excluded from sales and collection reporting.

Final audit findings:

- `order_items.line_subtotal` remains the pre-discount line amount.
- `order_items.line_discount` remains the line-level discount.
- `order_items.taxable_amount` remains the saved tax base.
- `order_items.line_tax` remains the saved historical tax amount.
- `order_items.line_total` remains the final customer-payable line amount.
- `orders.subtotal`, `orders.discount_total`, `orders.tax_total`, and `orders.grand_total` are saved ledger values used by receipts, dashboard widgets, and reports.
- No double-tax addition remains in exchange settlement; exchange return settlement uses saved `line_total`, while `line_tax` is retained as historical tax split information.
- Historical surfaces read saved snapshots and do not call `PricingEngine`, `TaxResolver`, `TaxCalculator`, current tax classes, current tax rates, merchant tax settings, category defaults, or product tax modes for historical financial values.

Snapshot philosophy:

- Tax masters, product prices, product tax modes, category defaults, and merchant tax settings are inputs for future orders only.
- Once an order is created, historical screens and reports must read `orders`, `order_items`, and `order_item_tax_components`.
- Snapshot master IDs are retained for traceability, but display and reporting must use saved names, codes, rates, amounts, and component rows.

Performance considerations:

- Sales history totals use SQL aggregation over saved `orders` columns.
- Sales history tax summaries aggregate saved `order_items` rows and component summaries aggregate saved `order_item_tax_components` rows.
- Receipt and order-detail pages eager-load item component snapshots and do not join tax master tables for historical display.
- Dashboard revenue, tax, and discount widgets aggregate saved `orders` values and exclude exchange replacement orders from sales/collection reporting.

Final freeze rule:

- The core tax engine is feature-complete through receipts, refunds, exchanges, dashboard, and sales history reporting.
- Future work must build on the saved snapshot contract instead of redesigning tax calculation, order creation, or historical reporting semantics.

Step 8D boundaries:

- No new business features were introduced.
- No database schema changes were introduced.
- No tax calculation, pricing, order creation, receipt, refund, exchange, category tax, product tax, or merchant tax setting architecture was redesigned.
- Future reporting features must export or summarize the same saved snapshot values.

## Step 9: IGST / Interstate Support

Status: Planned

Add jurisdiction-aware GST selection for interstate transactions.

Planned direction:

- Use merchant origin and customer/delivery destination to decide whether CGST/SGST or IGST applies.
- Preserve current generic tax schema; do not introduce GST-specific table names.
- Keep historical snapshots immutable and jurisdiction-aware.
- Add focused tests for intrastate and interstate orders.

Step 9 boundaries:

- Do not change Step 7 snapshot contracts unless an explicit migration/versioning plan is added.
- Do not recalculate historical orders when jurisdiction rules are introduced.

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
- Receipt and order display read historical tax snapshots.
- Refunds, exchanges, and reports read historical tax snapshots.
- Automated tests cover disabled tax, inherited tax, overrides, inclusive/exclusive pricing, snapshots, refunds, exchanges, and reports.
- Step 8D golden regression and full-suite verification pass; the Tax Engine is frozen for future modules to build on top of the saved snapshot contract.
