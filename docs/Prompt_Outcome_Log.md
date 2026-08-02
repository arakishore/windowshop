# Prompt Outcome Log

## Purpose

This document records important user prompts and the final implementation outcome.

Use it as a running project memory so we can quickly see:

- what the previous prompt/request was
- what was implemented
- what was intentionally not implemented
- what tests or checks were run

Add new entries at the top, newest first, with local time.

## 2026-08-02 11:25 +05:30 - Payment Status Master V1

### Exact User Prompt

```text
WindowShop - Payment Status Master (V1 Final)
Objective

Implement a dedicated Payment Status Master to manage the payment lifecycle independently from the Order Status lifecycle.

Order Status and Payment Status are separate concepts and must remain independent.

Examples:

Order Status: Confirmed → Payment Status: Pending (Cash on Delivery)
Order Status: Delivered → Payment Status: Paid
Order Status: Cancelled → Payment Status: Refunded
Payment Statuses

Seed the following system payment statuses.

Code	Name	Category	Terminal
pending	Pending	Awaiting Payment	No
partially_paid	Partially Paid	Partially Paid	No
paid	Paid	Paid	Yes
failed	Failed	Failed	Yes
cancelled	Cancelled	Failed	Yes
partially_refunded	Partially Refunded	Refunded	No
refunded	Refunded	Refunded	Yes
chargeback	Chargeback	Disputed	Yes
Categories

Create the following categories.

Category	Description
Awaiting Payment	Waiting for payment.
Partially Paid	Partial payment received.
Paid	Payment completed successfully.
Failed	Payment failed or cancelled.
Refunded	Full or partial refund completed.
Disputed	Payment reversed by bank or payment gateway.
Status Descriptions

Populate the following descriptions during seeding.

Pending

Description

Payment has not yet been received.

Partially Paid

Description

Partial payment has been received. Remaining balance is still outstanding.

Paid

Description

Full payment has been received successfully.

Failed

Description

Payment attempt was unsuccessful.

Cancelled

Description

Payment was cancelled before completion.

Partially Refunded

Description

Part of the payment has been refunded.

Refunded

Description

Full payment has been refunded.

Chargeback

Description

Payment was reversed by the customer's bank or payment provider.

Merchant UI

Payment Status should be managed independently of Order Status.

Examples:

Order Status
Confirmed

Payment Status
Pending
Order Status
Delivered

Payment Status
Paid
Order Status
Cancelled

Payment Status
Refunded
POS Behaviour
Scenario	Payment Status
Cash Sale	Paid
Customer pays advance	Partially Paid
Remaining balance collected	Paid
Cash on Delivery order	Pending
Full refund	Refunded
Partial refund	Partially Refunded
Future Online Payments

This structure must support future payment gateways without schema changes.

Examples:

Razorpay
Stripe
PayPal
PhonePe
Cashfree
Paytm

Future gateway integrations can update these statuses without introducing new core payment states.

Design Rules
Payment Status and Order Status are completely independent.
A payment status change must not automatically change the order status unless business rules explicitly require it.
Business workflows can later define automatic transitions where necessary.

Examples:

Order Status: Confirmed
Payment Status: Pending
Order Status: Delivered
Payment Status: Paid
Order Status: Cancelled
Payment Status: Refunded

All of the above combinations are valid depending on the business workflow.

Expected Result

Implement a scalable Payment Status Master with:

8 system payment statuses.
Category support.
Seeded descriptions.
Independent lifecycle from Order Status.
Ready for POS today.
Compatible with future Web Shop, Mobile App, Payment Gateways, Subscriptions, Refunds, and Chargebacks without requiring database redesign.

- pls append at end the excat prompt and outcome to Prompt_Outcome_Log.md
```

### Final Outcome

Implemented the Payment Status Master V1 foundation:

