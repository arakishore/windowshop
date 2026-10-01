<?php

namespace App\Services\Notification;

use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationEventDefinition;

class NotificationTemplateDefaults
{
    public function for(NotificationEventDefinition $event, string $channel): array
    {
        $content = $this->content()[$event->key] ?? [
            'subject' => '{{ marketplace_name }}: '.$event->label,
            'email' => $event->description,
            'short' => $event->description,
        ];

        return [
            'event_key' => $event->templateKey,
            'channel' => $channel,
            'subject' => $channel === NotificationChannelName::EMAIL ? $content['subject'] : null,
            'body' => $channel === NotificationChannelName::EMAIL ? $content['email'] : $content['short'],
            'is_active' => true,
            'variables' => $event->variables,
            'metadata' => ['branding' => $event->branding, 'seeded' => true],
        ];
    }

    /** @return array<string, array{subject: string, email: string, short: string}> */
    private function content(): array
    {
        return [
            'customer.registered' => [
                'subject' => 'Welcome to {{ marketplace_name }}',
                'email' => "Hello {{ customer_name }},\n\nWelcome to {{ marketplace_name }}.\n\nYour account has been created successfully. You can now discover local shops, explore products and manage your orders from your account.\n\nThank you for joining us.",
                'short' => 'Welcome to {{ marketplace_name }}, {{ customer_name }}. Your customer account has been created successfully.',
            ],
            'merchant.account_created' => [
                'subject' => 'Merchant account created — {{ marketplace_name }}',
                'email' => "Hello {{ merchant_name }},\n\nYour merchant account on {{ marketplace_name }} has been created successfully.\n\nWe will keep you informed about changes to your account status.",
                'short' => '{{ marketplace_name }}: Your merchant account has been created successfully.',
            ],
            'merchant.registered.admin' => [
                'subject' => 'New Merchant Registration - {{ business_name }}',
                'email' => "New Merchant Registration\n\nA new merchant has registered on {{ marketplace_name }} and is awaiting review.\n\nBusiness Name: {{ business_name }}\nOwner Name: {{ owner_name }}\nEmail: {{ email }}\nMobile: {{ mobile }}\nRegistration Date/Time: {{ registration_datetime }}\nVerification Status: {{ verification_status }}\nRegistration Source: {{ registration_source }}\n\nReview Merchant: {{ review_merchant_url }}",
                'short' => 'New merchant registration: {{ business_name }} is awaiting review.',
            ],
            'merchant.approved' => [
                'subject' => 'Merchant account approved — {{ marketplace_name }}',
                'email' => "Hello {{ merchant_name }},\n\nYour merchant account verification on {{ marketplace_name }} has been approved.",
                'short' => '{{ marketplace_name }}: Your merchant account verification has been approved.',
            ],
            'merchant.rejected' => [
                'subject' => 'Merchant verification update — {{ marketplace_name }}',
                'email' => "Hello {{ merchant_name }},\n\nYour merchant account verification on {{ marketplace_name }} was not approved.",
                'short' => '{{ marketplace_name }}: Your merchant account verification was not approved.',
            ],
            'merchant.suspended' => [
                'subject' => 'Merchant account suspended — {{ marketplace_name }}',
                'email' => "Hello {{ merchant_name }},\n\nYour merchant account on {{ marketplace_name }} has been suspended.",
                'short' => '{{ marketplace_name }}: Your merchant account has been suspended.',
            ],
            'merchant.reactivated' => [
                'subject' => 'Merchant account reactivated — {{ marketplace_name }}',
                'email' => "Hello {{ merchant_name }},\n\nYour previously suspended merchant account on {{ marketplace_name }} has been reactivated.",
                'short' => '{{ marketplace_name }}: Your merchant account has been reactivated.',
            ],
            'order.placed.customer' => [
                'subject' => 'Order received — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\nWe received your order {{ order_number }} placed with {{ shop_name }}.\n\nThe shop will review your order, and we will notify you when its status changes.",
                'short' => '{{ shop_name }} received order {{ order_number }}. We will notify you when its status changes.',
            ],
            'order.new.merchant' => [
                'subject' => 'New order received — {{ order_number }}',
                'email' => "Hello {{ merchant_name }},\n\nA new order, {{ order_number }}, has been placed with {{ shop_name }}.\n\nPlease review and process the order.",
                'short' => 'New order {{ order_number }} received for {{ shop_name }}. Please review and process it.',
            ],
            'order.new.admin' => [
                'subject' => 'New order received — {{ order_number }}',
                'email' => 'A new order has been placed on {{ marketplace_name }} for {{ shop_name }}.',
                'short' => 'New marketplace order {{ order_number }} placed with {{ shop_name }}.',
            ],
            'order.confirmed.customer' => [
                'subject' => 'Order confirmed — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\n{{ shop_name }} has confirmed your order {{ order_number }}.",
                'short' => '{{ shop_name }} has confirmed order {{ order_number }}.',
            ],
            'order.processing.customer' => [
                'subject' => 'Order processing — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\n{{ shop_name }} has started preparing your order {{ order_number }}.",
                'short' => '{{ shop_name }} has started preparing order {{ order_number }}.',
            ],
            'order.ready_for_pickup.customer' => [
                'subject' => 'Ready for pickup — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\nYour order {{ order_number }} is ready to collect from {{ shop_name }}.",
                'short' => 'Order {{ order_number }} is ready to collect from {{ shop_name }}.',
            ],
            'order.packed.customer' => [
                'subject' => 'Order packed — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\n{{ shop_name }} has packed your order {{ order_number }}.",
                'short' => '{{ shop_name }} has packed order {{ order_number }}.',
            ],
            'order.ready_for_dispatch.customer' => [
                'subject' => 'Ready for dispatch — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\nYour order {{ order_number }} is prepared and waiting to be handed over for delivery.",
                'short' => 'Order {{ order_number }} is prepared and waiting to be handed over for delivery.',
            ],
            'order.shipped.customer' => [
                'subject' => 'Order shipped — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\nYour order {{ order_number }} has been handed over for delivery.",
                'short' => 'Order {{ order_number }} has been handed over for delivery.',
            ],
            'order.in_transit.customer' => [
                'subject' => 'Order in transit — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\nYour order {{ order_number }} is travelling through the delivery network.",
                'short' => 'Order {{ order_number }} is travelling through the delivery network.',
            ],
            'order.out_for_delivery.customer' => [
                'subject' => 'Out for delivery — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\nYour order {{ order_number }} is on its final delivery run.",
                'short' => 'Order {{ order_number }} is on its final delivery run.',
            ],
            'order.delivered.customer' => [
                'subject' => 'Order delivered — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\nYour order {{ order_number }} has been marked as delivered.",
                'short' => 'Order {{ order_number }} has been marked as delivered.',
            ],
            'order.completed.customer' => [
                'subject' => 'Order completed — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\nThe order lifecycle for {{ order_number }} has been completed.",
                'short' => 'The order lifecycle for {{ order_number }} has been completed.',
            ],
            'order.cancelled.customer' => [
                'subject' => 'Order cancelled — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\nYour order {{ order_number }} with {{ shop_name }} has been cancelled.",
                'short' => 'Order {{ order_number }} with {{ shop_name }} has been cancelled.',
            ],
            'payment.upi_submitted.customer' => [
                'subject' => 'Payment awaiting verification — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\nWe received the UPI payment reference for order {{ order_number }}. It is awaiting verification by {{ shop_name }}.\n\nWe will notify you when verification is complete.",
                'short' => 'We received the UPI reference for order {{ order_number }}. It is awaiting verification by {{ shop_name }}.',
            ],
            'payment.upi_submitted.merchant' => [
                'subject' => 'Payment verification required — {{ order_number }}',
                'email' => "Hello {{ merchant_name }},\n\nA customer submitted a UPI payment reference for order {{ order_number }}.\n\nPlease review and verify the payment reference.",
                'short' => 'A UPI reference was submitted for order {{ order_number }}. Please review and verify it.',
            ],
            'payment.upi_verified.customer' => [
                'subject' => 'Payment verified — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\n{{ shop_name }} has verified the UPI payment reference for order {{ order_number }}.",
                'short' => '{{ shop_name }} verified the UPI payment reference for order {{ order_number }}.',
            ],
            'payment.upi_rejected.customer' => [
                'subject' => 'Payment reference needs attention — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\n{{ shop_name }} could not verify the submitted UPI payment reference for order {{ order_number }}.\n\nPlease review the details and submit a corrected or new payment reference.",
                'short' => 'The UPI reference for order {{ order_number }} could not be verified. Please submit corrected details.',
            ],
            'refund.processed.customer' => [
                'subject' => 'Refund processed — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\n{{ shop_name }} has processed the refund for order {{ order_number }}.",
                'short' => '{{ shop_name }} has processed the refund for order {{ order_number }}.',
            ],
            'exchange.processed.customer' => [
                'subject' => 'Exchange processed — {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\n{{ shop_name }} has processed the exchange for order {{ order_number }}.",
                'short' => '{{ shop_name }} has processed the exchange for order {{ order_number }}.',
            ],
            'order.message.customer' => [
                'subject' => 'Message about order {{ order_number }}',
                'email' => "Hello {{ customer_name }},\n\n{{ shop_name }} sent you a message about order {{ order_number }}:\n\n{{ message }}",
                'short' => '{{ shop_name }} sent a message about order {{ order_number }}: {{ message }}',
            ],
        ];
    }
}
