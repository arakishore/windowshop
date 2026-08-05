<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesBannerTemplateRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerTemplateRequest extends FormRequest
{
    use ValidatesBannerTemplateRequest;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->templateRules(false);
    }
}
