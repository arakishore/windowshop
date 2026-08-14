@php
    $locationErrors = $errors->customerLocation ?? null;
    $locationError = $locationErrors?->first('postal_code');
    $locationValue = old('postal_code', $currentPostalCode);
    $autoOpenLocationModal = $shouldAutoOpenCustomerLocationModal || $locationError;
@endphp

<div class="modal modalCentered fade customer-location-modal"
    id="customer-location-modal"
    tabindex="-1"
    aria-labelledby="customer-location-modal-title"
    data-auto-open="{{ $autoOpenLocationModal ? '1' : '0' }}"
    data-current-postal-code="{{ $currentPostalCode }}"
    data-detect-endpoint="{{ route('storefront.location.detect') }}"
    data-endpoint="{{ route('storefront.location.postal-code.store') }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <span class="icon-close-popup" data-bs-dismiss="modal"><i class="icon-X2"></i></span>
            <div class="modal-heading text-center">
                <span class="customer-location-badge">PIN</span>
                <h3 class="title-pop mb-8" id="customer-location-modal-title">Choose your location</h3>
                <p class="desc-pop cl-text-2">Enter your PIN code to see shops and products near you.</p>
                <p class="customer-location-current mb-0" {{ $currentPostalCode ? '' : 'hidden' }}>
                    Current PIN: <span>{{ $currentPostalCode }}</span>
                </p>
            </div>
            <div class="modal-main">
                <button type="button" class="tf-btn btn-white customer-location-detect-btn">
                    <span class="customer-location-detect-text">Use my current location</span>
                </button>

                <div class="customer-location-detected" aria-live="polite">
                    <p class="customer-location-detected-title">Location detected</p>
                    <p class="customer-location-detected-pin"></p>
                    <p class="customer-location-detected-meta"></p>
                    <div class="customer-location-detected-actions">
                        <button type="button" class="tf-btn animate-btn customer-location-confirm-detected">Use this location</button>
                        <button type="button" class="tf-btn btn-white customer-location-enter-manually">Enter PIN manually</button>
                    </div>
                </div>

                <div class="customer-location-or">or</div>

                <form method="POST"
                    action="{{ route('storefront.location.postal-code.store') }}"
                    class="customer-location-form"
                    novalidate>
                    @csrf
                    <fieldset class="tf-field">
                        <label for="customer-location-postal-code" class="tf-lable fw-medium">PIN Code</label>
                        <input type="text"
                            id="customer-location-postal-code"
                            name="postal_code"
                            value="{{ $locationValue }}"
                            inputmode="numeric"
                            pattern="\d{6}"
                            maxlength="6"
                            autocomplete="postal-code"
                            placeholder="Enter PIN code"
                            required>
                    </fieldset>
                    <button type="submit" class="tf-btn animate-btn">
                        <span class="customer-location-button-text">{{ $currentPostalCode ? 'Update' : 'Apply' }}</span>
                    </button>
                </form>
                <p class="customer-location-error {{ $locationError ? 'is-visible' : '' }}" role="alert">
                    {{ $locationError }}
                </p>
                <p class="desc-pop cl-text-2 customer-location-helper mb-0">We'll show nearby shops first. You can change your location anytime.</p>
            </div>
        </div>
    </div>
</div>
