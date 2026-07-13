{{-- Purpose: Merchant shop creation form. --}}
@extends('layouts.merchant')

@section('title', 'Add Shop | WindowShop')

@section('page_title', 'Add Shop')

@section('content')
    @php
        $selectedCountryId = old('country_id', $defaultLocation['country_id']);
        $selectedStateId = old('state_id', $defaultLocation['state_id']);
        $selectedCityId = old('city_id', $defaultLocation['city_id']);
        $selectedShopTypeId = old('root_product_category_id');
        $selectedStatus = old('status', 'active');
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

    <form method="POST" action="{{ route('merchant.shops.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="row g-3">
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Basic Information</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="root_product_category_id" class="form-label">Shop Type <span class="text-danger">*</span></label>
                            <select id="root_product_category_id" name="root_product_category_id" class="form-select @error('root_product_category_id') is-invalid @enderror" required>
                                <option value="">Select shop type</option>
                                @foreach($shopTypes as $shopType)
                                    <option value="{{ $shopType->id }}" @selected((string) $selectedShopTypeId === (string) $shopType->id)>
                                        {{ $shopType->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('root_product_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="name" class="form-label">Shop Name <span class="text-danger">*</span></label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="short_description" class="form-label">Short Description</label>
                            <input id="short_description" name="short_description" type="text" value="{{ old('short_description') }}" class="form-control @error('short_description') is-invalid @enderror">
                            @error('short_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-0">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" rows="5" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0">Public Contact</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="active" @selected($selectedStatus === 'active')>{{ $shopStatuses['active']['label'] ?? 'Active' }}</option>
                                    <option value="inactive" @selected($selectedStatus === 'inactive')>{{ $shopStatuses['inactive']['label'] ?? 'Inactive' }}</option>
                                </select>
                                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="mobile" class="form-label">Mobile</label>
                                <input id="mobile" name="mobile" type="text" value="{{ old('mobile') }}" class="form-control @error('mobile') is-invalid @enderror">
                                @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="whatsapp_number" class="form-label">WhatsApp Number</label>
                                <input id="whatsapp_number" name="whatsapp_number" type="text" value="{{ old('whatsapp_number') }}" class="form-control @error('whatsapp_number') is-invalid @enderror">
                                <div class="form-text">Include country code if required.</div>
                                @error('whatsapp_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="website_url" class="form-label">Website URL</label>
                                <input id="website_url" name="website_url" type="url" value="{{ old('website_url') }}" class="form-control @error('website_url') is-invalid @enderror">
                                @error('website_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Address</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="address_line_1" class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                                <input id="address_line_1" name="address_line_1" type="text" value="{{ old('address_line_1') }}" class="form-control @error('address_line_1') is-invalid @enderror" required>
                                @error('address_line_1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label for="address_line_2" class="form-label">Address Line 2</label>
                                <input id="address_line_2" name="address_line_2" type="text" value="{{ old('address_line_2') }}" class="form-control @error('address_line_2') is-invalid @enderror">
                                @error('address_line_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="landmark" class="form-label">Landmark</label>
                                <input id="landmark" name="landmark" type="text" value="{{ old('landmark') }}" class="form-control @error('landmark') is-invalid @enderror">
                                @error('landmark')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="pincode" class="form-label">Pincode</label>
                                <input id="pincode" name="pincode" type="text" value="{{ old('pincode') }}" class="form-control @error('pincode') is-invalid @enderror">
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

                            <div class="col-md-6">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input id="latitude" name="latitude" type="text" value="{{ old('latitude') }}" class="form-control @error('latitude') is-invalid @enderror">
                                @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input id="longitude" name="longitude" type="text" value="{{ old('longitude') }}" class="form-control @error('longitude') is-invalid @enderror">
                                @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Appearance</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-xl-6">
                                <label class="form-label d-block">Logo</label>
                                <div class="card border-dashed p-3 mb-0">
                                    <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                                        <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 180px; height: 120px;">
                                            <img id="shop_logo_preview" alt="Shop logo" class="img-fluid d-none" style="width: 100%; height: 100%; object-fit: cover;">
                                            <div id="shop_logo_placeholder" class="text-muted">Logo</div>
                                        </div>
                                        <div class="flex-fill">
                                            <label for="logo" class="btn btn-outline-primary btn-sm">
                                                <i class="ph-upload me-1"></i>
                                                Choose image
                                            </label>
                                            <input id="logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.webp" class="d-none @error('logo') is-invalid @enderror">
                                            <p class="text-muted mb-1 mt-2">JPG, JPEG, PNG or WEBP. Max {{ $logoMaxMb }}MB.</p>
                                            @error('logo')<div class="text-danger small">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6">
                                <label class="form-label d-block">Banner</label>
                                <div class="card border-dashed p-3 mb-0">
                                    <div class="d-flex flex-column flex-sm-row align-items-start gap-3">
                                        <div class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 240px; height: 120px;">
                                            <img id="shop_banner_preview" alt="Shop banner" class="img-fluid d-none" style="width: 100%; height: 100%; object-fit: cover;">
                                            <div id="shop_banner_placeholder" class="text-muted">Banner</div>
                                        </div>
                                        <div class="flex-fill">
                                            <label for="banner" class="btn btn-outline-primary btn-sm">
                                                <i class="ph-upload me-1"></i>
                                                Choose image
                                            </label>
                                            <input id="banner" name="banner" type="file" accept=".jpg,.jpeg,.png,.webp" class="d-none @error('banner') is-invalid @enderror">
                                            <p class="text-muted mb-1 mt-2">JPG, JPEG, PNG or WEBP. Max {{ $bannerMaxMb }}MB.</p>
                                            @error('banner')<div class="text-danger small">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end mt-3">
            <a href="{{ route('merchant.shops.index') }}" class="btn btn-light">Back to Shops</a>
            <button type="submit" class="btn btn-primary">
                <i class="ph-floppy-disk me-2"></i>
                Create Shop
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const setupImagePreview = function (inputId, previewId, placeholderId) {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                const placeholder = document.getElementById(placeholderId);

                if (!input || !preview) {
                    return;
                }

                input.addEventListener('change', function () {
                    const file = input.files && input.files[0];

                    if (!file || !/^image\/(jpeg|jpg|png|webp)$/i.test(file.type)) {
                        return;
                    }

                    preview.src = URL.createObjectURL(file);
                    preview.classList.remove('d-none');

                    if (placeholder) {
                        placeholder.classList.add('d-none');
                    }
                });
            };

            setupImagePreview('logo', 'shop_logo_preview', 'shop_logo_placeholder');
            setupImagePreview('banner', 'shop_banner_preview', 'shop_banner_placeholder');
        });
    </script>
@endpush
