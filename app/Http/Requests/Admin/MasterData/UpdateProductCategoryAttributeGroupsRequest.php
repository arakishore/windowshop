<?php

namespace App\Http\Requests\Admin\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductCategoryAttributeGroupsRequest extends FormRequest
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
            'mappings' => ['nullable', 'array'],
            'mappings.*.product_attribute_group_id' => [
                'required',
                'integer',
                Rule::exists('product_attribute_groups', 'id'),
            ],
            'mappings.*.enabled' => ['nullable', 'boolean'],
            'mappings.*.is_required' => ['nullable', 'boolean'],
            'mappings.*.is_variant' => ['nullable', 'boolean'],
            'mappings.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $groupIds = collect($this->input('mappings', []))
                    ->pluck('product_attribute_group_id')
                    ->filter(fn ($value): bool => is_numeric($value))
                    ->map(fn ($value): int => (int) $value);

                if ($groupIds->duplicates()->isNotEmpty()) {
                    $validator->errors()->add(
                        'mappings',
                        'Each attribute group can only be mapped once for this category.',
                    );
                }
            },
        ];
    }
}
