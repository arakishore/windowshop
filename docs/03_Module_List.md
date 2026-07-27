# Module List

This document tracks the modules and user-facing features currently implemented in WindowShop V1.

## Admin Area

### Authentication

- Admin login and logout
- Authenticated admin dashboard
- Admin role middleware protection

### Master Data

- Shop Audiences CRUD
- Brands CRUD
- Product Categories CRUD
- Product Category attribute-group mapping
- Product Attribute Groups CRUD
- Product Attribute Values CRUD
- Product Description Templates CRUD
- Product Description Template preview/generation flow

### Merchant Management

- Merchant CRUD
- Merchant address management
- Merchant shop management from admin
- State and city lookup endpoints for merchant addresses

### Product Management

- Admin product quick-create flow
- Product listing and CRUD
- Product duplicate action
- Product archive and restore from archive
- Product restore from trash
- Product force delete
- Bulk product actions
- Product tabbed edit flow:
  - Basic information
  - Attributes
  - Variants
  - Images
  - Pricing
  - Inventory
  - Description
  - SEO
- Category-scoped product attributes
- Variant generation from configured attributes
- Variant bulk update
- Product image upload, ordering, update, bulk delete, and single delete
- Description and SEO generation actions

### Global Admin Settings

- Generic `admin_settings` table with grouped key/value settings
- `AdminSettingsService`
- `AdminSettingsInitializer`
- `AdminSettingsSeeder`
- Regional settings:
  - Time zone
  - Date format
  - Time format
  - Financial year start month
- Currency settings:
  - Base currency
  - Currency symbol
  - Decimal places
  - Thousands separator
  - Decimal separator
  - Symbol position
- Static JSON catalogues:
  - `resources/data/timezones.json`
  - `resources/data/currencies.json`
- `TimezoneCatalog`
- `CurrencyCatalog`
- Admin settings UI with tabs and live preview
- Global currency formatting is consumed by POS and receipts

## Merchant Area

### Authentication and Account

- Merchant login and logout
- Merchant dashboard
- Merchant profile edit
- Merchant business details edit
- Merchant password change
- Active shop selector
- Active shop middleware for shop-scoped modules

### Merchant Shops

- Merchant shop listing
- Add shop
- View shop
- Edit shop
- Activate eligible shop as the current working shop
- Merchant shop creation uses root Product Category as Shop Type
- Merchants cannot delete shops from merchant area

### Merchant Settings

- Generic `merchant_settings` table with merchant, group, key, value, and type
- `MerchantSettingsService`
- `MerchantSettingsInitializer`
- `MerchantSettingsSeeder`
- Settings automatically initialized when a merchant is created
- Idempotent initialization for existing merchants
- Obsolete setting cleanup for DEV-stage schema changes
- Merchant settings UI with tabs:
  - General
  - Payments
  - POS
  - Inventory
  - Products
  - Receipts
  - Notifications
  - Advanced
- Payment settings:
  - Default payment method
  - Allow Cash
  - Allow UPI
  - Allow Card
  - Allow Credit
- POS settings:
  - Cash rounding method
  - Cash rounding payment-method scope
  - Product tile size
  - Exchange replacement selector mode
  - Add-to-cart sound
  - Held order expiry days
  - Allow order discount
  - Allow item discount
- Receipt settings:
  - Shop name
  - Address
  - Phone
  - GST number
  - Customer name and phone
  - Cashier name
  - Tax breakdown
  - Sale barcode
  - QR code
  - Order number
  - SKU under each item
  - HSN code under each item
  - HSN-wise GST summary
  - Footer text
  - Return policy
- Inventory settings:
  - Allow negative stock
  - Low stock warning
  - Low stock default quantity
- Product settings:
  - Auto-generate barcode

### Merchant Products

- Merchant product listing and CRUD
- Bulk product actions
- Product duplicate action
- Product archive and restore from archive
- Product tabbed edit flow
- Category-scoped attribute selection
- Variant generation
- Variant update and bulk update
- Product barcode generation
- Product image upload, update, ordering, bulk delete, and single delete
- Product description and SEO generation actions

