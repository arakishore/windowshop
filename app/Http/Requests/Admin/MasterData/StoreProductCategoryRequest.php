<?php

namespace App\Http\Requests\Admin\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductCategoryRequest extends FormRequest
{
    public const MAX_DEPTH = 3;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $thumb = config('images.product_category.variants.thumb', [160, 160]);

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('status', 'active')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('images.product_category.max_upload_kb', 4096),
                'dimensions:min_width='.(int) $thumb[0].',min_height='.(int) $thumb[1],
            ],
            'remove_image' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasDuplicateSiblingName()) {
                $validator->errors()->add('name', 'A category with this name already exists under the selected parent.');
            }

            if ($this->selectedDepth() > self::MAX_DEPTH) {
                $validator->errors()->add('parent_id', 'Product categories can only be nested up to 3 levels for V1.');
            }
        });
    }

    protected function hasDuplicateSiblingName(?int $ignoreId = null): bool
    {
        $name = trim((string) $this->input('name'));

        if ($name === '') {
            return false;
        }

        $parentId = $this->integer('parent_id') ?: null;

        return DB::table('product_categories')
            ->whereNull('deleted_at')
            ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'))
            ->when($parentId !== null, fn ($query) => $query->where('parent_id', $parentId))
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->exists();
    }

    protected function selectedDepth(): int
    {
        $parentId = $this->integer('parent_id') ?: null;
        $depth = $parentId === null ? 1 : 2;
        $visited = [];

        while ($parentId !== null && ! in_array($parentId, $visited, true)) {
            $visited[] = $parentId;
            $parentId = DB::table('product_categories')
                ->where('id', $parentId)
                ->value('parent_id');

            if ($parentId !== null) {
                $depth++;
                $parentId = (int) $parentId;
            }
        }

        return $depth;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->input('parent_id') === '' ? null : $this->input('parent_id'),
        ]);
    }
}
