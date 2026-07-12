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
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
