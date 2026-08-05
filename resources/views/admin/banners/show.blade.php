@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header title="View Banner" :breadcrumbs="['Admin' => route('admin.dashboard'), 'Banners' => route('admin.banners.index'), $banner->title => null]" />
@endsection

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h5 class="mb-0">{{ $banner->title }}</h5>
            <a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-primary"><i class="ph-pencil-simple me-2"></i>Edit</a>
        </div>
        <div class="card-body">
            <img src="{{ asset('storage/'.$banner->desktop_image_path) }}" alt="{{ $banner->title }}" class="img-fluid rounded border mb-3">
            <dl class="row mb-0">
                <dt class="col-sm-3">Position</dt><dd class="col-sm-9">{{ $banner->position?->label() }}</dd>
                <dt class="col-sm-3">Owner</dt><dd class="col-sm-9">{{ $banner->merchant_id ? ($banner->merchant?->business_name.' / '.$banner->shop?->name) : 'Marketplace' }}</dd>
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9">{{ ucfirst($banner->status) }}</dd>
                <dt class="col-sm-3">Schedule</dt><dd class="col-sm-9">{{ $banner->starts_at ? app_datetime($banner->starts_at) : 'Immediate' }} to {{ $banner->ends_at ? app_datetime($banner->ends_at) : 'No end date' }}</dd>
            </dl>
        </div>
    </div>
@endsection
