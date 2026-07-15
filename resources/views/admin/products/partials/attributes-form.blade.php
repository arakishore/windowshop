@php
    $selectedAttributeValues = $selectedAttributeValues ?? [];
    $variantMappings = $attributeMappings->filter(fn ($mapping) => $mapping->is_variant)->values();
    $otherMappings = $attributeMappings->reject(fn ($mapping) => $mapping->is_variant)->values();
    $productRoutePrefix = $productRoutePrefix ?? 'admin';
@endphp

<div class="card-body">
    @if($attributeMappings->isEmpty())
        <div class="text-center text-muted py-4">
            <i class="ph-sliders-horizontal d-block fs-1 mb-2"></i>
            No attributes are configured for this product category.
        </div>
    @else
        <form method="POST" action="{{ route($productRoutePrefix.'.products.attributes.update', $product) }}">
            @csrf
            @method('PUT')

            <div class="d-flex flex-column gap-4">
                @foreach(['Variant Attributes' => $variantMappings, 'Other Attributes' => $otherMappings] as $sectionTitle => $sectionMappings)
                    @continue($sectionMappings->isEmpty())

                    <div>
                        <h6 class="fw-semibold mb-3">{{ $sectionTitle }}</h6>

                        <div class="d-flex flex-column gap-3">
                            @foreach($sectionMappings as $mapping)
                                @php
                                    $group = $mapping->group;
                                    $groupId = (int) $mapping->product_attribute_group_id;
                                    $oldValue = old("attributes.{$groupId}", $selectedAttributeValues[$groupId] ?? []);
                                    $selectedValues = collect(is_array($oldValue) ? $oldValue : [$oldValue])
                                        ->map(fn ($value) => (string) $value)
                                        ->all();
                                @endphp

                                @continue(! $group)

                                <section class="border rounded p-3">
                                    <div class="fw-semibold mb-3">
                                        {{ $group->name }}
                                        @if($mapping->is_required)
                                            <span class="text-danger">*</span>
                                        @endif
                                        @if($mapping->is_variant)
                                            <span class="badge bg-info ms-2">Variant</span>
                                        @endif
                                    </div>

                                    @error("attributes.{$groupId}")
                                        <div class="alert alert-danger py-2">{{ $message }}</div>
                                    @enderror

                                    @if($group->values->isEmpty())
                                        <div class="text-muted">No values are available.</div>
                                    @else
                                        <div class="d-flex flex-wrap gap-3">
                                            @foreach($group->values as $value)
                                                @php
                                                    $inputId = "attribute_{$groupId}_{$value->id}";
                                                    $isChecked = in_array((string) $value->id, $selectedValues, true);
                                                @endphp

                                                <div class="form-check">
                                                    <input
                                                        id="{{ $inputId }}"
                                                        class="form-check-input"
                                                        type="{{ $group->selection_type === 'multiple' ? 'checkbox' : 'radio' }}"
                                                        name="attributes[{{ $groupId }}]{{ $group->selection_type === 'multiple' ? '[]' : '' }}"
                                                        value="{{ $value->id }}"
                                                        @checked($isChecked)
                                                    >
                                                    <label class="form-check-label" for="{{ $inputId }}">{{ $value->name }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </section>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="{{ route($productRoutePrefix.'.products.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="ph-floppy-disk me-2"></i>
                    Save Attributes
                </button>
            </div>
        </form>

        @if(! empty($variantPreview['errors']))
            <div class="alert alert-info mt-3 mb-0">
                {{ $variantPreview['errors'][0] }}
            </div>
        @elseif(($variantPreview['total'] ?? 0) > 0)
            <div class="border-top mt-4 pt-4">
                @error('variants')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <div class="d-flex flex-column flex-lg-row justify-content-lg-between gap-3">
                    <div>
                        <h6 class="fw-semibold mb-1">Variant Preview</h6>
                        <div class="text-muted">
                            {{ $variantPreview['new_count'] }} new variants available
                            @if(($variantPreview['existing_count'] ?? 0) > 0)
                                · {{ $variantPreview['existing_count'] }} already generated
                            @endif
                        </div>
                        @if(($variantPreview['stale_existing_count'] ?? 0) > 0)
                            <div class="text-warning mt-1">Some existing variants no longer match the currently selected attributes.</div>
                        @endif
                    </div>

                    @if(($variantPreview['new_count'] ?? 0) > 0 && ($variantPreview['total'] ?? 0) <= ($variantPreview['limit'] ?? 100))
                        <form method="POST" action="{{ route($productRoutePrefix.'.products.variants.generate', $product) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="ph-git-branch me-2"></i>
                                Generate Variants
                            </button>
                        </form>
                    @endif
                </div>

                @if(($variantPreview['total'] ?? 0) > ($variantPreview['limit'] ?? 100))
                    <div class="alert alert-danger mt-3 mb-0">
                        This selection would create {{ $variantPreview['total'] }} variants. The maximum allowed is {{ $variantPreview['limit'] }}.
                    </div>
                @else
                    <div class="mt-3 d-flex flex-column gap-1">
                        @foreach(array_slice($variantPreview['combinations'], 0, 20) as $combination)
                            <div>{{ $combination['name'] }}</div>
                        @endforeach
                        @if(count($variantPreview['combinations']) > 20)
                            <div class="text-muted">Showing first 20 of {{ count($variantPreview['combinations']) }} combinations.</div>
                        @endif
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>
