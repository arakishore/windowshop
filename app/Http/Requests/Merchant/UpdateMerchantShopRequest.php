<?php

namespace App\Http\Requests\Merchant;

use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMerchantShopRequest extends FormRequest
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
        $logoThumb = config('images.shop_logo.variants.thumb', [120, 120]);
        $bannerThumb = config('images.shop_banner.variants.thumb', [480, 270]);

        return [
            'name' => ['required', 'string', 'max:150'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'country_id' => ['nullable', 'integer', 'exists:loc_countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:loc_states,id'],
            'city_id' => ['nullable', 'integer', 'exists:loc_cities,id'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('images.shop_logo.max_upload_kb', 5120),
                'dimensions:min_width='.(int) $logoThumb[0].',min_height='.(int) $logoThumb[1],
            ],
            'remove_logo' => ['nullable', 'boolean'],
            'banner' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('images.shop_banner.max_upload_kb', 8192),
                'dimensions:min_width='.(int) $bannerThumb[0].',min_height='.(int) $bannerThumb[1],
            ],
            'remove_banner' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $countryId = $this->integer('country_id') ?: null;
            $stateId = $this->integer('state_id') ?: null;
            $cityId = $this->integer('city_id') ?: null;

            if ($stateId !== null && $countryId !== null) {
                $stateExists = DB::table('loc_states')
                    ->where('id', $stateId)
                    ->where('country_id', $countryId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $stateExists) {
                    $validator->errors()->add('state_id', 'The selected state does not belong to the selected country.');
                }
            }

            if ($cityId !== null && $countryId !== null && $stateId !== null) {
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

            $shop = $this->route('shop');
            $currentStatus = $shop instanceof Shop ? (string) $shop->status : null;
            $requestedStatus = $this->input('status');

            if ($requestedStatus === null) {
                return;
            }

            if (! in_array($currentStatus, ['active', 'inactive'], true)) {
                if ($requestedStatus !== null && $requestedStatus !== $currentStatus) {
                    $validator->errors()->add('status', 'This shop status cannot be changed by a merchant.');
                }

                return;
            }

            if (! in_array($requestedStatus, ['active', 'inactive'], true)) {
                $validator->errors()->add('status', 'Select either active or inactive.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->normalizeLower('email'),
            'mobile' => $this->normalizeString('mobile'),
            'whatsapp_number' => $this->normalizeString('whatsapp_number'),
            'name' => $this->normalizeString('name'),
            'status' => $this->normalizeString('status'),
        ]);
    }

    private function normalizeLower(string $key): ?string
    {
        $value = $this->normalizeString($key);

        return $value === null ? null : strtolower($value);
    }

    private function normalizeString(string $key): ?string
    {
        $value = $this->input($key);

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
