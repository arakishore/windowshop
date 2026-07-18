@php
    $description = $description ?? null;
@endphp

<div class="card merchant-settings-card">
    <div class="card-header">
        <h5 class="mb-0">{{ $title }}</h5>
        @if ($description)
            <div class="text-muted fs-sm mt-1">{{ $description }}</div>
        @endif
    </div>
    <div class="card-body">
        <div class="merchant-settings-grid">
            @foreach ($fields as $fieldConfig)
                @php
                    $meta = $field($fieldConfig['group'], $fieldConfig['key']);
                    $kind = $fieldConfig['kind'] ?? 'text';
                    $value = $meta['value'];
                    $isInverse = $kind === 'inverse_boolean';
                    $checked = $isInverse ? ! (bool) $value : (bool) $value;
                    $hasError = $errors->has($meta['errorKey']);
                @endphp

                <div>
                    @if ($kind === 'boolean' || $kind === 'inverse_boolean')
                        <input type="hidden" name="{{ $meta['name'] }}" value="{{ $isInverse ? '1' : '0' }}">
                        <div class="form-check form-switch">
                            <input
                                type="checkbox"
                                class="form-check-input {{ $hasError ? 'is-invalid' : '' }}"
                                id="{{ $meta['id'] }}"
                                name="{{ $meta['name'] }}"
                                value="{{ $isInverse ? '0' : '1' }}"
                                @checked($checked)
                            >
                            <label class="form-check-label fw-semibold" for="{{ $meta['id'] }}">{{ $fieldConfig['label'] }}</label>
                        </div>
                    @else
                        <label for="{{ $meta['id'] }}" class="form-label fw-semibold">{{ $fieldConfig['label'] }}</label>
                        @if ($kind === 'select' && isset($selectOptions[$meta['fullKey']]))
                            <select id="{{ $meta['id'] }}" name="{{ $meta['name'] }}" class="form-select {{ $hasError ? 'is-invalid' : '' }}">
                                @foreach ($selectOptions[$meta['fullKey']] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        @elseif ($kind === 'number')
                            <input id="{{ $meta['id'] }}" type="number" name="{{ $meta['name'] }}" value="{{ $value }}" class="form-control {{ $hasError ? 'is-invalid' : '' }}" step="{{ $meta['type'] === \App\Models\MerchantSetting::TYPE_DECIMAL ? '0.01' : '1' }}">
                        @elseif ($kind === 'textarea')
                            <textarea id="{{ $meta['id'] }}" name="{{ $meta['name'] }}" class="form-control {{ $hasError ? 'is-invalid' : '' }}" rows="{{ $fieldConfig['rows'] ?? 3 }}">{{ $value }}</textarea>
                        @else
                            <input id="{{ $meta['id'] }}" type="text" name="{{ $meta['name'] }}" value="{{ $value }}" class="form-control {{ $hasError ? 'is-invalid' : '' }}">
                        @endif
                    @endif

                    @if ($hasError)
                        <div class="invalid-feedback d-block">{{ $errors->first($meta['errorKey']) }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
