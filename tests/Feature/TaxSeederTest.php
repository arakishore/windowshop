<?php

namespace Tests\Feature;

use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Models\TaxRateComponent;
use Database\Seeders\MasterData\LocationSeeder;
use Database\Seeders\TaxSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class TaxSeederTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LocationSeeder::class);
    }

    public function test_tax_seeder_creates_india_gst_reference_data(): void
    {
        $this->seed(TaxSeeder::class);

        $indiaId = DB::table('loc_countries')->where('iso2', 'IN')->value('id');
        $expected = $this->expectedGstSlabs();
        $taxClasses = TaxClass::query()
            ->where('country_id', $indiaId)
            ->whereIn('code', array_keys($expected))
            ->with(['rates.components'])
            ->orderBy('code')
            ->get();

        $this->assertCount(8, $taxClasses);
        $this->assertFalse(TaxClass::query()->where('country_id', $indiaId)->where('code', 'GST')->exists());

        foreach ($taxClasses as $taxClass) {
            $expectedSlab = $expected[$taxClass->code];

            $this->assertSame($expectedSlab['name'], $taxClass->name);
            $this->assertSame($expectedSlab['sort_order'], $taxClass->sort_order);
            $this->assertSame(TaxClass::STATUS_ACTIVE, $taxClass->status);
            $this->assertNull($taxClass->created_by);
            $this->assertNull($taxClass->updated_by);
            $this->assertNull($taxClass->deleted_by);
            $this->assertCount(1, $taxClass->rates);

            $rate = $taxClass->rates->first();
            $components = $rate->components->sortBy('priority')->values();

            $this->assertSame($expectedSlab['name'], $rate->name);
            $this->assertSame($expectedSlab['total_rate'], $rate->total_rate);
            $this->assertSame(TaxRate::STATUS_ACTIVE, $rate->status);
            $this->assertSame('2017-07-01', $rate->effective_from?->format('Y-m-d'));
            $this->assertNull($rate->effective_to);
            $this->assertSame($expectedSlab['components'], $components
                ->map(fn (TaxRateComponent $component): array => [
                    $component->code,
                    $component->name,
                    $component->rate,
                    $component->jurisdiction_type,
                    $component->priority,
                ])
                ->all());

            $this->assertSame(
                (int) round(((float) $rate->total_rate) * 10000),
                (int) round($components->sum(fn (TaxRateComponent $component): float => (float) $component->rate) * 10000),
            );
        }

        $this->assertFalse(TaxRateComponent::query()->where('code', 'IGST')->exists());
        $this->assertFalse(TaxClass::query()->where('code', 'GST_12')->exists());
    }

    public function test_tax_seeder_is_idempotent(): void
    {
        $this->seed(TaxSeeder::class);

        $counts = [
            'tax_classes' => TaxClass::query()->count(),
            'tax_rates' => TaxRate::query()->count(),
            'tax_rate_components' => TaxRateComponent::query()->count(),
        ];

        $this->seed(TaxSeeder::class);

        $this->assertSame($counts['tax_classes'], TaxClass::query()->count());
        $this->assertSame($counts['tax_rates'], TaxRate::query()->count());
        $this->assertSame($counts['tax_rate_components'], TaxRateComponent::query()->count());
        $this->assertSame(8, TaxClass::query()->whereIn('code', array_keys($this->expectedGstSlabs()))->count());
        $this->assertSame(8, TaxRate::query()->whereIn('name', collect($this->expectedGstSlabs())->pluck('name')->all())->count());
        $this->assertSame(16, TaxRateComponent::query()->count());
    }

    public function test_tax_seeder_restores_soft_deleted_seed_records(): void
    {
        $this->seed(TaxSeeder::class);

        $taxClass = TaxClass::query()->where('code', 'GST_18')->firstOrFail();
        $taxRate = $taxClass->rates()->where('name', 'GST 18%')->firstOrFail();
        $component = $taxRate->components()->where('code', 'CGST')->firstOrFail();

        $taxClassId = $taxClass->getKey();
        $taxRateId = $taxRate->getKey();
        $componentId = $component->getKey();

        $deletedBy = $this->createUserId();

        $component->forceFill(['deleted_by' => $deletedBy])->save();
        $component->delete();
        $taxRate->forceFill(['deleted_by' => $deletedBy])->save();
        $taxRate->delete();
        $taxClass->forceFill(['deleted_by' => $deletedBy])->save();
        $taxClass->delete();

        $this->seed(TaxSeeder::class);

        $restoredClass = TaxClass::query()->findOrFail($taxClassId);
        $restoredRate = TaxRate::query()->findOrFail($taxRateId);
        $restoredComponent = TaxRateComponent::query()->findOrFail($componentId);

        $this->assertFalse($restoredClass->trashed());
        $this->assertFalse($restoredRate->trashed());
        $this->assertFalse($restoredComponent->trashed());
        $this->assertSame(TaxClass::STATUS_ACTIVE, $restoredClass->status);
        $this->assertSame(TaxRate::STATUS_ACTIVE, $restoredRate->status);
        $this->assertNull($restoredClass->deleted_by);
        $this->assertNull($restoredRate->deleted_by);
        $this->assertNull($restoredComponent->deleted_by);
        $this->assertSame(1, TaxClass::withTrashed()->where('code', 'GST_18')->count());
        $this->assertSame(1, TaxRate::withTrashed()->where('name', 'GST 18%')->count());
        $this->assertSame(1, TaxRateComponent::withTrashed()->where('tax_rate_id', $taxRateId)->where('code', 'CGST')->count());
    }

    public function test_tax_seeder_retires_legacy_generic_gst_class(): void
    {
        $indiaId = DB::table('loc_countries')->where('iso2', 'IN')->value('id');
        $legacyClass = TaxClass::query()->create([
            'country_id' => $indiaId,
            'code' => 'GST',
            'name' => 'Goods and Services Tax',
            'status' => TaxClass::STATUS_ACTIVE,
        ]);
        $legacyRate = $legacyClass->rates()->create([
            'name' => 'GST 18%',
            'total_rate' => '18.0000',
            'effective_from' => '2017-07-01',
            'status' => TaxRate::STATUS_ACTIVE,
        ]);
        $legacyRate->components()->create([
            'code' => 'CGST',
            'name' => 'CGST',
            'rate' => '9.0000',
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_CENTRAL,
            'priority' => 1,
        ]);

        $this->seed(TaxSeeder::class);

        $this->assertTrue(TaxClass::withTrashed()->findOrFail($legacyClass->getKey())->trashed());
        $this->assertTrue(TaxRate::withTrashed()->findOrFail($legacyRate->getKey())->trashed());
        $this->assertSame(8, TaxClass::query()->whereIn('code', array_keys($this->expectedGstSlabs()))->count());
    }

    public function test_tax_seeder_does_not_create_or_change_unrelated_module_data(): void
    {
        $tables = [
            'merchant_profiles',
            'products',
            'product_categories',
            'orders',
            'order_items',
            'order_totals',
            'order_refunds',
            'order_exchanges',
        ];

        $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();

        $this->seed(TaxSeeder::class);
        $this->seed(TaxSeeder::class);

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "{$table} count changed.");
        }
    }

    private function createUserId(): int
    {
        return DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tax Seeder User',
            'email' => 'tax-seeder-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function expectedGstSlabs(): array
    {
        return [
            'GST_0' => [
                'name' => 'GST 0%',
                'sort_order' => 10,
                'total_rate' => '0.0000',
                'components' => $this->expectedSplit('0.0000'),
            ],
            'GST_025' => [
                'name' => 'GST 0.25%',
                'sort_order' => 20,
                'total_rate' => '0.2500',
                'components' => $this->expectedSplit('0.1250'),
            ],
            'GST_15' => [
                'name' => 'GST 1.5%',
                'sort_order' => 30,
                'total_rate' => '1.5000',
                'components' => $this->expectedSplit('0.7500'),
            ],
            'GST_3' => [
                'name' => 'GST 3%',
                'sort_order' => 40,
                'total_rate' => '3.0000',
                'components' => $this->expectedSplit('1.5000'),
            ],
            'GST_5' => [
                'name' => 'GST 5%',
                'sort_order' => 50,
                'total_rate' => '5.0000',
                'components' => $this->expectedSplit('2.5000'),
            ],
            'GST_18' => [
                'name' => 'GST 18%',
                'sort_order' => 60,
                'total_rate' => '18.0000',
                'components' => $this->expectedSplit('9.0000'),
            ],
            'GST_28' => [
                'name' => 'GST 28%',
                'sort_order' => 70,
                'total_rate' => '28.0000',
                'components' => $this->expectedSplit('14.0000'),
            ],
            'GST_40' => [
                'name' => 'GST 40%',
                'sort_order' => 80,
                'total_rate' => '40.0000',
                'components' => $this->expectedSplit('20.0000'),
            ],
        ];
    }

    private function expectedSplit(string $rate): array
    {
        return [
            ['CGST', 'CGST', $rate, TaxRateComponent::JURISDICTION_CENTRAL, 1],
            ['SGST', 'SGST', $rate, TaxRateComponent::JURISDICTION_STATE, 2],
        ];
    }
}
