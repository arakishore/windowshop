{{-- Purpose: Shared merchant customer create/edit fields. --}}
@php
    $businessChecked = (bool) old('is_business_customer', $customer->is_business_customer);
    $selectedCountryCode = old('mobile_country_code', $customer->mobile_country_code ?: $defaultMobileCountryCode);
@endphp

<div class="card js-customer-form" data-mobile-lookup-url="{{ $mobileLookupUrl }}">
    <div class="card-header">
        <h5 class="mb-0">Customer Details</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="customer_code" class="form-label">Customer Code</label>
                <input id="customer_code" type="text" value="{{ $customer->exists ? $customer->customer_code : 'CUS-000001 (Auto Generated)' }}" class="form-control" readonly>
            </div>
            <div class="col-md-6"></div>

            <div class="col-md-6">
                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                <input id="name" name="name" type="text" value="{{ old('name', $customer->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="150" placeholder="e.g. Rahul Sharma" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="mobile" class="form-label">Mobile <span class="text-danger">*</span></label>
                <div class="input-group">
                    <select id="mobile_country_code" name="mobile_country_code" class="form-select @error('mobile_country_code') is-invalid @enderror" style="max-width: 112px;">
                        @foreach($countryCodes as $countryCode)
                            <option value="{{ $countryCode['code'] }}" @selected($selectedCountryCode === $countryCode['code'])>{{ $countryCode['code'] }}</option>
                        @endforeach
                    </select>
                    <input id="mobile" name="mobile" type="text" value="{{ old('mobile', $customer->mobile) }}" class="form-control @error('mobile') is-invalid @enderror" maxlength="30" placeholder="9876543210" required>
                    @error('mobile_country_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @error('mobile_normalized')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <div class="small mt-1 js-mobile-lookup-feedback"></div>
            </div>

            <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $customer->email) }}" class="form-control @error('email') is-invalid @enderror" maxlength="190" placeholder="rahul@example.com">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    @foreach($statuses as $value => $status)
                        <option value="{{ $value }}" @selected(old('status', $customer->status) === $value)>{{ $status['label'] }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label for="date_of_birth" class="form-label">Date of Birth</label>
                <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $customer->date_of_birth?->format('Y-m-d')) }}" class="form-control @error('date_of_birth') is-invalid @enderror">
                @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="gender" class="form-label">Gender</label>
                <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
                    <option value="">Select</option>
                    @foreach($genders as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender', $customer->gender) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <input type="hidden" name="is_business_customer" value="0">
                <div class="form-check form-switch">
                    <input id="is_business_customer" name="is_business_customer" value="1" type="checkbox" class="form-check-input js-business-customer-toggle" @checked($businessChecked)>
                    <label for="is_business_customer" class="form-check-label">Business Customer</label>
                </div>
            </div>

            <div class="col-12 js-business-customer-fields {{ $businessChecked ? '' : 'd-none' }}">
                <div class="border rounded p-3">
                    <div class="fw-semibold mb-3">Business Details</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input id="company_name" name="company_name" type="text" value="{{ old('company_name', $customer->company_name) }}" class="form-control @error('company_name') is-invalid @enderror" maxlength="150" placeholder="e.g. Rahul Stores">
                            @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="gst_number" class="form-label">GST Number</label>
                            <input id="gst_number" name="gst_number" type="text" value="{{ old('gst_number', $customer->gst_number) }}" class="form-control @error('gst_number') is-invalid @enderror" maxlength="30" placeholder="GSTIN">
                            @error('gst_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <label for="notes" class="form-label">Notes</label>
                <textarea id="notes" name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror" placeholder="Any useful customer notes">{{ old('notes', $customer->notes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ $customer->exists ? route('merchant.customers.show', $customer) : route('merchant.customers.index') }}" class="btn btn-light">Cancel</a>
        <button type="reset" class="btn btn-light js-clear-customer-form">Clear</button>
        <button type="submit" class="btn btn-primary">
            <i class="ph-floppy-disk me-2"></i>
            {{ $submitLabel }}
        </button>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const root = document.querySelector('.js-customer-form');
            if (!root) {
                return;
            }

            const businessToggle = root.querySelector('.js-business-customer-toggle');
            const businessFields = root.querySelector('.js-business-customer-fields');
            const mobileInput = root.querySelector('#mobile');
            const countryInput = root.querySelector('#mobile_country_code');
            const feedback = root.querySelector('.js-mobile-lookup-feedback');
            const lookupUrl = root.dataset.mobileLookupUrl;
            let lookupTimer = null;

            const renderBusinessFields = () => {
                businessFields?.classList.toggle('d-none', !businessToggle?.checked);
            };

            const renderMobileFeedback = (html, className) => {
                if (!feedback) {
                    return;
                }

                feedback.className = `small mt-1 ${className}`;
                feedback.innerHTML = html;
            };

            const lookupMobile = () => {
                const mobile = mobileInput?.value.trim() || '';
                if (mobile.length < 6 || !lookupUrl) {
                    renderMobileFeedback('', '');
                    return;
                }

                const url = new URL(lookupUrl, window.location.origin);
                url.searchParams.set('mobile', mobile);
                url.searchParams.set('mobile_country_code', countryInput?.value || '');

                fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => response.ok ? response.json() : null)
                    .then((payload) => {
                        if (!payload) {
                            return;
                        }

                        if (payload.available) {
                            renderMobileFeedback('<i class="ph-check-circle me-1"></i>Mobile available', 'text-success');
                            return;
                        }

                        const customer = payload.customer;
                        renderMobileFeedback(`Customer already exists: <strong>${customer.name}</strong> <a href="${customer.show_url}" class="ms-2">View customer</a>`, 'text-warning');
                    })
                    .catch(() => renderMobileFeedback('', ''));
            };

            const scheduleLookup = () => {
                window.clearTimeout(lookupTimer);
                lookupTimer = window.setTimeout(lookupMobile, 350);
            };

            businessToggle?.addEventListener('change', renderBusinessFields);
            mobileInput?.addEventListener('input', scheduleLookup);
            countryInput?.addEventListener('input', scheduleLookup);
            root.closest('form')?.addEventListener('reset', () => {
                window.setTimeout(() => {
                    renderBusinessFields();
                    renderMobileFeedback('', '');
                }, 0);
            });

            renderBusinessFields();
        });
    </script>
@endpush
