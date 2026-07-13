{{-- Purpose: Provides the admin tab-based product editing workspace. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Edit Product"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Products' => route('admin.products.index'), $product->product_name => null]"
        :action-url="route('admin.products.index')"
        action-label="Back to Products"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    @php
        $activeTab = request()->query('tab', 'basic');
        $tabs = [
            'basic' => ['label' => 'Basic Information', 'icon' => 'ph-info'],
            'attributes' => ['label' => 'Attributes', 'icon' => 'ph-sliders-horizontal'],
            'variants' => ['label' => 'Variants & Pricing', 'icon' => 'ph-tag'],
            'images' => ['label' => 'Images', 'icon' => 'ph-images'],
            'description' => ['label' => 'Description & SEO', 'icon' => 'ph-text-aa'],
            'preview' => ['label' => 'Preview', 'icon' => 'ph-eye'],
        ];
    @endphp

    <div class="card">
        <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-2">
            <div>
                <h5 class="mb-1">{{ $product->product_name }}</h5>
                <div class="text-muted">
                    {{ $product->shop?->name ?? '-' }} · {{ $product->category?->name ?? '-' }} · {{ $product->brand?->name ?? '-' }}
                </div>
            </div>
            <span class="badge {{ $statuses[$product->status]['badge_class'] ?? 'bg-secondary' }}">
                {{ $statuses[$product->status]['label'] ?? ucfirst($product->status) }}
            </span>
        </div>

        <div class="card-body border-bottom">
            <ul class="nav nav-tabs nav-tabs-highlight mb-0">
                @foreach($tabs as $key => $tab)
                    <li class="nav-item">
                        <a href="#product-tab-{{ $key }}" class="nav-link {{ $activeTab === $key ? 'active' : '' }}" data-bs-toggle="tab">
                            <i class="{{ $tab['icon'] }} me-2"></i>
                            {{ $tab['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade {{ $activeTab === 'basic' ? 'show active' : '' }}" id="product-tab-basic">
                <form method="POST" action="{{ route('admin.products.update', $product) }}">
                    @csrf
                    @method('PUT')
                    @include('admin.products.partials.quick-form', ['submitLabel' => 'Update Basic Information', 'includeShortDescription' => true])
                </form>
            </div>

            <div class="tab-pane fade {{ $activeTab === 'attributes' ? 'show active' : '' }}" id="product-tab-attributes">
                @include('admin.products.partials.tab-placeholder', [
                    'icon' => 'ph-sliders-horizontal',
                    'title' => 'Attributes',
                    'message' => 'Attribute assignment will be managed here for this product.',
                    'meta' => $product->attributes->count().' assigned attributes',
                ])
            </div>

            <div class="tab-pane fade {{ $activeTab === 'variants' ? 'show active' : '' }}" id="product-tab-variants">
                @include('admin.products.partials.tab-placeholder', [
                    'icon' => 'ph-tag',
                    'title' => 'Variants & Pricing',
                    'message' => 'Variant SKUs, prices, stock, and default variant controls will be managed here.',
                    'meta' => $product->variants->count().' variants',
                ])
            </div>

            <div class="tab-pane fade {{ $activeTab === 'images' ? 'show active' : '' }}" id="product-tab-images">
                @include('admin.products.partials.tab-placeholder', [
                    'icon' => 'ph-images',
                    'title' => 'Images',
                    'message' => 'Product and variant images will be uploaded and ordered here.',
                    'meta' => $product->images->count().' images',
                ])
            </div>

            <div class="tab-pane fade {{ $activeTab === 'description' ? 'show active' : '' }}" id="product-tab-description">
                <div class="card-body">
                    @php
                        $hasDescriptionContent = filled($product->short_description) || filled($product->description) || filled($product->meta_title) || filled($product->meta_description);
                    @endphp

                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-2 mb-3">
                        <div>
                            <div class="fw-semibold">Selected Template</div>
                            <div class="text-muted">{{ $selectedDescriptionTemplate?->name ?? 'No template available' }}</div>
                        </div>
                        @if($selectedDescriptionTemplate)
                            <form method="POST" action="{{ route('admin.products.description-seo.generate', $product) }}" class="js-regenerate-description-form">
                                @csrf
                                <button type="button" class="btn btn-light border js-regenerate-description">
                                    <i class="ph-arrows-clockwise me-2"></i>
                                    {{ $hasDescriptionContent ? 'Regenerate from Template' : 'Generate from Template' }}
                                </button>
                            </form>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('admin.products.description-seo.update', $product) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="template_id" value="{{ $selectedDescriptionTemplate?->id }}">

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="short_description" class="form-label">Short Description</label>
                                <textarea id="short_description" name="short_description" rows="3" class="form-control @error('short_description') is-invalid @enderror">{{ old('short_description', $product->short_description) }}</textarea>
                                @error('short_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea id="description" name="description" rows="10" class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="meta_title" class="form-label">Meta Title</label>
                                <input id="meta_title" name="meta_title" type="text" maxlength="255" value="{{ old('meta_title', $product->meta_title) }}" class="form-control @error('meta_title') is-invalid @enderror">
                                @error('meta_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label for="meta_description" class="form-label">Meta Description</label>
                                <textarea id="meta_description" name="meta_description" rows="3" class="form-control @error('meta_description') is-invalid @enderror">{{ old('meta_description', $product->meta_description) }}</textarea>
                                @error('meta_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ph-floppy-disk me-2"></i>
                                    Save Description & SEO
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="tab-pane fade {{ $activeTab === 'preview' ? 'show active' : '' }}" id="product-tab-preview">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <h5 class="mb-2">{{ $product->product_name }}</h5>
                            <div class="text-muted mb-3">{{ $product->short_description ?: 'No short description added.' }}</div>
                            <dl class="row mb-0">
                                <dt class="col-sm-3">Shop</dt>
                                <dd class="col-sm-9">{{ $product->shop?->name ?? '-' }}</dd>
                                <dt class="col-sm-3">Category</dt>
                                <dd class="col-sm-9">{{ $product->category?->name ?? '-' }}</dd>
                                <dt class="col-sm-3">Brand</dt>
                                <dd class="col-sm-9">{{ $product->brand?->name ?? '-' }}</dd>
                                <dt class="col-sm-3">Type</dt>
                                <dd class="col-sm-9">{{ $productTypes[$product->product_type] ?? ucfirst($product->product_type) }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-regenerate-description');

                if (!button) {
                    return;
                }

                const form = button.closest('.js-regenerate-description-form');
                const isRegenerate = button.textContent.trim().startsWith('Regenerate');

                if (!isRegenerate) {
                    form.submit();
                    return;
                }

                bootbox.confirm({
                    title: 'Regenerate Description',
                    message: 'Current short description, description, meta title, and meta description will be replaced with template-generated content.',
                    buttons: {
                        cancel: {
                            label: 'Cancel',
                            className: 'btn-link',
                        },
                        confirm: {
                            label: 'Yes, Regenerate',
                            className: 'btn-primary',
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
