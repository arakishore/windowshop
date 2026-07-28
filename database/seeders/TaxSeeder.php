<?php

namespace Database\Seeders;

use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Models\TaxRateComponent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TaxSeeder extends Seeder
{
    private const EFFECTIVE_FROM = '2017-07-01';

    public function run(): void
    {
        $countryId = DB::table('loc_countries')
            ->where('iso2', 'IN')
            ->where('iso3', 'IND')
            ->whereNull('deleted_at')
            ->value('id');

        if (! $countryId) {
            throw new RuntimeException('India country record was not found in loc_countries. Run the location seeders before TaxSeeder.');
        }

        $this->retireLegacyGstClass((int) $countryId);

        foreach ($this->gstSlabs() as $slabData) {
            $taxClass = $this->upsertTaxClass((int) $countryId, $slabData);
            $taxRate = $this->upsertTaxRate($taxClass, $slabData);

            foreach ($slabData['components'] as $componentData) {
                $this->upsertComponent($taxRate, $componentData);
            }
        }
    }

    private function retireLegacyGstClass(int $countryId): void
    {
        $legacyClass = TaxClass::withTrashed()
            ->where('country_id', $countryId)
            ->where('code', 'GST')
            ->first();

        if (! $legacyClass) {
            return;
        }

        $now = now();

        TaxRate::withTrashed()
            ->where('tax_class_id', $legacyClass->getKey())
            ->get()
            ->each(function (TaxRate $rate) use ($now): void {
                $rate->components()->withTrashed()->get()->each(function (TaxRateComponent $component) use ($now): void {
                    $component->forceFill([
                        'deleted_by' => null,
                        'updated_at' => $now,
                    ])->save();

                    if (! $component->trashed()) {
                        $component->delete();
                    }
                });

                $rate->forceFill([
                    'status' => TaxRate::STATUS_INACTIVE,
                    'deleted_by' => null,
                    'updated_at' => $now,
                ])->save();

                if (! $rate->trashed()) {
                    $rate->delete();
                }
            });

        $legacyClass->forceFill([
            'status' => TaxClass::STATUS_INACTIVE,
            'deleted_by' => null,
            'updated_at' => $now,
        ])->save();

        if (! $legacyClass->trashed()) {
            $legacyClass->delete();
        }
    }

    private function upsertTaxClass(int $countryId, array $slabData): TaxClass
    {
        $now = now();
        $taxClass = TaxClass::withTrashed()
            ->where('country_id', $countryId)
            ->where('code', $slabData['code'])
            ->first();

        if (! $taxClass) {
            $taxClass = new TaxClass([
                'uuid' => (string) Str::uuid(),
                'country_id' => $countryId,
                'code' => $slabData['code'],
                'created_at' => $now,
            ]);
        }

        if ($taxClass->trashed()) {
            $taxClass->restore();
        }

        $taxClass->forceFill([
            'country_id' => $countryId,
            'code' => $slabData['code'],
            'name' => $slabData['name'],
            'description' => 'India GST slab tax class.',
            'sort_order' => $slabData['sort_order'],
            'status' => TaxClass::STATUS_ACTIVE,
            'updated_at' => $now,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
        ])->save();

        return $taxClass;
    }

    private function upsertTaxRate(TaxClass $taxClass, array $slabData): TaxRate
    {
        $now = now();
        $taxRate = TaxRate::withTrashed()
            ->where('tax_class_id', $taxClass->getKey())
            ->where('name', $slabData['name'])
            ->whereDate('effective_from', self::EFFECTIVE_FROM)
            ->first();

        if (! $taxRate) {
            $taxRate = new TaxRate([
                'uuid' => (string) Str::uuid(),
                'tax_class_id' => $taxClass->getKey(),
                'name' => $slabData['name'],
                'effective_from' => self::EFFECTIVE_FROM,
                'created_at' => $now,
            ]);
        }

        if ($taxRate->trashed()) {
            $taxRate->restore();
        }

        $taxRate->forceFill([
            'tax_class_id' => $taxClass->getKey(),
            'name' => $slabData['name'],
            'total_rate' => $slabData['total_rate'],
            'effective_from' => self::EFFECTIVE_FROM,
            'effective_to' => null,
            'priority' => 0,
            'status' => TaxRate::STATUS_ACTIVE,
            'updated_at' => $now,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
        ])->save();

        return $taxRate;
    }

    private function upsertComponent(TaxRate $taxRate, array $componentData): TaxRateComponent
    {
        $now = now();
        $component = TaxRateComponent::withTrashed()
            ->where('tax_rate_id', $taxRate->getKey())
            ->where('code', $componentData['code'])
            ->first();

        if (! $component) {
            $component = new TaxRateComponent([
                'tax_rate_id' => $taxRate->getKey(),
                'code' => $componentData['code'],
                'created_at' => $now,
            ]);
        }

        if ($component->trashed()) {
            $component->restore();
        }

        $component->forceFill([
            'tax_rate_id' => $taxRate->getKey(),
            'code' => $componentData['code'],
            'name' => $componentData['name'],
            'rate' => $componentData['rate'],
            'jurisdiction_type' => $componentData['jurisdiction_type'],
            'priority' => $componentData['priority'],
            'updated_at' => $now,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
        ])->save();

        return $component;
    }

    private function gstSlabs(): array
    {
        return [
            [
                'code' => 'GST_0',
                'name' => 'GST 0%',
                'sort_order' => 10,
                'total_rate' => '0.0000',
                'components' => $this->splitComponents('0.0000'),
            ],
            [
                'code' => 'GST_025',
                'name' => 'GST 0.25%',
                'sort_order' => 20,
                'total_rate' => '0.2500',
                'components' => $this->splitComponents('0.1250'),
            ],
            [
                'code' => 'GST_15',
                'name' => 'GST 1.5%',
                'sort_order' => 30,
                'total_rate' => '1.5000',
                'components' => $this->splitComponents('0.7500'),
            ],
            [
                'code' => 'GST_3',
                'name' => 'GST 3%',
                'sort_order' => 40,
                'total_rate' => '3.0000',
                'components' => $this->splitComponents('1.5000'),
            ],
            [
                'code' => 'GST_5',
                'name' => 'GST 5%',
                'sort_order' => 50,
                'total_rate' => '5.0000',
                'components' => $this->splitComponents('2.5000'),
            ],
            [
                'code' => 'GST_18',
                'name' => 'GST 18%',
                'sort_order' => 60,
                'total_rate' => '18.0000',
                'components' => $this->splitComponents('9.0000'),
            ],
            [
                'code' => 'GST_28',
                'name' => 'GST 28%',
                'sort_order' => 70,
                'total_rate' => '28.0000',
                'components' => $this->splitComponents('14.0000'),
            ],
            [
                'code' => 'GST_40',
                'name' => 'GST 40%',
                'sort_order' => 80,
                'total_rate' => '40.0000',
                'components' => $this->splitComponents('20.0000'),
            ],
        ];
    }

    private function splitComponents(string $rate): array
    {
        return [
            [
                'code' => 'CGST',
                'name' => 'CGST',
                'rate' => $rate,
                'jurisdiction_type' => TaxRateComponent::JURISDICTION_CENTRAL,
                'priority' => 1,
            ],
            [
                'code' => 'SGST',
                'name' => 'SGST',
                'rate' => $rate,
                'jurisdiction_type' => TaxRateComponent::JURISDICTION_STATE,
                'priority' => 2,
            ],
        ];
    }
}
