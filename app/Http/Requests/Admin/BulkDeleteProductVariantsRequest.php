<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteProductVariantsRequest extends FormRequest
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
            'variant_ids' => ['required', 'array', 'min:1'],
            'variant_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function variantIds(): array
    {
        return collect($this->validated('variant_ids'))
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