### Barcode Labels

- Barcode label selection page
- Generate missing barcodes for selected variants
- Generate missing barcodes for active shop
- Barcode label print preview
- Code 128 SVG barcode generation
- Barcode lookup and uniqueness validation across active variants

### Customer Management

- Merchant-scoped customer list
- Search by name, mobile, and customer code
- Order by newest, orders high-to-low, or orders low-to-high
- Create customer
- View customer
- Edit customer
- Soft delete customer with confirmation
- Bulk customer soft delete
- Customer summary
- Customer order history
- Mobile lookup for duplicate prevention
- Merchant scoping prevents access to another merchant's customers
- Customer code generation
- Customer snapshot stored on POS orders
- Horizontal customer create/edit form layout
- Generated customer code hidden on create and shown only after creation

### Customer Addresses

- Customer address CRUD under customer profile
- Address label
- Recipient name
- Recipient country code
- Recipient mobile
- Address line 1
- Address line 2
- Landmark
- City
- State
- Country
- Postal code
- Default shipping
- Default billing
- Status
- State and city lookup endpoints
- Merchant scoping prevents access to another merchant's customer addresses

## Merchant POS

### POS Product Search and Cart

- POS product grid scoped to active shop
- Category filter
- Search by product name, SKU, variant, and barcode
- Barcode scanner friendly search
- Exact barcode auto-add flow
- Duplicate barcode conflict response
- Add item to cart
- Increase/decrease item quantity
- Remove item
- Clear cart
- Hold cart in browser storage
- Resume held cart
- Recent sales modal
- Product tile size setting applied to POS grid
- Optional add-to-cart sound setting

### POS Customer and Fulfilment

- Default walk-in customer state
- Customer search/select modal
- Search customer by mobile, name, email, or customer code
- Quick-add walk-in customer by mobile number from POS
- Existing customer reuse by mobile across shops under the same merchant
- Add shipping address from POS
- Counter fulfilment
- Pickup fulfilment
- Delivery fulfilment
- Delivery requires selected customer and address
- Customer and address snapshots stored on order
- POS More Actions dropdown closes on outside click, Escape, or action selection

### POS Payments

- Payment methods come from merchant payment settings
- Default payment method comes from merchant payment settings
- Checkout rejects disabled payment methods
- Supported manual POS methods:
  - Cash
  - UPI
  - Card
  - Credit
- Cash received and change calculation
- Non-cash paid amount defaults to payable total
- Credit sale stores zero paid amount and unpaid payment status

### POS Discounts

- Line item discount modal
- Order discount modal
- Percent discount mode
- Amount discount mode
- Discount live preview
- Discount validation:
  - No negative values
  - Percent not above 100
  - Amount not above subtotal
  - Empty values rejected when applying
- Line discount badges on cart rows
- Order discount badge
- Discount settings enforced:
  - Order discount can be disabled
  - Item discount can be disabled
- Centralized `DiscountService`

### POS Totals and Checkout

- Centralized order total calculation
- Subtotal
- Item discount
- Order discount
- Tax placeholder
- Shipping placeholder
- Cash rounding
- Round-off row
- Grand total
- Amount paid
- Change amount
- Stock deduction on checkout
- Payment status resolution:
  - paid
  - unpaid
  - partially paid
  - refunded
  - partially refunded
- Order status history creation
- POS order number generation

### POS Receipts

- Receipt page
- Print action
- Receipt settings applied from merchant settings
- Admin global currency formatting applied
- Receipt displays:
  - Invoice number
  - Date
  - Cashier
  - Customer details when enabled
  - Line items
  - SKU when enabled
  - HSN when enabled
  - Line discount
  - Item discount total
  - Order discount total
  - Tax breakdown when enabled
  - GST number when enabled
  - Sale barcode when enabled
  - QR code when enabled
  - Footer text
  - Return policy

