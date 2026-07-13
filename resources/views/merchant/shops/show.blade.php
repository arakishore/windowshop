{{-- Purpose: Merchant-safe shop detail page. --}}
@extends('layouts.merchant')

@section('title', $shop->name.' | My Shops | WindowShop')

@section('page_title', 'Shop Details')

@section('content')
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
            <div class="d-flex gap-2">
                <a href="{{ route('merchant.shops.index') }}" class="btn btn-light border">
                    <i class="ph-arrow-left me-2"></i>
                    Back
                </a>
                <a href="{{ route('merchant.shops.edit', $shop) }}" class="btn btn-primary">
                    <i class="ph-pencil-simple me-2"></i>
                    Edit Shop
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0">Shop Information</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Shop Name</dt>
                        <dd class="col-sm-8">{{ $shop->name }}</dd>
                        <dt class="col-sm-4">Shop Type</dt>
                        <dd class="col-sm-8">{{ $shop->rootProductCategory?->name ?? '-' }}</dd>
                        <dt class="col-sm-4">Audience</dt>
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
                <div class="card-header"><h5 class="mb-0">Public Contact</h5></div>
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
                <div class="card-header"><h5 class="mb-0">Shop Address</h5></div>
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
                <div class="card-header"><h5 class="mb-0">Account Information</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Merchant</dt>
                        <dd class="col-sm-8">{{ $shop->merchant?->business_name ?? '-' }}</dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><span class="badge {{ $statusConfig['badge_class'] }}">{{ $statusConfig['label'] }}</span></dd>
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
