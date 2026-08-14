<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBrandRequest extends FormRequest
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
        $thumb = config('images.brand_logo.variants.thumb', [120, 120]);

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('brands', 'name')],
            'short_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
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
            'root_product_category_ids' => ['nullable', 'array'],
            'root_product_category_ids.*' => [
                'integer',
                Rule::exists('product_categories', 'id')->where(fn ($query) => $query
                    ->whereNull('parent_id')
                    ->where('status', 'active')
                    ->whereNull('deleted_at')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'short_code' => $this->normalizeShortCode($this->input('short_code')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ids = collect($this->input('root_product_category_ids', []))
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                return;
            }

            $rootCount = ProductCategory::query()
                ->whereIn('id', $ids)
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->count();

            if ($rootCount !== $ids->count()) {
                $validator->errors()->add('root_product_category_ids', 'Applicable shop types must be active root product categories.');
            }
        });
    }

    /**
     * @return array<int, int>
     */
    public function rootProductCategoryIds(): array
    {
        return collect($this->input('root_product_category_ids', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeShortCode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : strtoupper($value);
    }
}
