<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductAvailabilityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductVariantsRequest extends FormRequest
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
            'default_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'variants' => ['required', 'array'],
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.mrp' => ['required', 'numeric', 'min:0'],
            'variants.*.selling_price' => ['required', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock_quantity' => ['required', 'integer', 'min:0'],
            'variants.*.low_stock_threshold' => ['required', 'integer', 'min:0'],
            'variants.*.availability_status_id' => ['nullable', 'integer'],
            'variants.*.status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $product = $this->route('product');

            if (! $product) {
                return;
            }

            foreach ($this->input('variants', []) as $key => $row) {
                $statusId = (int) ($row['availability_status_id'] ?? 0);

                if ($statusId <= 0) {
                    continue;
                }

                if (! ProductAvailabilityStatus::query()
                    ->whereKey($statusId)
                    ->where('merchant_id', $product->merchant_id)
                    ->active()
                    ->exists()) {
                    $validator->errors()->add("variants.{$key}.availability_status_id", 'Choose an active availability status for this merchant.');
                }
            }
        });
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function variants(): array
    {
        return $this->validated('variants', []);
    }

    public function defaultVariantId(): ?int
    {
        $value = $this->input('default_variant_id');

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
