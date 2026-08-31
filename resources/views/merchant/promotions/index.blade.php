{{-- Purpose: Lists merchant shop-scoped quick offers, custom offers, and coupon-backed promotions. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Offers"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Offers' => null]"
        :action-url="route('merchant.promotions.create')"
        action-label="Create Offer"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $hasFilters = $filters['search'] !== '' || $filters['status'] !== '' || $filters['activation_type'] !== '';
        $validityText = function ($promotion): string {
            if ($promotion->starts_at === null && $promotion->ends_at === null) {
                return 'No schedule';
            }

            if ($promotion->status === \App\Models\Promotion::STATUS_ACTIVE && $promotion->starts_at && $promotion->ends_at === null) {
                return 'Started '.$promotion->starts_at->format('d M Y').' &middot; No end date';
            }

            if ($promotion->starts_at && $promotion->ends_at) {
                return $promotion->starts_at->format('d M Y').' &ndash; '.$promotion->ends_at->format('d M Y');
            }

            if ($promotion->starts_at) {
                return 'Starts '.$promotion->starts_at->format('d M Y');
            }

            return 'Ends '.$promotion->ends_at->format('d M Y');
        };
    @endphp

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Offers</h5>
            <div class="text-muted small">{{ $activeShop->name }}</div>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('merchant.promotions.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="search">Search</label>
                    <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Offer name">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All</option>
                        @foreach($statuses as $value => $status)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $status['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="activation_type">Activation</label>
                    <select id="activation_type" name="activation_type" class="form-select">
                        <option value="">All</option>
                        @foreach($activationTypes as $value => $label)
                            <option value="{{ $value }}" @selected($filters['activation_type'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit">
                        <i class="ph-magnifying-glass me-2"></i>
                        Search
                    </button>
                    <a class="btn btn-light" href="{{ route('merchant.promotions.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Quick Offers</h5>
            <div class="text-muted small">Starter offers generated for this shop</div>
        </div>

        @if($starterPromotions->isEmpty())
            <x-empty-state icon="ph-sparkle" title="No starter offers found" message="{{ $hasFilters ? 'Adjust the filters to find starter offers.' : 'Starter offers will appear after promotion templates are seeded.' }}" />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Offer Name</th>
                            <th>Type</th>
                            <th>Activation</th>
                            <th>Validity</th>
                            <th>Status</th>
                            <th>Usage Limit</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($starterPromotions as $promotion)
                            @php($setupComplete = $promotion->isSetupComplete())
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $promotion->name }}</div>
                                    <div class="mt-1"><span class="badge bg-light text-body border">Quick Offer</span></div>
                                </td>
                                <td>{{ $promotion->template?->name ?? '-' }}</td>
                                <td>
                                    {{ $activationTypes[$promotion->activation_type] ?? ucfirst($promotion->activation_type) }}
                                    @if($promotion->coupons->isNotEmpty())
                                        <div><code>{{ $promotion->coupons->first()->code }}</code></div>
                                    @endif
                                </td>
                                <td>{!! $validityText($promotion) !!}</td>
                                <td>
                                    <span class="badge {{ $promotion->setupStatusBadgeClass() }}">{{ $promotion->setupStatusLabel() }}</span>
                                    @unless($setupComplete)
                                        <div class="text-muted small">{{ $promotion->setupIssues()[0] }}</div>
                                    @endunless
                                </td>
                                <td>
                                    {{ $promotion->total_usage_limit ? number_format($promotion->total_usage_limit) : 'Unlimited' }}
                                    @if($promotion->per_customer_usage_limit)
                                        <div class="text-muted small">{{ $promotion->per_customer_usage_limit }} per customer</div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex justify-content-center gap-2">
                                        <a href="{{ route('merchant.promotions.edit', $promotion) }}" class="btn btn-sm {{ $setupComplete ? 'btn-light' : 'btn-primary' }}">
                                            <i class="{{ $setupComplete ? 'ph-pencil-simple' : 'ph-wrench' }} me-1"></i>
                                            {{ $setupComplete ? 'Edit' : 'Set Up' }}
                                        </a>
                                        @if($setupComplete || $promotion->status === \App\Models\Promotion::STATUS_ACTIVE)
                                            <form method="POST" action="{{ route('merchant.promotions.toggle-status', $promotion) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm {{ $promotion->status === \App\Models\Promotion::STATUS_ACTIVE ? 'btn-warning' : 'btn-success' }}">
                                                    <i class="ph-power me-1"></i>
                                                    {{ $promotion->status === \App\Models\Promotion::STATUS_ACTIVE ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">My Offers</h5>
            <div class="text-muted small">Merchant-created offers</div>
        </div>

        @if($customPromotions->isEmpty())
            <x-empty-state icon="ph-percent" title="No offers found" message="{{ $hasFilters ? 'Adjust the filters to find offers.' : 'Create your first offer from a promotion template.' }}" />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Offer Name</th>
                            <th>Type</th>
                            <th>Activation</th>
                            <th>Validity</th>
                            <th>Status</th>
                            <th>Usage Limit</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customPromotions as $promotion)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $promotion->name }}</div>
                                </td>
                                <td>{{ $promotion->template?->name ?? '-' }}</td>
                                <td>
                                    {{ $activationTypes[$promotion->activation_type] ?? ucfirst($promotion->activation_type) }}
                                    @if($promotion->coupons->isNotEmpty())
                                        <div><code>{{ $promotion->coupons->first()->code }}</code></div>
                                    @endif
                                </td>
                                <td>{!! $validityText($promotion) !!}</td>
                                <td>
                                    <span class="badge {{ $promotion->setupStatusBadgeClass() }}">{{ $promotion->setupStatusLabel() }}</span>
                                </td>
                                <td>
                                    {{ $promotion->total_usage_limit ? number_format($promotion->total_usage_limit) : 'Unlimited' }}
                                    @if($promotion->per_customer_usage_limit)
                                        <div class="text-muted small">{{ $promotion->per_customer_usage_limit }} per customer</div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex justify-content-center gap-2">
                                        <a href="{{ route('merchant.promotions.edit', $promotion) }}" class="btn btn-sm btn-light">
                                            <i class="ph-pencil-simple me-1"></i>
                                            Edit
                                        </a>
                                        @if($promotion->isSetupComplete() || $promotion->status === \App\Models\Promotion::STATUS_ACTIVE)
                                            <form method="POST" action="{{ route('merchant.promotions.toggle-status', $promotion) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm {{ $promotion->status === \App\Models\Promotion::STATUS_ACTIVE ? 'btn-warning' : 'btn-success' }}">
                                                    <i class="ph-power me-1"></i>
                                                    {{ $promotion->status === \App\Models\Promotion::STATUS_ACTIVE ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('merchant.promotions.destroy', $promotion) }}" class="d-inline js-confirm-action-form" data-confirm-message="Delete this offer?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="ph-trash me-1"></i>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body">
                {{ $customPromotions->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection
