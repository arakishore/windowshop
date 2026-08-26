@extends('storefront.layouts.app')

@section('title', 'Addresses | WindowShop')
@section('meta_description', 'Review your saved WindowShop delivery and billing addresses.')

@php
    $locationLine = function ($address): string {
        return collect([$address->city?->name, $address->state?->name, $address->country?->name])
            ->filter()
            ->implode(', ');
    };
@endphp

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer])
        <div class="mb-24">
            <p class="text-caption-01 cl-text-3 mb-6">Addresses</p>
            <h4 class="mb-10">Saved Addresses</h4>
            <p class="cl-text-2 mb-0">These are the same saved addresses used during checkout.</p>
        </div>

        @if ($addresses->isEmpty())
            <div class="account-empty-panel">No saved addresses yet.</div>
        @else
            <div class="account-address-list">
                @foreach ($addresses as $address)
                    <article class="account-address-card">
                        <div class="d-flex justify-content-between gap-3 mb-10">
                            <div>
                                <p class="text-caption-01 cl-text-3 mb-4">Address Type / Label</p>
                                <h6 class="mb-0">{{ $address->label }}</h6>
                            </div>
                        </div>

                        <p class="mb-4"><strong>Recipient:</strong> {{ $address->recipient_name }}</p>
                        <p class="mb-4"><strong>Mobile:</strong> {{ $address->recipient_mobile }}</p>
                        <p class="cl-text-2 mb-4">
                            {{ $address->address_line_1 }}
                            @if ($address->address_line_2), {{ $address->address_line_2 }} @endif
                            @if ($address->landmark), {{ $address->landmark }} @endif
                        </p>
                        <p class="cl-text-2 mb-4">
                            {{ $locationLine($address) ?: 'Location details pending' }}
                        </p>
                        <p class="cl-text-2 mb-0"><strong>Postal Code:</strong> {{ $address->postal_code ?: '-' }}</p>

                        @if ($address->is_default_shipping || $address->is_default_billing)
                            <div class="account-badges">
                                @if ($address->is_default_shipping)
                                    <span class="account-badge">Default Delivery</span>
                                @endif
                                @if ($address->is_default_billing)
                                    <span class="account-badge">Default Billing</span>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    @endcomponent
@endsection
