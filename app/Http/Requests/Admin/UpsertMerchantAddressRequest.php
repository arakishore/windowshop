<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpsertMerchantAddressRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'country_id' => ['required', 'integer', 'exists:loc_countries,id'],
            'state_id' => ['required', 'integer', 'exists:loc_states,id'],
            'city_id' => ['required', 'integer', 'exists:loc_cities,id'],
            'pincode' => ['required', 'string', 'max:20'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $countryId = (int) $this->input('country_id');
            $stateId = (int) $this->input('state_id');
            $cityId = (int) $this->input('city_id');

            if ($countryId && $stateId) {
                $stateExists = DB::table('loc_states')
                    ->where('id', $stateId)
                    ->where('country_id', $countryId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $stateExists) {
                    $validator->errors()->add('state_id', 'The selected state does not belong to the selected country.');
                }
            }

            if ($countryId && $stateId && $cityId) {
                $cityExists = DB::table('loc_cities')
                    ->where('id', $cityId)
                    ->where('country_id', $countryId)
                    ->where('state_id', $stateId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $cityExists) {
                    $validator->errors()->add('city_id', 'The selected city does not belong to the selected state.');
                }
            }
        });
    }
}
