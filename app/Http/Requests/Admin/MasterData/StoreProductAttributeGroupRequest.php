<?php

namespace App\Http\Requests\Admin\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductAttributeGroupRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:100', Rule::unique('product_attribute_groups', 'code')],
            'description' => ['nullable', 'string'],
            'selection_type' => ['required', Rule::in(['single', 'multiple'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasDuplicateName()) {
                $validator->errors()->add('name', 'A product attribute group with this name already exists.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->normalizeString('name'),
            'code' => $this->normalizeCode($this->input('code') ?: $this->input('name')),
            'selection_type' => $this->input('selection_type') ?: 'single',
        ]);
    }

    protected function hasDuplicateName(?int $ignoreId = null): bool
    {
        $name = $this->normalizeString('name');

        if ($name === null) {
            return false;
        }

        return DB::table('product_attribute_groups')
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->exists();
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
