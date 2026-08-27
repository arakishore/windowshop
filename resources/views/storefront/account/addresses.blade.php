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
    @component('storefront.account.partials.shell', ['customer' => $customer, 'accountPageTitle' => 'Addresses'])
        <div class="account-section-head mb-24">
            <div>
                <p class="text-caption-01 cl-text-3 mb-6">Addresses</p>
                <h4 class="mb-10">Saved Addresses</h4>
                <p class="cl-text-2 mb-0">These are the same saved addresses used during checkout.</p>
            </div>
            <a href="{{ route('storefront.account.addresses.create') }}" class="account-primary-button">
                <i class="icon icon-Plus"></i>
                <span>Add Address</span>
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success mb-24">{{ session('success') }}</div>
        @endif

        @if ($addresses->isEmpty())
            <div class="account-empty-panel">
                <h6 class="mb-6">No saved addresses yet.</h6>
                <p class="mb-14">Add your first delivery or billing address to make checkout faster.</p>
                <a href="{{ route('storefront.account.addresses.create') }}" class="account-link-button">Add Address</a>
            </div>
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
                        <p class="mb-4"><strong>Mobile:</strong> {{ $address->recipient_mobile_country_code }} {{ $address->recipient_mobile }}</p>
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

                        <div class="account-address-actions">
                            <div class="account-address-action-group">
                                @unless ($address->is_default_shipping)
                                    <form method="POST" action="{{ route('storefront.account.addresses.default-delivery', $address) }}">
                                        @csrf
                                        <button type="submit" class="account-action-button">Set as Delivery Default</button>
                                    </form>
                                @endunless

                                @unless ($address->is_default_billing)
                                    <form method="POST" action="{{ route('storefront.account.addresses.default-billing', $address) }}">
                                        @csrf
                                        <button type="submit" class="account-action-button">Set as Billing Default</button>
                                    </form>
                                @endunless
                            </div>

                            <div class="account-address-action-group">
                                <a href="{{ route('storefront.account.addresses.edit', $address) }}" class="account-action-button">Edit</a>

                                <form method="POST" action="{{ route('storefront.account.addresses.destroy', $address) }}"
                                    class="js-account-address-delete-form"
                                    data-confirm-message="Delete this saved address?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="account-action-button is-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endcomponent
@endsection

@push('scripts')
    <script>
        if (window.jQuery && window.bootstrap && !window.jQuery.fn.modal) {
            window.jQuery.fn.modal = function (command, relatedTarget) {
                return this.each(function () {
                    const options = typeof command === 'object' ? command : {};
                    const modal = window.bootstrap.Modal.getOrCreateInstance(this, options);

                    if (typeof command === 'string' && typeof modal[command] === 'function') {
                        modal[command](relatedTarget);
                        return;
                    }

                    if (options.show) {
                        modal.show(relatedTarget);
                    }
                });
            };
            window.jQuery.fn.modal.Constructor = {VERSION: window.bootstrap.Modal.VERSION || '5'};
        }
    </script>
    <script src="{{ asset('assets/admin/js/vendor/notifications/bootbox.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.js-account-address-delete-form').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (form.dataset.confirmed === '1') {
                        return;
                    }

                    event.preventDefault();

                    const submitForm = () => {
                        form.dataset.confirmed = '1';
                        form.submit();
                    };

                    if (typeof window.bootbox === 'undefined') {
                        if (window.confirm(form.dataset.confirmMessage || 'Delete this saved address?')) {
                            submitForm();
                        }

                        return;
                    }

                    window.bootbox.confirm({
                        title: 'Delete Address',
                        message: form.dataset.confirmMessage || 'Delete this saved address?',
                        className: 'account-address-confirm-modal',
                        centerVertical: true,
                        buttons: {
                            cancel: {
                                label: 'Cancel',
                                className: 'btn-light',
                            },
                            confirm: {
                                label: 'Delete',
                                className: 'btn-danger',
                            },
                        },
                        callback: (confirmed) => {
                            if (confirmed) {
                                submitForm();
                            }
                        },
                    });
                });
            });
        });
    </script>
@endpush
