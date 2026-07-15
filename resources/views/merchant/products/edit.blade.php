{{-- Purpose: Provides merchant tab-based product editing workspace. --}}
@extends('layouts.merchant')

@section('breadcrumb')
    <x-page-header
        title="Edit Product"
        :breadcrumbs="['Merchant' => route('merchant.dashboard'), 'Products' => route('merchant.products.index'), $product->product_name => null]"
        :action-url="route('merchant.products.index')"
        action-label="Back to Products"
        action-icon="ph-arrow-left"
        action-class="btn-light border"
    />
@endsection

@section('content')
    @php
        $activeTab = request()->query('tab', 'basic');
        $productRoutePrefix = 'merchant';
        $tabs = [
            'basic' => ['label' => 'Basic Information', 'icon' => 'ph-info'],
            'attributes' => ['label' => 'Attributes', 'icon' => 'ph-sliders-horizontal'],
            'variants' => ['label' => 'Variants & Inventory', 'icon' => 'ph-tag'],
            'images' => ['label' => 'Images', 'icon' => 'ph-image'],
            'description' => ['label' => 'Description', 'icon' => 'ph-text-aa'],
            'seo' => ['label' => 'SEO', 'icon' => 'ph-magnifying-glass'],
        ];
    @endphp

    <div class="card">
        <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-2">
            <div>
                <h5 class="mb-1">{{ $product->product_name }}</h5>
                <div class="text-muted">
                    {{ $product->shop?->name ?? '-' }} - {{ $product->category?->name ?? '-' }} - {{ $product->brand?->name ?? '-' }}
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
                <form method="POST" action="{{ route('merchant.products.update', $product) }}">
                    @csrf
                    @method('PUT')
                    @include('admin.products.partials.quick-form', ['submitLabel' => 'Update Basic Information', 'includeShortDescription' => false, 'productRoutePrefix' => 'merchant'])
                </form>
            </div>

            <div class="tab-pane fade {{ $activeTab === 'attributes' ? 'show active' : '' }}" id="product-tab-attributes">
                @include('admin.products.partials.attributes-form', ['productRoutePrefix' => 'merchant'])
            </div>

            <div class="tab-pane fade {{ $activeTab === 'variants' ? 'show active' : '' }}" id="product-tab-variants">
                @include('admin.products.partials.variants-grid', ['productRoutePrefix' => 'merchant'])
            </div>

            <div class="tab-pane fade {{ $activeTab === 'images' ? 'show active' : '' }}" id="product-tab-images">
                @include('admin.products.partials.images-form', ['productRoutePrefix' => 'merchant'])
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
                            <form method="POST" action="{{ route('merchant.products.description-seo.generate', $product) }}" class="js-regenerate-description-form">
                                @csrf
                                <button type="button" class="btn btn-light border js-regenerate-description">
                                    <i class="ph-arrows-clockwise me-2"></i>
                                    {{ $hasDescriptionContent ? 'Regenerate from Template' : 'Generate from Template' }}
                                </button>
                            </form>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('merchant.products.description-seo.update', $product) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="current_tab" value="description">
                        <input type="hidden" name="template_id" value="{{ $selectedDescriptionTemplate?->id }}">
                        <input type="hidden" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}">
                        <textarea name="meta_description" class="d-none" aria-hidden="true">{{ old('meta_description', $product->meta_description) }}</textarea>

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

                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ph-floppy-disk me-2"></i>
                                    Save Description
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="tab-pane fade {{ $activeTab === 'seo' ? 'show active' : '' }}" id="product-tab-seo">
                <div class="card-body">
                    <form method="POST" action="{{ route('merchant.products.description-seo.update', $product) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="current_tab" value="seo">
                        <input type="hidden" name="template_id" value="{{ $selectedDescriptionTemplate?->id }}">
                        <textarea name="short_description" class="d-none" aria-hidden="true">{{ old('short_description', $product->short_description) }}</textarea>
                        <textarea name="description" class="d-none" aria-hidden="true">{{ old('description', $product->description) }}</textarea>

                        <div class="row g-3">
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
                                    Save SEO
                                </button>
                            </div>
                        </div>
                    </form>
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
