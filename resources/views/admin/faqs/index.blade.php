{{-- Purpose: Lists marketing FAQs for admin search, filtering, and management. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="FAQs"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Marketing' => null, 'FAQs' => null]"
        :action-url="route('admin.faqs.create')"
        action-label="Create FAQ"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        use App\Models\Faq;
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $categoryLabels = Faq::categories();
        $hasFilters = $filters['search'] !== '' || $filters['category'] || $filters['status'];
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">FAQ List</h5>
            <a href="#faq-filter-collapse" class="text-body collapsed faq-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="faq-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="faq-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.faqs.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Question or answer">
                    </div>
                    <div class="col-md-3">
                        <label for="category" class="form-label">Category</label>
                        <select id="category" name="category" class="form-select">
                            <option value="">All</option>
                            @foreach($categoryLabels as $value => $label)
                                <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                            @endforeach
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
                        <a href="{{ route('admin.faqs.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($faqs->isEmpty())
            <x-empty-state icon="ph-question" title="No FAQs found" message="Create an FAQ or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Question</th>
                            <th>Category</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($faqs as $faq)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $faq->question }}</div>
                                    <div class="fs-sm text-muted">{{ \Illuminate\Support\Str::limit($faq->answer, 80) }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-body border">
                                        {{ $categoryLabels[$faq->category] ?? ucfirst($faq->category) }}
                                    </span>
                                </td>
                                <td>{{ $faq->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $statusClasses[$faq->status] ?? 'bg-secondary' }}">
                                        {{ ucfirst($faq->status) }}
                                    </span>
                                </td>
                                <td>{{ app_datetime($faq->created_at) }}</td>
                                <td class="text-center">
                                    <div class="list-icons justify-content-center">
                                        <a href="{{ route('admin.faqs.edit', $faq) }}" class="list-icons-item text-primary" data-bs-popup="tooltip" title="Edit">
                                            <i class="ph-pencil-simple"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.faqs.destroy', $faq) }}" class="d-inline js-delete-faq-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="list-icons-item text-danger border-0 bg-transparent p-0 js-delete-faq" data-bs-popup="tooltip" title="Delete">
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
                    Showing {{ $faqs->firstItem() }} to {{ $faqs->lastItem() }} of {{ $faqs->total() }} entries
                </div>
                {{ $faqs->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .faq-filter-toggle i {
            display: inline-block;
            transition: transform 0.2s ease-in-out;
        }

        .faq-filter-toggle:not(.collapsed) i {
            transform: rotate(180deg);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-delete-faq');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-delete-faq-form');

                bootbox.confirm({
                    title: 'Delete FAQ',
                    message: 'Are you sure you want to delete this FAQ?',
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
