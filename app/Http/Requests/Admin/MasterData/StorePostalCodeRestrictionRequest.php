<?php

namespace App\Http\Requests\Admin\MasterData;

use App\Http\Requests\Concerns\ValidatesPostalCodeRestrictionRequest;
use Illuminate\Foundation\Http\FormRequest;

class StorePostalCodeRestrictionRequest extends FormRequest
{
    use ValidatesPostalCodeRestrictionRequest;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->restrictionRules();
    }
}
