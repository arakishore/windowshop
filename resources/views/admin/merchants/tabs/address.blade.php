{{-- Purpose: Creates or updates the merchant's single V1 business address. --}}
@php
    $selectedCountryId = old('country_id', $address?->country_id ?? $defaultLocation['country_id']);
    $selectedStateId = old('state_id', $address?->state_id ?? $defaultLocation['state_id']);
    $selectedCityId = old('city_id', $address?->city_id ?? $defaultLocation['city_id']);
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Please correct the highlighted fields.</div>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('admin.merchants.address.update', $merchant) }}">
    @csrf

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Business Address</h5>
        </div>

        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label for="address_line_1" class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                    <input id="address_line_1" name="address_line_1" type="text" value="{{ old('address_line_1', $address?->address_line_1) }}" class="form-control @error('address_line_1') is-invalid @enderror" required>
                    @error('address_line_1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="address_line_2" class="form-label">Address Line 2</label>
                    <input id="address_line_2" name="address_line_2" type="text" value="{{ old('address_line_2', $address?->address_line_2) }}" class="form-control @error('address_line_2') is-invalid @enderror">
                    @error('address_line_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="landmark" class="form-label">Landmark</label>
                    <input id="landmark" name="landmark" type="text" value="{{ old('landmark', $address?->landmark) }}" class="form-control @error('landmark') is-invalid @enderror">
                    @error('landmark')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label for="pincode" class="form-label">Pincode <span class="text-danger">*</span></label>
                    <input id="pincode" name="pincode" type="text" value="{{ old('pincode', $address?->pincode) }}" class="form-control @error('pincode') is-invalid @enderror" required>
                    @error('pincode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="country_id" class="form-label">Country <span class="text-danger">*</span></label>
                    <select id="country_id" name="country_id" class="form-select @error('country_id') is-invalid @enderror" required>
                        <option value="">Select country</option>
                        @foreach($countries as $country)
                            <option value="{{ $country->id }}" @selected((int) $selectedCountryId === (int) $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                    @error('country_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="state_id" class="form-label">State <span class="text-danger">*</span></label>
                    <select id="state_id" name="state_id" class="form-select @error('state_id') is-invalid @enderror" required>
                        <option value="">Select state</option>
                        @foreach($states as $state)
                            <option value="{{ $state->id }}" @selected((int) $selectedStateId === (int) $state->id)>{{ $state->name }}</option>
                        @endforeach
                    </select>
                    @error('state_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label for="city_id" class="form-label">City <span class="text-danger">*</span></label>
                    <select id="city_id" name="city_id" class="form-select @error('city_id') is-invalid @enderror" required>
                        <option value="">Select city</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->id }}" @selected((int) $selectedCityId === (int) $city->id)>{{ $city->name }}</option>
                        @endforeach
                    </select>
                    @error('city_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card-footer d-flex justify-content-end gap-2">
            <a href="{{ route('admin.merchants.show', $merchant) }}" class="btn btn-light border">Back to Overview</a>
            <button type="submit" class="btn btn-primary">Save Address</button>
        </div>
    </div>
</form>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const countrySelect = document.getElementById('country_id');
            const stateSelect = document.getElementById('state_id');
            const citySelect = document.getElementById('city_id');
            const statesUrl = @json(route('admin.merchants.address.states'));
            const citiesUrl = @json(route('admin.merchants.address.cities'));

            const resetSelect = function (select, placeholder) {
                select.innerHTML = '';
                const option = document.createElement('option');
                option.value = '';
                option.textContent = placeholder;
                select.appendChild(option);
            };

            const fillSelect = function (select, items, placeholder) {
                resetSelect(select, placeholder);

                items.forEach(function (item) {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.name;
                    select.appendChild(option);
                });
            };

            const fetchJson = function (url) {
                return fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                    },
                }).then(function (response) {
                    return response.ok ? response.json() : [];
                });
            };

            countrySelect.addEventListener('change', function () {
                resetSelect(stateSelect, 'Select state');
                resetSelect(citySelect, 'Select city');

                if (!countrySelect.value) {
                    return;
                }

                fetchJson(`${statesUrl}?country_id=${countrySelect.value}`)
                    .then(function (states) {
                        fillSelect(stateSelect, states, 'Select state');
                    });
            });

            stateSelect.addEventListener('change', function () {
                resetSelect(citySelect, 'Select city');

                if (!countrySelect.value || !stateSelect.value) {
                    return;
                }

                fetchJson(`${citiesUrl}?country_id=${countrySelect.value}&state_id=${stateSelect.value}`)
                    .then(function (cities) {
                        fillSelect(citySelect, cities, 'Select city');
                    });
            });
        });
    </script>
@endpush