- added `payment_statuses` master table with UUIDs, code, name, category, category description, status description, badge type, sort order, system/custom flags, terminal flag, merchant visibility, audit columns, timestamps, and soft deletes
- added `PaymentStatus` model with controlled categories, badge types, system defaults, scopes, safe badge rendering, UUID routing, and audit relationships
- seeded 8 system payment statuses: `pending`, `partially_paid`, `paid`, `failed`, `cancelled`, `partially_refunded`, `refunded`, and `chargeback`
- seeded category descriptions and status descriptions from the requested V1 values
- wired the seeder into `DatabaseSeeder`
- added Admin Master Data CRUD for Payment Statuses
- protected system status workflow fields so core seeded codes/categories/terminal/status values cannot be accidentally redesigned from the UI
- allowed safe system presentation edits such as name, description, category helper text, badge, sort order, and merchant visibility
- supported custom payment statuses with immutable generated codes, optional descriptions, soft delete, restore, and usage protection
- added the Payment Statuses sidebar entry under Master Data
- kept payment status lifecycle independent from order status lifecycle; no automatic order status transitions were introduced

### Verification

- `php artisan test tests\Feature\AdminPaymentStatusMasterTest.php` passed: 9 tests, 87 assertions
- `php artisan view:clear` passed
- `php artisan view:cache` passed
- `php artisan test` passed: 404 tests, 3354 assertions

## 2026-08-02 11:00 +05:30 - Order Status Master Description Columns

### User Prompt

User attached "WindowShop - Order Status History Enhancement (V1)" but clarified the current scope should be only table/master changes in `order_statuses`, and asked to append `Prompt_Outcome_Log.md`.

Requested order-status master changes:

- add `admin_description`
- add `customer_description`
- keep/use `internal_notes`
- seed all three fields for every system status
- do not implement order history workflow behavior in this step

### Final Outcome

Updated only the Order Status Master side:

- changed the `order_statuses` migration from generic `description` to `admin_description`
- added `customer_description`
- retained `internal_notes`
- updated `OrderStatus` fillable/defaults
- populated admin/customer/internal text for all 24 system statuses
- updated admin CRUD validation, search, list tooltips, and edit form fields
- system statuses now require both admin and customer descriptions on edit
- custom statuses may leave descriptions blank
- updated focused order-status tests

No `orders`, `order_status_histories`, status-change workflow, notification logic, or snapshot behavior was added.

### Verification

- Focused tests: `php artisan test tests\Feature\AdminOrderStatusMasterTest.php`
- Result: `9 passed (100 assertions)`
- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed

## 2026-08-01 23:34 +05:30 - Order Status Name Tooltip Layout

### User Prompt

User asked to remove the inline truncated description from the Admin Order Status list because the tooltip icon already carries the full description. Preferred display: `Pending` with an info icon beside the name.

### Final Outcome

Updated the Admin Order Status list:

- removed the inline truncated description text under the status name
- moved the description info icon beside the status name
- kept the muted code badge under the status name

### Verification

- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed

## 2026-08-01 23:31 +05:30 - Order Status List UX Polish

### User Prompt

User suggested two optional improvements for the Admin Order Status list:

- make internal `code` less prominent because admins usually care about the status name
- truncate the description in the grid and keep the full description available from an info icon

### Final Outcome

Updated the Admin Order Status list:

- removed the separate Code column
- displayed code under the status name as a small muted badge
- shortened the visible description preview
- kept the full description available through the existing info tooltip

### Verification

- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed

## 2026-08-01 23:22 +05:30 - Prompt Log Rule Correction

### User Prompt

User clarified that new module-specific docs should not be created by default. For future work, append the prompt and final outcome only in `Prompt_Outcome_Log.md`, and include time in the log.

User also asked whether the earlier Order Status Master work was appended.

### Final Outcome

Confirmed that the Order Status Master prompt/outcome had been appended, but a separate `docs/Order_Status_Master.md` file had also been created.

Removed the separate `docs/Order_Status_Master.md` file.

Updated this log convention so new entries include local time.

### Verification

- Documentation/log cleanup only.

## 2026-08-01 - Expand Order Status Master Defaults And Internal Notes

### User Prompt

User requested expanding seeded default order statuses to cover order, fulfilment, cancellation, return, exchange, and failed lifecycles. User also recommended machine-friendly category values:

