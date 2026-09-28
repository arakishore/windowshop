<?php

return [
    'standard' => [
        'privacy' => [
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'body' => <<<'HTML'
<h3>1. Information We Collect</h3>
<p>We may collect information you provide directly, such as name, email address, contact details, store enquiry details, and messages sent through forms. We may also collect basic device and usage information to improve the website.</p>
<h3>2. How We Use Information</h3>
<p>We use information to operate {{marketplace_name}}, respond to enquiries, improve discovery, support merchant pages, prevent misuse, and communicate relevant updates where permitted.</p>
<h3>3. Sharing Information</h3>
<p>We do not sell personal information. We may share limited information with merchants or service providers only when needed to support customer requests, platform operations, or legal requirements.</p>
<h3>4. Data Retention</h3>
<p>We keep information only as long as needed for business, support, legal, and security purposes. You may contact us for correction or deletion requests where applicable.</p>
<h3>5. Your Choices</h3>
<p>You can choose not to submit optional information, unsubscribe from non-essential messages, and contact support for privacy-related questions.</p>
HTML,
        ],
        'terms' => [
            'title' => 'Terms & Conditions',
            'slug' => 'terms-and-conditions',
            'body' => <<<'HTML'
<h3>1. Use Of {{marketplace_name}}</h3>
<p>{{marketplace_name}} provides a local storefront and discovery experience for customers and merchants. By using the website, you agree to use it for lawful browsing, discovery, communication, and shopping-related purposes.</p>
<h3>2. Store And Product Information</h3>
<p>Product details, prices, stock availability, images, offers, and store information may be updated from time to time. Customers should confirm important details with the merchant before completing a purchase.</p>
<h3>3. Merchant Responsibility</h3>
<p>Each merchant is responsible for the accuracy of their catalogue, store page, policies, service commitments, product quality, and fulfilment communication with customers.</p>
<h3>4. Accounts And Security</h3>
<p>Users are responsible for maintaining accurate account information and keeping login details secure. Please contact support if you believe your account has been misused.</p>
<h3>5. Updates To Terms</h3>
<p>We may update these terms as the platform grows. Continued use of {{marketplace_name}} after updates means you accept the latest version.</p>
HTML,
        ],
        'shipping' => [
            'title' => 'Shipping Policy',
            'slug' => 'shipping',
            'body' => <<<'HTML'
<h3>1. Shipping Methods</h3>
<p>Shipping or delivery options may differ by merchant, area, product type, and order value. Available options should be confirmed before checkout or directly with the shop.</p>
<h3>2. Processing Time</h3>
<p>Orders are usually processed during merchant working hours. Some products may need additional time for confirmation, packing, customization, or store-level availability checks.</p>
<h3>3. Shipping Costs</h3>
<p>Delivery charges may depend on distance, package size, selected fulfilment method, merchant policy, and any active offer. Charges should be shown or confirmed before purchase.</p>
<h3>4. Local Pickup</h3>
<p>Some merchants may offer store pickup. Customers should confirm pickup timing, order readiness, and required proof before visiting the store.</p>
<h3>5. Delivery Support</h3>
<p>For delivery questions, contact the merchant first. {{marketplace_name}} can help with platform-level support and routing where needed.</p>
HTML,
        ],
        'return_refund' => [
            'title' => 'Return & Refund Policy',
            'slug' => 'return-and-refund',
            'body' => <<<'HTML'
<h3>1. Returns</h3>
<p>Return eligibility may depend on the merchant, product type, item condition, and timing. Products should usually be unused, with original packaging and purchase proof.</p>
<h3>2. Return Process</h3>
<p>Contact the merchant or {{marketplace_name}} support with your order details and reason for return. The team will guide you on whether pickup, store return, or another process applies.</p>
<h3>3. Refunds</h3>
<p>Approved refunds are processed after the returned item is checked. Refund timelines may vary based on payment method, merchant approval, and bank processing time.</p>
<h3>4. Damaged Or Incorrect Items</h3>
<p>If an item is damaged, defective, or incorrect, contact support as soon as possible with photos and order details so the issue can be reviewed quickly.</p>
<h3>5. Non-Returnable Items</h3>
<p>Some items may not be returnable due to hygiene, personalization, perishability, final-sale terms, or merchant-specific restrictions.</p>
HTML,
        ],
    ],
];
