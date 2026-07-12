<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductCategoryRequest extends StoreProductCategoryRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('productCategory');
        $categoryId = $category instanceof ProductCategory ? $category->getKey() : null;
        $thumb = config('images.product_category.variants.thumb', [160, 160]);

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('status', 'active')),
                Rule::notIn(array_filter([$categoryId])),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('images.product_category.max_upload_kb', 4096),
                'dimensions:min_width='.(int) $thumb[0].',min_height='.(int) $thumb[1],
            ],
            'remove_image' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $category = $this->route('productCategory');
            $categoryId = $category instanceof ProductCategory ? (int) $category->getKey() : null;
            $parentId = $this->integer('parent_id') ?: null;

            if ($this->hasDuplicateSiblingName($categoryId)) {
                $validator->errors()->add('name', 'A category with this name already exists under the selected parent.');
            }

            if ($this->selectedDepth() > self::MAX_DEPTH) {
                $validator->errors()->add('parent_id', 'Product categories can only be nested up to 3 levels for V1.');
            }

            if ($categoryId === null || $parentId === null) {
                return;
            }

            while ($parentId !== null) {
                if ($parentId === $categoryId) {
                    $validator->errors()->add('parent_id', 'A category cannot be assigned under itself or one of its child categories.');

                    return;
                }

                $parentId = DB::table('product_categories')
                    ->where('id', $parentId)
                    ->value('parent_id');

                $parentId = $parentId === null ? null : (int) $parentId;
            }
        });
    }
}
