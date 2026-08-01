# Tax Engine Status

Version: 1.0

Status: Production Ready

Frozen: Yes

## Scope

The Tax Engine is feature-complete through tax masters, merchant settings, category defaults, product overrides, pricing, POS order creation, immutable order snapshots, receipts, refunds, exchanges, dashboard totals, sales history, and snapshot-based tax reporting.

Historical financial values must continue to come from:

- `orders`
- `order_items`
- `order_item_tax_components`

## Allowed Changes

- Bug fixes.
- Performance improvements.
- Tests.
- Documentation.
- Small compatibility changes that preserve the saved snapshot contract.

## Not Allowed

- New tax rules inside the frozen core engine.
- Tax schema redesign.
- `PricingEngine` redesign.
- Order snapshot redesign.
- Historical order recalculation.
- Reports that derive historical financial values from products, variants, tax masters, merchant settings, category defaults, or product tax modes.

## Future Work

Future tax and reporting work must build on top of the frozen snapshot contract:

- IGST / interstate GST.
- VAT.
- Sales tax.
- CSV/Excel/PDF exports.
- GST return reports.
- HSN/SAC reports.
- Accountant reports.
