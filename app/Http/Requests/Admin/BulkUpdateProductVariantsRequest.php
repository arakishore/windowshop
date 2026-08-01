<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductAvailabilityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'changes.availability_status_id' => ['nullable', 'integer'],
            'changes.status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $product = $this->route('product');
            $statusId = (int) ($this->input('changes.availability_status_id') ?? 0);

            if (! $product || $statusId <= 0) {
                return;
            }

            if (! ProductAvailabilityStatus::query()
                ->whereKey($statusId)
                ->where('merchant_id', $product->merchant_id)
                ->active()
                ->exists()) {
                $validator->errors()->add('changes.availability_status_id', 'Choose an active availability status for this merchant.');
            }
        });
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
