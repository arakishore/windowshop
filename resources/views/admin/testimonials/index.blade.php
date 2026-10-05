{{-- Purpose: Lists marketing testimonials for admin search, filtering, and management. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Testimonials"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Marketing' => null, 'Testimonials' => null]"
        :action-url="route('admin.testimonials.create')"
        action-label="Create Testimonial"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $typeLabels = ['customer' => 'Customer Say', 'merchant' => 'Merchant Say'];
        $hasFilters = $filters['search'] !== '' || $filters['type'] || $filters['status'];
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Testimonial List</h5>
            <a href="#testimonial-filter-collapse" class="text-body collapsed testimonial-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="testimonial-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="testimonial-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.testimonials.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Name, business or location">
                    </div>
                    <div class="col-md-3">
                        <label for="type" class="form-label">Type</label>
                        <select id="type" name="type" class="form-select">
                            <option value="">All</option>
                            <option value="customer" @selected($filters['type'] === 'customer')>Customer Say</option>
                            <option value="merchant" @selected($filters['type'] === 'merchant')>Merchant Say</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All</option>
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('admin.testimonials.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($testimonials->isEmpty())
            <x-empty-state icon="ph-chat-teardrop-text" title="No testimonials found" message="Create a testimonial or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Photo</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Business / Location</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($testimonials as $testimonial)
                            <tr>
                                <td style="width: 76px;">
                                    <div class="rounded-circle overflow-hidden bg-light border d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                        @if($testimonial->photo_path)
                                            <img src="{{ asset('storage/'.$testimonial->photo_path) }}" alt="{{ $testimonial->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                        @else
                                            <span class="fw-semibold text-muted">{{ mb_strtoupper(mb_substr(trim($testimonial->name), 0, 1)) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $testimonial->name }}</div>
                                    @if($testimonial->rating)
                                        <div class="fs-sm text-muted">Rated {{ $testimonial->rating }} out of 5</div>
                                    @endif
                                    <div class="fs-sm text-muted">{{ \Illuminate\Support\Str::limit($testimonial->body, 80) }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-body border">
                                        {{ $typeLabels[$testimonial->type] ?? ucfirst($testimonial->type) }}
                                    </span>
                                </td>
                                <td>
                                    @if($testimonial->business_name)
                                        <div class="fw-semibold">{{ $testimonial->business_name }}</div>
                                    @endif
                                    @if($testimonial->designation)
                                        <div class="fs-sm text-muted">{{ $testimonial->designation }}</div>
                                    @endif
                                    @if($testimonial->location)
                                        <div class="fs-sm text-muted">{{ $testimonial->location }}</div>
                                    @endif
                                    @if(! $testimonial->business_name && ! $testimonial->designation && ! $testimonial->location)
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $testimonial->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $statusClasses[$testimonial->status] ?? 'bg-secondary' }}">
                                        {{ ucfirst($testimonial->status) }}
                                    </span>
                                </td>
                                <td>{{ app_datetime($testimonial->created_at) }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('admin.testimonials.edit', $testimonial) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.testimonials.destroy', $testimonial) }}" class="d-inline js-delete-testimonial-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-delete-testimonial" data-bs-popup="tooltip" title="Delete">
                                                <i class="ph-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between">
                <div class="text-muted mb-3 mb-lg-0">
                    Showing {{ $testimonials->firstItem() }} to {{ $testimonials->lastItem() }} of {{ $testimonials->total() }} entries
                </div>
                {{ $testimonials->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .testimonial-filter-toggle i {
            display: inline-block;
            transition: transform 0.2s ease-in-out;
        }

        .testimonial-filter-toggle:not(.collapsed) i {
            transform: rotate(180deg);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-testimonial');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-delete-testimonial-form');

                bootbox.confirm({
                    title: 'Delete Testimonial',
                    message: 'Are you sure you want to delete this testimonial?',
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-link',
                        },
                        confirm: {
                            label: 'Yes, Delete',
                            className: 'btn-danger',
                        },
                    },
                    callback: function (confirmed) {
                        if (confirmed) {
                            form.submit();
                        }
                    },
                });
            });
        });
    </script>
@endpush
