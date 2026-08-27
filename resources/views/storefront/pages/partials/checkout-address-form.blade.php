@php
    $addressForm = $addressForm ?? [];
    $defaultCountryIso2 = strtoupper((string) ($defaultCountry?->iso2 ?? ''));
    $fieldPrefix = md5($action.($method ?? 'POST'));
    $defaultCheckboxName = $defaultCheckboxName ?? 'is_default_shipping';
    $defaultCheckboxLabel = $defaultCheckboxLabel ?? 'Use as default delivery address';
    $currentLabel = old('label', $addressForm['label'] ?? 'Home');
    $addressType = old('address_type', in_array($currentLabel, ['Home', 'Work'], true) ? $currentLabel : 'Other');
    $otherLabel = old('address_label', $addressType === 'Other' ? $currentLabel : '');
@endphp

<form
    method="POST"
    action="{{ $action }}"
    class="checkout-address-form"
    data-checkout-address-form
    data-checkout-ajax-form
    data-default-country-iso2="{{ $defaultCountryIso2 }}"
    data-postal-code-url-template="{{ route('storefront.checkout.postal-code.show', ['postalCode' => '__PIN__']) }}"
>
    @csrf
    @if (($method ?? 'POST') !== 'POST')
        @method($method)
    @endif
    @if (isset($addressContext))
        <input type="hidden" name="address_context" value="{{ $addressContext }}">
    @endif
    <input type="hidden" name="label" value="{{ $currentLabel }}" data-address-label-value>
    <input type="hidden" name="recipient_mobile_country_code" value="+91">

    <fieldset class="full">
        <label class="tf-lable fw-medium d-block mb-8">Address Type <span class="text-primary">*</span></label>
        <div class="d-flex flex-wrap gap-3" data-address-type-group>
            @foreach (['Home', 'Work', 'Other'] as $type)
                <label class="checkbox-wrap">
                    <input type="radio" name="address_type" value="{{ $type }}" class="tf-check-rounded style-2" {{ $addressType === $type ? 'checked' : '' }}>
                    <span class="fw-medium lh-24">{{ $type }}</span>
                </label>
            @endforeach
        </div>
        @error('label') <p class="text-danger text-caption-01 mt-4 mb-0">{{ $message }}</p> @enderror
    </fieldset>

    <fieldset class="full" data-address-other-label-wrap @if ($addressType !== 'Other') hidden @endif>
        <input type="text" name="address_label" value="{{ $otherLabel }}" placeholder="Address Label">
        @error('address_label') <p class="text-danger text-caption-01 mt-4 mb-0">{{ $message }}</p> @enderror
    </fieldset>

    <fieldset>
        <input type="text" name="recipient_name" value="{{ old('recipient_name', $addressForm['recipient_name'] ?? '') }}" placeholder="Full Name*" required>
        @error('recipient_name') <p class="text-danger text-caption-01 mt-4 mb-0">{{ $message }}</p> @enderror
    </fieldset>

    <fieldset>
        <input type="tel" name="recipient_mobile" value="{{ old('recipient_mobile', $addressForm['recipient_mobile'] ?? '') }}" placeholder="Phone Number*" required>
        @error('recipient_mobile') <p class="text-danger text-caption-01 mt-4 mb-0">{{ $message }}</p> @enderror
    </fieldset>

    <fieldset>
        <input type="text" name="postal_code" value="{{ old('postal_code', $addressForm['postal_code'] ?? '') }}" placeholder="PIN / Postal Code*" inputmode="numeric" data-address-postal-code>
        @error('postal_code') <p class="text-danger text-caption-01 mt-4 mb-0">{{ $message }}</p> @enderror
        <p class="text-caption-01 cl-text-3 mt-4 mb-0" data-address-pin-feedback></p>
    </fieldset>

    <fieldset class="full">
        <input type="text" name="address_line_1" value="{{ old('address_line_1', $addressForm['address_line_1'] ?? '') }}" placeholder="Address Line 1*" required>
        @error('address_line_1') <p class="text-danger text-caption-01 mt-4 mb-0">{{ $message }}</p> @enderror
    </fieldset>

    <fieldset class="full">
        <input type="text" name="address_line_2" value="{{ old('address_line_2', $addressForm['address_line_2'] ?? '') }}" placeholder="Address Line 2">
        @error('address_line_2') <p class="text-danger text-caption-01 mt-4 mb-0">{{ $message }}</p> @enderror
    </fieldset>

    <fieldset class="full">
        <input type="text" name="landmark" value="{{ old('landmark', $addressForm['landmark'] ?? '') }}" placeholder="Landmark">
        @error('landmark') <p class="text-danger text-caption-01 mt-4 mb-0">{{ $message }}</p> @enderror
    </fieldset>

    <fieldset>
        <input type="text" name="city_name" value="{{ old('city_name', $addressForm['city_name'] ?? '') }}" placeholder="City*" data-address-city-display>
        <input type="hidden" name="city_id" value="{{ old('city_id', $addressForm['city_id'] ?? '') }}" data-address-city-id>
        @error('city_id') <p class="text-danger text-caption-01 mt-4 mb-0">{{ $message }}</p> @enderror
        @error('city_name') <p class="text-danger text-caption-01 mt-4 mb-0">{{ $message }}</p> @enderror
    </fieldset>

    <fieldset>
        <input type="text" name="state_name" value="{{ old('state_name', $addressForm['state_name'] ?? '') }}" placeholder="State*" data-address-state-display readonly>
        <input type="hidden" name="state_id" value="{{ old('state_id', $addressForm['state_id'] ?? '') }}" data-address-state-id>
        @error('state_id') <p class="text-danger text-caption-01 mt-4 mb-0">{{ $message }}</p> @enderror
    </fieldset>

    <div class="checkbox-wrap full">
        <input id="default-address-{{ $fieldPrefix }}" type="checkbox" name="{{ $defaultCheckboxName }}" value="1" class="tf-check style-2" {{ old($defaultCheckboxName, $addressForm[$defaultCheckboxName] ?? false) ? 'checked' : '' }}>
        <label for="default-address-{{ $fieldPrefix }}" class="fw-medium lh-24">{{ $defaultCheckboxLabel }}</label>
    </div>

    <div class="full">
        <button type="submit" class="tf-btn animate-btn small">{{ $submitLabel }}</button>
    </div>
