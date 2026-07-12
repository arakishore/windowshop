<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\ProductAttributeGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreProductAttributeGroupValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = $this->route('productAttribute');
        $groupId = $group instanceof ProductAttributeGroup ? $group->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_attribute_group_values', 'code')
                    ->where(fn ($query) => $query->where('product_attribute_group_id', $groupId)),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->normalizeString('name'),
            'code' => $this->normalizeCode($this->input('code') ?: $this->input('name')),
        ]);
    }

    private function normalizeCode(mixed $value): ?string
    {
        $value = $this->normalizeStringValue($value);

        return $value === null ? null : Str::slug($value);
    }

    private function normalizeString(string $key): ?string
    {
        return $this->normalizeStringValue($this->input($key));
    }

    private function normalizeStringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
