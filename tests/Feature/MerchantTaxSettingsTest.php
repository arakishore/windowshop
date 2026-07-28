<?php

namespace Tests\Feature;

use App\Models\LocCountry;
use App\Models\LocState;
use App\Models\MerchantAddress;
use App\Models\MerchantProfile;
use App\Models\MerchantTaxSetting;
use App\Models\TaxClass;
use App\Models\TaxRateComponent;
use App\Models\User;
use Database\Seeders\MasterData\LocationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class MerchantTaxSettingsTest extends TestCase
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

    public function test_merchant_can_create_and_update_tax_settings(): void
    {
        $merchant = $this->merchantFixture('Tax Settings Merchant', gstNumber: '27ABCDE1234F1Z5');
        $this->assignMerchantRole($merchant->user);
        [$india, $state, $taxClass] = $this->activeIndiaTaxClass();
        $this->businessAddress($merchant, $india, $state);

        $this->actingAs($merchant->user)
            ->get(route('merchant.tax-settings.edit'))
            ->assertOk()
            ->assertSee('Tax Settings')
            ->assertSee('Enable Tax Calculation')
            ->assertSee('Default Tax Class')
            ->assertSee('Prices Include Tax')
            ->assertSee('GSTIN: 27ABCDE1234F1Z5')
            ->assertSee('Country: India')
            ->assertSee('Tax system: GST')
            ->assertSee('GST_5 / GST 5% - 5.0000% (CGST 2.5000% + SGST 2.5000%)')
            ->assertSee('Edit Merchant Details')
            ->assertSee(route('merchant.details.edit'), false)
            ->assertSee('Tax calculation is enabled.')
            ->assertSee('Make sure products are assigned a tax class before selling.')
            ->assertDontSee('GST Registered')
            ->assertDontSee('Tax System</label>', false)
            ->assertDontSee('name="country_id"', false)
            ->assertDontSee('name="state_id"', false)
            ->assertDontSee('name="tax_registration_number"', false)
            ->assertDontSee('name="status"', false);

        $this->actingAs($merchant->user)
            ->put(route('merchant.tax-settings.update'), [
                'tax_enabled' => '1',
                'default_tax_class_id' => $taxClass->id,
                'prices_include_tax' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Tax settings updated successfully.');

        $setting = MerchantTaxSetting::query()->where('merchant_id', $merchant->getKey())->firstOrFail();

        $this->assertTrue($setting->tax_enabled);
        $this->assertTrue($setting->prices_include_tax);
        $this->assertTrue($setting->merchant->is($merchant));
        $this->assertTrue($setting->defaultTaxClass->is($taxClass));
        $this->assertFalse(Schema::hasColumn('merchant_tax_settings', 'tax_system'));
        $this->assertFalse(Schema::hasColumn('merchant_tax_settings', 'is_tax_registered'));
        $this->assertFalse(Schema::hasColumn('merchant_tax_settings', 'tax_registration_number'));
        $this->assertFalse(Schema::hasColumn('merchant_tax_settings', 'country_id'));
        $this->assertFalse(Schema::hasColumn('merchant_tax_settings', 'state_id'));
        $this->assertFalse(Schema::hasColumn('merchant_tax_settings', 'status'));

        $this->actingAs($merchant->user)
            ->put(route('merchant.tax-settings.update'), [
                'tax_enabled' => '0',
                'default_tax_class_id' => null,
                'prices_include_tax' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Tax settings updated successfully.');

        $setting->refresh();
        $this->assertFalse($setting->tax_enabled);
        $this->assertNull($setting->default_tax_class_id);
        $this->assertFalse($setting->prices_include_tax);
        $this->assertSame(1, MerchantTaxSetting::query()->where('merchant_id', $merchant->getKey())->count());
    }

    public function test_default_tax_class_must_match_merchant_business_country(): void
    {
        $merchant = $this->merchantFixture('Country Scoped Tax Merchant');
        $this->assignMerchantRole($merchant->user);
        [$india, $state, $indiaTaxClass] = $this->activeIndiaTaxClass();
        $this->businessAddress($merchant, $india, $state);
        $otherCountryId = DB::table('loc_countries')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Another Country',
            'iso2' => 'AC',
            'iso3' => 'ACT',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherTaxClass = TaxClass::query()->create([
            'country_id' => $otherCountryId,
            'code' => 'VAT',
            'name' => 'Value Added Tax',
            'status' => TaxClass::STATUS_ACTIVE,
        ]);

        $this->actingAs($merchant->user)
            ->from(route('merchant.tax-settings.edit'))
            ->put(route('merchant.tax-settings.update'), [
                'tax_enabled' => '1',
                'default_tax_class_id' => $otherTaxClass->id,
                'prices_include_tax' => '1',
            ])
            ->assertRedirect(route('merchant.tax-settings.edit'))
            ->assertSessionHasErrors(['default_tax_class_id']);

        $this->actingAs($merchant->user)
            ->put(route('merchant.tax-settings.update'), [
                'tax_enabled' => '1',
                'default_tax_class_id' => $indiaTaxClass->id,
                'prices_include_tax' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_default_tax_class_is_required_when_tax_is_enabled(): void
    {
        $merchant = $this->merchantFixture('Required Default Tax Merchant');
        $this->assignMerchantRole($merchant->user);
        [$india, $state] = $this->activeIndiaTaxClass();
        $this->businessAddress($merchant, $india, $state);

        $this->actingAs($merchant->user)
            ->from(route('merchant.tax-settings.edit'))
            ->put(route('merchant.tax-settings.update'), [
                'tax_enabled' => '1',
                'default_tax_class_id' => null,
                'prices_include_tax' => '1',
            ])
            ->assertRedirect(route('merchant.tax-settings.edit'))
            ->assertSessionHasErrors(['default_tax_class_id']);

        $this->actingAs($merchant->user)
            ->put(route('merchant.tax-settings.update'), [
                'tax_enabled' => '0',
                'default_tax_class_id' => null,
                'prices_include_tax' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_default_tax_class_must_be_active_and_not_deleted(): void
    {
        $merchant = $this->merchantFixture('Inactive Class Merchant');
        $this->assignMerchantRole($merchant->user);
        [$india, $state, $taxClass] = $this->activeIndiaTaxClass();
        $this->businessAddress($merchant, $india, $state);
        $taxClass->forceFill(['status' => TaxClass::STATUS_INACTIVE])->save();

        $this->actingAs($merchant->user)
            ->from(route('merchant.tax-settings.edit'))
            ->put(route('merchant.tax-settings.update'), [
                'tax_enabled' => '1',
                'default_tax_class_id' => $taxClass->id,
                'prices_include_tax' => '1',
            ])
            ->assertRedirect(route('merchant.tax-settings.edit'))
            ->assertSessionHasErrors(['default_tax_class_id']);

        $taxClass->forceFill(['status' => TaxClass::STATUS_ACTIVE])->save();
        $taxClass->delete();

        $this->actingAs($merchant->user)
            ->from(route('merchant.tax-settings.edit'))
            ->put(route('merchant.tax-settings.update'), [
                'tax_enabled' => '1',
                'default_tax_class_id' => $taxClass->id,
                'prices_include_tax' => '1',
            ])
            ->assertRedirect(route('merchant.tax-settings.edit'))
            ->assertSessionHasErrors(['default_tax_class_id']);
    }

    public function test_database_enforces_one_tax_setting_per_merchant(): void
    {
        $merchant = $this->merchantFixture('Unique Tax Merchant');

        MerchantTaxSetting::query()->create($this->settingsPayload($merchant));

        $this->expectException(QueryException::class);

        MerchantTaxSetting::query()->create($this->settingsPayload($merchant));
    }

    public function test_soft_deleted_tax_settings_are_restored_instead_of_duplicated(): void
    {
        $merchant = $this->merchantFixture('Restore Tax Merchant');
        $this->assignMerchantRole($merchant->user);
        [$india, $state, $taxClass] = $this->activeIndiaTaxClass();
        $this->businessAddress($merchant, $india, $state);

        $setting = MerchantTaxSetting::query()->create($this->settingsPayload($merchant));
        $settingId = $setting->getKey();
        $deletedBy = $this->createUserId('deleter');
        $setting->forceFill(['deleted_by' => $deletedBy])->save();
        $setting->delete();

        $this->actingAs($merchant->user)
            ->put(route('merchant.tax-settings.update'), [
                'tax_enabled' => '1',
                'default_tax_class_id' => $taxClass->id,
                'prices_include_tax' => '0',
            ])
            ->assertRedirect();

        $restored = MerchantTaxSetting::query()->findOrFail($settingId);

        $this->assertFalse($restored->trashed());
        $this->assertNull($restored->deleted_by);
        $this->assertTrue($restored->tax_enabled);
        $this->assertFalse($restored->prices_include_tax);
        $this->assertSame($taxClass->id, $restored->default_tax_class_id);
        $this->assertSame(1, MerchantTaxSetting::withTrashed()->where('merchant_id', $merchant->getKey())->count());
    }

    public function test_merchant_cannot_delete_another_merchants_tax_settings(): void
    {
        $merchant = $this->merchantFixture('Owner Tax Merchant');
        $otherMerchant = $this->merchantFixture('Other Tax Merchant');
        $this->assignMerchantRole($merchant->user);

        $otherSetting = MerchantTaxSetting::query()->create($this->settingsPayload($otherMerchant));

        $this->actingAs($merchant->user)
            ->delete(route('merchant.tax-settings.destroy', $otherSetting))
            ->assertNotFound();

        $this->assertFalse($otherSetting->fresh()->trashed());
    }

    public function test_tax_settings_do_not_create_or_change_unrelated_module_data(): void
    {
        $merchant = $this->merchantFixture('Scoped Tax Merchant');
        $this->assignMerchantRole($merchant->user);
        [$india, $state, $taxClass] = $this->activeIndiaTaxClass();
        $this->businessAddress($merchant, $india, $state);
        $tables = [
            'merchant_settings',
            'merchant_profiles',
            'merchant_addresses',
            'products',
            'product_categories',
            'orders',
            'order_items',
            'order_totals',
            'order_refunds',
            'order_exchanges',
        ];
        $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();

        $this->actingAs($merchant->user)
            ->put(route('merchant.tax-settings.update'), [
                'tax_enabled' => '1',
                'default_tax_class_id' => $taxClass->id,
                'prices_include_tax' => '1',
            ])
            ->assertRedirect();

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), "{$table} count changed.");
        }
    }

    private function activeIndiaTaxClass(): array
    {
        $india = LocCountry::query()->where('iso2', 'IN')->firstOrFail();
        $state = LocState::query()
            ->where('country_id', $india->id)
            ->where('status', true)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $taxClass = TaxClass::query()->create([
            'country_id' => $india->id,
            'code' => 'GST_5',
            'name' => 'GST 5%',
            'status' => TaxClass::STATUS_ACTIVE,
        ]);
        $taxRate = $taxClass->rates()->create([
            'name' => 'GST 5%',
            'total_rate' => '5.0000',
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);

        $taxRate->components()->create([
            'code' => 'CGST',
            'name' => 'CGST',
            'rate' => '2.5000',
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_CENTRAL,
            'priority' => 1,
        ]);
        $taxRate->components()->create([
            'code' => 'SGST',
            'name' => 'SGST',
            'rate' => '2.5000',
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_STATE,
            'priority' => 2,
        ]);

        return [$india, $state, $taxClass];
    }

    private function businessAddress(MerchantProfile $merchant, LocCountry $country, LocState $state): MerchantAddress
    {
        return MerchantAddress::query()->create([
            'merchant_id' => $merchant->getKey(),
            'address_type' => 'business',
            'address_line_1' => 'Market Road',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'status' => 'active',
        ]);
    }

    private function settingsPayload(MerchantProfile $merchant, ?TaxClass $taxClass = null): array
    {
        return [
            'merchant_id' => $merchant->getKey(),
            'tax_enabled' => true,
            'default_tax_class_id' => $taxClass?->id,
            'prices_include_tax' => true,
        ];
    }

    private function merchantFixture(string $businessName, ?string $gstNumber = null): MerchantProfile
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
            'gst_number' => $gstNumber,
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

    private function createUserId(string $name): int
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'email' => $name.'-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ])->getKey();
    }
}
