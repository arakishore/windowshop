<?php

namespace App\Services\Admin;

use App\Models\AdminSetting;
use Illuminate\Support\Facades\DB;

class AdminSettingsInitializer
{
    public function __construct(private readonly AdminSettingsService $settings)
    {
    }

    /**
     * @return array<string, array<string, array{value: mixed, type: string}>>
     */
    public function defaults(): array
    {
        return [
            'regional' => [
                'timezone' => ['value' => 'Asia/Kolkata', 'type' => AdminSetting::TYPE_STRING],
                'date_format' => ['value' => 'd-m-Y', 'type' => AdminSetting::TYPE_STRING],
                'time_format' => ['value' => 'h:i A', 'type' => AdminSetting::TYPE_STRING],
                'financial_year_start_month' => ['value' => 4, 'type' => AdminSetting::TYPE_INTEGER],
            ],
            'currency' => [
                'base_currency' => ['value' => 'INR', 'type' => AdminSetting::TYPE_STRING],
                'symbol' => ['value' => '₹', 'type' => AdminSetting::TYPE_STRING],
                'decimal_places' => ['value' => 2, 'type' => AdminSetting::TYPE_INTEGER],
                'thousands_separator' => ['value' => ',', 'type' => AdminSetting::TYPE_STRING],
                'decimal_separator' => ['value' => '.', 'type' => AdminSetting::TYPE_STRING],
                'symbol_position' => ['value' => 'before', 'type' => AdminSetting::TYPE_STRING],
            ],
        ];
    }

    public function initialize(): void
    {
        DB::transaction(function (): void {
            foreach ($this->defaults() as $group => $settings) {
                foreach ($settings as $key => $definition) {
                    if (! $this->settings->has($group, $key)) {
                        $this->settings->setTyped($group, $key, $definition['value'], $definition['type']);
                    }
                }
            }
        });
    }
}