```text
open
processing
shipping
fulfilled
cancellation
return
exchange
failed
```

Additional requirements:

- system-seeded descriptions must be mandatory
- custom admin-created descriptions remain optional but recommended
- descriptions should display as helper text/tooltips in admin list/edit pages
- add nullable `internal_notes` for admin-only implementation/business notes
- append prompt and outcome in `Prompt_Outcome_Log.md`

### Final Outcome

Updated the Order Status Master foundation:

- expanded seeded system statuses from 6 to 24
- renamed categories to the requested machine-friendly values
- added `internal_notes` to the schema, model, admin validation, controller, views, prompt log, and tests
- seeded mandatory descriptions for all system statuses
- made system-status description required on edit
- kept custom-status description optional
- displayed descriptions and internal notes in admin list/edit UI where appropriate
- preserved seeded presentation/customisation fields on seeder rerun

No order linking, workflow transition logic, JSON registry, runtime cache, or transaction behavior was added.

### Verification

- Focused tests: `php artisan test tests\Feature\AdminOrderStatusMasterTest.php`
- Result: `9 passed (93 assertions)`
- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed
- Full suite: `php artisan test`
- Result: `395 passed (3260 assertions)`

## 2026-08-01 - Global Order Status Master CRUD Foundation

### User Prompt

User attached the final prompt for "Global Order Status Master CRUD Foundation" and explicitly asked to append the prompt and outcome in `Prompt_Outcome_Log.md`.

Requested scope:

- create global `order_statuses` master table
- create model, seeder, admin CRUD, validation, protections, menu entry, and tests
- seed initial system order statuses
- protect system status workflow fields
- allow custom statuses with generated immutable codes
- check current `orders` and `order_status_histories` string references before delete
- do not link orders, replace strings, add workflows, JSON registry, caching, or other modules

### Final Outcome

Implemented the foundation-only global Order Status Master.

Added:

- `order_statuses` migration
- `App\Models\OrderStatus`
- idempotent `OrderStatusSeeder`
- admin Form Requests
- admin CRUD controller
- admin routes under `admin.master.order-statuses.*`
- sidebar menu entry under `Admin -> Master Data -> Order Statuses`
- admin list/create/edit views
- focused feature tests

Confirmed current runtime order status storage remains string-based:

```text
orders.order_status
order_status_histories.from_status
order_status_histories.to_status
```

No order linking, workflow transition logic, JSON registry, runtime cache, or payment/shipping/promotion work was added.

### Verification

- Focused tests: `php artisan test tests\Feature\AdminOrderStatusMasterTest.php`
- Result: `8 passed (78 assertions)`
- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed
- Full suite: `php artisan test`
- Result: `394 passed (3245 assertions)`

## 2026-08-01 - Simplify Product Availability Customer Text Fields

### User Prompt

User clarified that internal `description` is not useful for merchant availability statuses, and that `display_label` is also unnecessary. Preferred rule: use `name` as the customer-visible label, with helper text near the name field, and keep `customer_description` for customer-facing tooltip/detail text.

### Final Outcome

Removed `display_label` and internal `description` from the product availability schema, model defaults, controller validation, merchant form, product badges, and tests.

Updated the Availability Statuses UI so:

- `Name` is the customer-visible label.
- `Customer Description` remains the optional website/mobile customer help text.
- Product and variant badges display `name`.

Updated `ProductAvailabilityResolver` so storefront/mobile payload labels use `name`.

Updated product availability documentation to describe the simplified field model.

### Verification

- Focused tests: `php artisan test tests\Feature\ProductAvailabilityStatusTest.php`
- Result: `8 passed (54 assertions)`
- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed

## 2026-08-01 - Add Customer Availability Description

### User Prompt

User clarified that `customer_description` should be added now, not deferred, and asked to add default customer-facing data for availability statuses.

### Final Outcome

Added `customer_description` to product availability statuses as a separate customer-facing field from the internal merchant `description`.

Added default customer descriptions for:

