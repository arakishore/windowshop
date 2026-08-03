# POS Held Orders

## Current Storage Decision

POS held orders are stored in the cashier's browser `localStorage`.

They are not stored in:

- the application database
- Laravel session storage
- browser cookies

## Storage Keys

The POS screen uses shop-scoped browser keys:

- `windowshop.pos.cart.{shopId}` for the active in-progress cart
- `windowshop.pos.held.{shopId}` for held carts
- `windowshop.pos.lastReceipt.{shopId}` for the last receipt reprint helper

When no shop id is available, the fallback suffix is `default`.

## Held Cart Lifecycle

Holding a cart:

1. Captures a browser-side cart snapshot.
2. Adds the snapshot to the `heldCarts` array.
3. Saves `heldCarts` to `localStorage` under `windowshop.pos.held.{shopId}`.
4. Clears the active cart.

Resuming a held cart:

1. Finds the matching snapshot in the browser-side `heldCarts` array.
2. Removes it from held carts.
3. Loads it into the active cart.
4. Re-prices the cart through the POS pricing endpoint.

Deleting a held cart removes only the browser-side snapshot. It does not affect completed sales.

## Data Captured

A held cart snapshot can include:

- held id
- optional label
- cart items with variant id, quantity, and item discount
- cash received
- payment method
- fulfilment type
- selected customer
- selected shipping address id
- order discount
- elapsed POS timer state
- held timestamp

Totals are intentionally refreshed when the held order is resumed.

## Operational Implications

- Held orders are device-specific and browser-specific.
- Held orders are not visible on another device, another browser, or another cashier profile unless they share the same browser storage.
- Clearing browser storage removes held orders.
- Private browsing or blocked/full browser storage can prevent held-order persistence.
- Held orders do not reserve stock in the database.
- Held orders are not auditable server-side.
- Held orders do not appear in sales, reports, recent sales, customer history, or order tables until checkout is completed.

## Completed Checkout

Checkout is the point where the server creates a real order.

Completed POS sales are stored in the database through the POS checkout controller and order creation service. The saved order rows, order item rows, totals, tax snapshots, customer/address snapshots, stock deduction, receipts, refunds, exchanges, and reporting all start from the completed checkout flow, not from holding a cart.

## Related Code

- POS browser storage and held cart JavaScript: `resources/views/merchant/pos/index.blade.php`
- POS checkout endpoint: `app/Http/Controllers/Merchant/PosController.php`
- Checkout persistence service: `app/Services/Order/OrderCreationService.php`
- POS routes: `routes/merchant.php`

## Future DB-Backed Held Orders

If held orders need to sync across devices, survive browser cleanup, reserve inventory, or be auditable, introduce a server-side held-order model instead of extending browser storage.

Recommended future shape:

- `pos_held_orders` table for the held-order header
- `pos_held_order_items` table for item rows
- merchant id, shop id, cashier/user id, optional customer id, optional address id
- label, fulfilment type, payment method, discounts, timer metadata, expires_at
- JSON metadata only for non-queryable UI details
- explicit cleanup job for expired held orders
- optional stock reservation policy if the business wants held carts to affect availability

Until that future change exists, the source of truth is browser `localStorage`.
