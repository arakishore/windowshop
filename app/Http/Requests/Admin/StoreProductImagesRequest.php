<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductImagesRequest extends FormRequest
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
            'images' => ['required', 'array', 'min:1', 'max:8'],
            'images.*' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('images.product.max_upload_kb', 3072),
            ],
            'attribute_value_id' => ['nullable', 'integer', Rule::exists('product_attribute_group_values', 'id')],
        ];
    }

    public function attributeValueId(): ?int
    {
        $value = $this->input('attribute_value_id');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
