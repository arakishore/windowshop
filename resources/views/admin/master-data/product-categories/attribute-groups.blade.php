{{-- Purpose: Configures product attribute groups for one product category. --}}
@extends('layouts.admin')

@section('breadcrumb')
    <x-page-header
        title="Category Attribute Mapping"
        :breadcrumbs="['Admin' => route('admin.dashboard'), 'Master Data' => null, 'Product Categories' => route('admin.master.product-categories.index'), $category->name => route('admin.master.product-categories.show', $category), 'Attribute Mapping' => null]"
        :action-url="route('admin.master.product-categories.show', $category)"
        action-label="Back to Category"
        action-icon="ph-arrow-left"
    />
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Please correct the highlighted fields.</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @isset($selectedCategory)
        @if($selectedCategory->getKey() !== $category->getKey())
            <div class="alert alert-info">
                Attribute mappings are configured on the root category. You opened {{ $selectedCategory->full_path }}, so this page is editing {{ $category->name }} mappings.
            </div>
        @endif
    @endisset

    <form method="POST" action="{{ route('admin.master.product-categories.attribute-groups.update', $category) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Attribute Groups for Root Category: {{ $category->name }}</h5>
                <div class="text-muted fs-sm mt-1">Select one image attribute for future product image grouping, such as Color, Shade, or Finish.</div>
            </div>

            @if($attributeGroups->isEmpty())
                <x-empty-state icon="ph-list-bullets" title="No active product attributes found" message="Create product attributes before mapping them to categories." />
            @else
                @php
                    $selectedImageAttributeGroupId = old(
                        'image_attribute_group_id',
                        optional($existingMappings->firstWhere('is_image_attribute', true))->product_attribute_group_id,
                    );
                @endphp
                <div class="card-body border-bottom">
                    <label class="form-check mb-0">
                        <input
                            type="radio"
                            name="image_attribute_group_id"
                            value=""
                            class="form-check-input js-image-attribute-radio"
                            @checked(blank($selectedImageAttributeGroupId))
                        >
                        <span class="form-check-label">No image attribute</span>
                    </label>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Attribute Group</th>
                                <th class="text-center" style="width: 150px;">Mapped</th>
                                <th class="text-center" style="width: 160px;">Required</th>
                                <th class="text-center" style="width: 210px;">Generates Variants</th>
                                <th class="text-center" style="width: 170px;">Image Attribute</th>
                                <th style="width: 140px;">Sort Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($attributeGroups as $group)
                                @php
                                    $mapping = $existingMappings->get($group->getKey());
                                    $rowKey = $group->getKey();
                                    $enabled = old("mappings.{$rowKey}.enabled", $mapping ? '1' : '0');
                                    $isRequired = old("mappings.{$rowKey}.is_required", $mapping?->is_required ? '1' : '0');
                                    $isVariant = old("mappings.{$rowKey}.is_variant", $mapping?->is_variant ? '1' : '0');
                                    $sortOrder = old("mappings.{$rowKey}.sort_order", $mapping?->sort_order ?? $group->sort_order);
                                    $isImageAttribute = (string) $selectedImageAttributeGroupId === (string) $group->getKey();
                                @endphp
                                <tr>
                                    <td>
                                        <input type="hidden" name="mappings[{{ $rowKey }}][product_attribute_group_id]" value="{{ $group->getKey() }}">
                                        <div class="fw-semibold">{{ $group->name }}</div>
                                        <div class="text-muted small">{{ ucfirst($group->selection_type) }} selection</div>
                                    </td>
                                    <td class="text-center">
                                        <input type="hidden" name="mappings[{{ $rowKey }}][enabled]" value="0">
                                        <div class="form-check form-switch d-inline-flex mb-0">
                                            <input
                                                id="mapping_enabled_{{ $rowKey }}"
                                                class="form-check-input js-mapping-enabled"
                                                type="checkbox"
                                                name="mappings[{{ $rowKey }}][enabled]"
                                                value="1"
                                                data-group-id="{{ $rowKey }}"
                                                @checked((bool) $enabled)
                                            >
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <input type="hidden" name="mappings[{{ $rowKey }}][is_required]" value="0">
                                        <div class="form-check form-switch d-inline-flex mb-0">
                                            <input
                                                id="mapping_required_{{ $rowKey }}"
                                                class="form-check-input"
                                                type="checkbox"
                                                name="mappings[{{ $rowKey }}][is_required]"
                                                value="1"
                                                @checked((bool) $isRequired)
                                            >
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center">
                                            <input type="hidden" name="mappings[{{ $rowKey }}][is_variant]" value="0">
                                            <div class="form-check form-switch mb-0">
                                                <input
                                                    id="mapping_variant_{{ $rowKey }}"
                                                    class="form-check-input js-mapping-variant"
                                                    type="checkbox"
                                                    name="mappings[{{ $rowKey }}][is_variant]"
                                                    value="1"
                                                    data-group-id="{{ $rowKey }}"
                                                    @checked((bool) $isVariant)
                                                >
                                                <label class="form-check-label" for="mapping_variant_{{ $rowKey }}">Generates Variants</label>
                                            </div>
                                        </div>
                                        <div class="form-text text-center">Use this attribute when creating product variant combinations.</div>
                                    </td>
                                    <td class="text-center">
                                        <input
                                            id="mapping_image_attribute_{{ $rowKey }}"
                                            class="form-check-input js-image-attribute-radio"
                                            type="radio"
                                            name="image_attribute_group_id"
                                            value="{{ $group->getKey() }}"
                                            data-group-id="{{ $rowKey }}"
                                            @checked($isImageAttribute)
                                        >
                                        <label class="visually-hidden" for="mapping_image_attribute_{{ $rowKey }}">
                                            Use {{ $group->name }} for image grouping
                                        </label>
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            min="0"
                                            name="mappings[{{ $rowKey }}][sort_order]"
                                            value="{{ $sortOrder }}"
                                            class="form-control @error("mappings.{$rowKey}.sort_order") is-invalid @enderror"
                                        >
                                        @error("mappings.{$rowKey}.sort_order")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('admin.master.product-categories.show', $category) }}" class="btn btn-light border">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="ph-floppy-disk me-2"></i>
                Save Attribute Mapping
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const noneRadio = document.querySelector('input[name="image_attribute_group_id"][value=""]');

            document.querySelectorAll('.js-image-attribute-radio[data-group-id]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    if (!radio.checked) {
                        return;
                    }

                    const groupId = radio.dataset.groupId;
                    const enabled = document.querySelector('.js-mapping-enabled[data-group-id="' + groupId + '"]');
                    const variant = document.querySelector('.js-mapping-variant[data-group-id="' + groupId + '"]');

                    if (enabled) {
                        enabled.checked = true;
                    }

                    if (variant) {
                        variant.checked = true;
                    }
                });
            });

            document.querySelectorAll('.js-mapping-enabled, .js-mapping-variant').forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    const groupId = checkbox.dataset.groupId;
                    const radio = document.querySelector('.js-image-attribute-radio[data-group-id="' + groupId + '"]');

                    if (radio && radio.checked && !checkbox.checked && noneRadio) {
                        noneRadio.checked = true;
                    }
                });
            });
        });
    </script>
@endpush
