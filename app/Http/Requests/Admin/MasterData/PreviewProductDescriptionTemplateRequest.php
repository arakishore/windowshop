<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Services\Product\ProductDescriptionTemplateService;
use Illuminate\Foundation\Http\FormRequest;

class PreviewProductDescriptionTemplateRequest extends FormRequest
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
        return collect(ProductDescriptionTemplateService::PLACEHOLDERS)
            ->mapWithKeys(fn (string $placeholder): array => [$placeholder => ['nullable', 'string', 'max:255']])
            ->all();
    }
}
