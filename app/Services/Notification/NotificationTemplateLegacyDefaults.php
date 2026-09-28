<?php

namespace App\Services\Notification;

use App\Notifications\NotificationChannelName;

class NotificationTemplateLegacyDefaults
{
    /** @return array{subject: ?string, body: string}|null */
    public function for(string $eventKey, string $channel): ?array
    {
        $legacy = $this->content()[$eventKey] ?? null;

        if ($legacy === null || ! in_array($channel, NotificationChannelName::all(), true)) {
            return null;
        }

        return [
            'subject' => $channel === NotificationChannelName::EMAIL ? $legacy['subject'] : null,
            'body' => $legacy['body'].($channel === NotificationChannelName::EMAIL ? "\n\nPowered by {{ marketplace_name }}" : ''),
        ];
    }

    /** @return array<string, array{subject: string, body: string}> */
    private function content(): array
    {
        return [
            'customer.registered' => ['subject' => '{{ marketplace_name }}: Customer registered', 'body' => "Hello {{ customer_name }},\n\nWelcome the customer after account registration."],
            'merchant.account_created' => ['subject' => '{{ marketplace_name }}: Merchant account created', 'body' => "Hello {{ merchant_name }},\n\nAcknowledge creation of a merchant account."],
            'merchant.approved' => ['subject' => '{{ marketplace_name }}: Merchant approved', 'body' => "Hello {{ merchant_name }},\n\nTell the merchant that their account was approved."],
            'merchant.rejected' => ['subject' => '{{ marketplace_name }}: Merchant rejected', 'body' => "Hello {{ merchant_name }},\n\nTell the merchant that their account was rejected."],
            'merchant.suspended' => ['subject' => '{{ marketplace_name }}: Merchant suspended', 'body' => "Hello {{ merchant_name }},\n\nTell the merchant that their account was suspended."],
            'merchant.reactivated' => ['subject' => '{{ marketplace_name }}: Merchant reactivated', 'body' => "Hello {{ merchant_name }},\n\nTell the merchant that their account was reactivated."],
            'order.placed.customer' => ['subject' => '{{ shop_name }}: Order placed', 'body' => "Hello {{ customer_name }},\n\nConfirm that the customer order was placed."],
            'order.new.merchant' => ['subject' => '{{ shop_name }}: New order for merchant', 'body' => "Hello {{ merchant_name }},\n\nAlert the merchant to a newly placed order."],
            'order.new.admin' => ['subject' => '{{ marketplace_name }}: New order for admin', 'body' => "Hello there,\n\nOptionally alert administrators to new orders across all shops."],
            'order.confirmed.customer' => ['subject' => '{{ shop_name }}: Order confirmed', 'body' => "Hello {{ customer_name }},\n\nTell the customer their order was confirmed."],
            'order.processing.customer' => ['subject' => '{{ shop_name }}: Order processing', 'body' => "Hello {{ customer_name }},\n\nTell the customer their order is processing."],
            'order.ready_for_pickup.customer' => ['subject' => '{{ shop_name }}: Order ready for pickup', 'body' => "Hello {{ customer_name }},\n\nTell the customer their order is ready for pickup."],
            'order.packed.customer' => ['subject' => '{{ shop_name }}: Order packed', 'body' => "Hello {{ customer_name }},\n\nTell the customer their order was packed."],
            'order.ready_for_dispatch.customer' => ['subject' => '{{ shop_name }}: Order ready for dispatch', 'body' => "Hello {{ customer_name }},\n\nTell the customer their order is ready for dispatch."],
            'order.shipped.customer' => ['subject' => '{{ shop_name }}: Order shipped', 'body' => "Hello {{ customer_name }},\n\nTell the customer their order was shipped."],
            'order.in_transit.customer' => ['subject' => '{{ shop_name }}: Order in transit', 'body' => "Hello {{ customer_name }},\n\nTell the customer their order is in transit."],
            'order.out_for_delivery.customer' => ['subject' => '{{ shop_name }}: Order out for delivery', 'body' => "Hello {{ customer_name }},\n\nTell the customer their order is out for delivery."],
            'order.delivered.customer' => ['subject' => '{{ shop_name }}: Order delivered', 'body' => "Hello {{ customer_name }},\n\nTell the customer their order was delivered."],
            'order.completed.customer' => ['subject' => '{{ shop_name }}: Order completed', 'body' => "Hello {{ customer_name }},\n\nTell the customer their order was completed."],
            'order.cancelled.customer' => ['subject' => '{{ shop_name }}: Order cancelled', 'body' => "Hello {{ customer_name }},\n\nTell the customer their order was cancelled."],
            'payment.upi_submitted.customer' => ['subject' => '{{ shop_name }}: UPI payment submitted', 'body' => "Hello {{ customer_name }},\n\nAcknowledge a submitted UPI payment awaiting verification."],
            'payment.upi_submitted.merchant' => ['subject' => '{{ shop_name }}: UPI payment submitted for verification', 'body' => "Hello {{ merchant_name }},\n\nAlert the merchant to verify a submitted UPI payment."],
            'payment.upi_verified.customer' => ['subject' => '{{ shop_name }}: UPI payment verified', 'body' => "Hello {{ customer_name }},\n\nTell the customer their UPI payment was verified."],
            'payment.upi_rejected.customer' => ['subject' => '{{ shop_name }}: UPI payment rejected', 'body' => "Hello {{ customer_name }},\n\nTell the customer their UPI payment was rejected."],
            'refund.processed.customer' => ['subject' => '{{ shop_name }}: Refund processed', 'body' => "Hello {{ customer_name }},\n\nTell the customer their refund was processed."],
            'exchange.processed.customer' => ['subject' => '{{ shop_name }}: Exchange processed', 'body' => "Hello {{ customer_name }},\n\nTell the customer their exchange was processed."],
            'order.message.customer' => ['subject' => '{{ shop_name }}: Order message', 'body' => "Hello {{ customer_name }},\n\nDeliver an eligible merchant order message to the customer."],
        ];
    }
}