## Merchant Sales and Returns

- Sales section in merchant sidebar
- POS sales history list
- Sales filters:
  - Payment status
  - Payment method
  - Customer
  - Date range
  - Search by order number, customer, mobile, or cashier
- Sales summary:
  - Total sales
  - Transactions
  - Items sold
  - Average sale
- Sale detail page
- Refund page for completed POS sales
- Refund processing:
  - Return reason selection
  - Refund method selection
  - Line-level refund quantities
  - Remaining refundable quantity calculation
  - Positive Restock checkbox per refund line
  - Stock increment when restocked
  - Refund subtotal, tax, and total calculation
  - Full and partial payment status updates
  - Refund status history entry
- Exchange page for completed POS sales
- Exchange processing:
  - Separate Exchange action from Refund/Return
  - Exchange is not a return reason
  - Shop exchange promise/policy text belongs in receipt/shop policy settings
  - Original line quantity selection
  - Old item scan/search to increment returned line quantities
  - Remaining exchangeable quantity calculation
  - Original MRP, selling price, and paid amount per item shown beside exchange quantity for cashier verification
  - Static "How exchange works" help modal with a simple exchange example and non-restock guidance
  - Returned value based on original tax-exclusive order-item sold value after item discount plus prorated line tax
  - Replacement item scan/search by barcode, SKU, or product name using POS search behavior
  - Exact barcode/SKU replacement matches can be added without a dropdown
  - Optional replacement dropdown for small shops without scanners
  - Merchant setting supports Search/Scan only, Dropdown only, or Both
  - Replacement order creation with `exchange_replacement` source
  - Sales and collection reports exclude `exchange_replacement` orders
  - Difference settlement as even, collect extra, refund balance, or credit adjustment
  - Returned item restock override
  - Returned stock increment and replacement stock deduction inside one transaction
  - Exchange status history entry
  - Printable exchange receipt
- Return reason management:
  - List/search return reasons
  - Create return reason
  - Edit return reason
  - Delete return reason
  - Sort order
  - Restock-by-default flag
  - Manager-override flag
  - Active/inactive return reason status
- Default merchant return reasons seeded during merchant settings initialization
- `Exchange` is excluded from default return reasons and hidden from refund reason selection
- Planned Non-restocked Returns report:
  - Lists refund/exchange returned items where `Restock` was unchecked
  - Defaults to last 30 days and limits date filtering to the last 1 year
  - Keeps older audit records in source refund/exchange tables, but hides them from this operational report

## Order Foundation

- Orders table
- Order items table
- Order refunds table
- Order refund items table
- Order exchanges table
- Order exchange return items table
- Order totals table
- Order status histories table
- Order creation service
- Order refund service
- Order exchange service
- Order totals service
- Order status service
- Order number service
- Customer snapshot fields
- Shipping address snapshot fields
- Item discount fields
- Order discount fields
- Rounding adjustment field
- Payment status fields
- Order to refunds relationship
- Order to exchanges relationship

## Product Foundation

- Products
- Product variants
- Product attributes
- Product attribute groups
- Product attribute group values
- Product variant attributes
- Product images
- Product image attribute values
- Product description templates
- Product category attribute mappings
- Brand to root product category mapping

## System Foundation

- Users
- Roles
- Permissions
- Role permissions
- User roles
- Sessions
- Login history
- Password reset tokens
- Email verification tokens
- Mobile verification tokens
- System settings
- System setting groups
- System audit logs
- Location master data:
  - Regions
  - Subregions
  - Countries
  - States
  - Cities

## Category Rules

Product Categories is the active category master module.

Root Product Categories act as Shop Types. Leaf Product Categories classify products under a shop type.

Removed modules:

- Shop Categories
- Shop-category-to-product-category mapping

## DEV-Stage Assumption

The project is still in active development. Clean main migrations, seeders, and settings defaults are preferred over backward-compatible patch migrations when the schema or setting design changes.
