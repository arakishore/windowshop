<?php

namespace App\Http\Requests\Admin;

use App\Models\ProductAttributeGroupValue;
use App\Services\Product\ProductAttributeConfigurationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductAttributesRequest extends FormRequest
{
    /**
     * @var array<int, array<int, int>>
     */
    private array $normalizedAttributes = [];

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
            'attributes' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $product = $this->route('product');

            if (! $product || ! $product->category) {
                $validator->errors()->add('attributes', 'Product category is required before attributes can be saved.');
                return;
            }

            $mappings = app(ProductAttributeConfigurationService::class)->forCategory($product->category);
            $mappedGroupIds = $mappings->pluck('product_attribute_group_id')->map(fn ($id) => (int) $id)->all();
            $submitted = $this->input('attributes', []);

            if (! is_array($submitted)) {
                $validator->errors()->add('attributes', 'Invalid attribute selection.');
                return;
            }

            foreach ($submitted as $groupId => $rawValueIds) {
                $groupId = (int) $groupId;

                if (! in_array($groupId, $mappedGroupIds, true)) {
                    $validator->errors()->add("attributes.{$groupId}", 'The selected attribute group is not available for this product category.');
                    continue;
                }

                $mapping = $mappings->firstWhere('product_attribute_group_id', $groupId);
                $valueIds = $this->normalizeValueIds($rawValueIds);

                if ($mapping?->group?->selection_type === 'single' && count($valueIds) > 1) {
                    $validator->errors()->add("attributes.{$groupId}", "{$mapping->group->name} allows only one value.");
                    continue;
                }

                $activeValueCount = ProductAttributeGroupValue::query()
                    ->where('product_attribute_group_id', $groupId)
                    ->whereIn('id', $valueIds)
                    ->where('status', 'active')
                    ->count();

                if ($activeValueCount !== count($valueIds)) {
                    $validator->errors()->add("attributes.{$groupId}", "One or more selected {$mapping?->group?->name} values are invalid.");
                    continue;
                }

                if ($valueIds !== []) {
                    $this->normalizedAttributes[$groupId] = $valueIds;
                }
            }

            foreach ($mappings as $mapping) {
                $groupId = (int) $mapping->product_attribute_group_id;

                if ($mapping->is_required && empty($this->normalizedAttributes[$groupId])) {
                    $validator->errors()->add("attributes.{$groupId}", "{$mapping->group->name} is required.");
                }
            }
        });
    }

    /**
     * @return array<int, array<int, int>>
     */
    public function selectedAttributes(): array
    {
        return $this->normalizedAttributes;
    }

    /**
     * @return array<int, int>
     */
    private function normalizeValueIds(mixed $valueIds): array
    {
        if (! is_array($valueIds)) {
            $valueIds = [$valueIds];
        }

        return collect($valueIds)
            ->map(fn ($valueId): int => (int) $valueId)
            ->filter(fn (int $valueId): bool => $valueId > 0)
            ->unique()
            ->values()
            ->all();
    }
}
