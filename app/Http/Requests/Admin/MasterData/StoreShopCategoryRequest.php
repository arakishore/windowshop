<?php

namespace App\Http\Requests\Admin\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreShopCategoryRequest extends FormRequest
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
        $thumb = config('images.shop_category.variants.thumb', [160, 160]);
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('shop_categories', 'id')->where(fn ($query) => $query
                    ->whereNull('deleted_at')
                    ->where('status', 'active')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('images.shop_category.max_upload_kb', 4096),
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
        });
    }

    protected function hasDuplicateSiblingName(?int $ignoreId = null): bool
    {
        $name = trim((string) $this->input('name'));

        if ($name === '') {
            return false;
        }

        $parentId = $this->integer('parent_id') ?: null;
        $normalizedName = mb_strtolower($name);

        return DB::table('shop_categories')
            ->whereNull('deleted_at')
            ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'))
            ->when($parentId !== null, fn ($query) => $query->where('parent_id', $parentId))
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
            ->exists();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->input('parent_id') === '' ? null : $this->input('parent_id'),
        ]);
    }
}
