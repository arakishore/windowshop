<?php

namespace App\Http\Requests\Admin;

use App\Models\Shop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateShopRequest extends StoreShopRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['shop_category_id'] = [
            'required',
            'integer',
            Rule::exists('shop_categories', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
        ];
        $rules['audience_ids.*'] = [
            'integer',
            'distinct',
            Rule::exists('shop_audiences', 'id')->where(fn ($query) => $query->whereNull('deleted_at')),
        ];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            $shop = $this->route('shop');
            $currentCategoryId = $shop instanceof Shop ? (int) $shop->shop_category_id : null;
            $selectedCategoryId = $this->integer('shop_category_id') ?: null;

            if ($selectedCategoryId === null || $selectedCategoryId === $currentCategoryId) {
                return;
            }

            $isActive = DB::table('shop_categories')
                ->where('id', $selectedCategoryId)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->exists();

            if (! $isActive) {
                $validator->errors()->add('shop_category_id', 'The selected shop category must be active.');
            }

            $currentAudienceIds = $shop instanceof Shop
                ? $shop->audiences()->pluck('shop_audiences.id')->map(fn ($id) => (int) $id)->all()
                : [];

            $selectedAudienceIds = collect(Arr::wrap($this->input('audience_ids', [])))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $newAudienceIds = array_values(array_diff($selectedAudienceIds, $currentAudienceIds));

            if ($newAudienceIds === []) {
                return;
            }

            $activeAudienceCount = DB::table('shop_audiences')
                ->whereIn('id', $newAudienceIds)
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->count();

            if ($activeAudienceCount !== count($newAudienceIds)) {
                $validator->errors()->add('audience_ids', 'Newly selected shop audiences must be active.');
            }
        });
    }
}
