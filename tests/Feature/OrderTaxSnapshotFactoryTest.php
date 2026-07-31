<?php

namespace Tests\Feature;

use App\Services\Order\Data\OrderItemTaxComponentSnapshot;
use App\Services\Order\Data\OrderItemTaxSnapshot;
use App\Services\Tax\Data\EffectiveTaxRateResult;
use App\Services\Tax\Data\PricingResult;
use App\Services\Tax\Data\TaxCalculationResult;
use App\Services\Tax\Data\TaxComponentAmount;
use App\Services\Tax\Data\TaxResolutionResult;
use App\Services\Tax\OrderTaxSnapshotFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderTaxSnapshotFactoryTest extends TestCase
{
    private CarbonImmutable $effectiveAt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->effectiveAt = CarbonImmutable::parse('2026-07-28 10:00:00');
    }

    public function test_exclusive_pricing_result_maps_correctly(): void
    {
        $snapshot = $this->snapshot($this->pricingResult(priceMode: 'exclusive'));

        $this->assertTrue($snapshot->taxEnabled);
        $this->assertSame('category_default', $snapshot->resolutionSource);
        $this->assertSame('exclusive', $snapshot->priceMode);
        $this->assertSame('300.00', $snapshot->lineSubtotal);
        $this->assertSame('30.00', $snapshot->discountAmount);
        $this->assertSame('270.00', $snapshot->taxableAmount);
        $this->assertSame('13.50', $snapshot->taxAmount);
        $this->assertSame('283.50', $snapshot->lineTotal);
    }

    public function test_inclusive_pricing_result_maps_correctly(): void
    {
        $snapshot = $this->snapshot($this->pricingResult(
            priceMode: 'inclusive',
            lineSubtotal: '315.00',
            discountAmount: '10.00',
            taxableAmount: '290.48',
            taxAmount: '14.52',
            lineTotal: '305.00',
        ));

        $this->assertSame('inclusive', $snapshot->priceMode);
        $this->assertSame('315.00', $snapshot->lineSubtotal);
        $this->assertSame('290.48', $snapshot->taxableAmount);
        $this->assertSame('14.52', $snapshot->taxAmount);
        $this->assertSame('305.00', $snapshot->lineTotal);
    }

    public function test_tax_disabled_result_maps_correctly(): void
    {
        $snapshot = $this->snapshot($this->noTaxPricingResult(TaxResolutionResult::SOURCE_TAX_DISABLED));

        $this->assertFalse($snapshot->taxEnabled);
        $this->assertSame(TaxResolutionResult::SOURCE_TAX_DISABLED, $snapshot->resolutionSource);
        $this->assertNull($snapshot->taxClassId);
        $this->assertNull($snapshot->taxRateId);
        $this->assertNull($snapshot->totalRate);
        $this->assertSame([], $snapshot->components);
        $this->assertSame('300.00', $snapshot->lineTotal);
    }

    public function test_product_exempt_result_maps_correctly(): void
    {
        $snapshot = $this->snapshot($this->noTaxPricingResult(TaxResolutionResult::SOURCE_PRODUCT_EXEMPT));

        $this->assertFalse($snapshot->taxEnabled);
        $this->assertSame(TaxResolutionResult::SOURCE_PRODUCT_EXEMPT, $snapshot->resolutionSource);
        $this->assertSame([], $snapshot->componentAttributes());
    }

    public function test_no_tax_class_result_maps_correctly(): void
    {
        $snapshot = $this->snapshot($this->noTaxPricingResult(TaxResolutionResult::SOURCE_NO_TAX_CLASS));

        $this->assertFalse($snapshot->taxEnabled);
        $this->assertSame(TaxResolutionResult::SOURCE_NO_TAX_CLASS, $snapshot->resolutionSource);
        $this->assertSame('300.00', $snapshot->lineSubtotal);
        $this->assertSame('0.00', $snapshot->taxAmount);
    }

    public function test_tax_class_and_rate_traceability_values_are_preserved(): void
    {
        $snapshot = $this->snapshot($this->pricingResult());

        $this->assertSame(501, $snapshot->taxClassId);
        $this->assertSame('GST_5', $snapshot->taxClassCode);
        $this->assertSame('GST 5%', $snapshot->taxClassName);
        $this->assertSame(901, $snapshot->taxRateId);
        $this->assertSame('GST 5% 2026', $snapshot->taxRateName);
        $this->assertSame('5.0000', $snapshot->totalRate);
    }

    public function test_cgst_and_sgst_component_snapshots_are_preserved(): void
    {
        $snapshot = $this->snapshot($this->pricingResult());
        $components = $snapshot->components;

        $this->assertCount(2, $components);
        $this->assertSame(301, $components[0]->taxComponentId);
        $this->assertSame('CGST', $components[0]->componentCode);
        $this->assertSame('Central GST', $components[0]->componentName);
        $this->assertSame('central', $components[0]->jurisdictionType);
        $this->assertSame('2.5000', $components[0]->rate);
        $this->assertSame('6.75', $components[0]->amount);
        $this->assertSame('SGST', $components[1]->componentCode);
        $this->assertSame('6.75', $components[1]->amount);
    }

    public function test_component_order_is_deterministic(): void
    {
        $snapshot = $this->snapshot($this->pricingResult());

        $this->assertSame([0, 1], array_map(fn (OrderItemTaxComponentSnapshot $component): int => $component->sortOrder, $snapshot->components));
        $this->assertSame(['CGST', 'SGST'], array_map(fn (OrderItemTaxComponentSnapshot $component): string => $component->componentCode, $snapshot->components));
    }

    public function test_decimal_strings_and_precision_are_preserved_exactly(): void
    {
        $snapshot = $this->snapshot($this->pricingResult(
            totalRate: '0.1250',
            lineSubtotal: '10.999',
            discountAmount: '0.001',
            taxableAmount: '10.998',
            taxAmount: '0.013',
            lineTotal: '11.011',
            components: [
                new TaxComponentAmount(777, 'MICRO', 'Micro Tax', '0.1250', '0.013', 'local'),
            ],
        ));

        $this->assertSame('0.1250', $snapshot->totalRate);
        $this->assertSame('10.999', $snapshot->lineSubtotal);
        $this->assertSame('0.001', $snapshot->discountAmount);
        $this->assertSame('0.013', $snapshot->taxAmount);
        $this->assertSame('11.011', $snapshot->lineTotal);
        $this->assertSame('0.1250', $snapshot->components[0]->rate);
        $this->assertSame('0.013', $snapshot->components[0]->amount);
    }

    public function test_no_float_calculation_or_additional_rounding_occurs(): void
    {
        $pricingResult = $this->pricingResult(
            lineSubtotal: '1.005',
            discountAmount: '0.005',
            taxableAmount: '1.000',
            taxAmount: '0.055',
            lineTotal: '1.055',
        );

        $attributes = $this->snapshot($pricingResult)->toOrderItemAttributes();

        $this->assertSame('1.005', $attributes['line_subtotal']);
        $this->assertSame('0.005', $attributes['line_discount']);
        $this->assertSame('0.055', $attributes['line_tax']);
        $this->assertSame('1.055', $attributes['line_total']);
    }

    public function test_dtos_expose_no_eloquent_models(): void
    {
        $snapshot = $this->snapshot($this->pricingResult());

        $this->assertNoEloquentModels($snapshot);
    }

    public function test_factory_performs_no_database_queries(): void
    {
        $pricingResult = $this->pricingResult();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->snapshot($pricingResult);

        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_database_attribute_arrays_use_step_7a_column_names(): void
    {
        $snapshot = $this->snapshot($this->pricingResult());

        $this->assertSame([
            'tax_enabled',
            'tax_resolution_source',
            'tax_class_id',
            'tax_class_code',
            'tax_class_name',
            'tax_rate_id',
            'tax_rate_name',
            'tax_rate',
            'price_mode',
            'taxable_amount',
            'line_subtotal',
            'line_discount',
            'line_tax',
            'line_total',
        ], array_keys($snapshot->toOrderItemAttributes()));

        $this->assertSame([
            'tax_component_id',
            'component_code',
            'component_name',
            'jurisdiction_type',
            'rate',
            'amount',
            'sort_order',
        ], array_keys($snapshot->componentAttributes()[0]));
    }

    public function test_mapping_does_not_mutate_original_pricing_result(): void
    {
        $pricingResult = $this->pricingResult();
        $before = $pricingResult->toArray();

        $this->snapshot($pricingResult);

        $this->assertSame($before, $pricingResult->toArray());
    }

    private function snapshot(PricingResult $pricingResult): OrderItemTaxSnapshot
    {
        return app(OrderTaxSnapshotFactory::class)->fromPricingResult($pricingResult);
    }

    /**
     * @param array<int, TaxComponentAmount>|null $components
     */
    private function pricingResult(
        string $priceMode = 'exclusive',
        string $totalRate = '5.0000',
        string $lineSubtotal = '300.00',
        string $discountAmount = '30.00',
        string $taxableAmount = '270.00',
        string $taxAmount = '13.50',
        string $lineTotal = '283.50',
        ?array $components = null,
    ): PricingResult {
        $components ??= [
            new TaxComponentAmount(301, 'CGST', 'Central GST', '2.5000', '6.75', 'central'),
            new TaxComponentAmount(302, 'SGST', 'State GST', '2.5000', '6.75', 'state'),
        ];

        return new PricingResult(
            resolution: TaxResolutionResult::resolved(
                source: TaxResolutionResult::SOURCE_CATEGORY_DEFAULT,
                taxClassId: 501,
                taxClassCode: 'GST_5',
                taxClassName: 'GST 5%',
                effectiveAt: $this->effectiveAt,
            ),
            effectiveRate: new EffectiveTaxRateResult(
                taxRateId: 901,
                taxRateName: 'GST 5% 2026',
                totalRate: $totalRate,
                effectiveFrom: '2026-01-01',
                effectiveTo: null,
                effectiveAt: $this->effectiveAt,
                components: $components,
            ),
            calculation: new TaxCalculationResult(
                taxEnabled: true,
                priceMode: $priceMode,
                unitPrice: '150.00',
                quantity: '2',
                lineSubtotal: $lineSubtotal,
                discountAmount: $discountAmount,
                taxableAmount: $taxableAmount,
                taxAmount: $taxAmount,
                lineTotal: $lineTotal,
                totalRate: $totalRate,
                componentAmounts: $components,
            ),
            calculatedAt: $this->effectiveAt,
        );
    }

    private function noTaxPricingResult(string $source): PricingResult
    {
        return new PricingResult(
            resolution: TaxResolutionResult::noTax($source, $this->effectiveAt),
            effectiveRate: null,
            calculation: new TaxCalculationResult(
                taxEnabled: false,
                priceMode: 'inclusive',
                unitPrice: '150.00',
                quantity: '2',
                lineSubtotal: '300.00',
                discountAmount: '0.00',
                taxableAmount: '300.00',
                taxAmount: '0.00',
                lineTotal: '300.00',
                totalRate: null,
                componentAmounts: [],
            ),
            calculatedAt: $this->effectiveAt,
        );
    }

    private function assertNoEloquentModels(mixed $value): void
    {
        if ($value instanceof Model) {
            $this->fail('Snapshot DTO exposes an Eloquent model.');
        }

        if (is_object($value)) {
            foreach (get_object_vars($value) as $property) {
                $this->assertNoEloquentModels($property);
            }
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertNoEloquentModels($item);
            }
        }

        $this->assertTrue(true);
    }
}
