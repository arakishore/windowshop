<?php

namespace App\Http\Requests\Merchant;

class UpdatePromotionRequest extends StorePromotionRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['promotion_template_id']);

        return $rules;
    }
}
