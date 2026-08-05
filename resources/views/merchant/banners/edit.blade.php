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
@endsection
