<?php

namespace App\Http\Requests\Storefront;

use App\Enums\MerchantBusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterMerchantRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $authenticatedUser = $this->user();
        $mobileRules = ['required', 'string', 'max:20'];
        $emailRules = ['required', 'email', 'max:255'];

        if ($authenticatedUser === null) {
            $mobileRules[] = Rule::unique('users', 'mobile')
                ->where(fn ($query) => $query->where('email', '!=', strtolower((string) $this->input('email'))));
        } else {
            $emailRules[] = Rule::in([strtolower((string) $authenticatedUser->email)]);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'mobile' => $mobileRules,
            'password' => $authenticatedUser === null
                ? ['required', 'string', 'min:8', 'confirmed']
                : ['prohibited'],
            'business_name' => ['required', 'string', 'max:150'],
            'business_type' => ['required', 'string', Rule::in(MerchantBusinessType::values())],
            'terms' => ['accepted'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