- `IN_STOCK`
- `OUT_OF_STOCK`
- `PREORDER`
- `BACKORDER`
- `COMING_SOON`
- `DISCONTINUED`

Updated the merchant Availability Statuses form to edit customer description.

Updated `ProductAvailabilityResolver` so storefront/mobile payloads include:

```text
availability.description
```

Updated product availability documentation with default customer descriptions and payload example.

### Verification

- Focused tests: `php artisan test tests\Feature\ProductAvailabilityStatusTest.php`
- Result: `8 passed (53 assertions)`
- Blade cache: `php artisan view:clear` and `php artisan view:cache` passed

### Follow-Up / Notes

Website/mobile UI should use `availability.description` for future tooltips or product-detail helper text.

## 2026-08-01 - Customer-Facing Availability Description Decision

### User Prompt

User asked whether the availability `description` should be customer-facing for future website/mobile tooltip display, instead of only helping merchants understand status purpose.

### Final Outcome

Decision: keep the current `description` as merchant-facing/internal guidance.

Future storefront/mobile work should add a separate customer-facing field, such as:

```text
customer_description
```

Reason:

- Current descriptions use operational wording like "Use when..."
- Customer-facing text needs different language suitable for tooltips or product detail pages.
- Storefront/mobile availability display does not exist yet, so adding the customer field now is not required.

### Verification

- Documentation-only decision.
- No code changes.

### Follow-Up / Notes

Add `customer_description` when website/mobile product availability tooltips or product-detail messaging are implemented.

## 2026-08-01 - Availability Seeder Fix And Default Descriptions

### User Prompt

During `php artisan migrate:fresh --seed`, seeding failed because `product_availability_statuses.uuid` had no default value.

After that, the user asked to add description data for default availability statuses so merchants can understand each status purpose.

### Final Outcome

Fixed the availability default seeder so it explicitly writes a UUID when creating missing default statuses. This was needed because `DatabaseSeeder` uses `WithoutModelEvents`, so the model UUID event does not run during seeding.

Added default descriptions to all six availability statuses:

- `IN_STOCK`
- `OUT_OF_STOCK`
- `PREORDER`
- `BACKORDER`
- `COMING_SOON`
- `DISCONTINUED`

Updated the product availability documentation table to include those descriptions.

### Verification

- Focused tests: `php artisan test tests\Feature\ProductAvailabilityStatusTest.php`
- Result: `8 passed (53 assertions)`

### Follow-Up / Notes

Rerun `php artisan migrate:fresh --seed` after this fix to confirm MySQL seed completion.

## Entry Template

```text
## YYYY-MM-DD - Short Feature/Task Name

### User Prompt

Brief summary of the user request.

### Final Outcome

Brief summary of what was delivered.

### Verification

- Focused tests:
- Full suite:
- Other checks:

### Follow-Up / Notes

Anything intentionally deferred or useful for the next prompt.
```

## 2026-08-01 - Product Availability Statuses

### User Prompt

Implement a merchant-specific Product Availability Status feature for WindowShop.

The request included:

- merchant-specific availability statuses, not a global stock status master
- `purchase_allowed` as the single zero-stock customer purchase control
- six default statuses per merchant: `IN_STOCK`, `OUT_OF_STOCK`, `PREORDER`, `BACKORDER`, `COMING_SOON`, `DISCONTINUED`
- product-level availability status
- variant-level override with product inheritance
- effective availability resolver
- merchant CRUD UI
- customer storefront/mobile payload contract with `availability` and `can_purchase`
- server-side customer cart/checkout guard
- no POS inventory behaviour change
- focused tests and full regression

### Final Outcome

Implemented Merchant Product Availability Statuses and Zero-Stock Customer Purchase Behaviour.

Delivered:

- `product_availability_statuses` merchant-scoped table
- `availability_status_id` on `products`
- `availability_status_id` on `product_variants`
- `ProductAvailabilityStatus` model and relationships
- idempotent `MerchantAvailabilityStatusSeeder`
- `ProductAvailabilityResolver`
- `CustomerPurchaseAvailabilityGuard`
- merchant Availability Statuses CRUD screen under Catalog
- Customer Availability section on product form
- variant availability inheritance/override controls
- admin and merchant product list availability badges
- product duplication preservation within the same merchant
- documentation in `docs/Product_Availability_Statuses.md`

