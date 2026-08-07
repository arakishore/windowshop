@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Banner Library"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Storefront' => null, 'Banner Library' => null]"
    />
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-2">
            <h5 class="mb-0">Templates for {{ $activeShop->name }}</h5>
            <span class="badge bg-light text-body border">{{ $usedSlots }} of {{ $slotLimit }} banner slots used</span>
        </div>
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('merchant.banner-library.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label" for="search">Search</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" type="search" class="form-control" placeholder="Name or title">
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label" for="category">Category</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">All</option>
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label" for="position">Position</label>
                    <select id="position" name="position" class="form-select">
                        <option value="">All</option>
                        @foreach($positions as $value => $label)
                            <option value="{{ $value }}" @selected($filters['position'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label" for="event">Type</label>
                    <select id="event" name="event" class="form-select">
                        <option value="">All</option>
                        <option value="event" @selected($filters['event'] === 'event')>Event</option>
                        <option value="general" @selected($filters['event'] === 'general')>General</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit"><i class="ph-magnifying-glass me-2"></i>Filter</button>
                    <a class="btn btn-light" href="{{ route('merchant.banner-library.index') }}">Reset</a>
                </div>
            </form>
        </div>
        @if($usedSlots >= $slotLimit)
            <div class="alert alert-warning rounded-0 mb-0">
                This shop has reached its maximum of {{ $slotLimit }} banner slots. Edit or replace one of the existing banners.
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Preview</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Position</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($banners as $banner)
                            <tr>
                                <td style="width: 96px;"><img src="{{ asset('storage/'.$banner->desktop_image_path) }}" alt="{{ $banner->title }}" class="rounded border" style="width: 72px; height: 42px; object-fit: cover;"></td>
                                <td>{{ $banner->title }}</td>
                                <td><span class="badge {{ $banner->status === 'active' ? 'bg-success' : 'bg-light text-body border' }}">{{ ucfirst($banner->status) }}</span></td>
                                <td>{{ $banner->position?->label() }}</td>
                                <td class="text-center"><a href="{{ route('merchant.banners.edit', $banner) }}" class="btn btn-light border btn-sm">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($templates->isEmpty())
        <x-empty-state icon="ph-images-square" title="No templates found" message="Adjust the filters or check back after more templates are published." />
    @else
        <div class="row g-3">
            @foreach($templates as $template)
                <div class="col-xl-4 col-md-6">
                    <div class="card h-100">
                        <img src="{{ asset('storage/'.$template->desktop_image_path) }}" alt="{{ $template->name }}" class="card-img-top" style="aspect-ratio: 16 / 6; object-fit: cover;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between gap-2 mb-2">
                                <h6 class="mb-0">{{ $template->name }}</h6>
                                <span class="badge bg-light text-body border">{{ $template->categoryLabel() }}</span>
                            </div>
                            <div class="fw-semibold">{{ $template->default_title }}</div>
                            @if($template->default_subtitle)
                                <div class="text-muted fs-sm">{{ $template->default_subtitle }}</div>
                            @endif
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25">{{ $template->positionLabel() }}</span>
                                <span class="badge bg-light text-body border">{{ $template->mobile_image_path ? 'Mobile ready' : 'Desktop fallback' }}</span>
                                @if($template->event_code)
                                    <span class="badge bg-info-subtle text-info border">{{ $template->event_code }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <a href="{{ route('merchant.banners.create', ['template' => $template->uuid]) }}" class="btn btn-primary btn-sm {{ $usedSlots >= $slotLimit ? 'disabled' : '' }}">
                                <i class="ph-lightning me-1"></i>
                                Use Template
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-3">
            {{ $templates->links('pagination::admin-datatable') }}
        </div>
    @endif
@endsection
