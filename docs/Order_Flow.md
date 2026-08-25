# Order Flow

## Purpose

This document records the current merchant-controlled V1 order workflow and the intended future separation between order status and delivery/logistics status.

The order workflow is the business lifecycle of the customer order. Delivery workflow is a separate logistics layer that may overlap with order status names, but should not be forced into the same concept.

## Current Merchant-Controlled V1 Order Flow

For delivery orders, the current merchant-controlled V1 order flow is:

```text
pending
-> confirmed
-> processing
-> packed
-> shipped
-> out_for_delivery
-> delivered
-> completed
```

For pickup orders, the current merchant-controlled V1 order flow is:

```text
pending
-> confirmed
-> processing
-> ready_for_pickup
-> completed
```

For V1, delivery uses WindowShop's common customer-facing lifecycle. Merchants manually progress the delivery statuses now; later delivery staff, courier APIs, or logistics integrations may trigger the same normalized transitions. Provider-specific statuses remain outside the order-status workflow. For manual Cash on Delivery handling, Mark Delivered confirms both physical delivery and COD payment, then completes the order through the normal delivered-to-completed transition. `ready_for_pickup` means pickup preparation is complete and can move to `completed` only through the explicit Complete Pickup collection action.

The centralized backend authority for allowed transitions is:

```php
config/order_workflow.php
App\Services\Order\OrderStatusService
```

The sections below are a readable snapshot copied from `config/order_workflow.php`. If the config changes, update this document in the same task.

Do not add arbitrary status jumps in controllers or Blade views. UI actions should ask `OrderStatusService` what transitions are currently allowed.

## Configured Transition Snapshot

### Pickup

```text
pending
-> confirmed
-> cancelled

confirmed
-> processing
-> cancelled

processing
-> ready_for_pickup
-> cancelled

ready_for_pickup
-> completed

completed
-> no next status

cancelled
-> no next status
```

### Delivery

```text
pending
-> confirmed
-> cancelled

confirmed
-> processing
-> cancelled

processing
-> packed
-> cancelled

packed
-> shipped

shipped
-> out_for_delivery

out_for_delivery
-> delivered

delivered
-> completed

completed
-> no next status

cancelled
-> no next status
```

`packed` means the delivery order is prepared. `shipped` means the order has been handed over to the delivery process/provider. No `ready_for_dispatch` order status is added for V1.

`ready_for_pickup` means the pickup order is prepared and ready for customer collection. The V1 Complete Pickup action moves it to `completed` after the merchant confirms collection. For `cash_at_shop` orders that are not already paid, the merchant must confirm payment received before completion; the order payment fields are updated in the same transaction as the status transition.

## Cancellation Cut-Off Rules

For V1, simple merchant cancellation is allowed only before the order reaches the handoff/readiness point.

Pickup cancellation is allowed from:

- `pending`
- `confirmed`
- `processing`

Pickup cancellation is not allowed from:

- `ready_for_pickup`
- `completed`

Delivery cancellation is allowed from:

- `pending`
- `confirmed`
- `processing`

Delivery cancellation is not allowed from:

- `packed`
- `shipped`
- `out_for_delivery`
- `delivered`
- `completed`

Later cancellation after pickup readiness or delivery packing may use a dedicated cancellation/refund workflow. Do not add those later rules to the normal order-status flow.

## Special Statuses Excluded From Normal Flow

The normal operational flow must not expose return, exchange, partial cancellation, or failure statuses as generic next statuses.

Excluded statuses include:

- `partially_cancelled`
- `return_requested`
- `return_approved`
- `return_rejected`
- `return_in_transit`
- `return_received`
- `partially_returned`
- `returned`
- `exchange_requested`
- `exchange_approved`
- `exchange_rejected`
- `partially_exchanged`
- `exchanged`
- `failed`

These statuses may remain in master data, but `OrderStatusService::allowedNextStatuses()` should return an empty list for them in the normal workflow.

## Configured Action Labels

The current action labels from `config/order_workflow.php` are:

| Target status | Merchant action label |
| --- | --- |
| `confirmed` | Accept Order |
| `processing` | Start Processing |
| `ready_for_pickup` | Mark Ready for Pickup |
| `packed` | Mark Packed |
| `shipped` | Mark Shipped |
| `out_for_delivery` | Mark Out for Delivery |
| `delivered` | Mark Delivered |
| `completed` | Complete Pickup |
| `cancelled` | Cancel Order |

For delivery, `completed` is structurally available after `delivered`, but the normal merchant V1 UI does not expose a separate Complete Order button. Manual Cash on Delivery completion is bundled into Mark Delivered: `out_for_delivery -> delivered`, COD payment marked paid, then `delivered -> completed` atomically. Future delivery APIs or courier integrations may still create an intermediate `delivered + payment pending` state until payment settlement arrives.

## Future Delivery Flow Layer

Delivery operations will eventually need their own lifecycle and data. That lifecycle can overlap with order statuses, but it is not exactly the same thing.

Future delivery flow may look like:

```text
delivery_created
-> delivery_assigned
-> picked_up_from_shop
-> in_transit
-> out_for_delivery
-> delivered
-> failed_attempt / reschedule
```

The order may show:

```text
Order Status = out_for_delivery
```

while a separate delivery module stores logistics-specific details such as courier, driver, tracking, attempts, and proof of delivery.

## Reserved Future Delivery Data

Do not force the following logistics fields directly into the normal order-status workflow:

- `delivery_status`
- `driver_id` or courier assignment
- `courier_name`
- `tracking_number`
- `pickup_at`
- `shipped_at`
- `out_for_delivery_at`
- `delivered_at`
- `delivery_attempts`
- `failed_delivery_reason`
- `proof_of_delivery`

These belong to a future delivery/fulfillment module or related delivery table/service, not to ad hoc status notes.

## Future Merchant Order Detail UI

Later, the merchant order detail page can show a separate Delivery card:

```text
Delivery

Courier: XYZ
Tracking: WS123456
Status: Out for Delivery

[View Tracking]
```

This should sit beside the order status/progress UI rather than replacing it.

## Current Recommendation

For now:

- finish merchant order workflow actions
- finish comments/notes handling
- keep pickup completion/payment collection limited to the V1 Complete Pickup action
- keep manual delivery COD collection/completion limited to the V1 Mark Delivered action
- keep delivery settings focused on V1 storefront capability and pricing

Later:

- build a dedicated delivery/fulfillment module
- synchronize delivery events with order status where business rules require it
- support local delivery staff, courier integrations, failed attempts, rescheduling, tracking, and proof of delivery

This separation keeps order processing clean while leaving room for real delivery operations.
