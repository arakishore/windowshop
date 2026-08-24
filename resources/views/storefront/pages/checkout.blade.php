@extends('storefront.layouts.app')

@section('title', 'Checkout | WindowShop')
@section('meta_description', 'Complete checkout for selected local shop products on WindowShop.')

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
            border-radius: 6px;
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
            </div>

            <div class="checkout-grid">
                <div>
                    @if ($selectedAddress && $canPlaceOrder)
                        <div class="checkout-section">
                            <div class="checkout-section-title mb-0">
                                <div>
                                    <p class="text-caption-01 cl-text-3 mb-4">Fast Checkout</p>
                                    <h5 class="mb-4">Ready from your defaults</h5>
                                    <p class="cl-text-2 mb-0">
                                        Deliver to {{ $selectedAddress->label }}, PIN {{ $selectedPostalCode }} with Cash on Delivery.
                                    </p>
                                </div>
                                <button type="button" class="tf-btn animate-btn small" disabled>Place Order Now</button>
                            </div>
                        </div>
                    @endif

                    <div class="checkout-section">
                        <div class="checkout-section-title">
                            <h5 class="mb-0">Delivery Address</h5>
                            <button class="tf-btn animate-btn small" type="button" data-bs-toggle="collapse" data-bs-target="#checkout-add-address">
                                + Add New Address
                            </button>
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                Please check the highlighted checkout details.
                            </div>
                        @endif

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

                            <div class="collapse {{ $errors->any() && old('address_context') === 'billing' ? 'show' : '' }}" id="checkout-add-billing-address">
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

                    <div class="checkout-section">
                        <div class="checkout-section-title">
                            <h5 class="mb-0">Delivery Options</h5>
                            @if ($selectedPostalCode)
                                <span class="text-caption-01 cl-text-3">PIN {{ $selectedPostalCode }}</span>
                            @endif
                        </div>

                        @if ($shippingOptions === [])
                            <div class="checkout-empty-panel">Delivery options will be calculated after selecting an address.</div>
                        @else
                            <div class="checkout-options">
                                @foreach ($shippingOptions as $option)
                                    <label class="checkout-option {{ $option['selected'] ? 'is-selected' : '' }}">
                                        <input type="radio" name="shipping_method_visual" value="{{ $option['id'] }}" checked disabled>
                                        <span>
                                            <strong>{{ $option['label'] }}</strong>
                                            <span class="d-block cl-text-2">{{ $option['description'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="checkout-section">
                        <div class="checkout-section-title">
                            <h5 class="mb-0">Payment Method</h5>
                        </div>
                        <div class="checkout-options">
                            @foreach ($paymentMethods as $method)
                                <label class="checkout-option {{ $method['selected'] ? 'is-selected' : '' }}">
                                    <input type="radio" name="payment_method_visual" value="{{ $method['id'] }}" checked disabled>
                                    <span>
                                        <strong>{{ $method['label'] }}</strong>
                                        <span class="d-block cl-text-2">{{ $method['description'] }}</span>
                                    </span>
                                </label>
                            @endforeach
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
                    <div class="checkout-total-row">
                        <span>Shipping</span>
                        <strong>Calculated later</strong>
                    </div>
                    <div class="checkout-total-row">
                        <span>Tax</span>
                        <strong>{{ $cartData['tax'] ?? 'Calculated later' }}</strong>
                    </div>
                    <div class="checkout-total-row grand">
                        <span>Grand Total</span>
                        <strong>{{ $cartData['total'] }}</strong>
                    </div>

                    <form method="POST" action="{{ route('storefront.checkout.place-order') }}" class="mt-20">
                        @csrf
                        <input type="hidden" name="address_id" value="{{ $selectedAddressId }}">
                        <input type="hidden" name="billing_same_as_delivery" value="{{ $billingSameForView ? 1 : 0 }}" data-billing-same-order-field>
                        <input type="hidden" name="billing_address_id" value="{{ $selectedBillingAddressIdForView }}">
                        <input type="hidden" name="shipping_method" value="standard">
                        <input type="hidden" name="payment_method" value="cod">
                        <button type="submit" class="tf-btn animate-btn w-100" {{ $canPlaceOrder ? '' : 'disabled' }}>
                            Place Order
                        </button>
                    </form>
                    <p class="text-caption-01 cl-text-3 mt-12 mb-0">
                        Final order creation, shipping charge, tax resolution, and payment capture are intentionally deferred.
                    </p>
                </aside>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-billing-same-form]');
            const toggle = document.querySelector('[data-billing-same-toggle]');
            const panel = document.querySelector('[data-billing-address-panel]');
            const orderField = document.querySelector('[data-billing-same-order-field]');

            if (!form || !toggle || !panel || !orderField) {
                return;
            }

            const csrfToken = () => {
                const metaToken = document.querySelector('meta[name="csrf-token"]');

                return metaToken ? metaToken.getAttribute('content') : '';
            };

            const applyBillingSameState = () => {
                const sameAsDelivery = toggle.checked;

                panel.hidden = sameAsDelivery;
                orderField.value = sameAsDelivery ? '1' : '0';

                return sameAsDelivery;
            };

            const syncBillingSameState = async (sameAsDelivery) => {
                if (!window.fetch) {
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

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                syncBillingSameState(applyBillingSameState());
            });

            toggle.addEventListener('change', () => {
                syncBillingSameState(applyBillingSameState());
            });

            applyBillingSameState();
        });
    </script>
@endpush
