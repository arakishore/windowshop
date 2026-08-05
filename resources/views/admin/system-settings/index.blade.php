{{-- Purpose: Lists seeded global system settings for admin visibility and focused edits. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="System Settings"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'System Settings' => null]"
    />
@endsection

@section('content')
    @php
        $hasFilters = $filters['search'] !== '' || $filters['group_id'] || $filters['status'] || $filters['value_type'];
    @endphp

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">System Setting List</h5>
            <a href="#system-setting-filter-collapse" class="text-body collapsed system-setting-filter-toggle" data-bs-toggle="collapse" aria-expanded="false" aria-controls="system-setting-filter-collapse">
                <i class="ph-arrow-circle-down"></i>
            </a>
        </div>

        <div class="collapse {{ $hasFilters ? 'show' : '' }}" id="system-setting-filter-collapse">
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('admin.system-settings.index') }}" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input id="search" name="search" type="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Key, label, value, description">
                    </div>
                    <div class="col-md-3">
                        <label for="group_id" class="form-label">Group</label>
                        <select id="group_id" name="group_id" class="form-select">
                            <option value="">All</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->getKey() }}" @selected((string) $filters['group_id'] === (string) $group->getKey())>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="value_type" class="form-label">Value Type</label>
                        <select id="value_type" name="value_type" class="form-select">
                            <option value="">All</option>
                            @foreach($valueTypes as $type)
                                <option value="{{ $type }}" @selected($filters['value_type'] === $type)>{{ Str::headline($type) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">All</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="ph-magnifying-glass me-2"></i>
                            Filter
                        </button>
                        <a href="{{ route('admin.system-settings.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        @if($settings->isEmpty())
            <x-empty-state icon="ph-gear-six" title="No system settings found" message="Run the system setting seeders or adjust the current filters." />
        @else
            <div class="table-responsive datatable-wrapper">
                <table class="table table-bordered table-hover align-middle datatable-highlight mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Group</th>
                            <th>Key</th>
                            <th>Label</th>
                            <th>Value</th>
                            <th>Type</th>
                            <th>Public</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($settings as $setting)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $setting->group?->name ?? 'Ungrouped' }}</div>
                                    <div class="text-muted fs-sm">{{ $setting->group?->slug }}</div>
                                </td>
                                <td><code>{{ $setting->key }}</code></td>
                                <td>
                                    <div class="fw-semibold">{{ $setting->label }}</div>
                                    @if($setting->description)
                                        <div class="text-muted fs-sm">{{ Str::limit($setting->description, 80) }}</div>
                                    @endif
                                </td>
                                <td class="text-break" style="max-width: 220px;">{{ Str::limit((string) $setting->value, 80) }}</td>
                                <td><span class="badge bg-light text-body border">{{ Str::headline($setting->value_type) }}</span></td>
                                <td>{{ $setting->is_public ? 'Yes' : 'No' }}</td>
                                <td>
                                    <span class="badge {{ $setting->status === 'active' ? 'bg-success' : 'bg-light text-body border' }}">{{ Str::headline($setting->status) }}</span>
                                </td>
                                <td class="text-muted fs-sm">{{ app_datetime($setting->updated_at) }}</td>
                                <td class="text-center">
                                    <a href="{{ route('admin.system-settings.edit', $setting) }}" class="list-icons-item text-primary" title="Edit">
                                        <i class="ph-pencil-simple"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body d-lg-flex align-items-lg-center justify-content-lg-between">
                <div class="text-muted mb-3 mb-lg-0">
                    Showing {{ $settings->firstItem() }} to {{ $settings->lastItem() }} of {{ $settings->total() }} entries
                </div>
                {{ $settings->onEachSide(1)->links('pagination::admin-datatable') }}
            </div>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .system-setting-filter-toggle i { display: inline-block; transition: transform 0.2s ease-in-out; }
        .system-setting-filter-toggle:not(.collapsed) i { transform: rotate(180deg); }
    </style>
@endpush
