<?php

namespace Tests\Feature;

use App\Models\LocCountry;
use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Models\TaxRateComponent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class TaxFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation(
                'utf8mb4_unicode_ci',
                fn (string $left, string $right): int => strcmp($left, $right),
            );
        }
    }

    public function test_tax_class_can_be_created_for_a_country(): void
    {
        $country = $this->createCountry();

        $taxClass = TaxClass::query()->create([
            'country_id' => $country->getKey(),
            'code' => 'STANDARD',
            'name' => 'Standard Tax',
            'description' => 'Default standard tax class.',
            'status' => TaxClass::STATUS_ACTIVE,
        ]);

        $this->assertNotEmpty($taxClass->uuid);
        $this->assertTrue($taxClass->country->is($country));
        $this->assertDatabaseHas('tax_classes', [
            'country_id' => $country->getKey(),
            'code' => 'STANDARD',
            'name' => 'Standard Tax',
            'status' => TaxClass::STATUS_ACTIVE,
        ]);
    }

    public function test_tax_class_can_have_multiple_historical_rates(): void
    {
        $taxClass = $this->createTaxClass();

        $oldRate = $taxClass->rates()->create([
            'name' => 'Standard Tax 2025',
            'total_rate' => '18.0000',
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-12-31',
            'status' => TaxRate::STATUS_ACTIVE,
        ]);
        $newRate = $taxClass->rates()->create([
            'name' => 'Standard Tax 2026',
            'total_rate' => '20.0000',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'priority' => 10,
            'status' => TaxRate::STATUS_ACTIVE,
        ]);

        $taxClass->refresh();

        $this->assertCount(2, $taxClass->rates);
        $this->assertTrue($taxClass->rates->contains($oldRate));
        $this->assertTrue($taxClass->rates->contains($newRate));
    }

    public function test_tax_rate_can_have_multiple_components(): void
    {
        $taxRate = $this->createTaxRate(totalRate: '7.2500');

        $state = $taxRate->components()->create([
            'code' => 'STATE',
            'name' => 'State Tax',
            'rate' => '6.2500',
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_STATE,
            'priority' => 10,
        ]);
        $city = $taxRate->components()->create([
            'code' => 'CITY',
            'name' => 'City Tax',
            'rate' => '1.0000',
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_LOCAL,
            'priority' => 20,
        ]);

        $taxRate->refresh();

        $this->assertCount(2, $taxRate->components);
        $this->assertTrue($taxRate->components->first()->is($state));
        $this->assertTrue($taxRate->components->last()->is($city));
    }

    public function test_relationships_resolve_from_components_to_rate_class_and_country(): void
    {
        $country = $this->createCountry();
        $taxClass = $this->createTaxClass($country);
        $taxRate = $this->createTaxRate($taxClass);
        $component = $taxRate->components()->create([
            'code' => 'VAT',
            'name' => 'VAT',
            'rate' => '20.0000',
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_CENTRAL,
        ]);

        $this->assertTrue($component->taxRate->is($taxRate));
        $this->assertTrue($component->taxRate->taxClass->is($taxClass));
        $this->assertTrue($component->taxRate->taxClass->country->is($country));
    }

    public function test_decimal_rate_precision_is_preserved_for_tax_rates_and_components(): void
    {
        $taxClass = $this->createTaxClass();

        foreach (['0.1250', '5.0000', '18.0000', '28.0000'] as $rateValue) {
            $taxRate = $this->createTaxRate($taxClass, $rateValue);
            $component = $taxRate->components()->create([
                'code' => 'RATE_'.$taxRate->getKey(),
                'name' => "Component {$rateValue}",
                'rate' => $rateValue,
                'jurisdiction_type' => TaxRateComponent::JURISDICTION_CENTRAL,
            ]);

            $this->assertSame($rateValue, $taxRate->refresh()->total_rate);
            $this->assertSame($rateValue, $component->refresh()->rate);
        }
    }

    public function test_effective_on_scope_returns_matching_active_rates(): void
    {
        $taxClass = $this->createTaxClass();

        $taxClass->rates()->create([
            'name' => 'Past Rate',
            'total_rate' => '12.0000',
            'effective_from' => '2025-01-01',
            'effective_to' => '2025-12-31',
            'status' => TaxRate::STATUS_ACTIVE,
        ]);
        $currentRate = $taxClass->rates()->create([
            'name' => 'Current Rate',
            'total_rate' => '18.0000',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'status' => TaxRate::STATUS_ACTIVE,
        ]);
        $taxClass->rates()->create([
            'name' => 'Inactive Matching Rate',
            'total_rate' => '99.0000',
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'status' => TaxRate::STATUS_INACTIVE,
        ]);

        $resolved = TaxRate::query()
            ->active()
            ->effectiveOn('2026-07-27')
            ->orderByDesc('priority')
            ->get();

        $this->assertCount(1, $resolved);
        $this->assertTrue($resolved->first()->is($currentRate));
    }

    public function test_tax_class_code_is_unique_within_country(): void
    {
        $country = $this->createCountry();
        $otherCountry = $this->createCountry('GBR', 'GB');

        $this->createTaxClass($country, 'STANDARD');

        $this->expectException(QueryException::class);

        TaxClass::query()->create([
            'country_id' => $country->getKey(),
            'code' => 'STANDARD',
            'name' => 'Duplicate Standard Tax',
            'status' => TaxClass::STATUS_ACTIVE,
        ]);

        TaxClass::query()->create([
            'country_id' => $otherCountry->getKey(),
            'code' => 'STANDARD',
            'name' => 'Other Country Standard Tax',
            'status' => TaxClass::STATUS_ACTIVE,
        ]);
    }

    public function test_same_tax_class_code_can_be_used_in_different_countries(): void
    {
        $country = $this->createCountry();
        $otherCountry = $this->createCountry('GBR', 'GB');

        $this->createTaxClass($country, 'STANDARD');
        $otherTaxClass = $this->createTaxClass($otherCountry, 'STANDARD');

        $this->assertDatabaseHas('tax_classes', [
            'country_id' => $otherCountry->getKey(),
            'code' => 'STANDARD',
            'id' => $otherTaxClass->getKey(),
        ]);
    }

    public function test_foreign_keys_protect_referenced_tax_master_records(): void
    {
        $taxClass = $this->createTaxClass();
        $taxRate = $this->createTaxRate($taxClass);
        $taxRate->components()->create([
            'code' => 'VAT',
            'name' => 'VAT',
            'rate' => '20.0000',
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_CENTRAL,
        ]);

        $this->expectException(QueryException::class);

        $taxRate->forceDelete();
    }

    public function test_tax_class_force_delete_is_protected_when_rates_exist(): void
    {
        $taxClass = $this->createTaxClass();
        $this->createTaxRate($taxClass);

        $this->expectException(QueryException::class);

        $taxClass->forceDelete();
    }

    private function createCountry(string $iso3 = 'IND', string $iso2 = 'IN'): LocCountry
    {
        $country = new LocCountry();
        $country->name = $iso3 === 'IND' ? 'India' : 'United Kingdom';
        $country->iso3 = $iso3;
        $country->iso2 = $iso2;
        $country->status = true;
        $country->save();

        return $country;
    }

    private function createTaxClass(?LocCountry $country = null, string $code = 'STANDARD'): TaxClass
    {
        $country ??= $this->createCountry();

        return TaxClass::query()->create([
            'country_id' => $country->getKey(),
            'code' => $code,
            'name' => "{$code} Tax",
            'status' => TaxClass::STATUS_ACTIVE,
        ]);
    }

    private function createTaxRate(?TaxClass $taxClass = null, string $totalRate = '20.0000'): TaxRate
    {
        $taxClass ??= $this->createTaxClass();

        return $taxClass->rates()->create([
            'name' => 'Standard Rate',
            'total_rate' => $totalRate,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'status' => TaxRate::STATUS_ACTIVE,
        ]);
    }
}
