@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header title="Edit Banner" :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Banners' => route('merchant.banners.index'), $banner->title => null]" />
@endsection

@section('content')
    <div class="card">
        <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2">
            <h5 class="mb-0">Banner Details</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light border btn-sm" data-banner-accordion="open">
                    <i class="ph-arrows-out-simple me-1"></i>
                    Open All
                </button>
                <button type="button" class="btn btn-light border btn-sm" data-banner-accordion="collapse">
                    <i class="ph-arrows-in-simple me-1"></i>
                    Collapse All
                </button>
            </div>
        </div>
        <form method="POST" action="{{ route('merchant.banners.update', $banner) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('merchant.banners.partials.form')
        </form>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h5 class="mb-0">Replace Template</h5>
        </div>
        <form method="POST" action="{{ route('merchant.banners.replace-template', $banner) }}">
            @csrf
            @method('PATCH')
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="replace_banner_template_uuid">Template</label>
                    <select id="replace_banner_template_uuid" name="banner_template_uuid" class="form-select">
                        @foreach($bannerTemplates as $template)
                            <option value="{{ $template->uuid }}" @selected($banner->banner_template_id === $template->getKey())>{{ $template->name }} - {{ $template->positionLabel() }}</option>
                        @endforeach
                    </select>
                    @error('banner_template_uuid')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="apply_template_defaults">Apply template defaults?</label>
                    <select id="apply_template_defaults" name="apply_template_defaults" class="form-select">
                        <option value="images_only">Replace image only</option>
                        <option value="text">Replace image and reset title/subtitle/button</option>
                        <option value="all">Replace all template defaults including position</option>
                    </select>
                </div>
            </div>
            <div class="card-footer text-end">
                <button type="submit" class="btn btn-light border"><i class="ph-arrows-clockwise me-2"></i>Replace Template</button>
            </div>
        </form>
    </div>
@endsection
