<?php

namespace App\Http\Requests\Admin;

use App\Models\Brand;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\TaxClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductBasicRequest extends FormRequest
{
    private bool $taxConfigurationSubmitted = true;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('tax_mode')) {
            $this->taxConfigurationSubmitted = false;
            $product = $this->route('product');

            $this->merge([
                'tax_mode' => $product?->tax_mode ?? 'inherit',
                'tax_class_id' => $product?->tax_class_id,
            ]);
        }
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
                Rule::exists('shops', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where(function ($query): void {
                        $query->whereIn('status', ['pending', 'active']);

                        if ($this->route('product')?->shop_id) {
                            $query->orWhere('id', $this->route('product')->shop_id);
                        }
                    })),
            ],
            'product_category_id' => [
                'required',
                'integer',
                Rule::exists('product_categories', 'id')->where(fn ($query) => $query->where(function ($query): void {
                    $query->where('status', 'active')
                        ->orWhere('id', $this->route('product')?->product_category_id);
                })->whereNotNull('parent_id')->whereNull('deleted_at')),
            ],
            'root_product_category_id' => ['prohibited'],
            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')->where(fn ($query) => $query->where(function ($query): void {
                    $query->where('status', 'active')
                        ->orWhere('id', $this->route('product')?->brand_id);
                })->whereNull('deleted_at')),
            ],
            'product_name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive', 'archived'])],
            'tax_mode' => ['required', Rule::in(['inherit', 'override', 'exempt'])],
            'tax_class_id' => ['nullable', 'integer'],
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

            if (! $this->taxConfigurationSubmitted || $this->input('tax_mode') !== 'override') {
                return;
            }

            $taxClassId = $this->integer('tax_class_id');

            if ($taxClassId <= 0) {
                $validator->errors()->add('tax_class_id', 'Choose a tax class when overriding product tax.');
                return;
            }

            if (! TaxClass::query()
                ->whereKey($taxClassId)
                ->where('status', TaxClass::STATUS_ACTIVE)
                ->whereNull('deleted_at')
                ->exists()) {
                $validator->errors()->add('tax_class_id', 'Choose an active tax class.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function taxConfiguration(): array
    {
        $mode = $this->input('tax_mode', 'inherit');

        return [
            'tax_mode' => $mode,
            'tax_class_id' => $mode === 'override' ? $this->integer('tax_class_id') : null,
        ];
    }
}
