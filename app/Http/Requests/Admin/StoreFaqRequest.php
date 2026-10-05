<?php

namespace App\Http\Requests\Admin;

use App\Models\Faq;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFaqRequest extends FormRequest
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
            'category' => ['required', 'string', Rule::in(array_keys(Faq::categories()))],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in([Faq::STATUS_ACTIVE, Faq::STATUS_INACTIVE])],
        ];
    }
}
