<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\ShopCategory;
use Illuminate\Validation\Rule;

class UpdateShopCategoryRequest extends StoreShopCategoryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('shop_category');
        $categoryId = $category instanceof ShopCategory ? $category->getKey() : null;
        $thumb = config('images.shop_category.variants.thumb', [160, 160]);

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('shop_categories', 'name')->ignore($categoryId)],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('shop_categories', 'slug')->ignore($categoryId)],
            'description' => ['nullable', 'string'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('images.shop_category.max_upload_kb', 4096),
                'dimensions:min_width='.(int) $thumb[0].',min_height='.(int) $thumb[1],
            ],
            'remove_image' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
