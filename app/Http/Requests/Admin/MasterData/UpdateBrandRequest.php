<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\Brand;
use Illuminate\Validation\Rule;

class UpdateBrandRequest extends StoreBrandRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $brand = $this->route('brand');
        $brandId = $brand instanceof Brand ? $brand->getKey() : null;
        $thumb = config('images.brand_logo.variants.thumb', [120, 120]);

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('brands', 'name')->ignore($brandId)],
            'description' => ['nullable', 'string'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('images.brand_logo.max_upload_kb', 5120),
                'dimensions:min_width='.(int) $thumb[0].',min_height='.(int) $thumb[1],
            ],
            'remove_logo' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
