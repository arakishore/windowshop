<?php

use App\Notifications\RecipientContactSource;

$channels = static fn (bool $email, bool $mandatory = false): array => [
    'email' => ['default_enabled' => $email, 'mandatory' => $mandatory],
    'sms' => ['default_enabled' => false, 'mandatory' => false],
    'whatsapp' => ['default_enabled' => false, 'mandatory' => false],
];

$event = static fn (
    string $label,
    string $audience,
    string $category,
    string $description,
    array $rules,
    array $variables,
    string $contactSource,
    string $branding,
    string $preferenceScope = 'shop_merchant',
    array $policy = [],
): array => [
    'label' => $label,
    'audience' => $audience,
    'category' => $category,
    'description' => $description,
    'channels' => $rules,
    'template_key' => null,
    'variables' => $variables,
    'contact_source' => $contactSource,
    'branding' => $branding,
    'preference_scope' => $preferenceScope,
    'policy' => $policy,
];

$customerVars = ['customer_name', 'marketplace_name'];
$orderVars = ['customer_name', 'order_number', 'shop_name', 'marketplace_name'];
$merchantVars = ['merchant_name', 'shop_name', 'order_number', 'marketplace_name'];

return [
    'events' => [
        'customer.registered' => $event('Customer registered', 'customer', 'account', 'Welcome the customer after account registration.', $channels(true, true), $customerVars, 'current_account', 'marketplace'),
        'merchant.account_created' => $event('Merchant account created', 'merchant', 'merchant_lifecycle', 'Acknowledge creation of a merchant account.', $channels(true, true), ['merchant_name', 'marketplace_name'], RecipientContactSource::MERCHANT_PRIMARY, 'marketplace'),
        'merchant.approved' => $event('Merchant approved', 'merchant', 'merchant_lifecycle', 'Tell the merchant that their account was approved.', $channels(true, true), ['merchant_name', 'marketplace_name'], RecipientContactSource::MERCHANT_PRIMARY, 'marketplace'),
        'merchant.rejected' => $event('Merchant rejected', 'merchant', 'merchant_lifecycle', 'Tell the merchant that their account was rejected.', $channels(true, true), ['merchant_name', 'marketplace_name'], RecipientContactSource::MERCHANT_PRIMARY, 'marketplace'),
        'merchant.suspended' => $event('Merchant suspended', 'merchant', 'merchant_lifecycle', 'Tell the merchant that their account was suspended.', $channels(true, true), ['merchant_name', 'marketplace_name'], RecipientContactSource::MERCHANT_PRIMARY, 'marketplace'),
        'merchant.reactivated' => $event('Merchant reactivated', 'merchant', 'merchant_lifecycle', 'Tell the merchant that their account was reactivated.', $channels(true, true), ['merchant_name', 'marketplace_name'], RecipientContactSource::MERCHANT_PRIMARY, 'marketplace'),
        'order.placed.customer' => $event('Order placed', 'customer', 'order', 'Confirm that the customer order was placed.', $channels(true, true), $orderVars, 'order_snapshot', 'shop'),
        'order.new.merchant' => $event('New order for merchant', 'merchant', 'order', 'Alert the merchant to a newly placed order.', $channels(true, true), $merchantVars, RecipientContactSource::MERCHANT_PRIMARY_PLUS_ADDITIONAL, 'shop'),
        'order.new.admin' => $event('New order for admin', 'admin', 'order', 'Optionally alert administrators to new orders across all shops.', $channels(false), ['order_number', 'shop_name', 'marketplace_name'], 'admin', 'marketplace', 'global'),
        'order.confirmed.customer' => $event('Order confirmed', 'customer', 'order_status', 'Tell the customer their order was confirmed.', $channels(true), $orderVars, 'order_snapshot', 'shop'),
        'order.processing.customer' => $event('Order processing', 'customer', 'order_status', 'Tell the customer their order is processing.', $channels(false), $orderVars, 'order_snapshot', 'shop'),
        'order.ready_for_pickup.customer' => $event('Order ready for pickup', 'customer', 'order_status', 'Tell the customer their order is ready for pickup.', $channels(true), $orderVars, 'order_snapshot', 'shop'),
        'order.packed.customer' => $event('Order packed', 'customer', 'order_status', 'Tell the customer their order was packed.', $channels(false), $orderVars, 'order_snapshot', 'shop'),
        'order.ready_for_dispatch.customer' => $event('Order ready for dispatch', 'customer', 'order_status', 'Tell the customer their order is ready for dispatch.', $channels(false), $orderVars, 'order_snapshot', 'shop'),
        'order.shipped.customer' => $event('Order shipped', 'customer', 'order_status', 'Tell the customer their order was shipped.', $channels(true), $orderVars, 'order_snapshot', 'shop'),
        'order.in_transit.customer' => $event('Order in transit', 'customer', 'order_status', 'Tell the customer their order is in transit.', $channels(false), $orderVars, 'order_snapshot', 'shop'),
        'order.out_for_delivery.customer' => $event('Order out for delivery', 'customer', 'order_status', 'Tell the customer their order is out for delivery.', $channels(true), $orderVars, 'order_snapshot', 'shop'),
        'order.delivered.customer' => $event('Order delivered', 'customer', 'order_status', 'Tell the customer their order was delivered.', $channels(true), $orderVars, 'order_snapshot', 'shop'),
        'order.completed.customer' => $event('Order completed', 'customer', 'order_status', 'Tell the customer their order was completed.', $channels(false), $orderVars, 'order_snapshot', 'shop', policy: ['suppress_when' => 'auto_after_delivered']),
        'order.cancelled.customer' => $event('Order cancelled', 'customer', 'order_status', 'Tell the customer their order was cancelled.', $channels(true, true), $orderVars, 'order_snapshot', 'shop'),
        'payment.upi_submitted.customer' => $event('UPI payment submitted', 'customer', 'payment', 'Acknowledge a submitted UPI payment awaiting verification.', $channels(true), $orderVars, 'order_snapshot', 'shop'),
        'payment.upi_submitted.merchant' => $event('UPI payment submitted for verification', 'merchant', 'payment', 'Alert the merchant to verify a submitted UPI payment.', $channels(true), $merchantVars, RecipientContactSource::MERCHANT_PRIMARY_PLUS_ADDITIONAL, 'shop'),
        'payment.upi_verified.customer' => $event('UPI payment verified', 'customer', 'payment', 'Tell the customer their UPI payment was verified.', $channels(true, true), $orderVars, 'order_snapshot', 'shop'),
        'payment.upi_rejected.customer' => $event('UPI payment rejected', 'customer', 'payment', 'Tell the customer their UPI payment was rejected.', $channels(true, true), $orderVars, 'order_snapshot', 'shop'),
        'refund.processed.customer' => $event('Refund processed', 'customer', 'post_purchase', 'Tell the customer their refund was processed.', $channels(true, true), $orderVars, 'order_snapshot', 'shop'),
        'exchange.processed.customer' => $event('Exchange processed', 'customer', 'post_purchase', 'Tell the customer their exchange was processed.', $channels(true, true), $orderVars, 'order_snapshot', 'shop'),
        'order.message.customer' => $event('Order message', 'customer', 'order_message', 'Deliver an eligible merchant order message to the customer.', $channels(false), [...$orderVars, 'message'], 'order_snapshot', 'shop', policy: ['delivery_control' => 'per_message_opt_in']),
    ],
];
