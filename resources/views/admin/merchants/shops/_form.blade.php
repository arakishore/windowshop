@php
    $isEdit = $shop !== null;
    $selectedCountryId = old('country_id', $shop?->country_id ?? $defaultLocation['country_id']);
    $selectedStateId = old('state_id', $shop?->state_id ?? $defaultLocation['state_id']);
    $selectedCityId = old('city_id', $shop?->city_id ?? $defaultLocation['city_id']);
    $selectedStatus = old('status', $shop?->status ?? 'active');
    $removeLogo = old('remove_logo') && $shop?->logo_path;
    $removeBanner = old('remove_banner') && $shop?->banner_path;
    $logoMaxMb = (int) ceil(config('images.shop_logo.max_upload_kb', 5120) / 1024);
    $bannerMaxMb = (int) ceil(config('images.shop_banner.max_upload_kb', 8192) / 1024);
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

<div class="row g-3">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Shop Information</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="name" class="form-label">Shop Name <span class="text-danger">*</span></label>
                        <input id="name" name="name" type="text" value="{{ old('name', $shop?->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="shop_category_id" class="form-label">Shop Category <span class="text-danger">*</span></label>
                        <select id="shop_category_id" name="shop_category_id" class="form-select @error('shop_category_id') is-invalid @enderror" required>
                            <option value="">Select category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('shop_category_id', $shop?->shop_category_id) === (int) $category->id)>
                                    {{ $category->name }}{{ $category->status !== 'active' ? ' (Inactive)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('shop_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label d-block">Audience</label>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($audiences as $audience)
                                <div class="form-check">
                                    <input id="audience_{{ $audience->id }}" name="audience_ids[]" type="checkbox" value="{{ $audience->id }}" class="form-check-input @error('audience_ids') is-invalid @enderror @error('audience_ids.*') is-invalid @enderror" @checked(in_array((int) $audience->id, $selectedAudienceIds, true))>
                                    <label for="audience_{{ $audience->id }}" class="form-check-label">
                                        {{ $audience->name }}{{ $audience->status !== 'active' ? ' (Inactive)' : '' }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        @error('audience_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('audience_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="short_description" class="form-label">Short Description</label>
                        <input id="short_description" name="short_description" type="text" value="{{ old('short_description', $shop?->short_description) }}" class="form-control @error('short_description') is-invalid @enderror">
                        @error('short_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" rows="4" class="form-control @error('description') is-invalid @enderror">{{ old('description', $shop?->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Public Contact</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $shop?->email) }}" class="form-control @error('email') is-invalid @enderror">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="mobile" class="form-label">Mobile</label>
                        <input id="mobile" name="mobile" type="text" value="{{ old('mobile', $shop?->mobile) }}" class="form-control @error('mobile') is-invalid @enderror">
                        @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                        <input id="whatsapp_number" name="whatsapp_number" type="text" value="{{ old('whatsapp_number', $shop?->whatsapp_number) }}" class="form-control @error('whatsapp_number') is-invalid @enderror">
                        <div class="form-text">Include country code if required.</div>
                        @error('whatsapp_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="website_url" class="form-label">Website URL</label>
                        <input id="website_url" name="website_url" type="url" value="{{ old('website_url', $shop?->website_url) }}" class="form-control @error('website_url') is-invalid @enderror">
                        @error('website_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Shop Address</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="address_line_1" class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                        <input id="address_line_1" name="address_line_1" type="text" value="{{ old('address_line_1', $shop?->address_line_1) }}" class="form-control @error('address_line_1') is-invalid @enderror" required>
                        @error('address_line_1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="address_line_2" class="form-label">Address Line 2</label>
                        <input id="address_line_2" name="address_line_2" type="text" value="{{ old('address_line_2', $shop?->address_line_2) }}" class="form-control @error('address_line_2') is-invalid @enderror">
                        @error('address_line_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="landmark" class="form-label">Landmark</label>
                        <input id="landmark" name="landmark" type="text" value="{{ old('landmark', $shop?->landmark) }}" class="form-control @error('landmark') is-invalid @enderror">
                        @error('landmark')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="pincode" class="form-label">Pincode</label>
                        <input id="pincode" name="pincode" type="text" value="{{ old('pincode', $shop?->pincode) }}" class="form-control @error('pincode') is-invalid @enderror">
                        @error('pincode')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

                    <div class="col-12">
                        <button class="btn btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#advanced-location" aria-expanded="{{ old('latitude', $shop?->latitude) || old('longitude', $shop?->longitude) ? 'true' : 'false' }}" aria-controls="advanced-location">
                            <i class="ph-map-pin me-2"></i>
                            Advanced Location
                        </button>
                    </div>

                    <div class="col-12 collapse {{ old('latitude', $shop?->latitude) || old('longitude', $shop?->longitude) ? 'show' : '' }}" id="advanced-location">
                        <div class="row g-3 pt-2">
                            <div class="col-md-6">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input id="latitude" name="latitude" type="text" value="{{ old('latitude', $shop?->latitude) }}" class="form-control @error('latitude') is-invalid @enderror">
                                @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input id="longitude" name="longitude" type="text" value="{{ old('longitude', $shop?->longitude) }}" class="form-control @error('longitude') is-invalid @enderror">
                                @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Branding</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label d-block">Logo</label>
                        <div class="card border-dashed p-3 mb-0">
                            <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                                <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 180px; height: 120px;">
                                    <img id="shop_logo_preview" src="{{ $shop?->logo_path && ! $removeLogo ? asset('storage/'.$shop->logo_path) : '' }}" data-current-src="{{ $shop?->logo_path ? asset('storage/'.$shop->logo_path) : '' }}" alt="Shop logo" class="img-fluid {{ $shop?->logo_path && ! $removeLogo ? '' : 'd-none' }}" style="width: 100%; height: 100%; object-fit: cover;">
                                    <div id="shop_logo_placeholder" class="text-muted {{ $shop?->logo_path && ! $removeLogo ? 'd-none' : '' }}">{{ $removeLogo ? 'Will remove' : 'Logo' }}</div>
                                </div>
                                <div class="flex-fill">
                                    <label for="logo" class="btn btn-outline-primary btn-sm">
                                        <i class="ph-upload me-1"></i>
                                        Choose image
                                    </label>
                                    <input id="logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.webp" class="d-none @error('logo') is-invalid @enderror">
                                    <p class="text-muted mb-1 mt-2">JPG, JPEG, PNG or WEBP. Max {{ $logoMaxMb }}MB.</p>
                                    @if($shop?->logo_path)<div class="text-muted small text-break">Current: {{ $shop->logo_path }}</div>@endif
                                    @if($shop?->logo_path)
                                        <div class="form-check mt-2">
                                            <input id="remove_logo" name="remove_logo" type="checkbox" value="1" class="form-check-input @error('remove_logo') is-invalid @enderror" @checked($removeLogo)>
                                            <label for="remove_logo" class="form-check-label">Remove current logo</label>
                                        </div>
                                        @error('remove_logo')<div class="text-danger small">{{ $message }}</div>@enderror
                                    @endif
                                    @error('logo')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label d-block">Banner</label>
                        <div class="card border-dashed p-3 mb-0">
                            <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                                <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 240px; height: 120px;">
                                    <img id="shop_banner_preview" src="{{ $shop?->banner_path && ! $removeBanner ? asset('storage/'.$shop->banner_path) : '' }}" data-current-src="{{ $shop?->banner_path ? asset('storage/'.$shop->banner_path) : '' }}" alt="Shop banner" class="img-fluid {{ $shop?->banner_path && ! $removeBanner ? '' : 'd-none' }}" style="width: 100%; height: 100%; object-fit: cover;">
                                    <div id="shop_banner_placeholder" class="text-muted {{ $shop?->banner_path && ! $removeBanner ? 'd-none' : '' }}">{{ $removeBanner ? 'Will remove' : 'Banner' }}</div>
                                </div>
                                <div class="flex-fill">
                                    <label for="banner" class="btn btn-outline-primary btn-sm">
                                        <i class="ph-upload me-1"></i>
                                        Choose image
                                    </label>
                                    <input id="banner" name="banner" type="file" accept=".jpg,.jpeg,.png,.webp" class="d-none @error('banner') is-invalid @enderror">
                                    <p class="text-muted mb-1 mt-2">JPG, JPEG, PNG or WEBP. Max {{ $bannerMaxMb }}MB.</p>
                                    @if($shop?->banner_path)<div class="text-muted small text-break">Current: {{ $shop->banner_path }}</div>@endif
                                    @if($shop?->banner_path)
                                        <div class="form-check mt-2">
                                            <input id="remove_banner" name="remove_banner" type="checkbox" value="1" class="form-check-input @error('remove_banner') is-invalid @enderror" @checked($removeBanner)>
                                            <label for="remove_banner" class="form-check-label">Remove current banner</label>
                                        </div>
                                        @error('remove_banner')<div class="text-danger small">{{ $message }}</div>@enderror
                                    @endif
                                    @error('banner')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Status</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="status" class="form-label">Account Status <span class="text-danger">*</span></label>
                        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                            @foreach($shopStatuses as $value => $statusConfig)
                                <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $statusConfig['label'] }}</option>
                            @endforeach
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="admin_note" class="form-label">Admin Note</label>
                        <textarea id="admin_note" name="admin_note" rows="4" class="form-control @error('admin_note') is-invalid @enderror">{{ old('admin_note', $shop?->admin_note) }}</textarea>
                        @error('admin_note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<x-form-buttons
    :submit="$isEdit ? 'Update Shop' : 'Create Shop'"
    :cancel="route('admin.merchants.shops.index', $merchant)"
    cancel-label="Back to Shops"
/>

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

            if (countrySelect && stateSelect && citySelect) {
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
            }

            const setupImagePreview = function (inputId, previewId, placeholderId, removeId, emptyLabel) {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                const placeholder = document.getElementById(placeholderId);
                const remove = document.getElementById(removeId);

                if (!input || !preview) {
                    return;
                }

                input.addEventListener('change', function () {
                    const file = input.files && input.files[0];

                    if (!file || !/^image\/(jpeg|jpg|png|webp)$/i.test(file.type)) {
                        return;
                    }

                    if (remove) {
                        remove.checked = false;
                    }

                    preview.src = URL.createObjectURL(file);
                    preview.classList.remove('d-none');

                    if (placeholder) {
                        placeholder.textContent = emptyLabel;
                        placeholder.classList.add('d-none');
                    }
                });

                if (remove) {
                    remove.addEventListener('change', function () {
                        if (!remove.checked) {
                            const currentSrc = preview.dataset.currentSrc;

                            if (currentSrc) {
                                preview.src = currentSrc;
                                preview.classList.remove('d-none');

                                if (placeholder) {
                                    placeholder.textContent = emptyLabel;
                                    placeholder.classList.add('d-none');
                                }
                            }

                            return;
                        }

                        input.value = '';
                        preview.removeAttribute('src');
                        preview.classList.add('d-none');

                        if (placeholder) {
                            placeholder.textContent = 'Will remove';
                            placeholder.classList.remove('d-none');
                        }
                    });
                }
            };

            setupImagePreview('logo', 'shop_logo_preview', 'shop_logo_placeholder', 'remove_logo', 'Logo');
            setupImagePreview('banner', 'shop_banner_preview', 'shop_banner_placeholder', 'remove_banner', 'Banner');
        });
    </script>
@endpush