### Verification

- `php artisan view:clear` passed
- `php artisan optimize:clear` passed
- `php artisan view:cache` passed
- Focused availability tests passed: `8 passed`
- Related product/POS tests passed: `93 passed`
- Full test suite passed: `386 passed (3166 assertions)`

### Follow-Up / Notes

There is no storefront/cart module in this repo yet, so the resolver and customer guard were added for future website/mobile/cart integration.

POS stock rules were intentionally left unchanged.

## 2026-08-02 13:39 +05:30 - Merchant Cancellation Reasons CRUD Only

### Exact User Prompt

```text
WindowShop – Merchant Cancellation Reasons CRUD Only
Objective

Implement a new merchant-level Cancellation Reasons master CRUD.

This feature is only for managing cancellation reason records.

Do not integrate it with Orders, POS, refunds, returns, exchanges, webshop, customer portal, notifications, or any other workflow in this step.

Do not modify any existing module unless required only to register this new CRUD, such as routes, permissions, sidebar menu, models, migrations, requests, controllers, Blade views, seeders, and tests.

1. Table

Create the table:

merchant_cancellation_reasons

This is merchant-level and shared across all shops belonging to that merchant.

Do not add:

shop_id
product_category_id
top_parent_category_id

No shop or category mapping is required.

Migration
public function up(): void
{
    Schema::create('merchant_cancellation_reasons', function (Blueprint $table): void {
        $table->engine = 'InnoDB';
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_unicode_ci';

        $table->id();
        $table->uuid('uuid')->unique();

        $table->foreignId('merchant_id')
            ->constrained('merchant_profiles')
            ->cascadeOnDelete();

        $table->string('code', 80);
        $table->string('name', 120);

        $table->string('description', 500)->nullable();
        $table->text('internal_notes')->nullable();

        $table->unsignedInteger('sort_order')->default(99);

        $table->boolean('customer_selectable')->default(false);
        $table->boolean('merchant_selectable')->default(true);
        $table->boolean('requires_comment')->default(false);

        $table->string('status', 30)->default('active')->index();

        $table->foreignId('created_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->foreignId('updated_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->timestamps();
        $table->softDeletes();

        $table->unique(
            ['merchant_id', 'code'],
            'merchant_cancellation_reasons_merchant_code_unique'
        );

        $table->index(
            ['merchant_id', 'status', 'sort_order'],
            'merchant_cancellation_reasons_merchant_status_sort_idx'
        );
    });
}
Column purpose
merchant_id
Reason belongs to one merchant and is available across all merchant shops.

code
Stable internal code. Unique per merchant.

name
Visible reason name shown in the CRUD list and dropdowns later.

description
Plain business explanation of the reason.

internal_notes
Internal admin/developer note. Not intended for customers.

sort_order
Controls reason ordering.

customer_selectable
Whether this reason may later be shown to customers.

merchant_selectable
Whether this reason may later be selected by merchant users.

requires_comment
Whether a comment will later be mandatory when this reason is selected.

status
active or inactive.

created_by
User who created the record.

updated_by
User who last updated the record.

deleted_at
Soft deletion.
2. Model

Create:

App\Models\MerchantCancellationReason

Requirements:

Use HasFactory
Use SoftDeletes
Generate UUID automatically
Add fillable fields
Add casts for boolean fields
Add constants for statuses
Add merchant relationship
Add createdBy and updatedBy relationships
Add useful scopes:
forMerchant($merchantId)
active()
ordered()

Suggested constants:

public const STATUS_ACTIVE = 'active';
public const STATUS_INACTIVE = 'inactive';

Suggested casts:

protected $casts = [
    'customer_selectable' => 'boolean',
    'merchant_selectable' => 'boolean',
    'requires_comment' => 'boolean',
    'sort_order' => 'integer',
];
3. Seeder

Create or update a dedicated seeder:

MerchantCancellationReasonSeeder

Seed the same default reasons for every existing merchant.

Use updateOrCreate() or another idempotent method so repeated seeding does not create duplicates.

The unique matching key should be:

merchant_id + code

Seed these records.

1. Customer Requested Cancellation
code:
customer_requested

name:
Customer Requested Cancellation

description:
The customer asked to cancel the order before fulfilment was completed.

internal_notes:
Customer-originated cancellation. A comment may be added when extra context is required.

sort_order:
10

customer_selectable:
true

merchant_selectable:
true

requires_comment:
false

status:
active
2. Ordered by Mistake
code:
ordered_by_mistake

name:
Ordered by Mistake

description:
The customer placed the order accidentally or selected incorrect items.

internal_notes:
Customer-originated cancellation. Normally used before shipping or pickup completion.

sort_order:
20

customer_selectable:
true

merchant_selectable:
true

requires_comment:
false

status:
active
3. Duplicate Order
code:
duplicate_order

name:
Duplicate Order

description:
The order duplicates another order already placed by the customer.

internal_notes:
Verify the related order before cancelling.

sort_order:
30

customer_selectable:
true

merchant_selectable:
true

requires_comment:
false

status:
active
4. Product Out of Stock
code:
out_of_stock

name:
Product Out of Stock

description:
One or more products required for the order are unavailable.

internal_notes:
Merchant-originated cancellation. Inventory should be reviewed separately.

sort_order:
40

customer_selectable:
false

merchant_selectable:
true

requires_comment:
false

status:
active
5. Unable to Fulfil
code:
unable_to_fulfil

name:
Unable to Fulfil Order

description:
The merchant is unable to complete the order.

internal_notes:
Use when no more specific cancellation reason applies. A comment is required.

sort_order:
50

customer_selectable:
false

merchant_selectable:
true

requires_comment:
true

status:
active
6. Store Closed
code:
store_closed

name:
Store Closed

description:
The order cannot be completed because the store is temporarily unavailable or closed.

internal_notes:
Merchant-originated cancellation.

sort_order:
60

customer_selectable:
false

merchant_selectable:
true

requires_comment:
false

status:
active
7. Payment Not Received
code:
payment_not_received

name:
Payment Not Received

description:
The required payment was not received within the expected time.

internal_notes:
Do not use this reason as a replacement for payment status management.

sort_order:
70

customer_selectable:
false

merchant_selectable:
true

requires_comment:
false

status:
active
8. Suspected Fraud
code:
suspected_fraud

name:
Suspected Fraud

description:
The order requires cancellation because fraudulent or suspicious activity is suspected.

internal_notes:
Internal reason. Should not normally be exposed to customers.

sort_order:
80

customer_selectable:
false

merchant_selectable:
true

requires_comment:
true

status:
active
9. System Error
code:
system_error

name:
System Error

description:
The order cannot continue because of a technical or system-related issue.

internal_notes:
A comment is required to record the exact issue.

sort_order:
90

customer_selectable:
false

merchant_selectable:
true

requires_comment:
true

status:
active
10. Other
code:
other

name:
Other

description:
The cancellation reason does not match any predefined option.

internal_notes:
A comment is mandatory.

sort_order:
999

customer_selectable:
true

merchant_selectable:
true

requires_comment:
true

status:
active
4. Merchant CRUD

Add a merchant-level CRUD page.

Suggested menu:

Merchant
→ Settings
→ Cancellation Reasons

or place it near the existing Return Reasons menu for consistency.

List page columns
Name
Code
Description
Customer Selectable
Merchant Selectable
Requires Comment
Status
Sort Order
Actions
CRUD capabilities

Implement:

List use DataTables 

Create
Edit
Activate
Deactivate
Soft delete
Trash list
Restore
Merchant scoping

Every query must be restricted to the authenticated merchant.

A merchant must never view, edit, restore, or delete another merchant’s cancellation reason.

Use the current active merchant context already used by the project.

Validation
Create
code
required
string
max 80
lowercase snake_case format
unique within the current merchant, including active records

name
required
string
max 120

description
nullable
string
max 500

internal_notes
nullable
string

sort_order
required
integer
minimum 0

customer_selectable
boolean

merchant_selectable
boolean

requires_comment
boolean

status
required
in active,inactive
Update
code should remain unique within the merchant.
Prefer making code read-only after creation to keep it stable.
Allow editing all other fields.
Business rules
other must always have requires_comment = true.
At least one of customer_selectable or merchant_selectable should be true.
Soft-deleted records must not appear in the normal list.
Inactive records must remain editable.
No force-delete UI is required in this step.
Do not create integration logic with orders.
5. Permissions

Add permissions consistent with the existing project convention, for example:

merchant.cancellation-reasons.view
merchant.cancellation-reasons.create
merchant.cancellation-reasons.update
merchant.cancellation-reasons.delete
merchant.cancellation-reasons.restore

Assign them to the appropriate merchant role through the existing permission seeder pattern.

Do not alter unrelated permissions.

6. UI Requirements

Follow the current Laravel Blade, Bootstrap 5, and Limitless design used in the project.

Create:

index
create
edit
trash
_form partial

Use:

Status badges
Yes/No badges for boolean fields
Confirmation before delete
Confirmation before restore
Validation errors
Success/error flash messages
Existing pagination style
Existing breadcrumb and card layout
7. Tests

Add focused feature tests for only this CRUD.

Cover:

Merchant can view own cancellation reasons
Merchant cannot access another merchant’s reasons
Create reason
Update reason
Duplicate code rejected within same merchant
Same code allowed for a different merchant
Invalid code format rejected
other requires requires_comment = true
At least one selectable audience is required
Status filters
Search
Soft delete
Trash list
Restore
Seeder is idempotent
Default reasons are created for existing merchants

Run focused tests and report the exact test results.

Strict Scope

Do not touch or modify:

POS
Order creation
Order cancellation workflow
Order statuses
Payment statuses
Returns
Refunds
Exchanges
Inventory
Stock
Receipts
Notifications
Webshop
Customer portal
Mobile API
Payment settings
Merchant return reasons

Do not add:

shop_id
category_id
top_parent_category_id
order_id
order_item_id
refund behaviour
restock behaviour
notification behaviour

This step is strictly:

merchant_cancellation_reasons
migration
model
seeder
merchant CRUD
permissions
tests

Stop after the independent CRUD is implemented and verified.

pls append at end the excat prompt and outcome to Prompt_Outcome_Log.md
```

