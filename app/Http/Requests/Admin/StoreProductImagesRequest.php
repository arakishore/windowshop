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
        $imageRules = [
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:'.(int) config('images.product.max_upload_kb', 3072),
        ];

        return [
            'images' => ['nullable', 'array', 'required_without:image_groups'],
            'images.*' => ['required', ...$imageRules],
            'attribute_value_id' => ['nullable', 'integer', Rule::exists('product_attribute_group_values', 'id')],
            'image_groups' => ['nullable', 'array', 'required_without:images'],
            'image_groups.*' => ['nullable', 'array'],
            'image_groups.*.*' => ['required', ...$imageRules],
        ];
    }

    public function attributeValueId(): ?int
    {
        $value = $this->input('attribute_value_id');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * @return array<int|string, array<int, \Illuminate\Http\UploadedFile>>
     */
    public function imageGroups(): array
    {
        $groups = $this->file('image_groups', []);

        return collect(is_array($groups) ? $groups : [])
            ->filter(fn (mixed $files): bool => is_array($files) && $files !== [])
            ->all();
    }

    public function hasGroupedImages(): bool
    {
        return $this->imageGroups() !== [];
    }
}
