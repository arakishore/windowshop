<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductImagesRequest extends FormRequest
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
            'primary_image_id' => ['nullable', 'integer', Rule::exists('product_images', 'id')],
            'images' => ['nullable', 'array'],
            'images.*.title' => ['nullable', 'string', 'max:255'],
            'images.*.alt_text' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'images.*.status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function imageRows(): array
    {
        return $this->validated('images', []);
    }

    public function primaryImageId(): ?int
    {
        $value = $this->input('primary_image_id');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
