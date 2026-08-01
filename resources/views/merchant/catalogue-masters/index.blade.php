{{-- Purpose: Shows merchant read-only product category and attribute masters scoped to the active shop type. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Categories & Attributes"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Catalog' => route('merchant.products.index'), 'Categories & Attributes' => null]"
        :action-url="route('merchant.products.create')"
        action-label="Add Product"
        action-icon="ph-plus"
    />
@endsection

@section('content')
    @php
        $statusClasses = ['active' => 'bg-success', 'inactive' => 'bg-light text-body border'];
        $requestStatusClasses = [
            'pending' => 'bg-warning bg-opacity-10 text-warning',
            'approved' => 'bg-success bg-opacity-10 text-success',
            'rejected' => 'bg-danger bg-opacity-10 text-danger',
            'needs_info' => 'bg-info bg-opacity-10 text-info',
        ];
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Please correct the request form.</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="ph-info mt-1"></i>
        <div>
            <div class="fw-semibold">Read-only catalogue masters for {{ $activeShop->name }}</div>
            <div>Only categories and attributes under shop type {{ $activeShop->rootProductCategory?->name ?? 'Selected shop type' }} are shown here. If something is missing, submit a request for admin approval.</div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 class="mb-0">Available Categories</h5>
            <span class="badge bg-light text-body border">{{ $categories->count() }} shown</span>
        </div>

        @if($categories->isEmpty())
            <x-empty-state icon="ph-tag" title="No categories available" message="No active categories are mapped under this shop type yet." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category</th>
                            <th>Parent</th>
                            <th>Path</th>
                            <th>Default Tax Class</th>
                            <th>Can Select in Product</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $category)
                            @php
                                $path = $categoryPaths[$category->id] ?? $category->name;
                                $depth = substr_count($path, ' > ');
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold" style="padding-left: {{ $depth * 20 }}px;">
                                        @if($depth > 0)
                                            <span class="text-muted">{{ str_repeat('-- ', $depth) }}</span>
                                        @endif
                                        {{ $category->name }}
                                    </div>
                                    @if($category->description)
                                        <div class="fs-sm text-muted">{{ \Illuminate\Support\Str::limit($category->description, 90) }}</div>
                                    @endif
                                </td>
                                <td>{{ $category->parent?->name ?? '-' }}</td>
                                <td class="text-muted">{{ $path }}</td>
                                <td>
                                    @if($category->defaultTaxClass)
                                        <span class="badge bg-info bg-opacity-10 text-info">{{ $category->defaultTaxClass->displayLabel() }}</span>
                                    @else
                                        <span class="badge bg-light text-body border">No default</span>
                                    @endif
                                </td>
                                <td>
                                    @if($category->is_selectable_leaf)
                                        <span class="badge bg-success bg-opacity-10 text-success">Yes</span>
                                    @else
                                        <span class="badge bg-light text-body border">No, parent only</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 class="mb-0">Available Product Attributes</h5>
            <span class="badge bg-light text-body border">{{ $attributeGroups->count() }} shown</span>
        </div>

        @if($attributeGroups->isEmpty())
            <x-empty-state icon="ph-list-bullets" title="No attributes available" message="No active product attributes are mapped under this shop type yet." />
        @else
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Attribute</th>
                            <th>Selection</th>
                            <th>Use</th>
                            <th>Values</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attributeGroups as $group)
                            @php
                                $mapping = $group->categoryMappings->first();
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
                                    <div class="d-flex flex-wrap gap-1">
                                        @if($mapping?->is_required)
                                            <span class="badge bg-danger bg-opacity-10 text-danger">Required</span>
                                        @endif
                                        @if($mapping?->is_variant)
                                            <span class="badge bg-primary bg-opacity-10 text-primary">Variant</span>
                                        @endif
                                        @if($mapping?->is_image_attribute)
                                            <span class="badge bg-info bg-opacity-10 text-info">Image</span>
                                        @endif
                                        @if(! $mapping?->is_required && ! $mapping?->is_variant && ! $mapping?->is_image_attribute)
                                            <span class="badge bg-light text-body border">Product detail</span>
                                        @endif
                                    </div>
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

    <div class="row g-3 mt-0">
        <div class="col-xl-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Request Missing Master</h5>
                </div>
                <form method="POST" action="{{ route('merchant.catalogue-masters.requests.store') }}">
                    @csrf
                    <div class="card-body">
                        <div class="alert alert-info d-flex align-items-start gap-2 py-2">
                            <i class="ph-info mt-1"></i>
                            <div>
                                <div class="fw-semibold">Need a category or attribute that is not listed?</div>
                                <div class="small mb-0">Send a request to admin with the suggested name and an example product. Once approved, it will become available for product setup in this shop type.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="request_type" class="form-label">Request Type <span class="text-danger">*</span></label>
                            <select id="request_type" name="request_type" class="form-select" required>
                                <option value="category" @selected(old('request_type') === 'category')>Category</option>
                                <option value="attribute" @selected(old('request_type') === 'attribute')>Attribute</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="suggested_name" class="form-label">Suggested Name <span class="text-danger">*</span></label>
                            <input id="suggested_name" name="suggested_name" type="text" value="{{ old('suggested_name') }}" class="form-control" placeholder="Example: Lip Balm, Fabric, Shade" required>
                        </div>
                        <div class="mb-3">
                            <label for="parent_product_category_id" class="form-label">Parent Category</label>
                            <select id="parent_product_category_id" name="parent_product_category_id" class="form-select">
                                <option value="">Not sure / Admin to decide</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('parent_product_category_id') === (string) $category->id)>
                                        {{ $categoryPaths[$category->id] ?? $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="example_product_name" class="form-label">Example Product</label>
                            <input id="example_product_name" name="example_product_name" type="text" value="{{ old('example_product_name') }}" class="form-control" placeholder="Product where this is needed">
                        </div>
                        <div>
                            <label for="description" class="form-label">Why Needed</label>
                            <textarea id="description" name="description" rows="3" class="form-control" placeholder="Short note for admin">{{ old('description') }}</textarea>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="ph-paper-plane-tilt me-2"></i>
                            Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Requests</h5>
                </div>
                @if($requests->isEmpty())
                    <x-empty-state icon="ph-paper-plane-tilt" title="No requests yet" message="Submitted category or attribute requests will appear here." />
                @else
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Request</th>
                                    <th>Parent</th>
                                    <th>Status</th>
                                    <th>Admin Note</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($requests as $requestRow)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $requestRow->suggested_name }}</div>
                                            <span class="badge bg-light text-body border">{{ ucfirst($requestRow->request_type) }}</span>
                                            @if($requestRow->example_product_name)
                                                <div class="text-muted small">Example: {{ $requestRow->example_product_name }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $requestRow->parentCategory?->name ?? '-' }}</td>
                                        <td>
                                            <span class="badge {{ $requestStatusClasses[$requestRow->status] ?? 'bg-light text-body border' }}">
                                                {{ str_replace('_', ' ', ucfirst($requestRow->status)) }}
                                            </span>
                                        </td>
                                        <td>{{ $requestRow->admin_note ?: '-' }}</td>
                                        <td>{{ app_datetime($requestRow->created_at) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
