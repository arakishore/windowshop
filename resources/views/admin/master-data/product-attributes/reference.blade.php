{{-- Purpose: Read-only admin reference of active product attributes, values, and shop-type mappings. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Attribute Reference"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Attribute Reference' => null]"
        :action-url="route('admin.master.product-attributes.index')"
        action-label="Manage Attributes"
        action-icon="ph-pencil-simple"
        action-class="btn-light border"
    />
@endsection

@section('content')
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="ph-info mt-1"></i>
        <div>
            <div class="fw-semibold">Product attribute reference</div>
            <div>This page is for quick checking only. Add, edit, or delete attribute groups and values from Product Attributes.</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 class="mb-0">Available Product Attributes</h5>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-light text-body border">{{ $groups->count() }} shown</span>
                <span class="badge bg-light text-body border">{{ $rootCategories->count() }} shop types</span>
            </div>
        </div>

        @if($groups->isEmpty())
            <x-empty-state icon="ph-list-bullets" title="No attributes available" message="No active product attributes are configured yet." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Attribute</th>
                            <th>Selection</th>
                            <th>Mapped Shop Types</th>
                            <th>Use</th>
                            <th>Values</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($groups as $group)
                            @php
                                $mappings = $group->categoryMappings
                                    ->filter(fn ($mapping) => $mapping->rootCategory)
                                    ->sortBy(fn ($mapping) => $mapping->rootCategory->sort_order);
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $group->name }}</div>
                                    <code>{{ $group->code }}</code>
                                    @if($group->description)
                                        <div class="fs-sm text-muted">{{ \Illuminate\Support\Str::limit($group->description, 90) }}</div>
                                    @endif
                                </td>
                                <td>{{ ucfirst($group->selection_type) }}</td>
                                <td>
                                    @if($mappings->isEmpty())
                                        <span class="badge bg-warning bg-opacity-10 text-warning">Not mapped</span>
                                    @else
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($mappings as $mapping)
                                                <span class="badge bg-light text-body border">{{ $mapping->rootCategory->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($mappings->isEmpty())
                                        <span class="text-muted">-</span>
                                    @else
                                        <div class="d-flex flex-wrap gap-1">
                                            @if($mappings->contains('is_required', true))
                                                <span class="badge bg-danger bg-opacity-10 text-danger">Required</span>
                                            @endif
                                            @if($mappings->contains('is_variant', true))
                                                <span class="badge bg-primary bg-opacity-10 text-primary">Variant</span>
                                            @endif
                                            @if($mappings->contains('is_image_attribute', true))
                                                <span class="badge bg-info bg-opacity-10 text-info">Image</span>
                                            @endif
                                            @if(! $mappings->contains('is_required', true) && ! $mappings->contains('is_variant', true) && ! $mappings->contains('is_image_attribute', true))
                                                <span class="badge bg-light text-body border">Product detail</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($group->values->isEmpty())
                                        <span class="text-muted">No values configured</span>
                                    @else
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($group->values as $value)
                                                <span class="badge bg-light text-body border">{{ $value->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
