<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\ProductAvailabilityStatus;
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
            'availability_status_id' => ['nullable', 'integer'],
            'product_name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_featured' => ['nullable', 'boolean'],
            'featured_from' => ['nullable', 'date'],
            'featured_until' => ['nullable', 'date', 'after_or_equal:featured_from'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'archived'])],
            'tax_mode' => ['sometimes', Rule::in(['inherit'])],
            'tax_class_id' => ['prohibited'],
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

            $brandId = $this->integer('brand_id');

            if ($brandId > 0 && ! Brand::query()
                ->whereKey($brandId)
                ->whereHas('rootProductCategories', fn ($query) => $query->whereKey($shop->root_product_category_id))
                ->exists()) {
                $validator->errors()->add('brand_id', 'The selected brand is not applicable to the selected shop type.');
            }

            $availabilityStatusId = $this->integer('availability_status_id');

            if ($availabilityStatusId > 0 && ! ProductAvailabilityStatus::query()
                ->whereKey($availabilityStatusId)
                ->where('merchant_id', $shop->merchant_id)
                ->active()
                ->exists()) {
                $validator->errors()->add('availability_status_id', 'Choose an active availability status for this merchant.');
            }
        });
    }

    /**
     * @return array{sort_order: int, is_featured: bool, featured_from: mixed, featured_until: mixed}
     */
    public function merchandisingConfiguration(): array
    {
        return [
            'sort_order' => $this->integer('sort_order'),
            'is_featured' => $this->boolean('is_featured'),
            'featured_from' => $this->input('featured_from') ?: null,
            'featured_until' => $this->input('featured_until') ?: null,
        ];
    }
}
