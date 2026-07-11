<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Models\ShopAudience;
use Illuminate\Validation\Rule;

class UpdateShopAudienceRequest extends StoreShopAudienceRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $audience = $this->route('shop_audience');
        $audienceId = $audience instanceof ShopAudience ? $audience->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('shop_audiences', 'name')->ignore($audienceId)],
            'slug' => ['nullable', 'string', 'max:180', Rule::unique('shop_audiences', 'slug')->ignore($audienceId)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
