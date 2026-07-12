<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeGroupValue;
use Illuminate\Validation\Rule;

class UpdateProductAttributeGroupValueRequest extends StoreProductAttributeGroupValueRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $group = $this->route('productAttribute');
        $groupId = $group instanceof ProductAttributeGroup ? $group->getKey() : null;
        $value = $this->route('productAttributeGroupValue');
        $valueId = $value instanceof ProductAttributeGroupValue ? $value->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('product_attribute_group_values', 'code')
                    ->where(fn ($query) => $query->where('product_attribute_group_id', $groupId))
                    ->ignore($valueId),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
