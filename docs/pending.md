Implement **Product Reviews V1** in the existing WindowShop Laravel application.

First inspect the existing project architecture, migrations, models, order/order-item structure, customer structure, shop structure, status conventions, UUID handling, audit fields, soft-delete conventions, routes, controllers, services, and admin/merchant UI patterns.

Do **not** unnecessarily change existing working functionality.

## 1. Create Product Reviews Table

Create a new table:

`product_reviews`

Use the project's existing Laravel/database conventions.

Suggested fields:

* `id` BIGINT UNSIGNED primary key auto increment
* `uuid` CHAR(36) unique
* `customer_id` BIGINT UNSIGNED
* `order_id` BIGINT UNSIGNED nullable if appropriate based on existing architecture
* `order_item_id` BIGINT UNSIGNED
* `product_id` BIGINT UNSIGNED
* `shop_id` BIGINT UNSIGNED
* `rating` TINYINT UNSIGNED
* `review_title` VARCHAR(255) nullable
* `review` TEXT nullable
* `verified_purchase` BOOLEAN default false
* `status` VARCHAR(30) default `pending`
* `admin_note` TEXT nullable
* `created_by` nullable
* `updated_by` nullable
* `deleted_by` nullable
* timestamps
* soft deletes

Use proper foreign keys based on the existing project's actual table/column types.

Add useful indexes for:

* customer_id
* product_id
* shop_id
* order_id
* order_item_id
* status
* rating

Prevent duplicate reviews for the same purchased order item.

Prefer a database unique constraint on `order_item_id` if one order item can only belong to one customer/review. Otherwise use the appropriate composite unique constraint based on the existing order architecture.

## 2. Verified Purchase

`verified_purchase` must **never be trusted from frontend input**.

When a customer submits a review, validate server-side that:

* the order belongs to the logged-in customer
* the order item belongs to that order
* the order item corresponds to the specified product
* the shop/product relationship is valid
* the order has reached an appropriate completed/delivered status
* the order item has not already been reviewed

Only then set:

`verified_purchase = true`

Do not allow the customer to manually set this value.

## 3. Rating Validation

Allow ratings:

`1, 2, 3, 4, 5`

Reject values outside this range.

Review title should be optional.

Review text should be optional for V1 unless existing project requirements suggest otherwise.

A customer should therefore be able to submit only a star rating if desired.

## 4. Review Status

Use:

* `pending`
* `approved`
* `rejected`

New customer reviews should default to:

`pending`

Only `approved` reviews should appear publicly on the storefront.

Keep the implementation flexible enough to add statuses such as `hidden` later, but don't over-engineer V1.

## 5. Customer Review Submission

Add review functionality to the appropriate customer order/order-detail area.

Only show **Write a Review** when:

* customer purchased the item
* order/item is eligible for review
* that order item has not already been reviewed

The form should contain:

* 1–5 star rating
* Review Title — optional
* Review — optional
* Submit Review

Do not expose customer_id, order_id, order_item_id, product_id, shop_id or verified_purchase as trusted editable values.

Resolve and validate them server-side.

After submission, show an appropriate message such as:

`Thank you. Your review has been submitted for approval.`

## 6. Product Detail Page

On the storefront product detail page, show only approved reviews.

Display:

* customer display name
* rating/stars
* review title if available
* review text if available
* Verified Purchase badge when applicable
* review date

Do not expose unnecessary customer information.

Also calculate and display:

* average rating
* total review count

Example:

`4.4 ★ (27 Reviews)`

The aggregate must only use approved reviews.

Avoid inefficient N+1 queries.

## 7. Admin Review Management

Create an Admin → Product Reviews section following the existing admin UI conventions.

Admin should be able to:

* list reviews
* search
* filter by status
* filter by rating
* filter by shop if useful
* view review details
* approve
* reject
* soft delete
* restore if consistent with existing project patterns

Useful list columns:

* Product
* Customer
* Shop
* Rating
* Review
* Verified Purchase
* Status
* Submitted At
* Actions

Use existing badge/status styling.

## 8. Merchant Review Management

Add a Product Reviews section to the merchant area.

A merchant must only be able to see reviews belonging to their own shop(s).

Respect the project's existing `active_shop_id`/shop access architecture.

For V1, merchants may:

* view their reviews
* search/filter reviews

Before allowing merchants to approve/reject reviews, inspect the existing business rules.

Prefer **Admin moderation only for V1** unless the existing architecture already establishes merchant moderation.

Do not allow one merchant to access another shop's reviews by modifying IDs or URLs.

## 9. Customer Name

Do not store duplicate `customer_name`, `customer_city`, or `avatar` fields in `product_reviews` unless the existing order snapshot architecture makes that necessary.

Prefer retrieving safe display information through the existing customer/order relationships.

Do not expose sensitive customer information publicly.

## 10. Review Media

Do NOT implement review photos or videos in V1.

The architecture should allow a separate table such as:

`product_review_media`

to be introduced later without redesigning `product_reviews`.

## 11. Product Rating Performance

Do not prematurely add `average_rating` or `review_count` columns to `products`.

For V1, calculate aggregates from approved `product_reviews` efficiently.

If the existing application already has a caching/aggregate convention, follow it.

## 12. Important

Reuse existing:

* authentication
* authorization/policies
* customer/order relationships
* shop isolation
* UUID generation
* validation style
* audit conventions
* soft-delete conventions
* Bootstrap/Limitless UI
* pagination/DataTables conventions where applicable

Do not duplicate functionality already present in the project.

Before implementation, inspect the existing schema and tell me if any suggested field or foreign key conflicts with the current WindowShop architecture.

Then implement the feature step-by-step with minimal changes to existing working modules.
