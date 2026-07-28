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
        $taxClass = TaxClass::query()
            ->where('country_id', $indiaId)
            ->where('code', 'GST')
            ->firstOrFail();

        $this->assertSame('Goods and Services Tax', $taxClass->name);
        $this->assertSame(TaxClass::STATUS_INACTIVE, $taxClass->status);
        $this->assertNull($taxClass->created_by);
        $this->assertNull($taxClass->updated_by);
        $this->assertNull($taxClass->deleted_by);

        $rates = $taxClass->rates()->with('components')->orderBy('total_rate')->get();

        $this->assertSame(
            ['0.0000', '0.2500', '1.5000', '3.0000', '5.0000', '18.0000', '28.0000', '40.0000'],
            $rates->pluck('total_rate')->all(),
        );
        $this->assertTrue($rates->every(fn (TaxRate $rate): bool => $rate->status === TaxRate::STATUS_INACTIVE));
        $this->assertTrue($rates->every(fn (TaxRate $rate): bool => $rate->effective_from?->format('Y-m-d') === '2017-07-01'));
        $this->assertTrue($rates->every(fn (TaxRate $rate): bool => $rate->effective_to === null));

        $expectedComponents = [
            'GST 0%' => [['GST', 'GST', '0.0000', TaxRateComponent::JURISDICTION_CENTRAL, 1]],
            'GST 0.25%' => [
                ['CGST', 'CGST', '0.1250', TaxRateComponent::JURISDICTION_CENTRAL, 1],
                ['SGST', 'SGST', '0.1250', TaxRateComponent::JURISDICTION_STATE, 2],
            ],
            'GST 1.5%' => [
                ['CGST', 'CGST', '0.7500', TaxRateComponent::JURISDICTION_CENTRAL, 1],
                ['SGST', 'SGST', '0.7500', TaxRateComponent::JURISDICTION_STATE, 2],
            ],
            'GST 3%' => [
                ['CGST', 'CGST', '1.5000', TaxRateComponent::JURISDICTION_CENTRAL, 1],
                ['SGST', 'SGST', '1.5000', TaxRateComponent::JURISDICTION_STATE, 2],
            ],
            'GST 5%' => [
                ['CGST', 'CGST', '2.5000', TaxRateComponent::JURISDICTION_CENTRAL, 1],
                ['SGST', 'SGST', '2.5000', TaxRateComponent::JURISDICTION_STATE, 2],
            ],
            'GST 18%' => [
                ['CGST', 'CGST', '9.0000', TaxRateComponent::JURISDICTION_CENTRAL, 1],
                ['SGST', 'SGST', '9.0000', TaxRateComponent::JURISDICTION_STATE, 2],
            ],
            'GST 28%' => [
                ['CGST', 'CGST', '14.0000', TaxRateComponent::JURISDICTION_CENTRAL, 1],
                ['SGST', 'SGST', '14.0000', TaxRateComponent::JURISDICTION_STATE, 2],
            ],
            'GST 40%' => [
                ['CGST', 'CGST', '20.0000', TaxRateComponent::JURISDICTION_CENTRAL, 1],
                ['SGST', 'SGST', '20.0000', TaxRateComponent::JURISDICTION_STATE, 2],
            ],
        ];

        foreach ($rates as $rate) {
            $components = $rate->components->sortBy('priority')->values();

            $this->assertSame($expectedComponents[$rate->name], $components
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
        $this->assertFalse($taxClass->rates()->where('name', 'GST 12%')->exists());
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
        $this->assertSame(1, TaxClass::query()->where('code', 'GST')->count());
        $this->assertSame(8, TaxRate::query()->whereIn('name', [
            'GST 0%',
            'GST 0.25%',
            'GST 1.5%',
            'GST 3%',
            'GST 5%',
            'GST 18%',
            'GST 28%',
            'GST 40%',
        ])->count());
        $this->assertSame(15, TaxRateComponent::query()->count());
    }

    public function test_tax_seeder_restores_soft_deleted_seed_records(): void
    {
        $this->seed(TaxSeeder::class);

        $taxClass = TaxClass::query()->where('code', 'GST')->firstOrFail();
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
        $this->assertSame(TaxClass::STATUS_INACTIVE, $restoredClass->status);
        $this->assertSame(TaxRate::STATUS_INACTIVE, $restoredRate->status);
        $this->assertNull($restoredClass->deleted_by);
        $this->assertNull($restoredRate->deleted_by);
        $this->assertNull($restoredComponent->deleted_by);
        $this->assertSame(1, TaxClass::withTrashed()->where('code', 'GST')->count());
        $this->assertSame(1, TaxRate::withTrashed()->where('name', 'GST 18%')->count());
        $this->assertSame(1, TaxRateComponent::withTrashed()->where('tax_rate_id', $taxRateId)->where('code', 'CGST')->count());
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
}
