@csrf
@php
    $positionValue = old('position', $banner->position instanceof \App\Enums\BannerPosition ? $banner->position->value : $banner->position);
    $ownerType = old('owner_type', $banner->merchant_id ? 'merchant' : 'marketplace');
    $linkType = old('link_type', $banner->link_type instanceof \App\Enums\BannerLinkType ? $banner->link_type->value : ($banner->link_type ?: 'none'));
@endphp

<div class="card-body">
    @if($errors->any())
        <div class="alert alert-danger">Please review the highlighted banner fields.</div>
    @endif
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label d-block">Owner Type</label>
            <div class="d-flex flex-wrap gap-3">
                <label class="form-check">
                    <input type="radio" name="owner_type" value="marketplace" class="form-check-input js-owner-type" @checked($ownerType === 'marketplace')>
                    <span class="form-check-label">Marketplace</span>
                </label>
                <label class="form-check">
                    <input type="radio" name="owner_type" value="merchant" class="form-check-input js-owner-type" @checked($ownerType === 'merchant')>
                    <span class="form-check-label">Merchant Store</span>
                </label>
            </div>
            @error('owner_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 js-owner-field">
            <label class="form-label" for="merchant_id">Merchant</label>
            <select id="merchant_id" name="merchant_id" class="form-select @error('merchant_id') is-invalid @enderror">
                <option value="">Select merchant</option>
                @foreach($merchants as $merchant)
                    <option value="{{ $merchant->getKey() }}" @selected((int) old('merchant_id', $banner->merchant_id) === (int) $merchant->getKey())>{{ $merchant->business_name }}</option>
                @endforeach
            </select>
            @error('merchant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6 js-owner-field">
            <label class="form-label" for="shop_id">Shop</label>
            <select id="shop_id" name="shop_id" class="form-select @error('shop_id') is-invalid @enderror">
                <option value="">Select shop</option>
                @foreach($shops as $shop)
                    <option value="{{ $shop->getKey() }}" data-merchant-id="{{ $shop->merchant_id }}" @selected((int) old('shop_id', $banner->shop_id) === (int) $shop->getKey())>{{ $shop->name }}</option>
                @endforeach
            </select>
            @error('shop_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @include('shared.banners.form-fields')
    </div>
</div>
<div class="card-footer d-flex justify-content-end gap-2">
    <a href="{{ route('admin.banners.index') }}" class="btn btn-light">Cancel</a>
    <button type="submit" class="btn btn-primary"><i class="ph-floppy-disk me-2"></i>Save Banner</button>
</div>

@include('shared.banners.form-script')
