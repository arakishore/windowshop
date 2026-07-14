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
            'image_attribute_group_id' => ['nullable', 'integer', Rule::exists('product_attribute_groups', 'id')],
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

                $imageAttributeGroupId = $this->imageAttributeGroupId();

                if ($imageAttributeGroupId === null) {
                    return;
                }

                $imageMapping = collect($this->input('mappings', []))
                    ->first(fn (array $mapping): bool => (int) ($mapping['product_attribute_group_id'] ?? 0) === $imageAttributeGroupId);

                if (! $imageMapping || ! (bool) ($imageMapping['enabled'] ?? false)) {
                    $validator->errors()->add(
                        'image_attribute_group_id',
                        'The image attribute must be mapped to this category.',
                    );
                    return;
                }

                if (! (bool) ($imageMapping['is_variant'] ?? false)) {
                    $validator->errors()->add(
                        'image_attribute_group_id',
                        'The image attribute must also generate variants.',
                    );
                }
            },
        ];
    }

    public function imageAttributeGroupId(): ?int
    {
        $value = $this->input('image_attribute_group_id');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