</form>

@once
    @push('scripts')
        <script>
            (function () {
                const checkout = window.WindowShopCheckout = window.WindowShopCheckout || {};

                const checkoutContent = function () {
                    return document.querySelector('[data-checkout-content]');
                };

                const checkoutMessages = function () {
                    return document.querySelector('[data-checkout-messages]');
                };

                const alertNode = function (message, type) {
                    const div = document.createElement('div');
                    div.className = 'alert alert-' + type;
                    div.textContent = message;

                    return div;
                };

                const clearFormErrors = function (form) {
                    form.querySelectorAll('[data-checkout-error]').forEach(function (node) {
                        node.remove();
                    });
                    form.querySelectorAll('.is-invalid').forEach(function (node) {
                        node.classList.remove('is-invalid');
                    });
                };

                const showFormErrors = function (form, errors) {
                    clearFormErrors(form);

                    Object.keys(errors || {}).forEach(function (field) {
                        const input = form.querySelector('[name="' + field + '"]');

                        if (!input) {
                            return;
                        }

                        input.classList.add('is-invalid');
                        const holder = input.closest('fieldset') || input.closest('.checkbox-wrap') || input.parentElement;
                        const error = document.createElement('p');
                        error.className = 'text-danger text-caption-01 mt-4 mb-0';
                        error.setAttribute('data-checkout-error', '');
                        error.textContent = errors[field][0] || 'Please check this field.';
                        holder.appendChild(error);
                    });
                };

                const refreshCheckoutContent = function (message) {
                    return fetch(window.location.href, {
                        headers: {
                            'Accept': 'text/html',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(function (response) { return response.text(); })
                        .then(function (html) {
                            const doc = new DOMParser().parseFromString(html, 'text/html');
                            const fresh = doc.querySelector('[data-checkout-content]');
                            const current = checkoutContent();

                            if (!fresh || !current) {
                                window.location.reload();
                                return;
                            }

                            current.innerHTML = fresh.innerHTML;

                            if (message) {
                                const messages = checkoutMessages();

                                if (messages) {
                                    messages.innerHTML = '';
                                    messages.appendChild(alertNode(message, 'success'));
                                }
                            }

                            checkout.init();
                        });
                };

                const initAddressForm = function (form) {
                    if (form.dataset.checkoutFormReady === '1') {
                        return;
                    }

                    form.dataset.checkoutFormReady = '1';

                    const postal = form.querySelector('[data-address-postal-code]');
                    const cityDisplay = form.querySelector('[data-address-city-display]');
                    const stateDisplay = form.querySelector('[data-address-state-display]');
                    const cityId = form.querySelector('[data-address-city-id]');
                    const stateId = form.querySelector('[data-address-state-id]');
                    const feedback = form.querySelector('[data-address-pin-feedback]');
                    const labelValue = form.querySelector('[data-address-label-value]');
                    const otherWrap = form.querySelector('[data-address-other-label-wrap]');
                    const otherInput = form.querySelector('[name="address_label"]');
                    const defaultCountryIso2 = (form.dataset.defaultCountryIso2 || '').toUpperCase();

                    const isIndia = function () {
                        return defaultCountryIso2 === 'IN';
                    };

                    const clearLocation = function () {
                        if (cityDisplay) cityDisplay.value = '';
                        if (stateDisplay) stateDisplay.value = '';
                        if (cityId) cityId.value = '';
                        if (stateId) stateId.value = '';
                        if (feedback) {
                            feedback.textContent = '';
                            feedback.classList.remove('text-success', 'text-danger');
                        }
                    };

                    const syncLabel = function () {
                        const checked = form.querySelector('[name="address_type"]:checked');
                        const type = checked ? checked.value : 'Home';
                        const isOther = type === 'Other';

                        if (otherWrap) {
                            otherWrap.hidden = !isOther;
                        }

                        if (labelValue) {
                            labelValue.value = isOther ? (otherInput ? otherInput.value : '') : type;
                        }
                    };

                    const lookupPin = function () {
                        if (!isIndia() || !postal || postal.value.trim() === '') {
                            return;
                        }

                        if (!/^\d{6}$/.test(postal.value.trim())) {
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

                        fetch(form.dataset.postalCodeUrlTemplate.replace('__PIN__', encodeURIComponent(postal.value.trim())), {
                            headers: { 'Accept': 'application/json' }
                        })
                            .then(function (response) { return response.json(); })
                            .then(function (payload) {
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
                                    feedback.textContent = payload.shipping_enabled
                                        ? 'PIN verified.'
                                        : 'PIN verified, but shipping is currently unavailable.';
                                    feedback.classList.add(payload.shipping_enabled ? 'text-success' : 'text-danger');
                                }

                                const restricted = (payload.shop_availability || []).some(function (shop) {
                                    return shop.available === false;
                                });

                                if (restricted && feedback) {
                                    feedback.textContent += ' Some items cannot currently be delivered to this PIN code.';
                                }
                            })
                            .catch(function () {
                                if (feedback) {
                                    feedback.textContent = 'Unable to verify PIN right now.';
                                    feedback.classList.add('text-danger');
                                }
                            });
                    };

                    if (postal && isIndia()) {
                        postal.setAttribute('pattern', '[0-9]{6}');
                    }

                    if (postal) {
                        postal.addEventListener('blur', lookupPin);
                    }

                    form.querySelectorAll('[name="address_type"]').forEach(function (radio) {
                        radio.addEventListener('change', syncLabel);
                    });

                    if (otherInput) {
                        otherInput.addEventListener('input', syncLabel);
                    }

                    form.addEventListener('submit', function (event) {
                        syncLabel();

                        if (!form.hasAttribute('data-checkout-ajax-form')) {
                            return;
                        }

                        event.preventDefault();
                        clearFormErrors(form);

                        const button = form.querySelector('[type="submit"]');
                        const originalText = button ? button.textContent : '';

                        if (button) {
                            button.disabled = true;
                            button.textContent = 'Saving...';
                        }

                        fetch(form.action, {
                            method: form.method || 'POST',
                            body: new FormData(form),
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                            .then(function (response) {
                                if (response.status === 422) {
                                    return response.json().then(function (payload) {
                                        showFormErrors(form, payload.errors || {});
                                        throw new Error('validation');
                                    });
                                }

                                if (!response.ok) {
                                    throw new Error('request');
                                }

                                return response.json();
                            })
                            .then(function (payload) {
                                return refreshCheckoutContent(payload.message || 'Address saved.');
                            })
                            .catch(function (error) {
                                if (error.message === 'validation') {
                                    return;
                                }

                                const messages = checkoutMessages();

                                if (messages) {
                                    messages.innerHTML = '';
                                    messages.appendChild(alertNode('Unable to save address right now.', 'danger'));
                                }
                            })
                            .finally(function () {
                                if (button) {
                                    button.disabled = false;
                                    button.textContent = originalText;
                                }
                            });
                    });

                    syncLabel();
                };

                checkout.init = function () {
                    document.querySelectorAll('[data-checkout-address-form]').forEach(initAddressForm);
                };

                document.addEventListener('DOMContentLoaded', checkout.init);
            })();
        </script>
    @endpush
@endonce
