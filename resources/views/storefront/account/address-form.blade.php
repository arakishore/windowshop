@extends('storefront.layouts.app')

@section('title', $title.' | WindowShop')
@section('meta_description', 'Manage your saved WindowShop delivery and billing address.')

@php
    $currentLabel = old('label', $address?->label ?? 'Home');
    $addressType = old('address_type', in_array($currentLabel, ['Home', 'Work'], true) ? $currentLabel : 'Other');
    $otherLabel = old('address_label', $addressType === 'Other' ? $currentLabel : '');
@endphp

@section('content')
    @component('storefront.account.partials.shell', ['customer' => $customer, 'accountPageTitle' => $title])
        <div class="account-section-head mb-24">
            <div>
                <p class="text-caption-01 cl-text-3 mb-6">Addresses</p>
                <h4 class="mb-10">{{ $title }}</h4>
                <p class="cl-text-2 mb-0">Saved changes apply to future checkouts only.</p>
            </div>
            <a href="{{ route('storefront.account.addresses') }}" class="account-link-button">Back to Addresses</a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger mb-24">
                Please review the highlighted fields and try again.
            </div>
        @endif

        <form
            method="POST"
            action="{{ $action }}"
            class="account-address-form"
            data-storefront-address-form
            data-default-country-iso2="{{ strtoupper((string) ($defaultCountry?->iso2 ?? '')) }}"
            data-postal-code-url-template="{{ route('storefront.checkout.postal-code.show', ['postalCode' => '__PIN__']) }}"
            novalidate
        >
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <input type="hidden" name="label" value="{{ $currentLabel }}" data-address-label-value>
            <input type="hidden" name="recipient_mobile_country_code" value="+91">
            <input type="hidden" name="state_id" value="{{ $selectedStateId ?: '' }}" data-address-state-id>
            <input type="hidden" name="city_id" value="{{ $selectedCityId ?: '' }}" data-address-city-id>

            <div class="account-form-grid">
                <div class="account-form-field is-wide">
                    <label>Address Type <span class="text-primary">*</span></label>
                    <div class="account-radio-row" data-address-type-group>
                        @foreach (['Home', 'Work', 'Other'] as $type)
                            <label>
                                <input type="radio" name="address_type" value="{{ $type }}" @checked($addressType === $type)>
                                <span>{{ $type }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('label')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="account-form-field is-wide" data-address-other-label-wrap @if ($addressType !== 'Other') hidden @endif>
                    <label for="address_label">Address Label</label>
                    <input id="address_label" name="address_label" type="text" maxlength="80"
                        value="{{ $otherLabel }}" placeholder="Parents, Warehouse, Hostel">
                    @error('address_label')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="account-form-field">
                    <label for="recipient_name">Recipient Name <span class="text-primary">*</span></label>
                    <input id="recipient_name" name="recipient_name" type="text" maxlength="150"
                        value="{{ old('recipient_name', $address?->recipient_name ?? $customer->name) }}" data-address-required>
                    @error('recipient_name')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="account-form-field">
                    <label for="recipient_mobile">Mobile <span class="text-primary">*</span></label>
                    <input id="recipient_mobile" name="recipient_mobile" type="text" maxlength="30"
                        value="{{ old('recipient_mobile', $address?->recipient_mobile ?? $customer->mobile) }}" data-address-required>
                    @error('recipient_mobile')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="account-form-field is-wide">
                    <label for="address_line_1">Address Line 1 <span class="text-primary">*</span></label>
                    <input id="address_line_1" name="address_line_1" type="text" maxlength="190"
                        value="{{ old('address_line_1', $address?->address_line_1) }}" data-address-required>
                    @error('address_line_1')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="account-form-field is-wide">
                    <label for="address_line_2">Address Line 2</label>
                    <input id="address_line_2" name="address_line_2" type="text" maxlength="190"
                        value="{{ old('address_line_2', $address?->address_line_2) }}">
                    @error('address_line_2')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="account-form-field">
                    <label for="landmark">Landmark</label>
                    <input id="landmark" name="landmark" type="text" maxlength="150"
                        value="{{ old('landmark', $address?->landmark) }}">
                    @error('landmark')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="account-form-field">
                    <label for="postal_code">Postal Code <span class="text-primary">*</span></label>
                    <input id="postal_code" name="postal_code" type="text" maxlength="20" inputmode="numeric"
                        value="{{ old('postal_code', $address?->postal_code) }}" data-address-postal-code data-address-required>
                    <div class="account-field-help" data-address-pin-feedback></div>
                    @error('postal_code')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="account-form-field">
                    <label for="state_name">State <span class="text-primary">*</span></label>
                    <input id="state_name" name="state_name" type="text" value="{{ $selectedStateName }}"
                        placeholder="Auto-filled from PIN" readonly data-address-state-display data-address-required>
                    @error('state_id')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="account-form-field">
                    <label for="city_name">City <span class="text-primary">*</span></label>
                    <input id="city_name" name="city_name" type="text" maxlength="120"
                        value="{{ $selectedCityName }}" placeholder="Auto-filled from PIN" data-address-city-display data-address-required>
                    @error('city_id')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                    @error('city_name')
                        <div class="account-field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="account-form-checks">
                <label>
                    <input type="checkbox" name="is_default_shipping" value="1"
                        @checked(old('is_default_shipping', $address?->is_default_shipping ?? false))>
                    <span>Default Delivery</span>
                </label>
                <label>
                    <input type="checkbox" name="is_default_billing" value="1"
                        @checked(old('is_default_billing', $address?->is_default_billing ?? false))>
                    <span>Default Billing</span>
                </label>
            </div>

            <div class="account-address-actions">
                <button type="submit" class="account-primary-button">{{ $method === 'POST' ? 'Add Address' : 'Save Changes' }}</button>
                <a href="{{ route('storefront.account.addresses') }}" class="account-link-button">Cancel</a>
            </div>
        </form>
    @endcomponent
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-storefront-address-form]').forEach((form) => {
                const labelValue = form.querySelector('[data-address-label-value]');
                const otherWrap = form.querySelector('[data-address-other-label-wrap]');
                const otherInput = form.querySelector('[name="address_label"]');
                const postal = form.querySelector('[data-address-postal-code]');
                const cityDisplay = form.querySelector('[data-address-city-display]');
                const stateDisplay = form.querySelector('[data-address-state-display]');
                const cityId = form.querySelector('[data-address-city-id]');
                const stateId = form.querySelector('[data-address-state-id]');
                const feedback = form.querySelector('[data-address-pin-feedback]');

                const clearClientErrors = () => {
                    form.querySelectorAll('[data-address-client-error]').forEach((node) => node.remove());
                    form.querySelectorAll('.is-invalid').forEach((node) => node.classList.remove('is-invalid'));
                };

                const fieldHolder = (field) => {
                    return field.closest('.account-form-field') || field.parentElement;
                };

                const showClientError = (field, message) => {
                    field.classList.add('is-invalid');

                    const holder = fieldHolder(field);
                    if (!holder) {
                        return;
                    }

                    const error = document.createElement('div');
                    error.className = 'account-field-error';
                    error.dataset.addressClientError = '1';
                    error.textContent = message;
                    holder.appendChild(error);
                };

                const syncLabel = () => {
                    const checked = form.querySelector('[name="address_type"]:checked');
                    const type = checked ? checked.value : 'Home';
                    const isOther = type === 'Other';

                    if (otherWrap) {
                        otherWrap.hidden = !isOther;
                    }

                    if (labelValue) {
                        labelValue.value = isOther ? (otherInput?.value || '') : type;
                    }
                };

                const clearLocation = () => {
                    if (cityDisplay) cityDisplay.value = '';
                    if (stateDisplay) stateDisplay.value = '';
                    if (cityId) cityId.value = '';
                    if (stateId) stateId.value = '';
                    if (feedback) {
                        feedback.textContent = '';
                        feedback.classList.remove('text-success', 'text-danger');
                    }
                };

                const lookupPin = async () => {
                    if (!postal || postal.value.trim() === '') {
                        return;
                    }

                    const pin = postal.value.trim();

                    if (!/^\d{6}$/.test(pin)) {
                        clearLocation();
                        if (feedback) {
                            feedback.textContent = 'Please enter a valid Indian PIN code.';
                            feedback.classList.add('text-danger');
                        }
                        return;
                    }

                    if (feedback) {
                        feedback.textContent = 'Checking PIN...';
                        feedback.classList.remove('text-danger', 'text-success');
                    }

                    try {
                        const response = await fetch(form.dataset.postalCodeUrlTemplate.replace('__PIN__', encodeURIComponent(pin)), {
                            headers: {'Accept': 'application/json'},
                        });
                        const payload = await response.json();
                        clearLocation();

                        if (!payload.valid) {
                            if (feedback) {
                                feedback.textContent = payload.message || 'Please enter a valid Indian PIN code.';
                                feedback.classList.add('text-danger');
                            }
                            return;
                        }

                        if (cityDisplay) cityDisplay.value = payload.city || '';
                        if (stateDisplay) stateDisplay.value = payload.state || '';
                        if (feedback) {
                            feedback.textContent = 'PIN verified.';
                            feedback.classList.add('text-success');
                        }
                    } catch (error) {
                        if (feedback) {
                            feedback.textContent = 'Unable to verify PIN right now.';
                            feedback.classList.add('text-danger');
                        }
                    }
                };

                form.querySelectorAll('[name="address_type"]').forEach((radio) => radio.addEventListener('change', syncLabel));
                if (otherInput) {
                    otherInput.addEventListener('input', syncLabel);
                }
                if (postal) {
                    postal.setAttribute('pattern', '[0-9]{6}');
                    postal.addEventListener('blur', lookupPin);
                }
                form.querySelectorAll('[data-address-required], [name="address_label"]').forEach((field) => {
                    field.addEventListener('input', () => {
                        field.classList.remove('is-invalid');
                        fieldHolder(field)?.querySelectorAll('[data-address-client-error]').forEach((node) => node.remove());
                    });
                });
                form.addEventListener('submit', (event) => {
                    syncLabel();
                    clearClientErrors();

                    let firstInvalid = null;
                    const fail = (field, message) => {
                        if (!firstInvalid) {
                            firstInvalid = field;
                        }

                        showClientError(field, message);
                    };

                    if (form.querySelector('[name="address_type"]:checked')?.value === 'Other' && otherInput && otherInput.value.trim() === '') {
                        fail(otherInput, 'Please enter an address label.');
                    }

                    form.querySelectorAll('[data-address-required]').forEach((field) => {
                        if (field.value.trim() === '') {
                            fail(field, 'This field is required.');
                        }
                    });

                    if (postal && postal.value.trim() !== '' && !/^\d{6}$/.test(postal.value.trim())) {
                        fail(postal, 'Please enter a valid 6-digit Indian PIN code.');
                    }

                    if (firstInvalid) {
                        event.preventDefault();
                        firstInvalid.focus();
                        firstInvalid.scrollIntoView({behavior: 'smooth', block: 'center'});
                    }
                });
                syncLabel();
            });
        });
    </script>
@endpush
