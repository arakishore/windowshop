<?php

namespace App\Http\Requests\Admin\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostalCodeRequest extends FormRequest
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
            'circle_name' => ['nullable', 'string', 'max:120'],
            'region_name' => ['nullable', 'string', 'max:120'],
            'division_name' => ['nullable', 'string', 'max:120'],
            'office_name' => ['required', 'string', 'max:180'],
            'postal_code' => ['required', 'string', 'max:20'],
            'office_type' => ['nullable', 'string', 'max:20'],
            'delivery_status' => ['nullable', 'string', 'max:40'],
            'shipping_enabled' => ['required', 'boolean'],
            'district' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        $data = $this->validated();

        foreach (['circle_name', 'region_name', 'division_name', 'office_name', 'postal_code', 'office_type', 'delivery_status', 'district', 'state'] as $key) {
            $value = isset($data[$key]) ? trim((string) $data[$key]) : null;
            $data[$key] = $value === '' ? null : $value;
        }

        $data['shipping_enabled'] = $this->boolean('shipping_enabled');
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;
        $data['latitude'] = $latitude !== null && $latitude !== '' ? number_format((float) $latitude, 7, '.', '') : null;
        $data['longitude'] = $longitude !== null && $longitude !== '' ? number_format((float) $longitude, 7, '.', '') : null;

        return $data;
    }
}
