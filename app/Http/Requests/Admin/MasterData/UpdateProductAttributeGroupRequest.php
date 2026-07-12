<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\ProductAttributeGroup;
use Illuminate\Validation\Rule;

class UpdateProductAttributeGroupRequest extends StoreProductAttributeGroupRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = $this->route('productAttribute');
        $groupId = $group instanceof ProductAttributeGroup ? $group->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:100', Rule::unique('product_attribute_groups', 'code')->ignore($groupId)],
            'description' => ['nullable', 'string'],
            'selection_type' => ['required', Rule::in(['single', 'multiple'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $group = $this->route('productAttribute');
        $groupId = $group instanceof ProductAttributeGroup ? $group->getKey() : null;

        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($groupId): void {
            if ($this->hasDuplicateName($groupId === null ? null : (int) $groupId)) {
                $validator->errors()->add('name', 'A product attribute group with this name already exists.');
            }
        });
    }
}
