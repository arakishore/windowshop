<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\ProductDescriptionTemplate;
use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductDescriptionTemplateRequest extends FormRequest
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
            'product_category_id' => [
                'required',
                'integer',
                Rule::exists('product_categories', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'name' => ['required', 'string', 'max:150'],
            'short_description_template' => ['required', 'string'],
            'description_template' => ['required', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('status') !== 'active') {
                return;
            }

            $categoryId = $this->integer('product_category_id') ?: null;

            if ($categoryId === null) {
                return;
            }

            $template = $this->route('description_template');
            $templateId = $template instanceof ProductDescriptionTemplate ? $template->getKey() : null;

            $activeExists = ProductDescriptionTemplate::query()
                ->where('product_category_id', $categoryId)
                ->where('status', 'active')
                ->when($templateId !== null, fn ($query) => $query->whereKeyNot($templateId))
                ->exists();

            if ($activeExists) {
                $categoryName = ProductCategory::query()->whereKey($categoryId)->value('name') ?? 'this category';
                $validator->errors()->add('status', "Only one active description template is allowed for {$categoryName}.");
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->normalizeString('name'),
        ]);
    }

    private function normalizeString(string $key): ?string
    {
        $value = $this->input($key);

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
