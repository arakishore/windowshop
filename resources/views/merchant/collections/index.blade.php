{{-- Purpose: Lists merchant product collections for the active shop. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Collections"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Catalog' => route('merchant.products.index'), 'Collections' => null]"
        :action-url="route('merchant.collections.create')"
        action-label="Create Collection"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Collections for {{ $activeShop->name }}</h5>
        </div>
        <div class="card-body border-bottom">
            <form method="GET" action="{{ route('merchant.collections.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label" for="search">Search</label>
                    <input id="search" name="search" value="{{ $filters['search'] }}" type="search" class="form-control" placeholder="Collection name or slug">
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
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary flex-fill" type="submit">
                        <i class="ph-magnifying-glass me-2"></i>
                        Filter
                    </button>
                    <a class="btn btn-light" href="{{ route('merchant.collections.index') }}">Reset</a>
                </div>
            </form>
        </div>

        @if($collections->isEmpty())
            <x-empty-state icon="ph-stack" title="No collections found" message="Create a collection or adjust the current filters." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Collection Name</th>
                            <th>Products</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($collections as $collection)
                            <tr>
                                <td>
                                    <a href="{{ route('merchant.collections.edit', $collection) }}" class="fw-semibold text-body text-decoration-none">
                                        {{ $collection->name }}
                                    </a>
                                    <div><code>{{ $collection->slug }}</code></div>
                                    @if($collection->description)
                                        <div class="text-muted small mt-1">{{ Str::limit($collection->description, 90) }}</div>
                                    @endif
                                </td>
                                <td>{{ $collection->products_count }}</td>
                                <td>{{ $collection->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $statuses[$collection->status]['badge_class'] ?? 'bg-secondary' }}">
                                        {{ $statuses[$collection->status]['label'] ?? ucfirst($collection->status) }}
                                    </span>
                                </td>
                                <td>{{ app_datetime($collection->updated_at) }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('merchant.collections.edit', $collection) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
                                        <a href="{{ route('merchant.collections.products', $collection) }}" class="list-icons-item text-info" data-bs-popup="tooltip" title="Manage Products">
                                            <i class="ph-package"></i>
                                        </a>
                                        <form method="POST" action="{{ route('merchant.collections.toggle-status', $collection) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="list-icons-item {{ $collection->status === 'active' ? 'text-warning' : 'text-success' }} border-0 bg-transparent p-0" data-bs-popup="tooltip" title="{{ $collection->status === 'active' ? 'Deactivate' : 'Activate' }}">
                                                <i class="{{ $collection->status === 'active' ? 'ph-pause-circle' : 'ph-play-circle' }}"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('merchant.collections.destroy', $collection) }}" class="d-inline js-confirm-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-confirm-delete" data-bs-popup="tooltip" title="Delete">
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
            <div class="card-body">{{ $collections->onEachSide(1)->links('pagination::admin-datatable') }}</div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-confirm-delete');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-confirm-delete-form');

                bootbox.confirm({
                    title: 'Delete Collection',
                    message: 'Delete this collection?<br><br>Products will remain in your catalog.',
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
