<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductCategory;
use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductQuickCreateRequest extends FormRequest
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
            'shop_id' => [
                'required',
                'integer',
                Rule::exists('shops', 'id')->where(fn ($query) => $query->whereIn('status', ['pending', 'active'])->whereNull('deleted_at')),
            ],
            'product_category_id' => [
                'required',
                'integer',
                Rule::exists('product_categories', 'id')->where(fn ($query) => $query
                    ->where('status', 'active')
                    ->whereNotNull('parent_id')
                    ->whereNull('deleted_at')),
            ],
            'root_product_category_id' => ['prohibited'],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where('status', 'active')->whereNull('deleted_at')),
            ],
            'product_name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'archived'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $shop = Shop::query()
                ->with('rootProductCategory')
                ->find($this->integer('shop_id'));
            $category = ProductCategory::query()
                ->with(['parent.parent', 'children'])
                ->find($this->integer('product_category_id'));

            if (! $shop || ! $shop->rootProductCategory || ! $category) {
                return;
            }

            if (! $category->isDescendantOf($shop->rootProductCategory)) {
                $validator->errors()->add('product_category_id', 'The selected product category must belong under the selected shop type.');
                return;
            }

            if (! $category->isLeaf()) {
                $validator->errors()->add('product_category_id', 'Please select a leaf product category.');
            }
        });
    }
}
