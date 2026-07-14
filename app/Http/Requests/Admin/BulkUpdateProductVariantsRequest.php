<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateProductVariantsRequest extends FormRequest
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
            'scope' => ['required', Rule::in(['selected', 'all'])],
            'variant_ids' => ['nullable', 'array'],
            'variant_ids.*' => ['integer'],
            'changes' => ['required', 'array'],
            'changes.mrp' => ['nullable', 'numeric', 'min:0'],
            'changes.selling_price' => ['nullable', 'numeric', 'min:0'],
            'changes.cost_price' => ['nullable', 'numeric', 'min:0'],
            'changes.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'changes.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'changes.status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function variantIds(): array
    {
        return collect($this->input('variant_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function changes(): array
    {
        return $this->validated('changes', []);
    }

    public function appliesToAll(): bool
    {
        return $this->input('scope') === 'all';
    }
}
