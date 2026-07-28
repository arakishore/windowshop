<?php

namespace Tests\Feature;

use App\Models\LocCountry;
use App\Models\LocState;
use App\Models\MerchantAddress;
use App\Models\MerchantProfile;
use App\Models\MerchantTaxSetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\TaxClass;
use App\Models\TaxRateComponent;
use App\Models\User;
use App\Services\Tax\Data\TaxResolutionResult;
use App\Services\Tax\EffectiveTaxRateResolver;
use App\Services\Tax\Exceptions\TaxConfigurationException;
use App\Services\Tax\Exceptions\OverlappingTaxRatesException;
use App\Services\Tax\Exceptions\TaxCalculationInputException;
use App\Services\Tax\Exceptions\TaxComponentMismatchException;
use App\Services\Tax\Exceptions\TaxRateNotFoundException;
use App\Services\Tax\PricingEngine;
use App\Services\Tax\TaxCalculator;
use App\Services\Tax\TaxResolver;
use Carbon\CarbonImmutable;
use Database\Seeders\MasterData\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class TaxPricingServicesTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $effectiveAt;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LocationSeeder::class);
        $this->effectiveAt = CarbonImmutable::parse('2026-07-28 10:00:00');
    }

    public function test_tax_resolver_returns_no_tax_when_merchant_tax_is_disabled(): void
    {
        [$merchant, $product] = $this->fixture(taxEnabled: false);

        $result = app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);

        $this->assertFalse($result->taxEnabled);
        $this->assertSame(TaxResolutionResult::SOURCE_TAX_DISABLED, $result->resolutionSource);
        $this->assertNull($result->taxClassId);
    }

    public function test_tax_resolver_returns_no_tax_when_merchant_tax_settings_are_missing(): void
    {
        [$merchant, $product] = $this->fixture();
        $merchant->taxSetting()->delete();

        $result = app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);

        $this->assertFalse($result->taxEnabled);
        $this->assertSame(TaxResolutionResult::SOURCE_TAX_DISABLED, $result->resolutionSource);
    }

    public function test_tax_resolver_respects_product_exempt_before_defaults(): void
    {
        [$merchant, $product] = $this->fixture();
        $product->forceFill(['tax_mode' => 'exempt'])->save();

        $result = app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);

        $this->assertFalse($result->taxEnabled);
        $this->assertSame(TaxResolutionResult::SOURCE_PRODUCT_EXEMPT, $result->resolutionSource);
    }

    public function test_tax_resolver_uses_product_override(): void
    {
        [$merchant, $product, $gst5, $gst18] = $this->fixture();
        $product->forceFill([
            'tax_mode' => 'override',
            'tax_class_id' => $gst18->getKey(),
        ])->save();

        $result = app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);

        $this->assertTrue($result->taxEnabled);
        $this->assertSame(TaxResolutionResult::SOURCE_PRODUCT_OVERRIDE, $result->resolutionSource);
        $this->assertSame($gst18->getKey(), $result->taxClassId);
        $this->assertSame('GST_18', $result->taxClassCode);
    }

    public function test_tax_resolver_product_override_wins_over_category_default(): void
    {
        [$merchant, $product, , $gst18] = $this->fixture();
        $product->forceFill([
            'tax_mode' => 'override',
            'tax_class_id' => $gst18->getKey(),
        ])->save();

        $result = app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);

        $this->assertSame(TaxResolutionResult::SOURCE_PRODUCT_OVERRIDE, $result->resolutionSource);
        $this->assertSame($gst18->getKey(), $result->taxClassId);
    }

    public function test_tax_resolver_uses_category_default_before_merchant_default(): void
    {
        [$merchant, $product, $gst5] = $this->fixture();

        $result = app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);

        $this->assertSame(TaxResolutionResult::SOURCE_CATEGORY_DEFAULT, $result->resolutionSource);
        $this->assertSame($gst5->getKey(), $result->taxClassId);
    }

    public function test_tax_resolver_uses_merchant_default_when_category_has_no_default(): void
    {
        [$merchant, $product, , $gst18] = $this->fixture(categoryDefault: false);

        $result = app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);

        $this->assertSame(TaxResolutionResult::SOURCE_MERCHANT_DEFAULT, $result->resolutionSource);
        $this->assertSame($gst18->getKey(), $result->taxClassId);
    }

    public function test_tax_resolver_returns_no_tax_when_no_default_exists(): void
    {
        [$merchant, $product] = $this->fixture(categoryDefault: false, merchantDefault: false);

        $result = app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);

        $this->assertFalse($result->taxEnabled);
        $this->assertSame(TaxResolutionResult::SOURCE_NO_TAX_CLASS, $result->resolutionSource);
    }

    public function test_tax_resolver_rejects_invalid_stored_tax_classes(): void
    {
        [$merchant, $product, $gst5] = $this->fixture();
        $product->category->forceFill(['default_tax_class_id' => null])->save();
        $product->forceFill([
            'tax_mode' => 'override',
            'tax_class_id' => $gst5->getKey(),
        ])->save();

        $gst5->forceFill(['status' => TaxClass::STATUS_INACTIVE])->save();
        $this->expectException(TaxConfigurationException::class);

        app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);
    }

    public function test_tax_resolver_rejects_deleted_and_other_country_classes(): void
    {
        [$merchant, $product, $gst5] = $this->fixture();
        $product->forceFill([
            'tax_mode' => 'override',
            'tax_class_id' => $gst5->getKey(),
        ])->save();
        $gst5->delete();

        try {
            app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);
            $this->fail('Deleted tax class was accepted.');
        } catch (TaxConfigurationException $exception) {
            $this->assertStringContainsString('deleted', $exception->getMessage());
        }

        $otherCountry = $this->country('ZZ', 'ZZZ', 'Zedland');
        $otherClass = $this->taxClass($otherCountry, 'VAT_20', 'VAT 20%', '20.0000', '10.0000');
        $product->forceFill(['tax_class_id' => $otherClass->getKey()])->save();

        $this->expectException(TaxConfigurationException::class);
        $this->expectExceptionMessage('does not match');

        app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);
    }

    public function test_tax_resolver_rejects_invalid_category_and_merchant_default_classes(): void
    {
        [$merchant, $product, $gst5, $gst18] = $this->fixture();
        $gst5->forceFill(['status' => TaxClass::STATUS_INACTIVE])->save();

        try {
            app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);
            $this->fail('Inactive category tax class was accepted.');
        } catch (TaxConfigurationException $exception) {
            $this->assertStringContainsString('category default', $exception->getMessage());
        }

        $product->category->forceFill(['default_tax_class_id' => null])->save();
        $gst18->delete();

        $this->expectException(TaxConfigurationException::class);
        $this->expectExceptionMessage('merchant default');

        app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);
    }

    public function test_effective_tax_rate_resolver_selects_date_matching_rate(): void
    {
        [$merchant, $product, $gst5] = $this->fixture();
        $gst5->rates()->delete();
        $oldRate = $this->rate($gst5, 'GST 5% old', '5.0000', '2025-01-01', '2026-03-31');
        $newRate = $this->rate($gst5, 'GST 6%', '6.0000', '2026-04-01');
        $this->taxComponent($oldRate, 'CGST', '2.5000', 1);
        $this->taxComponent($oldRate, 'SGST', '2.5000', 2);
        $this->taxComponent($newRate, 'CGST', '3.0000', 1);
        $this->taxComponent($newRate, 'SGST', '3.0000', 2);

        $resolution = app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);
        $rate = app(EffectiveTaxRateResolver::class)->resolve($resolution, $this->effectiveAt);

        $this->assertSame('GST 6%', $rate->taxRateName);
        $this->assertSame('6.0000', $rate->totalRate);
        $this->assertCount(2, $rate->components);
    }

    public function test_effective_tax_rate_resolver_respects_future_expired_and_exact_boundaries(): void
    {
        [, , $gst5] = $this->fixture();
        $gst5->rates()->delete();
        $expired = $this->rate($gst5, 'Expired', '5.0000', '2026-01-01', '2026-03-31');
        $current = $this->rate($gst5, 'Boundary Current', '5.0000', '2026-04-01', '2026-07-28');
        $future = $this->rate($gst5, 'Future', '5.0000', '2026-07-29');
        foreach ([$expired, $current, $future] as $rate) {
            $this->taxComponent($rate, 'CGST', '2.5000', 1);
            $this->taxComponent($rate, 'SGST', '2.5000', 2);
        }

        $fromBoundary = app(EffectiveTaxRateResolver::class)->resolve($gst5, CarbonImmutable::parse('2026-04-01 00:00:00'));
        $toBoundary = app(EffectiveTaxRateResolver::class)->resolve($gst5, CarbonImmutable::parse('2026-07-28 23:59:59'));

        $this->assertSame($current->getKey(), $fromBoundary->taxRateId);
        $this->assertSame($current->getKey(), $toBoundary->taxRateId);
        $this->assertSame('2026-04-01', $toBoundary->effectiveFrom);
        $this->assertSame('2026-07-28', $toBoundary->effectiveTo);
    }

    public function test_effective_tax_rate_resolver_rejects_overlapping_applicable_rates(): void
    {
        [, , $gst5] = $this->fixture();
        $this->rate($gst5, 'Overlap', '6.0000', '2026-07-01', '2026-12-31');

        $this->expectException(OverlappingTaxRatesException::class);

        app(EffectiveTaxRateResolver::class)->resolve($gst5, $this->effectiveAt);
    }

    public function test_effective_tax_rate_resolver_ignores_inactive_and_soft_deleted_rates(): void
    {
        [, , $gst5] = $this->fixture();
        $gst5->rates()->delete();
        $inactive = $this->rate($gst5, 'Inactive', '5.0000', '2026-01-01', status: 'inactive');
        $deleted = $this->rate($gst5, 'Deleted', '5.0000', '2026-01-01');
        $active = $this->rate($gst5, 'Active', '5.0000', '2026-01-01');
        foreach ([$inactive, $deleted, $active] as $rate) {
            $this->taxComponent($rate, 'CGST', '2.5000', 1);
            $this->taxComponent($rate, 'SGST', '2.5000', 2);
        }
        $deleted->delete();

        $result = app(EffectiveTaxRateResolver::class)->resolve($gst5, $this->effectiveAt);

        $this->assertSame($active->getKey(), $result->taxRateId);
    }

    public function test_effective_tax_rate_resolver_rejects_component_sum_mismatch(): void
    {
        [, , $gst5] = $this->fixture();
        $gst5->rates()->delete();
        $rate = $this->rate($gst5, 'GST 5%', '5.0000', '2026-01-01');
        $this->taxComponent($rate, 'CGST', '2.0000', 1);
        $this->taxComponent($rate, 'SGST', '2.0000', 2);

        $this->expectException(TaxComponentMismatchException::class);

        app(EffectiveTaxRateResolver::class)->resolve($gst5, $this->effectiveAt);
    }

    public function test_effective_tax_rate_resolver_rejects_missing_effective_rate(): void
    {
        [$merchant, $product, $gst5] = $this->fixture();
        $gst5->rates()->delete();

        $resolution = app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);

        $this->expectException(TaxRateNotFoundException::class);

        app(EffectiveTaxRateResolver::class)->resolve($resolution, $this->effectiveAt);
    }

    public function test_tax_calculator_handles_exclusive_inclusive_and_no_tax_lines(): void
    {
        [, , $gst5] = $this->fixture();
        $rate = app(EffectiveTaxRateResolver::class)->resolve($gst5, $this->effectiveAt);
        $calculator = app(TaxCalculator::class);

        $exclusive = $calculator->calculateLine('100.00', 2, false, $rate);
        $this->assertSame('exclusive', $exclusive->priceMode);
        $this->assertSame('200.00', $exclusive->taxableAmount);
        $this->assertSame('10.00', $exclusive->taxAmount);
        $this->assertSame('210.00', $exclusive->lineTotal);
        $this->assertSame('5.00', $exclusive->componentAmounts[0]->amount);

        $inclusive = $calculator->calculateLine('105.00', 2, true, $rate);
        $this->assertSame('inclusive', $inclusive->priceMode);
        $this->assertSame('200.00', $inclusive->taxableAmount);
        $this->assertSame('10.00', $inclusive->taxAmount);
        $this->assertSame('210.00', $inclusive->lineTotal);

        $noTax = $calculator->calculateLine('100.00', 2, true, null, '10.00');
        $this->assertFalse($noTax->taxEnabled);
        $this->assertSame('190.00', $noTax->taxableAmount);
        $this->assertSame('0.00', $noTax->taxAmount);
        $this->assertSame('190.00', $noTax->lineTotal);
    }

    public function test_tax_calculator_handles_zero_rate_full_discount_and_discount_cap(): void
    {
        [, , $gst5] = $this->fixture();
        $gst5->rates()->delete();
        $zeroRate = $this->rate($gst5, 'GST 0%', '0.0000', '2026-01-01');
        $this->taxComponent($zeroRate, 'CGST', '0.0000', 1);
        $this->taxComponent($zeroRate, 'SGST', '0.0000', 2);
        $rate = app(EffectiveTaxRateResolver::class)->resolve($gst5, $this->effectiveAt);
        $calculator = app(TaxCalculator::class);

        $zero = $calculator->calculateLine('100.00', 2, false, $rate);
        $this->assertTrue($zero->taxEnabled);
        $this->assertSame('0.00', $zero->taxAmount);
        $this->assertSame('200.00', $zero->lineTotal);

        $fullDiscount = $calculator->calculateLine('100.00', 2, false, $rate, '200.00');
        $this->assertSame('0.00', $fullDiscount->taxableAmount);
        $this->assertSame('0.00', $fullDiscount->taxAmount);
        $this->assertSame('0.00', $fullDiscount->lineTotal);

        $cappedDiscount = $calculator->calculateLine('100.00', 2, false, $rate, '999.00');
        $this->assertSame('200.00', $cappedDiscount->discountAmount);
        $this->assertSame('0.00', $cappedDiscount->lineTotal);
    }

    public function test_tax_calculator_allocates_component_rounding_remainder_and_exact_total(): void
    {
        [, , $gst5] = $this->fixture();
        $gst5->rates()->delete();
        $rate = $this->rate($gst5, 'GST 50%', '50.0000', '2026-01-01');
        $this->taxComponent($rate, 'A', '25.0000', 1);
        $this->taxComponent($rate, 'B', '25.0000', 2);
        $resolvedRate = app(EffectiveTaxRateResolver::class)->resolve($gst5, $this->effectiveAt);

        $result = app(TaxCalculator::class)->calculateLine('2.02', 1, false, $resolvedRate);

        $this->assertSame('1.01', $result->taxAmount);
        $this->assertSame('0.51', $result->componentAmounts[0]->amount);
        $this->assertSame('0.50', $result->componentAmounts[1]->amount);
        $this->assertSame($result->taxAmount, $this->sumMoney($result->componentAmounts[0]->amount, $result->componentAmounts[1]->amount));
    }

    public function test_tax_calculator_rejects_negative_inputs_and_zero_quantity(): void
    {
        [, , $gst5] = $this->fixture();
        $rate = app(EffectiveTaxRateResolver::class)->resolve($gst5, $this->effectiveAt);
        $calculator = app(TaxCalculator::class);

        foreach ([['-1.00', 1, '0.00'], ['1.00', 0, '0.00'], ['1.00', 1, '-0.01']] as $input) {
            try {
                $calculator->calculateLine($input[0], $input[1], false, $rate, $input[2]);
                $this->fail('Invalid calculator input was accepted.');
            } catch (TaxCalculationInputException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_pricing_engine_composes_resolution_rate_and_calculation(): void
    {
        [$merchant, $product] = $this->fixture(pricesIncludeTax: false);

        $result = app(PricingEngine::class)->calculateProductLine(
            product: $product,
            merchant: $merchant,
            unitPrice: '100.00',
            quantity: 3,
            effectiveAt: $this->effectiveAt,
            discountAmount: '30.00',
        );

        $this->assertSame(TaxResolutionResult::SOURCE_CATEGORY_DEFAULT, $result->resolution->resolutionSource);
        $this->assertSame('5.0000', $result->effectiveRate->totalRate);
        $this->assertSame('270.00', $result->calculation->taxableAmount);
        $this->assertSame('13.50', $result->calculation->taxAmount);
        $this->assertSame('283.50', $result->calculation->lineTotal);
    }

    public function test_pricing_engine_handles_merchant_fallback_product_override_exempt_and_disabled(): void
    {
        [$merchant, $product, , $gst18] = $this->fixture(categoryDefault: false, pricesIncludeTax: true);

        $merchantFallback = app(PricingEngine::class)->calculateProductLine($product, $merchant, '118.00', 1, $this->effectiveAt);
        $this->assertSame(TaxResolutionResult::SOURCE_MERCHANT_DEFAULT, $merchantFallback->resolution->resolutionSource);
        $this->assertSame('inclusive', $merchantFallback->calculation->priceMode);

        $product->forceFill(['tax_mode' => 'override', 'tax_class_id' => $gst18->getKey()])->save();
        $override = app(PricingEngine::class)->calculateProductLine($product, $merchant, '118.00', 1, $this->effectiveAt);
        $this->assertSame(TaxResolutionResult::SOURCE_PRODUCT_OVERRIDE, $override->resolution->resolutionSource);

        $product->forceFill(['tax_mode' => 'exempt', 'tax_class_id' => null])->save();
        $exempt = app(PricingEngine::class)->calculateProductLine($product, $merchant, '118.00', 1, $this->effectiveAt);
        $this->assertFalse($exempt->calculation->taxEnabled);
        $this->assertSame(TaxResolutionResult::SOURCE_PRODUCT_EXEMPT, $exempt->resolution->resolutionSource);

        $merchant->taxSetting->forceFill(['tax_enabled' => false])->save();
        $product->forceFill(['tax_mode' => 'inherit'])->save();
        $disabled = app(PricingEngine::class)->calculateProductLine($product, $merchant, '118.00', 1, $this->effectiveAt);
        $this->assertSame(TaxResolutionResult::SOURCE_TAX_DISABLED, $disabled->resolution->resolutionSource);
        $this->assertSame('0.00', $disabled->calculation->taxAmount);
    }

    public function test_pricing_engine_array_shape_contains_snapshot_fields(): void
    {
        [$merchant, $product] = $this->fixture(pricesIncludeTax: false);

        $array = app(PricingEngine::class)
            ->calculateProductLine($product, $merchant, '100.00', 1, $this->effectiveAt)
            ->toArray();

        foreach ([
            'tax_enabled',
            'resolution_source',
            'tax_class_id',
            'tax_class_code',
            'tax_class_name',
            'tax_rate_id',
            'tax_rate_name',
            'total_rate',
            'price_mode',
            'unit_price',
            'quantity',
            'line_subtotal',
            'discount_amount',
            'taxable_amount',
            'tax_amount',
            'component_amounts',
            'line_total',
            'calculated_at',
        ] as $key) {
            $this->assertArrayHasKey($key, $array);
        }

        $this->assertSame($this->effectiveAt->toDateTimeString(), $array['calculated_at']);
    }

    public function test_tax_service_dtos_do_not_expose_eloquent_models(): void
    {
        [$merchant, $product, $gst5] = $this->fixture(pricesIncludeTax: false);

        $resolution = app(TaxResolver::class)->resolve($product, $merchant, $this->effectiveAt);
        $rate = app(EffectiveTaxRateResolver::class)->resolve($gst5, $this->effectiveAt);

        $this->assertObjectNotHasProperty('taxClass', $resolution);
        $this->assertObjectNotHasProperty('taxRate', $rate);
    }

    private function fixture(
        bool $taxEnabled = true,
        bool $categoryDefault = true,
        bool $merchantDefault = true,
        bool $pricesIncludeTax = true,
    ): array {
        $india = LocCountry::query()->where('iso2', 'IN')->firstOrFail();
        $state = LocState::query()->where('country_id', $india->getKey())->firstOrFail();
        $merchant = MerchantProfile::query()->create([
            'user_id' => $this->user()->getKey(),
            'business_name' => 'Tax Service Merchant '.Str::random(6),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        MerchantAddress::query()->create([
            'merchant_id' => $merchant->getKey(),
            'address_type' => 'business',
            'address_line_1' => 'Market Road',
            'country_id' => $india->getKey(),
            'state_id' => $state->getKey(),
            'status' => 'active',
        ]);
        $gst5 = $this->taxClass($india, 'GST_5', 'GST 5%', '5.0000', '2.5000');
        $gst18 = $this->taxClass($india, 'GST_18', 'GST 18%', '18.0000', '9.0000');
        MerchantTaxSetting::query()->create([
            'merchant_id' => $merchant->getKey(),
            'tax_enabled' => $taxEnabled,
            'default_tax_class_id' => $merchantDefault ? $gst18->getKey() : null,
            'prices_include_tax' => $pricesIncludeTax,
        ]);
        $root = ProductCategory::query()->create([
            'name' => 'Apparel '.Str::random(4),
            'slug' => 'apparel-'.Str::random(6),
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'parent_id' => $root->getKey(),
            'name' => 'Shirts '.Str::random(4),
            'slug' => 'shirts-'.Str::random(6),
            'default_tax_class_id' => $categoryDefault ? $gst5->getKey() : null,
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $root->getKey(),
            'name' => 'Tax Service Shop '.Str::random(4),
            'slug' => 'tax-service-shop-'.Str::random(6),
            'address_line_1' => 'Market Road',
            'country_id' => $india->getKey(),
            'state_id' => $state->getKey(),
            'status' => 'active',
        ]);
        $product = Product::query()->create([
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'root_product_category_id' => $root->getKey(),
            'product_category_id' => $category->getKey(),
            'product_name' => 'Tax Service Shirt '.Str::random(4),
            'slug' => 'tax-service-shirt-'.Str::random(6),
            'tax_mode' => 'inherit',
            'tax_class_id' => null,
            'status' => 'draft',
        ]);

        return [$merchant, $product, $gst5, $gst18];
    }

    private function country(string $iso2, string $iso3, string $name): LocCountry
    {
        $country = new LocCountry();
        $country->uuid = (string) Str::uuid();
        $country->name = $name;
        $country->iso2 = $iso2;
        $country->iso3 = $iso3;
        $country->status = true;
        $country->save();

        return $country;
    }

    private function user(): User
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tax Service User',
            'email' => 'tax-service-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    private function taxClass(LocCountry $country, string $code, string $name, string $totalRate, string $componentRate): TaxClass
    {
        $taxClass = TaxClass::query()->create([
            'country_id' => $country->getKey(),
            'code' => $code,
            'name' => $name,
            'status' => TaxClass::STATUS_ACTIVE,
        ]);
        $taxRate = $this->rate($taxClass, $name, $totalRate, '2026-01-01');
        $this->taxComponent($taxRate, 'CGST', $componentRate, 1);
        $this->taxComponent($taxRate, 'SGST', $componentRate, 2);

        return $taxClass;
    }

    private function rate(TaxClass $taxClass, string $name, string $totalRate, string $from, ?string $to = null, string $status = 'active')
    {
        return $taxClass->rates()->create([
            'name' => $name,
            'total_rate' => $totalRate,
            'effective_from' => $from,
            'effective_to' => $to,
            'status' => $status,
        ]);
    }

    private function taxComponent($taxRate, string $code, string $rate, int $priority): void
    {
        $taxRate->components()->create([
            'code' => $code,
            'name' => $code,
            'rate' => $rate,
            'jurisdiction_type' => $code === 'CGST'
                ? TaxRateComponent::JURISDICTION_CENTRAL
                : TaxRateComponent::JURISDICTION_STATE,
            'priority' => $priority,
        ]);
    }

    private function sumMoney(string ...$amounts): string
    {
        $cents = 0;

        foreach ($amounts as $amount) {
            [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
            $cents += ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
        }

        return number_format($cents / 100, 2, '.', '');
    }
}
