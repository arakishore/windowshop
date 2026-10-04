@extends('storefront.layouts.app')

@section('title', 'Checkout | ' . $marketplaceName)
@section('meta_description', 'Complete checkout for selected local shop products on '.$marketplaceName.'.')

@php
    $addressFormDefaults = [
        'label' => 'Home',
        'recipient_name' => $customer->name ?? '',
        'recipient_mobile' => $customer->mobile ?? '',
        'state_id' => null,
        'city_id' => null,
        'state_name' => '',
        'city_name' => '',
        'address_line_1' => '',
        'address_line_2' => '',
        'landmark' => '',
        'postal_code' => '',
        'is_default_shipping' => false,
        'is_default_billing' => false,
    ];

    $addressLabel = function ($address): string {
        return collect([$address->city?->name, $address->state?->name])
            ->filter()
            ->implode(', ');
    };

    $billingSameForView = (bool) old('billing_same_as_delivery', $billingSameAsDelivery ? 1 : 0);
    $selectedBillingAddressIdForView = (int) old('billing_address_id', $selectedBillingAddressId);
    $deliveryOption = collect($shippingOptions)->firstWhere('id', 'delivery');
    $pickupOption = collect($shippingOptions)->firstWhere('id', 'pickup');
@endphp

@push('styles')
    <style>
        .checkout-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 32px;
            align-items: start;
        }

        .checkout-section {
            padding: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
        }

        .checkout-section + .checkout-section {
            margin-top: 20px;
        }

        .checkout-section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .checkout-section-heading {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .checkout-section-heading .icon {
            color: var(--primary);
            font-size: 21px;
        }

        .checkout-fulfillment-choices {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .checkout-fulfillment-card {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            min-height: 92px;
            padding: 18px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
        }

        .checkout-fulfillment-card:hover {
            border-color: #cbd5e1;
        }

        .checkout-fulfillment-card:focus-within {
            outline: 2px solid color-mix(in srgb, var(--primary) 35%, transparent);
            outline-offset: 2px;
        }

        .checkout-fulfillment-card.is-selected {
            border-color: var(--primary);
            background: color-mix(in srgb, var(--primary) 5%, #fff);
            box-shadow: 0 0 0 1px color-mix(in srgb, var(--primary) 18%, transparent);
        }

        .checkout-fulfillment-card__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #f8fafc;
            color: #111827;
            font-size: 22px;
        }

        .checkout-fulfillment-card.is-selected .checkout-fulfillment-card__icon {
            background: color-mix(in srgb, var(--primary) 12%, #fff);
            color: var(--primary);
        }

        .checkout-fulfillment-card input {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: var(--primary);
        }

        .checkout-fulfillment-card__description {
            display: block;
            margin-top: 4px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.45;
        }

        .checkout-pickup-card {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            gap: 16px;
            padding: 18px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fff;
        }

        .checkout-pickup-card__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 8px;
            background: #f8fafc;
            color: var(--primary);
            font-size: 24px;
        }

        .checkout-pickup-instructions {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 6px;
            background: #f8fafc;
            color: #475569;
            font-size: 14px;
            line-height: 1.5;
        }

        .checkout-address-list,
        .checkout-options {
            display: grid;
            gap: 12px;
        }

        .checkout-address-card,
        .checkout-option {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 12px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
        }

        .checkout-address-card.is-selected,
        .checkout-option.is-selected {
            border-color: #111;
        }

        .checkout-option.is-disabled {
            opacity: .62;
            background: #f8fafc;
        }

        .checkout-fulfillment-description {
            display: grid;
            gap: 3px;
            margin-top: 4px;
        }

        .checkout-fulfillment-line {
            display: block;
            line-height: 1.45;
        }

        .checkout-fulfillment-line--shop {
            color: #111827;
            font-weight: 600;
        }

        .checkout-fulfillment-line--address {
            color: #4b5563;
            font-size: 14px;
        }

        .checkout-fulfillment-line--instructions {
            color: #6b7280;
            font-size: 13px;
            font-style: italic;
        }

        .checkout-address-card input,
        .checkout-option input {
            margin-top: 5px;
        }

        .checkout-address-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 12px;
        }

        .checkout-address-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 16px;
        }

        .checkout-address-form .full {
            grid-column: 1 / -1;
        }

        .checkout-address-form input {
            width: 100%;
        }

        .checkout-summary {
            position: sticky;
            top: 24px;
        }

        .checkout-summary-items {
            display: grid;
            gap: 14px;
            margin-bottom: 18px;
        }

        .checkout-summary-item {
            display: grid;
            grid-template-columns: 64px 1fr;
            gap: 12px;
            align-items: start;
        }

        .checkout-summary-item img {
            width: 64px;
            height: 64px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #edf0f3;
        }

        .checkout-total-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f2f4;
        }

        .checkout-total-row.grand {
            border-bottom: 0;
            margin-top: 8px;
            font-size: 18px;
            font-weight: 700;
        }

        .checkout-muted-action {
            border: 0;
            background: transparent;
            padding: 0;
            text-decoration: underline;
        }

        .checkout-empty-panel {
            padding: 18px;
            border: 1px dashed #d8dee6;
            border-radius: 6px;
            color: #64748b;
            background: #f8fafc;
        }

        .checkout-upi-panel {
            margin-top: 16px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #f8fafc;
        }

        .checkout-upi-layout {
            display: grid;
            grid-template-columns: 160px minmax(0, 1fr);
            gap: 20px;
            align-items: center;
            margin: 16px 0 18px;
        }

        .checkout-upi-qr {
            display: block;
            width: 160px;
            height: 160px;
            padding: 6px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
            object-fit: contain;
        }

        .checkout-upi-id-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-bottom: 14px;
        }

        .checkout-upi-copy {
            min-width: 58px;
        }

        .checkout-upi-steps {
            margin: 0;
            padding-left: 20px;
            color: #4b5563;
        }

        .checkout-upi-steps li + li {
            margin-top: 5px;
        }

        .checkout-upi-pending-note,
        .checkout-upi-order-hint {
            color: #64748b;
            font-size: 13px;
            line-height: 1.45;
        }

        .checkout-processing-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(15, 23, 42, .58);
        }

        .checkout-processing-card {
            width: min(360px, 100%);
            padding: 28px 24px;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .24);
            color: #111827;
            text-align: center;
        }

        .checkout-processing-card .spinner-border {
            width: 2rem;
            height: 2rem;
        }

        @media (max-width: 991px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }

            .checkout-summary {
                position: static;
            }
        }

        @media (max-width: 575px) {
            .checkout-section {
                padding: 18px;
            }

            .checkout-address-form {
                grid-template-columns: 1fr;
            }

            .checkout-fulfillment-choices {
                grid-template-columns: 1fr;
            }

            .checkout-upi-panel {
                padding: 16px;
            }

            .checkout-upi-layout {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            .checkout-upi-qr {
                width: min(190px, 100%);
                height: auto;
                aspect-ratio: 1;
                margin: 0 auto;
            }
        }
    </style>
@endpush

@section('content')
    <section class="section-page-title text-center storefront-page-title">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="{{ route('storefront.home') }}" class="text-caption-01 cl-text-3 link">Home</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="{{ route('storefront.cart') }}" class="text-caption-01 cl-text-3 link">Shopping Cart</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Checkout</p>
                </div>
                <h3>Checkout</h3>
            </div>
        </div>
    </section>

    <section class="flat-spacing">
        <div class="container" data-checkout-content>
            <div data-checkout-messages>
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('info'))
                    <div class="alert alert-info">{{ session('info') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
            </div>

            <div class="checkout-grid">
                <div>
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            Please check the highlighted checkout details.
                        </div>
                    @endif

                    @if (! $orderingAvailable)
                        <div class="checkout-section">
                            <div class="checkout-empty-panel">
                                <strong class="d-block mb-2">Ordering is currently unavailable for this store.</strong>
                                This store currently has no pickup or delivery option available.
                            </div>
                        </div>
                    @endif

                    @if ($deliveryEnabled && $pickupEnabled)
                        <div class="checkout-section" data-fulfillment-url="{{ route('storefront.checkout.fulfillment') }}" data-fulfillment-selector>
                            <div class="checkout-section-title">
                                <h5 class="checkout-section-heading mb-0">
                                    <i class="icon icon-Truck" aria-hidden="true"></i>
                                    <span>How would you like to receive your order?</span>
                                </h5>
                            </div>
                            <div class="checkout-fulfillment-choices">
                                <label class="checkout-fulfillment-card {{ $selectedFulfillment === 'delivery' ? 'is-selected' : '' }}" data-fulfillment-option="delivery">
                                    <span class="checkout-fulfillment-card__icon"><i class="icon icon-Truck" aria-hidden="true"></i></span>
                                    <span>
                                        <strong class="d-block">Delivery</strong>
                                        <span class="checkout-fulfillment-card__description">Get your order delivered to your address</span>
                                    </span>
                                    <input type="radio" name="shipping_method_visual" value="delivery" data-fulfillment-radio @checked($selectedFulfillment === 'delivery')>
                                </label>
                                <label class="checkout-fulfillment-card {{ $selectedFulfillment === 'pickup' ? 'is-selected' : '' }}" data-fulfillment-option="pickup">
                                    <span class="checkout-fulfillment-card__icon"><i class="icon icon-storefront" aria-hidden="true"></i></span>
                                    <span>
                                        <strong class="d-block">Pick Up from Store</strong>
                                        <span class="checkout-fulfillment-card__description">Collect your order from the shop</span>
                                    </span>
                                    <input type="radio" name="shipping_method_visual" value="pickup" data-fulfillment-radio @checked($selectedFulfillment === 'pickup')>
                                </label>
                            </div>
                        </div>
                    @endif

                    @if ($deliveryEnabled)
                    <div data-delivery-checkout-section {{ $selectedFulfillment === 'delivery' ? '' : 'hidden' }}>
                    <div class="checkout-section">
                        <div class="checkout-section-title">
                            <h5 class="mb-0">Delivery Address</h5>
                            <button class="tf-btn animate-btn small" type="button" data-bs-toggle="collapse" data-bs-target="#checkout-add-address">
                                + Add New Address
                            </button>
                        </div>

                        @if ($addresses->isEmpty())
                            <div class="checkout-empty-panel">No saved delivery addresses yet.</div>
                        @else
                            <div class="checkout-address-list">
                                @foreach ($addresses as $address)
                                    @php($isSelected = (int) $selectedAddressId === (int) $address->getKey())
                                    <div class="checkout-address-card {{ $isSelected ? 'is-selected' : '' }}">
                                        <form method="POST" action="{{ route('storefront.checkout.addresses.select') }}">
                                            @csrf
                                            <input type="hidden" name="address_id" value="{{ $address->getKey() }}">
                                            <input type="radio" name="selected_address_visual" value="{{ $address->getKey() }}" {{ $isSelected ? 'checked' : '' }} onchange="this.form.submit()">
                                        </form>
                                        <div>
                                            <div class="d-flex justify-content-between gap-3">
                                                <h6 class="mb-4">{{ $address->label }}</h6>
                                                @if ($address->is_default_shipping)
                                                    <span class="text-caption-01 cl-text-3">Default</span>
                                                @endif
                                            </div>
                                            <p class="mb-4">{{ $address->recipient_name }}</p>
                                            <p class="cl-text-2 mb-4">
                                                {{ $address->address_line_1 }}
                                                @if ($address->address_line_2), {{ $address->address_line_2 }} @endif
                                                @if ($address->landmark), {{ $address->landmark }} @endif
                                            </p>
                                            <p class="cl-text-2 mb-4">
                                                {{ $addressLabel($address) ?: 'Location details pending' }}
                                                @if ($address->postal_code) - {{ $address->postal_code }} @endif
                                            </p>
                                            <p class="cl-text-2 mb-0">Phone: {{ $address->recipient_mobile }}</p>
                                            <div class="checkout-address-actions">
                                                @unless ($isSelected)
                                                    <form method="POST" action="{{ route('storefront.checkout.addresses.select') }}">
                                                        @csrf
                                                        <input type="hidden" name="address_id" value="{{ $address->getKey() }}">
                                                        <button type="submit" class="checkout-muted-action">Deliver here</button>
                                                    </form>
                                                @endunless
                                                <button class="checkout-muted-action" type="button" data-bs-toggle="collapse" data-bs-target="#checkout-edit-address-{{ $address->getKey() }}">Edit</button>
                                            </div>

                                            <div class="collapse" id="checkout-edit-address-{{ $address->getKey() }}">
                                                @include('storefront.pages.partials.checkout-address-form', [
                                                    'action' => route('storefront.checkout.addresses.update', $address),
                                                    'method' => 'PATCH',
                                                    'addressForm' => [
                                                        'label' => $address->label,
                                                        'recipient_name' => $address->recipient_name,
                                                        'recipient_mobile' => $address->recipient_mobile,
                                                        'state_id' => $address->state_id,
                                                        'city_id' => $address->city_id,
                                                        'state_name' => $address->state?->name,
                                                        'city_name' => $address->city?->name,
                                                        'address_line_1' => $address->address_line_1,
                                                        'address_line_2' => $address->address_line_2,
                                                        'landmark' => $address->landmark,
                                                        'postal_code' => $address->postal_code,
                                                        'is_default_shipping' => $address->is_default_shipping,
                                                    ],
                                                    'submitLabel' => 'Save Address',
                                                    'defaultCountry' => $defaultCountry,
                                                    'addressContext' => 'delivery',
                                                ])
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="collapse {{ $addresses->isEmpty() || ($errors->any() && old('address_context', 'delivery') === 'delivery') ? 'show' : '' }}" id="checkout-add-address">
                            @include('storefront.pages.partials.checkout-address-form', [
                                'action' => route('storefront.checkout.addresses.store'),
                                'method' => 'POST',
                                'addressForm' => $addressFormDefaults,
                                'submitLabel' => 'Add Address',
                                'defaultCountry' => $defaultCountry,
                                'addressContext' => 'delivery',
                            ])
                        </div>
                    </div>

                    <div class="checkout-section">
                        <div class="checkout-section-title">
                            <h5 class="mb-0">Billing Address</h5>
                        </div>

                        <form method="POST" action="{{ route('storefront.checkout.billing.same') }}" class="mb-0" data-billing-same-form>
                            @csrf
                            <input type="hidden" name="billing_same_as_delivery" value="0">
                            <div class="checkbox-wrap">
                                <input
                                    id="billing-same-as-delivery"
                                    type="checkbox"
                                    name="billing_same_as_delivery"
                                    value="1"
                                    class="tf-check style-2"
                                    data-billing-same-toggle
                                    {{ $billingSameForView ? 'checked' : '' }}
                                >
                                <label for="billing-same-as-delivery" class="fw-medium lh-24">Same as delivery address</label>
                            </div>
                        </form>

                        <div class="mt-18" data-billing-address-panel {{ $billingSameForView ? 'hidden' : '' }}>
                            <div class="checkout-section-title">
                                <p class="fw-medium mb-0">Saved Billing Addresses</p>
                                <button class="tf-btn animate-btn small" type="button" data-bs-toggle="collapse" data-bs-target="#checkout-add-billing-address">
                                    + Add New Billing Address
                                </button>
                            </div>

                            @if ($addresses->isEmpty())
                                <div class="checkout-empty-panel">No saved billing addresses yet.</div>
                            @else
                                <div class="checkout-address-list">
                                    @foreach ($addresses as $address)
                                        @php($isBillingSelected = (int) $selectedBillingAddressIdForView === (int) $address->getKey())
                                        <div class="checkout-address-card {{ $isBillingSelected ? 'is-selected' : '' }}">
                                            <form method="POST" action="{{ route('storefront.checkout.billing-addresses.select') }}">
                                                @csrf
                                                <input type="hidden" name="billing_address_id" value="{{ $address->getKey() }}">
                                                <input type="radio" name="selected_billing_address_visual" value="{{ $address->getKey() }}" {{ $isBillingSelected ? 'checked' : '' }} onchange="this.form.submit()">
                                            </form>
                                            <div>
                                                <div class="d-flex justify-content-between gap-3">
                                                    <h6 class="mb-4">{{ $address->label }}</h6>
                                                    @if ($address->is_default_billing)
                                                        <span class="text-caption-01 cl-text-3">Default Billing</span>
                                                    @endif
                                                </div>
                                                <p class="mb-4">{{ $address->recipient_name }}</p>
                                                <p class="cl-text-2 mb-4">
                                                    {{ $address->address_line_1 }}
                                                    @if ($address->address_line_2), {{ $address->address_line_2 }} @endif
                                                    @if ($address->landmark), {{ $address->landmark }} @endif
                                                </p>
                                                <p class="cl-text-2 mb-4">
                                                    {{ $addressLabel($address) ?: 'Location details pending' }}
                                                    @if ($address->postal_code) - {{ $address->postal_code }} @endif
                                                </p>
                                                <p class="cl-text-2 mb-0">Phone: {{ $address->recipient_mobile }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="collapse {{ ($errors->any() && old('address_context') === 'billing') || (! $billingSameForView && ! $selectedBillingAddress) ? 'show' : '' }}" id="checkout-add-billing-address">
                                @include('storefront.pages.partials.checkout-address-form', [
                                    'action' => route('storefront.checkout.billing-addresses.store'),
                                    'method' => 'POST',
                                    'addressForm' => [
                                        ...$addressFormDefaults,
                                        'is_default_shipping' => false,
                                        'is_default_billing' => false,
                                    ],
                                    'submitLabel' => 'Add Billing Address',
                                    'defaultCountry' => $defaultCountry,
                                    'defaultCheckboxName' => 'is_default_billing',
                                    'defaultCheckboxLabel' => 'Use as default billing address',
                                    'addressContext' => 'billing',
                                ])
                            </div>
                        </div>
                    </div>
                    </div>
                    @endif

                    @if ($deliveryEnabled)
                        <div class="checkout-section" data-delivery-checkout-section {{ $selectedFulfillment === 'delivery' ? '' : 'hidden' }}>
                            <div class="checkout-section-title">
                                <h5 class="checkout-section-heading mb-0">
                                    <i class="icon icon-Truck" aria-hidden="true"></i>
                                    <span>Delivery Options</span>
                                </h5>
                                @if ($selectedPostalCode)
                                    <span class="text-caption-01 cl-text-3">PIN {{ $selectedPostalCode }}</span>
                                @endif
                            </div>
                            @if ($deliveryOption)
                                <div class="checkout-options">
                                    <div class="checkout-option {{ $deliveryOption['available'] ? 'is-selected' : 'is-disabled' }}" data-delivery-option>
                                        <span aria-hidden="true"></span>
                                        <span>
                                            <span class="d-flex justify-content-between gap-3">
                                                <strong>{{ $deliveryOption['label'] }}</strong>
                                                <strong data-fulfillment-amount>{{ $deliveryOption['amount'] }}</strong>
                                            </span>
                                            <span class="d-block cl-text-2" data-fulfillment-estimate>{{ $deliveryOption['estimate'] }}</span>
                                            <span class="{{ $deliveryOption['available'] || ! $deliveryOption['reason'] ? 'd-none' : 'd-block' }} text-danger text-caption-01" data-fulfillment-reason>{{ $deliveryOption['reason'] }}</span>
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($pickupEnabled && $pickupOption)
                        <div class="checkout-section" data-pickup-information {{ $selectedFulfillment === 'pickup' ? '' : 'hidden' }}>
                            <div class="checkout-section-title">
                                <h5 class="checkout-section-heading mb-0">
                                    <i class="icon icon-storefront" aria-hidden="true"></i>
                                    <span>Pickup Information</span>
                                </h5>
                            </div>
                            <div class="checkout-pickup-card">
                                <span class="checkout-pickup-card__icon"><i class="icon icon-storefront" aria-hidden="true"></i></span>
                                <div>
                                    <strong class="d-block mb-4" data-pickup-shop-name>{{ $pickupOption['shop_name'] }}</strong>
                                    <p class="cl-text-2 mb-0" data-pickup-shop-address>{{ $pickupOption['shop_address'] }}</p>
                                    <p class="text-caption-01 cl-text-3 mt-8 mb-0">This is where you will collect your order.</p>
                                    @if ($pickupOption['instructions'])
                                        <div class="checkout-pickup-instructions" data-pickup-instructions>{{ $pickupOption['instructions'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="checkout-section">
                        <div class="checkout-section-title">
                            <h5 class="mb-0">Payment Method</h5>
                        </div>
                        <div class="checkout-options" data-payment-options>
                            @forelse ($paymentMethods as $method)
                                <label
                                    class="checkout-option {{ $method['selected'] ? 'is-selected' : '' }} {{ $method['available'] ? '' : 'is-disabled' }}"
                                    data-payment-option="{{ $method['id'] }}"
                                >
                                    <input
                                        type="radio"
                                        name="payment_method_visual"
                                        value="{{ $method['id'] }}"
                                        data-payment-radio
                                        @checked($method['selected'])
                                        @disabled(! $method['available'])
                                    >
                                    <span>
                                        <span class="d-flex justify-content-between gap-3">
                                            <strong>{{ $method['label'] }}</strong>
                                            @unless ($method['available'])
                                                <strong class="text-danger" data-payment-status>Unavailable</strong>
                                            @endunless
                                        </span>
                                        <span class="d-block cl-text-2" data-payment-description>{{ $method['description'] }}</span>
                                        @if (! $method['available'] && $method['reason'])
                                            <span class="d-block text-danger text-caption-01" data-payment-reason>{{ $method['reason'] }}</span>
                                        @else
                                            <span class="d-none text-danger text-caption-01" data-payment-reason></span>
                                        @endif
                                    </span>
                                </label>
                            @empty
                                <div class="checkout-empty-panel" data-payment-empty>{{ $paymentUnavailableMessage }}</div>
                            @endforelse
                        </div>
                        @php($selectedPayment = collect($paymentMethods)->firstWhere('id', $selectedPaymentMethod))
                        @php($selectedUpiDetails = $selectedPaymentMethod === \App\Services\Checkout\StorefrontPaymentMethodService::PAYMENT_MERCHANT_UPI ? ($selectedPayment['details'] ?? null) : null)
                        <div class="checkout-upi-panel {{ $selectedUpiDetails ? '' : 'd-none' }}" data-upi-payment-panel>
                            <p class="mb-1">Pay <strong data-upi-amount>{{ $selectedUpiDetails['amount'] ?? '' }}</strong> directly to</p>
                            <h6 class="mb-0" data-upi-payee>{{ $selectedUpiDetails['payee_name'] ?? '' }}</h6>
                            <div class="checkout-upi-layout">
                                <img src="{{ $selectedUpiDetails['qr_url'] ?? '' }}" alt="Merchant UPI QR code" class="checkout-upi-qr" data-upi-qr data-upi-uri="{{ $selectedUpiDetails['upi_uri'] ?? '' }}">
                                <div>
                                    <div class="text-muted text-caption-01 mb-1">UPI ID</div>
                                    <div class="checkout-upi-id-row">
                                        <span class="fw-semibold text-break" data-upi-id>{{ $selectedUpiDetails['upi_id'] ?? '' }}</span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary checkout-upi-copy" data-upi-copy aria-label="Copy merchant UPI ID">Copy</button>
                                        <span class="text-success text-caption-01 d-none" data-upi-copy-feedback role="status" aria-live="polite">Copied</span>
                                    </div>
                                    <ol class="checkout-upi-steps">
                                        <li>Scan the QR and pay the exact amount.</li>
                                        <li>Enter the UPI transaction/reference ID below.</li>
                                        <li>Place your order.</li>
                                    </ol>
                                </div>
                            </div>
                            <label for="upi_reference" class="form-label fw-semibold">UPI Transaction / Reference ID</label>
                            <input
                                id="upi_reference"
                                name="upi_reference"
                                form="checkout-place-order-form"
                                type="text"
                                maxlength="100"
                                value="{{ old('upi_reference') }}"
                                class="form-control @error('upi_reference') is-invalid @enderror"
                                data-upi-reference
                                @required($selectedUpiDetails)
                                @disabled(! $selectedUpiDetails)
                            >
                            @error('upi_reference')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="checkout-upi-pending-note mt-2">Your order will remain pending until the shop verifies your payment.</div>
                        </div>
                    </div>
                </div>

                <aside class="checkout-section checkout-summary">
                    <h5 class="mb-18">Order Summary</h5>
                    <div class="checkout-summary-items">
                        @foreach ($cartData['shop_groups'] as $group)
                            @foreach ($group['items'] as $item)
                                <div class="checkout-summary-item">
                                    <img src="{{ $item['image'] }}" alt="{{ $item['product_name'] }}">
                                     <div>
                                         <p class="mb-4">{{ $item['product_name'] }}</p>
                                         @if (! empty($item['is_generated_gift']))
                                             <div class="badge bg-success-subtle text-success border border-success-subtle mb-4">Free Gift</div>
                                         @endif
                                         @if ($item['attributes'])
                                             <p class="text-caption-01 cl-text-3 mb-4">
                                                @foreach ($item['attributes'] as $attribute)
                                                    {{ $attribute['label'] }}: {{ $attribute['value'] }}{{ ! $loop->last ? ', ' : '' }}
                                                @endforeach
                                            </p>
                                        @endif
                                         <div class="d-flex justify-content-between gap-3">
                                             <span class="cl-text-2">Qty {{ $item['quantity'] }}</span>
                                             <strong>{{ $item['line_subtotal'] }}</strong>
                                         </div>
                                         @if (($item['promotion_discount_cents'] ?? 0) > 0)
                                             <p class="text-caption-01 text-success mb-0">{{ $item['promotion_discount'] }} offer</p>
                                         @endif
                                         @unless ($item['is_available'])
                                            <p class="text-danger text-caption-01 mb-0">{{ $item['availability_message'] }}</p>
                                        @endunless
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    </div>

                    <div class="checkout-total-row">
                        <span>Subtotal</span>
                        <strong>{{ $cartData['subtotal'] }}</strong>
                    </div>
                    <div class="checkout-total-row">
                        <span>Discount</span>
                        <strong>{{ $cartData['discount'] ?? 'None' }}</strong>
                    </div>
                    @php($checkoutGroup = collect($cartData['shop_groups'] ?? [])->first())
                    @php($checkoutCoupon = is_array($checkoutGroup) ? ($checkoutGroup['coupon'] ?? null) : null)
                    @if (! empty($checkoutCoupon['code']))
                        <div class="checkout-total-row">
                            <span>Coupon {{ $checkoutCoupon['code'] }}</span>
                            <strong>{{ $checkoutCoupon['message'] ?? 'Applied' }}</strong>
                        </div>
                    @endif
                    <div class="checkout-total-row">
                        <span>Shipping</span>
                        <strong data-checkout-shipping-total>{{ $cartData['shipping'] ?? 'Calculated later' }}</strong>
                    </div>
                    <div class="checkout-total-row">
                        <span>Tax</span>
                        <strong>{{ $cartData['tax'] ?? 'Calculated later' }}</strong>
                    </div>
                    <div class="checkout-total-row grand">
                        <span>Grand Total</span>
                        <strong data-checkout-grand-total>{{ $cartData['total'] }}</strong>
                    </div>

                    <form id="checkout-place-order-form" method="POST" action="{{ route('storefront.checkout.place-order') }}" class="mt-20" data-place-order-form>
                        @csrf
                        <input type="hidden" name="address_id" value="{{ $selectedAddressId }}">
                        <input type="hidden" name="billing_same_as_delivery" value="{{ $billingSameForView ? 1 : 0 }}" data-billing-same-order-field>
                        <input type="hidden" name="billing_address_id" value="{{ $selectedBillingAddressIdForView }}">
                        <input type="hidden" name="shipping_method" value="{{ $selectedFulfillment }}" data-selected-fulfillment-field>
                        <input type="hidden" name="payment_method" value="{{ $selectedPaymentMethod }}" data-selected-payment-field>
                        <p class="checkout-upi-order-hint {{ $selectedUpiDetails ? '' : 'd-none' }} mb-3" data-upi-order-hint>Pay by UPI and enter the transaction ID before placing your order.</p>
                        <div class="mb-3">
                            <label for="customer_order_note" class="form-label">Order Note <span class="text-muted">(Optional)</span></label>
                            <textarea id="customer_order_note" name="customer_order_note" rows="3" maxlength="1000" class="form-control @error('customer_order_note') is-invalid @enderror" placeholder="Add delivery or order instructions">{{ old('customer_order_note') }}</textarea>
                            @error('customer_order_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button
                            type="submit"
                            class="tf-btn animate-btn w-100"
                            data-place-order-button {{ $canPlaceOrder ? '' : 'disabled' }}
                            data-checkout-has-address="{{ $selectedAddress ? '1' : '0' }}"
                            data-checkout-has-postal-code="{{ $selectedPostalCode !== null ? '1' : '0' }}"
                            data-checkout-has-billing-address="{{ $selectedBillingAddress ? '1' : '0' }}"
                            data-checkout-cart-has-items="{{ ! ($cartData['is_empty'] ?? true) ? '1' : '0' }}"
                            data-checkout-ordering-available="{{ $orderingAvailable ? '1' : '0' }}"
                        >
                            <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-place-order-spinner></span>
                            <span data-place-order-label>Place Order</span>
                        </button>
                    </form>
                </aside>
            </div>
        </div>
    </section>

    <div class="checkout-processing-overlay d-none" role="status" aria-live="polite" aria-busy="true" data-place-order-processing>
        <div class="checkout-processing-card">
            <span class="spinner-border mb-3" aria-hidden="true"></span>
            <strong class="d-block fs-5 mb-2">Processing your order…</strong>
            <span class="d-block">Please wait while we confirm your order.</span>
            <span class="d-block mt-2 text-muted">Do not refresh or close this page.</span>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-billing-same-form]');
            const toggle = document.querySelector('[data-billing-same-toggle]');
            const panel = document.querySelector('[data-billing-address-panel]');
            const orderField = document.querySelector('[data-billing-same-order-field]');
            const billingAddressCollapse = document.getElementById('checkout-add-billing-address');
            const fulfillmentSection = document.querySelector('[data-fulfillment-url]');
            const deliveryCheckoutSections = document.querySelectorAll('[data-delivery-checkout-section]');
            const pickupInformation = document.querySelector('[data-pickup-information]');
            const deliveryOptionNode = document.querySelector('[data-delivery-option]');
            const selectedFulfillmentField = document.querySelector('[data-selected-fulfillment-field]');
            const shippingTotal = document.querySelector('[data-checkout-shipping-total]');
            const grandTotal = document.querySelector('[data-checkout-grand-total]');
            const placeOrderButton = document.querySelector('[data-place-order-button]');
            const placeOrderForm = document.querySelector('[data-place-order-form]');
            const placeOrderLabel = document.querySelector('[data-place-order-label]');
            const placeOrderSpinner = document.querySelector('[data-place-order-spinner]');
            const placeOrderProcessing = document.querySelector('[data-place-order-processing]');
            const paymentOptions = document.querySelector('[data-payment-options]');
            const selectedPaymentField = document.querySelector('[data-selected-payment-field]');
            const upiPanel = document.querySelector('[data-upi-payment-panel]');
            const upiReference = document.querySelector('[data-upi-reference]');
            const upiOrderHint = document.querySelector('[data-upi-order-hint]');
            const upiCopyButton = document.querySelector('[data-upi-copy]');
            const upiCopyFeedback = document.querySelector('[data-upi-copy-feedback]');
            let currentPaymentMethods = @json($paymentMethods);
            let orderSubmissionProcessing = false;

            const csrfToken = () => {
                const metaToken = document.querySelector('meta[name="csrf-token"]');

                return metaToken ? metaToken.getAttribute('content') : '';
            };

            const applyBillingSameState = () => {
                if (!toggle || !panel || !orderField) {
                    return true;
                }

                const sameAsDelivery = toggle.checked;

                panel.hidden = sameAsDelivery;
                orderField.value = sameAsDelivery ? '1' : '0';

                if (!sameAsDelivery && billingAddressCollapse && window.bootstrap?.Collapse) {
                    window.bootstrap.Collapse.getOrCreateInstance(billingAddressCollapse, { toggle: false }).show();
                }

                return sameAsDelivery;
            };

            const syncBillingSameState = async (sameAsDelivery) => {
                if (!form || !window.fetch) {
                    return;
                }

                const body = new URLSearchParams();
                body.set('billing_same_as_delivery', sameAsDelivery ? '1' : '0');

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        body,
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error('Billing preference sync failed.');
                    }
                } catch (error) {
                    console.warn(error.message);
                }
            };

            form?.addEventListener('submit', (event) => {
                event.preventDefault();
                syncBillingSameState(applyBillingSameState());
            });

            toggle?.addEventListener('change', () => {
                syncBillingSameState(applyBillingSameState());
            });

            const optionNode = (id) => Array.from(document.querySelectorAll('[data-fulfillment-option]'))
                .find((node) => node.dataset.fulfillmentOption === id);
            const renderFulfillmentOptions = (options, selected) => {
                if (!Array.isArray(options)) {
                    return;
                }

                options.forEach((option) => {
                    const node = optionNode(option.id);
                    if (!node) {
                        return;
                    }

                    const radio = node.querySelector('[data-fulfillment-radio]');
                    const amount = node.querySelector('[data-fulfillment-amount]');
                    const description = node.querySelector('[data-fulfillment-description]');
                    const estimate = node.querySelector('[data-fulfillment-estimate]');
                    const reason = node.querySelector('[data-fulfillment-reason]');
                    const isSelected = option.id === selected;
                    const isAvailable = Boolean(option.available);

                    node.classList.toggle('is-selected', isSelected);

                    if (radio) {
                        radio.checked = isSelected;
                        radio.disabled = false;
                    }

                    if (amount) {
                        amount.textContent = option.amount || '';
                    }

                    if (description) {
                        description.innerHTML = '';
                        (option.description_lines || [option.description || '']).filter(Boolean).forEach((line) => {
                            const lineText = typeof line === 'object' ? (line.text || '') : line;
                            const lineType = typeof line === 'object' ? (line.type || 'summary') : 'summary';

                            if (!lineText) {
                                return;
                            }

                            const lineNode = document.createElement('span');
                            lineNode.className = `checkout-fulfillment-line checkout-fulfillment-line--${lineType}`;
                            lineNode.textContent = lineText;
                            description.appendChild(lineNode);
                        });
                    }

                    if (estimate) {
                        estimate.textContent = option.estimate || '';
                        estimate.classList.toggle('d-none', !option.estimate);
                    }

                    if (reason) {
                        reason.textContent = option.reason || '';
                        reason.classList.toggle('d-none', isAvailable || !option.reason);
                    }
                });
            };

            const renderDeliveryOption = (options) => {
                if (!deliveryOptionNode || !Array.isArray(options)) {
                    return;
                }

                const option = options.find((item) => item.id === 'delivery');
                if (!option) {
                    return;
                }

                const amount = deliveryOptionNode.querySelector('[data-fulfillment-amount]');
                const estimate = deliveryOptionNode.querySelector('[data-fulfillment-estimate]');
                const reason = deliveryOptionNode.querySelector('[data-fulfillment-reason]');
                deliveryOptionNode.classList.toggle('is-selected', Boolean(option.available));
                deliveryOptionNode.classList.toggle('is-disabled', !option.available);

                if (amount) {
                    amount.textContent = option.amount || '';
                }
                if (estimate) {
                    estimate.textContent = option.estimate || '';
                    estimate.classList.toggle('d-none', !option.estimate);
                }
                if (reason) {
                    reason.textContent = option.reason || '';
                    reason.classList.toggle('d-none', Boolean(option.available) || !option.reason);
                    reason.classList.toggle('d-block', !option.available && Boolean(option.reason));
                }
            };

            const paymentOptionTemplate = (method) => {
                const label = document.createElement('label');
                label.className = `checkout-option ${method.selected ? 'is-selected' : ''} ${method.available ? '' : 'is-disabled'}`;
                label.dataset.paymentOption = method.id;

                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'payment_method_visual';
                radio.value = method.id;
                radio.dataset.paymentRadio = '';
                radio.checked = Boolean(method.selected);
                radio.disabled = !method.available;
                radio.addEventListener('change', () => {
                    if (radio.checked && !radio.disabled) {
                        selectPaymentMethod(method);
                    }
                });

                const content = document.createElement('span');
                const header = document.createElement('span');
                header.className = 'd-flex justify-content-between gap-3';
                const title = document.createElement('strong');
                title.textContent = method.label || '';
                header.appendChild(title);

                if (!method.available) {
                    const status = document.createElement('strong');
                    status.className = 'text-danger';
                    status.dataset.paymentStatus = '';
                    status.textContent = 'Unavailable';
                    header.appendChild(status);
                }

                const description = document.createElement('span');
                description.className = 'd-block cl-text-2';
                description.dataset.paymentDescription = '';
                description.textContent = method.description || '';
                const reason = document.createElement('span');
                reason.className = `text-danger text-caption-01 ${method.available || !method.reason ? 'd-none' : 'd-block'}`;
                reason.dataset.paymentReason = '';
                reason.textContent = method.reason || '';

                content.append(header, description, reason);
                label.append(radio, content);

                return label;
            };

            const renderUpiDetails = (method) => {
                if (!upiPanel || !upiReference) {
                    return;
                }

                const details = method && method.id === 'merchant_upi' ? method.details : null;
                upiPanel.classList.toggle('d-none', !details);
                upiOrderHint?.classList.toggle('d-none', !details);
                upiReference.required = Boolean(details);
                upiReference.disabled = !details;
                upiCopyFeedback?.classList.add('d-none');
                if (upiCopyButton) {
                    upiCopyButton.textContent = 'Copy';
                }

                if (!details) {
                    return;
                }

                upiPanel.querySelector('[data-upi-amount]').textContent = details.amount || '';
                upiPanel.querySelector('[data-upi-payee]').textContent = details.payee_name || '';
                upiPanel.querySelector('[data-upi-id]').textContent = details.upi_id || '';
                const qr = upiPanel.querySelector('[data-upi-qr]');
                qr.src = details.qr_url || '';
                qr.dataset.upiUri = details.upi_uri || '';
            };

            const updatePlaceOrderState = (method) => {
                if (!placeOrderButton) {
                    return;
                }

                if (orderSubmissionProcessing) {
                    placeOrderButton.disabled = true;
                    return;
                }

                const fulfillment = selectedFulfillmentField?.value || '';
                const fulfillmentReady = fulfillment !== ''
                    && (fulfillment !== 'delivery'
                        || (placeOrderButton.dataset.checkoutHasAddress === '1'
                            && placeOrderButton.dataset.checkoutHasPostalCode === '1'));
                const checkoutReady = fulfillmentReady
                    && (fulfillment !== 'delivery'
                        || placeOrderButton.dataset.checkoutHasBillingAddress === '1')
                    && placeOrderButton.dataset.checkoutOrderingAvailable === '1'
                    && placeOrderButton.dataset.checkoutCartHasItems === '1';

                placeOrderButton.disabled = !checkoutReady || !method || !method.available;
            };

            const selectPaymentMethod = (method) => {
                if (selectedPaymentField) {
                    selectedPaymentField.value = method?.id || '';
                }

                document.querySelectorAll('[data-payment-option]').forEach((option) => {
                    option.classList.toggle('is-selected', option.dataset.paymentOption === method?.id);
                });
                renderUpiDetails(method);
                updatePlaceOrderState(method);
            };

            const fallbackCopy = (value) => {
                const input = document.createElement('textarea');
                input.value = value;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();

                try {
                    return document.execCommand('copy');
                } finally {
                    input.remove();
                }
            };

            upiCopyButton?.addEventListener('click', async () => {
                const value = upiPanel?.querySelector('[data-upi-id]')?.textContent?.trim() || '';
                if (!value) {
                    return;
                }

                let copied = false;
                try {
                    if (navigator.clipboard?.writeText) {
                        await navigator.clipboard.writeText(value);
                        copied = true;
                    } else {
                        copied = fallbackCopy(value);
                    }
                } catch (error) {
                    copied = fallbackCopy(value);
                }

                if (copied) {
                    upiCopyButton.textContent = 'Copied';
                    upiCopyFeedback?.classList.remove('d-none');
                    window.setTimeout(() => {
                        upiCopyButton.textContent = 'Copy';
                        upiCopyFeedback?.classList.add('d-none');
                    }, 1800);
                }
            });

            const renderPaymentMethods = (methods, selected, message) => {
                if (!paymentOptions || !Array.isArray(methods)) {
                    return;
                }

                paymentOptions.innerHTML = '';

                if (methods.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'checkout-empty-panel';
                    empty.dataset.paymentEmpty = '';
                    empty.textContent = message || 'No payment method is currently available.';
                    paymentOptions.appendChild(empty);
                } else {
                    methods.forEach((method) => {
                        method.selected = method.id === selected;
                        paymentOptions.appendChild(paymentOptionTemplate(method));
                    });
                }

                if (selectedPaymentField) {
                    selectedPaymentField.value = selected || '';
                }
                currentPaymentMethods = methods;
                selectPaymentMethod(methods.find((method) => method.id === selected));
            };

            const syncFulfillment = async (fulfillment) => {
                if (!fulfillmentSection || !window.fetch) {
                    return;
                }

                const body = new URLSearchParams();
                body.set('fulfillment', fulfillment);

                try {
                    const response = await fetch(fulfillmentSection.dataset.fulfillmentUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        body,
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error('Fulfillment preference sync failed.');
                    }

                    const payload = await response.json();
                    const selected = payload.selected_fulfillment || fulfillment;

                    renderFulfillmentOptions(payload.shipping_options, selected);
                    renderDeliveryOption(payload.shipping_options);
                    renderPaymentMethods(
                        payload.payment_methods || [],
                        payload.selected_payment_method || '',
                        payload.payment_unavailable_message || '',
                    );

                    if (selectedFulfillmentField) {
                        selectedFulfillmentField.value = selected;
                    }

                    deliveryCheckoutSections.forEach((section) => {
                        section.hidden = selected !== 'delivery';
                    });
                    if (pickupInformation) {
                        pickupInformation.hidden = selected !== 'pickup';
                    }

                    if (shippingTotal && payload.shipping) {
                        shippingTotal.textContent = payload.shipping;
                    }

                    if (grandTotal && payload.total) {
                        grandTotal.textContent = payload.total;
                    }

                    if (placeOrderButton) {
                        placeOrderButton.disabled = orderSubmissionProcessing || !payload.can_place_order;
                    }
                } catch (error) {
                    console.warn(error.message);
                }
            };

            document.querySelectorAll('[data-fulfillment-radio]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    if (radio.checked) {
                        syncFulfillment(radio.value);
                    }
                });
            });

            document.querySelectorAll('[data-payment-radio]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    if (radio.checked && !radio.disabled) {
                        selectPaymentMethod(currentPaymentMethods.find((method) => method.id === radio.value));
                    }
                });
            });

            if (placeOrderForm && placeOrderButton) {
                const resetOrderSubmission = () => {
                    orderSubmissionProcessing = false;
                    placeOrderSpinner?.classList.add('d-none');
                    placeOrderProcessing?.classList.add('d-none');
                    if (placeOrderLabel) {
                        placeOrderLabel.textContent = 'Place Order';
                    }
                    const selectedMethod = currentPaymentMethods.find((method) => method.id === selectedPaymentField?.value);
                    updatePlaceOrderState(selectedMethod);
                };

                placeOrderForm.addEventListener('submit', (event) => {
                    if (orderSubmissionProcessing) {
                        event.preventDefault();
                        return;
                    }

                    if (placeOrderButton.disabled) {
                        return;
                    }

                    orderSubmissionProcessing = true;
                    placeOrderButton.disabled = true;
                    placeOrderSpinner?.classList.remove('d-none');
                    placeOrderProcessing?.classList.remove('d-none');
                    if (placeOrderLabel) {
                        placeOrderLabel.textContent = 'Processing...';
                    }
                });

                placeOrderForm.addEventListener('invalid', resetOrderSubmission, true);
                window.addEventListener('pageshow', (event) => {
                    if (event.persisted) {
                        resetOrderSubmission();
                    }
                });
            }

            applyBillingSameState();
        });
    </script>
@endpush
