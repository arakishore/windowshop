@csrf
@php
    $positionValue = old('position', $banner->position instanceof \App\Enums\BannerPosition ? $banner->position->value : $banner->position);
    $linkType = old('link_type', $banner->link_type instanceof \App\Enums\BannerLinkType ? $banner->link_type->value : ($banner->link_type ?: 'none'));
@endphp

<div class="card-body">
    @if($errors->any())
        <div class="alert alert-danger">Please review the highlighted banner fields.</div>
    @endif
    <div class="alert alert-info mb-3">Managing banners for {{ $activeShop->name }}.</div>
    <div class="row g-3">
        @include('shared.banners.form-fields')
    </div>
</div>
<div class="card-footer d-flex justify-content-end gap-2">
    <a href="{{ route('merchant.banners.index') }}" class="btn btn-light">Cancel</a>
    <button type="submit" class="btn btn-primary"><i class="ph-floppy-disk me-2"></i>Save Banner</button>
</div>

@include('shared.banners.form-script')
