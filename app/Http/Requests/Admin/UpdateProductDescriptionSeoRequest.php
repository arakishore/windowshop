<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductCategory;
use App\Models\ProductDescriptionTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductDescriptionSeoRequest extends FormRequest
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
            'template_id' => ['nullable', 'integer', 'exists:product_description_templates,id'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $templateId = $this->integer('template_id') ?: null;

            if ($templateId === null) {
                return;
            }

            $product = $this->route('product');
            $template = ProductDescriptionTemplate::query()->find($templateId);

            if (! $product || ! $template || ! $this->templateCategoryAllowed((int) $product->product_category_id, (int) $template->product_category_id)) {
                $validator->errors()->add('template_id', 'The selected template is not available for this product category.');
            }
        });
    }

    private function templateCategoryAllowed(int $productCategoryId, int $templateCategoryId): bool
    {
        $currentId = $productCategoryId;
        $visited = [];

        while ($currentId && ! in_array($currentId, $visited, true)) {
            if ($currentId === $templateCategoryId) {
                return true;
            }

            $visited[] = $currentId;
            $currentId = (int) ProductCategory::query()
                ->whereKey($currentId)
                ->value('parent_id');
        }

        return false;
    }
}
