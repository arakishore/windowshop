@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Marketplace Pages"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Content Management' => null, 'Pages' => null]"
        :action-url="route('admin.cms-pages.create')"
        action-label="Add New Page"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php($hasFilters = $filters['q'] !== '' || $filters['type'] !== '' || $filters['status'] !== '')
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">{{ $marketplaceName }} / Marketplace Pages</h5>
            <a href="#cms-page-filter-collapse" class="text-body" data-bs-toggle="collapse" aria-expanded="{{ $hasFilters ? 'true' : 'false' }}" aria-controls="cms-page-filter-collapse" title="Show filters"><i class="ph-arrow-circle-down"></i></a>
        </div>
        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="cms-page-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.cms-pages.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label for="cms_page_q" class="form-label">Search</label>
                        <input id="cms_page_q" name="q" type="search" value="{{ $filters['q'] }}" class="form-control" placeholder="Page title or slug">
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label for="cms_page_type" class="form-label">Type</label>
                        <select id="cms_page_type" name="type" class="form-select">
                            <option value="">All</option>
                            <option value="standard" @selected($filters['type'] === 'standard')>Standard</option>
                            <option value="custom" @selected($filters['type'] === 'custom')>Custom</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-lg-2">
                        <label for="cms_page_status" class="form-label">Status</label>
                        <select id="cms_page_status" name="status" class="form-select">
                            <option value="">All</option>
                            <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                            <option value="published" @selected($filters['status'] === 'published')>Published</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-lg-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="ph-magnifying-glass me-2"></i>Filter</button>
                        <a href="{{ route('admin.cms-pages.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        @if ($pages->isNotEmpty())
            <form id="cms-page-bulk-form" method="POST" action="{{ route('admin.cms-pages.bulk-action') }}">
                @csrf
                <div class="card-body border-bottom d-flex flex-wrap align-items-center gap-2">
                    <div class="input-group" style="max-width: 280px;">
                        <label class="input-group-text" for="cms_page_bulk_action">Bulk Actions</label>
                        <select id="cms_page_bulk_action" name="action" class="form-select" required>
                            <option value="">Choose</option>
                            <option value="publish">Publish</option>
                            <option value="draft">Move to Draft</option>
                            <option value="delete">Delete Custom Pages</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-light"><i class="ph-check-square-offset me-2"></i>Apply</button>
                    <span class="text-muted small" id="cms-page-selected-count">0 selected</span>
                </div>
            </form>
        @endif
        @error('page_ids')<div class="alert alert-danger m-3">{{ $message }}</div>@enderror
        @error('action')<div class="alert alert-danger m-3">{{ $message }}</div>@enderror
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width: 44px;"><input id="cms-page-select-all" type="checkbox" class="form-check-input" aria-label="Select all pages"></th>
                        <th>Page</th>
                        <th>Type</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pages as $page)
                        <tr>
                            <td class="text-center"><input type="checkbox" name="page_ids[]" value="{{ $page->id }}" form="cms-page-bulk-form" class="form-check-input js-cms-page-checkbox" aria-label="Select {{ $page->title }}"></td>
                            <td><a class="fw-semibold text-body text-decoration-none" href="{{ route('admin.cms-pages.edit', $page) }}">{{ $page->title }}</a></td>
                            <td><span class="badge {{ $page->page_type === 'standard' ? 'bg-secondary bg-opacity-10 text-secondary' : 'bg-info bg-opacity-10 text-info' }}">{{ ucfirst($page->page_type) }}</span></td>
                            <td><code>{{ $page->slug }}</code></td>
                            <td><span class="badge {{ $page->status === 'published' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($page->status) }}</span></td>
                            <td>{{ app_datetime($page->updated_at) }}</td>
                            <td class="text-center">
                                <div class="list-icons justify-content-center">
                                    <a href="{{ route('admin.cms-pages.edit', $page) }}" class="list-icons-item text-primary" title="Edit" aria-label="Edit {{ $page->title }}"><i class="ph-pencil-simple"></i></a>
                                    <a href="{{ route('admin.cms-pages.preview', $page) }}" class="list-icons-item text-info" title="Preview" aria-label="Preview {{ $page->title }}"><i class="ph-eye"></i></a>
                                    @if ($page->page_type === 'custom')
                                        <form method="POST" action="{{ route('admin.cms-pages.destroy', $page) }}" class="d-inline" onsubmit="return confirm('Delete this custom page?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="list-icons-item text-danger border-0 bg-transparent p-0" title="Delete" aria-label="Delete {{ $page->title }}"><i class="ph-trash"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No marketplace pages found. Adjust the current filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($pages->hasPages())
            <div class="card-body">{{ $pages->links('pagination::admin-datatable') }}</div>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('cms-page-bulk-form');
            const selectAll = document.getElementById('cms-page-select-all');
            const checkboxes = Array.from(document.querySelectorAll('.js-cms-page-checkbox'));
            const count = document.getElementById('cms-page-selected-count');
            const updateCount = function () {
                const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
                if (count) count.textContent = selected + ' selected';
                selectAll.checked = selected > 0 && selected === checkboxes.length;
                selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
            };

            selectAll.addEventListener('change', function () {
                checkboxes.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
                updateCount();
            });
            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateCount));

            if (form) form.addEventListener('submit', function (event) {
                const action = form.querySelector('[name="action"]').value;
                if (!checkboxes.some((checkbox) => checkbox.checked)) {
                    event.preventDefault();
                    window.alert('Select at least one page.');
                } else if (action === 'delete' && !window.confirm('Delete the selected custom pages? This cannot be undone.')) {
                    event.preventDefault();
                }
            });
        });
    </script>
@endpush