### Final Outcome

Implemented the independent merchant Cancellation Reasons CRUD only:

- added `merchant_cancellation_reasons` migration with merchant-level scoping, UUID, code/name, descriptions, flags, status, audit users, timestamps, soft deletes, unique merchant/code key, and merchant/status/sort index
- added `MerchantCancellationReason` model with UUIDs, factories, soft deletes, casts, status constants, merchant/audit relationships, and `forMerchant`, `active`, and `ordered` scopes
- added idempotent `MerchantCancellationReasonSeeder` with all 10 requested default reasons for every existing merchant
- seeded requested permission slugs and assigned them to the merchant role
- added merchant CRUD routes for index, create, edit, update, soft delete, trash, and restore
- added Merchant Sales sidebar link beside Return reasons
- added Blade views: `index`, `create`, `edit`, `trash`, `_form` partial, and shared confirmation/DataTables script partial
- enforced merchant scoping for view/edit/update/delete/trash/restore
- enforced create code format/uniqueness, read-only code after creation, `other` requires comment, and at least one selectable audience
- did not integrate with Orders, POS, refunds, returns, exchanges, webshop, customer portal, notifications, inventory, stock, receipts, mobile API, or payment settings

### Verification

- `php artisan test tests\Feature\MerchantCancellationReasonCrudTest.php` passed: 7 tests, 53 assertions
- `php artisan view:cache` passed
- `php artisan test` passed: 411 tests, 3407 assertions
