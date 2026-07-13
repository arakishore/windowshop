{{-- Purpose: Shows merchant-scoped shop details. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Shop Details"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Merchants' => route('admin.merchants.index'), $merchant->business_name => route('admin.merchants.show', $merchant), 'Shops' => route('admin.merchants.shops.index', $merchant), $shop->name => null]"
        :action-url="route('admin.merchants.shops.index', $merchant)"
        action-label="Back to Shops"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    @include('admin.merchants.partials.management-header')
    @include('admin.merchants.partials.management-tabs')

    @php
        $statusConfig = $shopStatuses[$shop->status] ?? ['label' => ucfirst($shop->status), 'badge_class' => 'bg-secondary'];
    @endphp

    <div class="card mb-3">
        <div class="card-header d-flex flex-column flex-sm-row gap-2 align-items-sm-center justify-content-sm-between">
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h5 class="mb-0">{{ $shop->name }}</h5>
                    <span class="badge {{ $statusConfig['badge_class'] }}">{{ $statusConfig['label'] }}</span>
                </div>
                <div class="text-muted small">{{ $shop->rootProductCategory?->name ?? '-' }} · {{ $shop->slug }}</div>
            </div>
            <a href="{{ route('admin.merchants.shops.edit', [$merchant, $shop]) }}" class="btn btn-primary">
                <i class="ph-pencil-simple me-2"></i>
                Edit Shop
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Shop Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Shop Name</dt>
                        <dd class="col-sm-8">{{ $shop->name }}</dd>

                        <dt class="col-sm-4">Shop Type</dt>
                        <dd class="col-sm-8">{{ $shop->rootProductCategory?->name ?? '-' }}</dd>

                        <dt class="col-sm-4">Audiences</dt>
                        <dd class="col-sm-8">
                            @forelse($shop->audiences as $audience)
                                <span class="badge bg-light text-body border me-1">{{ $audience->name }}</span>
                            @empty
                                -
                            @endforelse
                        </dd>

                        <dt class="col-sm-4">Short Description</dt>
                        <dd class="col-sm-8">{{ $shop->short_description ?? '-' }}</dd>

                        <dt class="col-sm-4">Description</dt>
                        <dd class="col-sm-8">{{ $shop->description ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Public Contact</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8 text-break">{{ $shop->email ?? '-' }}</dd>

                        <dt class="col-sm-4">Mobile</dt>
                        <dd class="col-sm-8">{{ $shop->mobile ?? '-' }}</dd>

                        <dt class="col-sm-4">WhatsApp</dt>
                        <dd class="col-sm-8">{{ $shop->whatsapp_number ?? '-' }}</dd>

                        <dt class="col-sm-4">Website</dt>
                        <dd class="col-sm-8 text-break">
                            @if($shop->website_url)
                                <a href="{{ $shop->website_url }}" target="_blank" rel="noopener">{{ $shop->website_url }}</a>
                            @else
                                -
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Shop Address</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Address Line 1</dt>
                        <dd class="col-sm-8">{{ $shop->address_line_1 }}</dd>

                        <dt class="col-sm-4">Address Line 2</dt>
                        <dd class="col-sm-8">{{ $shop->address_line_2 ?? '-' }}</dd>

                        <dt class="col-sm-4">Landmark</dt>
                        <dd class="col-sm-8">{{ $shop->landmark ?? '-' }}</dd>

                        <dt class="col-sm-4">Country</dt>
                        <dd class="col-sm-8">{{ $shop->country?->name ?? '-' }}</dd>

                        <dt class="col-sm-4">State</dt>
                        <dd class="col-sm-8">{{ $shop->state?->name ?? '-' }}</dd>

                        <dt class="col-sm-4">City</dt>
                        <dd class="col-sm-8">{{ $shop->city?->name ?? '-' }}</dd>

                        <dt class="col-sm-4">Pincode</dt>
                        <dd class="col-sm-8">{{ $shop->pincode ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Map Location</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Latitude</dt>
                        <dd class="col-sm-8">{{ $shop->latitude ?? '-' }}</dd>

                        <dt class="col-sm-4">Longitude</dt>
                        <dd class="col-sm-8">{{ $shop->longitude ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Branding</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="fw-semibold mb-2">Logo</div>
                            @if($shop->logo_path)
                                <a href="{{ asset('storage/'.$shop->logo_path) }}" target="_blank" rel="noopener" class="d-inline-block text-decoration-none">
                                    <span class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 160px; height: 120px;">
                                        <img src="{{ asset('storage/'.$shop->logo_path) }}" alt="{{ $shop->name }} logo" style="width: 100%; height: 100%; object-fit: cover;">
                                    </span>
                                </a>
                                <div class="small text-muted mt-2 text-break">{{ $shop->logo_path }}</div>
                            @else
                                <div class="rounded bg-light border d-flex align-items-center justify-content-center text-muted" style="width: 160px; height: 120px;">
                                    No logo
                                </div>
                            @endif
                        </div>

                        <div class="col-lg-7">
                            <div class="fw-semibold mb-2">Banner</div>
                            @if($shop->banner_path)
                                <a href="{{ asset('storage/'.$shop->banner_path) }}" target="_blank" rel="noopener" class="d-block text-decoration-none">
                                    <span class="rounded overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 100%; max-width: 360px; aspect-ratio: 16 / 9;">
                                        <img src="{{ asset('storage/'.$shop->banner_path) }}" alt="{{ $shop->name }} banner" style="width: 100%; height: 100%; object-fit: cover;">
                                    </span>
                                </a>
                                <div class="small text-muted mt-2 text-break">{{ $shop->banner_path }}</div>
                            @else
                                <div class="rounded bg-light border d-flex align-items-center justify-content-center text-muted" style="width: 100%; max-width: 360px; aspect-ratio: 16 / 9;">
                                    No banner
                                </div>
                            @endif
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
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge {{ $statusConfig['badge_class'] }}">{{ $statusConfig['label'] }}</span>
                        </dd>

                        <dt class="col-sm-4">Admin Note</dt>
                        <dd class="col-sm-8">{{ $shop->admin_note ?? '-' }}</dd>

                        <dt class="col-sm-4">Created</dt>
                        <dd class="col-sm-8">{{ $shop->created_at?->format('d M Y h:i A') ?? '-' }}</dd>

                        <dt class="col-sm-4">Updated</dt>
                        <dd class="col-sm-8">{{ $shop->updated_at?->format('d M Y h:i A') ?? '-' }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
