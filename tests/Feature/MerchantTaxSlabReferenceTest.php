<?php

namespace Tests\Feature;

use App\Models\LocCountry;
use App\Models\LocState;
use App\Models\MerchantAddress;
use App\Models\MerchantProfile;
use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Models\TaxRateComponent;
use App\Models\User;
use Database\Seeders\MasterData\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantTaxSlabReferenceTest extends TestCase
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

    public function test_merchant_can_view_active_tax_slabs_for_business_country(): void
    {
        $merchant = $this->merchantFixture('Tax Slab Merchant');
        $this->assignMerchantRole($merchant->user);
        [$india, $state] = $this->businessLocation($merchant);

        $gst = $this->taxClass($india, 'GST', 'Goods and Services Tax', TaxClass::STATUS_ACTIVE);
        $gst18 = $this->taxRate($gst, 'GST 18%', '18.0000', TaxRate::STATUS_ACTIVE);
        $this->taxComponent($gst18, 'CGST', 'Central GST', '9.0000', TaxRateComponent::JURISDICTION_CENTRAL, 1);
        $this->taxComponent($gst18, 'SGST', 'State GST', '9.0000', TaxRateComponent::JURISDICTION_STATE, 2);

        $inactiveRate = $this->taxRate($gst, 'GST 99%', '99.0000', TaxRate::STATUS_INACTIVE);
        $this->taxComponent($inactiveRate, 'TEST', 'Hidden Test Tax', '99.0000', TaxRateComponent::JURISDICTION_LOCAL, 1);

        $otherCountryId = DB::table('loc_countries')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'United Kingdom',
            'iso2' => 'GB',
            'iso3' => 'GBR',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $vat = TaxClass::query()->create([
            'country_id' => $otherCountryId,
            'code' => 'VAT',
            'name' => 'Value Added Tax',
            'status' => TaxClass::STATUS_ACTIVE,
        ]);
        $this->taxRate($vat, 'VAT 20%', '20.0000', TaxRate::STATUS_ACTIVE);

        $this->actingAs($merchant->user)
            ->get(route('merchant.tax-slabs.index'))
            ->assertOk()
            ->assertSee('Tax Slabs')
            ->assertSee('Reference only')
            ->assertSee('Country: '.$india->name)
            ->assertSee('State: '.$state->name)
            ->assertSee('GST / Goods and Services Tax')
            ->assertSee('GST 18%')
            ->assertSee('18.0000%')
            ->assertSee('CGST')
            ->assertSee('9.0000%')
            ->assertSee('Central')
            ->assertSee('SGST')
            ->assertSee('State')
            ->assertDontSee('GST 99%')
            ->assertDontSee('Hidden Test Tax')
            ->assertDontSee('VAT 20%')
            ->assertSee(route('merchant.tax-settings.edit'), false);
    }

    public function test_merchant_tax_slabs_menu_link_is_visible(): void
    {
        $merchant = $this->merchantFixture('Tax Slab Menu Merchant');
        $this->assignMerchantRole($merchant->user);
        $this->businessLocation($merchant);

        $this->actingAs($merchant->user)
            ->get(route('merchant.tax-slabs.index'))
            ->assertOk()
            ->assertSee('Tax Slabs')
            ->assertSee(route('merchant.tax-slabs.index'), false);
    }

    private function businessLocation(MerchantProfile $merchant): array
    {
        $india = LocCountry::query()->where('iso2', 'IN')->firstOrFail();
        $state = LocState::query()
            ->where('country_id', $india->id)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->firstOrFail();

        MerchantAddress::query()->create([
            'merchant_id' => $merchant->getKey(),
            'address_type' => 'business',
            'address_line_1' => 'Market Road',
            'country_id' => $india->id,
            'state_id' => $state->id,
            'status' => 'active',
        ]);

        return [$india, $state];
    }

    private function taxClass(LocCountry $country, string $code, string $name, string $status): TaxClass
    {
        return TaxClass::query()->create([
            'country_id' => $country->id,
            'code' => $code,
            'name' => $name,
            'status' => $status,
        ]);
    }

    private function taxRate(TaxClass $taxClass, string $name, string $rate, string $status): TaxRate
    {
        return TaxRate::query()->create([
            'tax_class_id' => $taxClass->id,
            'name' => $name,
            'total_rate' => $rate,
            'effective_from' => '2026-01-01',
            'priority' => 0,
            'status' => $status,
        ]);
    }

    private function taxComponent(TaxRate $taxRate, string $code, string $name, string $rate, string $jurisdiction, int $priority): TaxRateComponent
    {
        return TaxRateComponent::query()->create([
            'tax_rate_id' => $taxRate->id,
            'code' => $code,
            'name' => $name,
            'rate' => $rate,
            'jurisdiction_type' => $jurisdiction,
            'priority' => $priority,
        ]);
    }

    private function merchantFixture(string $businessName): MerchantProfile
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $businessName.' Owner',
            'email' => Str::slug($businessName).'-'.Str::random(6).'@example.test',
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        return MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => $businessName.' '.Str::random(4),
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
    }

    private function assignMerchantRole(User $user): void
    {
        $roleId = DB::table('auth_roles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Merchant',
            'slug' => 'merchant',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('auth_user_roles')->insert([
            'user_id' => $user->getKey(),
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
