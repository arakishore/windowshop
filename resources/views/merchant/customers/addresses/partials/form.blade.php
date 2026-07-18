{{-- Purpose: Shared customer address create/edit fields. --}}
@php
    $selectedCountryId = old('country_id', $selectedCountryId);
    $selectedStateId = old('state_id', $selectedStateId);
    $selectedCityId = old('city_id', $selectedCityId);
@endphp

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Address Details</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="label" class="form-label">Address Label <span class="text-danger">*</span></label>
                <input id="label" name="label" type="text" value="{{ old('label', $address->label) }}" class="form-control @error('label') is-invalid @enderror" maxlength="80" placeholder="Home, Office, Warehouse" required>
                @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    @foreach($statuses as $value => $status)
                        <option value="{{ $value }}" @selected(old('status', $address->status) === $value)>{{ $status['label'] }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <label for="recipient_name" class="form-label">Recipient Name <span class="text-danger">*</span></label>
                <input id="recipient_name" name="recipient_name" type="text" value="{{ old('recipient_name', $address->recipient_name) }}" class="form-control @error('recipient_name') is-invalid @enderror" maxlength="150" required>
                @error('recipient_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="recipient_mobile" class="form-label">Recipient Mobile <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input id="recipient_mobile_country_code" name="recipient_mobile_country_code" type="text" value="{{ old('recipient_mobile_country_code', $address->recipient_mobile_country_code ?: '+91') }}" class="form-control @error('recipient_mobile_country_code') is-invalid @enderror" maxlength="10" style="max-width: 86px;">
                    <input id="recipient_mobile" name="recipient_mobile" type="text" value="{{ old('recipient_mobile', $address->recipient_mobile) }}" class="form-control @error('recipient_mobile') is-invalid @enderror" maxlength="30" required>
                    @error('recipient_mobile_country_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @error('recipient_mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="col-12">
                <label for="address_line_1" class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                <input id="address_line_1" name="address_line_1" type="text" value="{{ old('address_line_1', $address->address_line_1) }}" class="form-control @error('address_line_1') is-invalid @enderror" maxlength="190" required>
                @error('address_line_1')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="address_line_2" class="form-label">Address Line 2</label>
                <input id="address_line_2" name="address_line_2" type="text" value="{{ old('address_line_2', $address->address_line_2) }}" class="form-control @error('address_line_2') is-invalid @enderror" maxlength="190">
                @error('address_line_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="landmark" class="form-label">Landmark</label>
                <input id="landmark" name="landmark" type="text" value="{{ old('landmark', $address->landmark) }}" class="form-control @error('landmark') is-invalid @enderror" maxlength="150">
                @error('landmark')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="postal_code" class="form-label">Postal Code</label>
                <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code', $address->postal_code) }}" class="form-control @error('postal_code') is-invalid @enderror" maxlength="20">
                @error('postal_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-4">
                <label for="country_id" class="form-label">Country</label>
                <select id="country_id" name="country_id" class="form-select @error('country_id') is-invalid @enderror">
                    <option value="">Select country</option>
                    @foreach($countries as $country)
                        <option value="{{ $country->id }}" @selected((int) $selectedCountryId === (int) $country->id)>{{ $country->name }}</option>
                    @endforeach
                </select>
                @error('country_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="state_id" class="form-label">State</label>
                <select id="state_id" name="state_id" class="form-select @error('state_id') is-invalid @enderror">
                    <option value="">Select state</option>
                    @foreach($states as $state)
                        <option value="{{ $state->id }}" @selected((int) $selectedStateId === (int) $state->id)>{{ $state->name }}</option>
                    @endforeach
                </select>
                @error('state_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label for="city_id" class="form-label">City</label>
                <select id="city_id" name="city_id" class="form-select @error('city_id') is-invalid @enderror">
                    <option value="">Select city</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}" @selected((int) $selectedCityId === (int) $city->id)>{{ $city->name }}</option>
                    @endforeach
                </select>
                @error('city_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
                <input type="hidden" name="is_default_shipping" value="0">
                <div class="form-check">
                    <input id="is_default_shipping" name="is_default_shipping" value="1" type="checkbox" class="form-check-input" @checked(old('is_default_shipping', $address->is_default_shipping))>
                    <label for="is_default_shipping" class="form-check-label">Default shipping address</label>
                </div>
            </div>
            <div class="col-md-6">
                <input type="hidden" name="is_default_billing" value="0">
                <div class="form-check">
                    <input id="is_default_billing" name="is_default_billing" value="1" type="checkbox" class="form-check-input" @checked(old('is_default_billing', $address->is_default_billing))>
                    <label for="is_default_billing" class="form-check-label">Default billing address</label>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('merchant.customers.show', ['customer' => $customer, 'tab' => 'addresses']) }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="ph-floppy-disk me-2"></i>
            {{ $submitLabel }}
        </button>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const countrySelect = document.getElementById('country_id');
            const stateSelect = document.getElementById('state_id');
            const citySelect = document.getElementById('city_id');
            const statesUrl = @json($statesUrl);
            const citiesUrl = @json($citiesUrl);

            const fillSelect = (select, rows, placeholder) => {
                select.innerHTML = `<option value="">${placeholder}</option>`;
                rows.forEach((row) => {
                    const option = document.createElement('option');
                    option.value = row.id;
                    option.textContent = row.name;
                    select.appendChild(option);
                });
            };

            countrySelect?.addEventListener('change', function () {
                fillSelect(stateSelect, [], 'Select state');
                fillSelect(citySelect, [], 'Select city');

                if (!countrySelect.value) {
                    return;
                }

                fetch(`${statesUrl}?country_id=${countrySelect.value}`)
                    .then((response) => response.json())
                    .then((states) => fillSelect(stateSelect, states, 'Select state'));
            });

            stateSelect?.addEventListener('change', function () {
                fillSelect(citySelect, [], 'Select city');

                if (!countrySelect.value || !stateSelect.value) {
                    return;
                }

                fetch(`${citiesUrl}?country_id=${countrySelect.value}&state_id=${stateSelect.value}`)
                    .then((response) => response.json())
                    .then((cities) => fillSelect(citySelect, cities, 'Select city'));
            });
        });
    </script>
@endpush
