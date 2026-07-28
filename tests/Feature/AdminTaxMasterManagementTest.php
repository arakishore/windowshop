<?php

namespace Tests\Feature;

use App\Models\LocCountry;
use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Models\TaxRateComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminTaxMasterManagementTest extends TestCase
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

    public function test_guest_is_redirected_and_non_admin_is_forbidden(): void
    {
        $this->get(route('admin.master.tax-classes.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($this->createUser())
            ->get(route('admin.master.tax-classes.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_view_update_delete_and_restore_tax_class(): void
    {
        $admin = $this->createAdminUser();
        $country = $this->createCountry();

        $this->actingAs($admin)
            ->post(route('admin.master.tax-classes.store'), [
                'country_id' => $country->getKey(),
                'code' => 'standard',
                'name' => 'Standard Tax',
                'description' => 'Country standard rate.',
                'sort_order' => 25,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.index'))
            ->assertSessionHas('success', 'Tax class created successfully.');

        $taxClass = TaxClass::query()->where('code', 'STANDARD')->firstOrFail();
        $this->assertSame(25, $taxClass->sort_order);

        $this->actingAs($admin)
            ->get(route('admin.master.tax-classes.show', $taxClass))
            ->assertOk()
            ->assertSee('Standard Tax')
            ->assertSee('Add Rate');

        $this->actingAs($admin)
            ->get(route('admin.master.tax-classes.edit', $taxClass))
            ->assertOk()
            ->assertSee('Standard Tax')
            ->assertSee('India');

        $this->actingAs($admin)
            ->put(route('admin.master.tax-classes.update', $taxClass), [
                'country_id' => $country->getKey(),
                'code' => 'REDUCED',
                'name' => 'Reduced Tax',
                'description' => null,
                'sort_order' => 15,
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.master.tax-classes.edit', $taxClass));

        $this->assertDatabaseHas('tax_classes', [
            'id' => $taxClass->getKey(),
            'code' => 'REDUCED',
            'sort_order' => 15,
            'status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.master.tax-classes.destroy', $taxClass))
            ->assertRedirect(route('admin.master.tax-classes.index'));

        $trashed = TaxClass::withTrashed()->findOrFail($taxClass->getKey());
        $this->assertTrue($trashed->trashed());
        $this->assertSame($admin->getKey(), $trashed->deleted_by);

        $this->actingAs($admin)
            ->post(route('admin.master.tax-classes.restore', $trashed))
            ->assertRedirect(route('admin.master.tax-classes.index', ['status' => 'trash']));

        $restored = $taxClass->fresh();
        $this->assertFalse($restored->trashed());
        $this->assertSame('inactive', $restored->status);
        $this->assertNull($restored->deleted_by);
    }

    public function test_tax_class_duplicate_validation_includes_trashed_records(): void
    {
        $admin = $this->createAdminUser();
        $country = $this->createCountry();
        $taxClass = $this->createTaxClass($country);
        $taxClass->delete();

        $this->actingAs($admin)
            ->from(route('admin.master.tax-classes.create'))
            ->post(route('admin.master.tax-classes.store'), [
                'country_id' => $country->getKey(),
                'code' => 'STANDARD',
                'name' => 'Duplicate Standard',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.create'))
            ->assertSessionHasErrors(['code' => 'A matching tax class exists in Trash. Restore the existing tax class instead of creating a duplicate.']);
    }

    public function test_tax_class_with_active_rates_cannot_be_deleted(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass();
        $this->createTaxRate($taxClass);

        $this->actingAs($admin)
            ->from(route('admin.master.tax-classes.index'))
            ->delete(route('admin.master.tax-classes.destroy', $taxClass))
            ->assertRedirect(route('admin.master.tax-classes.index'))
            ->assertSessionHasErrors(['tax_class' => 'This tax class cannot be deleted because it still has active tax rates. Inactivate the rates first.']);

        $this->assertFalse($taxClass->fresh()->trashed());
    }

    public function test_tax_class_code_is_unique_per_country(): void
    {
        $admin = $this->createAdminUser();
        $india = $this->createCountry();
        $uk = $this->createCountry('GBR', 'GB');
        $this->createTaxClass($india, 'STANDARD');

        $this->actingAs($admin)
            ->from(route('admin.master.tax-classes.create'))
            ->post(route('admin.master.tax-classes.store'), [
                'country_id' => $india->getKey(),
                'code' => 'STANDARD',
                'name' => 'Duplicate India',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)
            ->post(route('admin.master.tax-classes.store'), [
                'country_id' => $uk->getKey(),
                'code' => 'STANDARD',
                'name' => 'UK Standard',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.index'));

        $this->assertDatabaseHas('tax_classes', [
            'country_id' => $uk->getKey(),
            'code' => 'STANDARD',
        ]);
    }

    public function test_same_tax_class_same_rate_overlapping_dates_are_rejected(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass();

        $this->actingAs($admin)
            ->post(route('admin.master.tax-classes.rates.store', $taxClass), [
                'name' => 'Standard 2026',
                'total_rate' => '18.0000',
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'priority' => 0,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.show', $taxClass));

        $rate = $taxClass->rates()->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.master.tax-classes.rates.create', $taxClass))
            ->post(route('admin.master.tax-classes.rates.store', $taxClass), [
                'name' => 'Overlapping 2026',
                'total_rate' => '18.0000',
                'effective_from' => '2026-06-01',
                'effective_to' => null,
                'priority' => 10,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.rates.create', $taxClass))
            ->assertSessionHasErrors('effective_from');
    }

    public function test_same_tax_class_different_rate_overlapping_dates_are_rejected(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass();

        $this->actingAs($admin)
            ->post(route('admin.master.tax-classes.rates.store', $taxClass), [
                'name' => 'GST 5%',
                'total_rate' => '5.0000',
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'priority' => 0,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.show', $taxClass));

        $this->actingAs($admin)
            ->from(route('admin.master.tax-classes.rates.create', $taxClass))
            ->post(route('admin.master.tax-classes.rates.store', $taxClass), [
                'name' => 'GST 6%',
                'total_rate' => '6.0000',
                'effective_from' => '2026-06-01',
                'effective_to' => null,
                'priority' => 10,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.rates.create', $taxClass))
            ->assertSessionHasErrors('effective_from');
    }

    public function test_adjacent_non_overlapping_tax_rate_dates_are_allowed(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass();

        $this->actingAs($admin)
            ->post(route('admin.master.tax-classes.rates.store', $taxClass), [
                'name' => 'GST 5% Old',
                'total_rate' => '5.0000',
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-03-31',
                'priority' => 0,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.show', $taxClass));

        $this->actingAs($admin)
            ->post(route('admin.master.tax-classes.rates.store', $taxClass), [
                'name' => 'GST 6% New',
                'total_rate' => '6.0000',
                'effective_from' => '2026-04-01',
                'effective_to' => null,
                'priority' => 0,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.show', $taxClass))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $taxClass->rates()->count());
    }

    public function test_tax_rates_for_different_tax_classes_may_overlap(): void
    {
        $admin = $this->createAdminUser();
        $firstTaxClass = $this->createTaxClass(code: 'GST_5');
        $secondTaxClass = $this->createTaxClass($firstTaxClass->country, 'GST_6');

        foreach ([$firstTaxClass, $secondTaxClass] as $taxClass) {
            $this->actingAs($admin)
                ->post(route('admin.master.tax-classes.rates.store', $taxClass), [
                    'name' => $taxClass->code,
                    'total_rate' => '5.0000',
                    'effective_from' => '2026-01-01',
                    'effective_to' => '2026-12-31',
                    'priority' => 0,
                    'status' => 'active',
                ])
                ->assertRedirect(route('admin.master.tax-classes.show', $taxClass))
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(1, $firstTaxClass->rates()->count());
        $this->assertSame(1, $secondTaxClass->rates()->count());
    }

    public function test_inactive_tax_rates_do_not_block_active_tax_rates(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass();

        $this->actingAs($admin)
            ->post(route('admin.master.tax-classes.rates.store', $taxClass), [
                'name' => 'Inactive GST 5%',
                'total_rate' => '5.0000',
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'priority' => 0,
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.master.tax-classes.show', $taxClass));

        $this->actingAs($admin)
            ->post(route('admin.master.tax-classes.rates.store', $taxClass), [
                'name' => 'Active GST 6%',
                'total_rate' => '6.0000',
                'effective_from' => '2026-06-01',
                'effective_to' => null,
                'priority' => 0,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.show', $taxClass))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $taxClass->rates()->count());
    }

    public function test_admin_can_update_delete_and_restore_tax_rates(): void
    {
        $admin = $this->createAdminUser();
        $taxClass = $this->createTaxClass();

        $this->actingAs($admin)
            ->post(route('admin.master.tax-classes.rates.store', $taxClass), [
                'name' => 'Standard 2026',
                'total_rate' => '18.0000',
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'priority' => 0,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.show', $taxClass));

        $rate = $taxClass->rates()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.master.tax-classes.rates.update', [$taxClass, $rate]), [
                'name' => 'Standard 2026 Updated',
                'total_rate' => '18.0000',
                'effective_from' => '2026-01-01',
                'effective_to' => '2026-12-31',
                'priority' => 5,
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.master.tax-classes.rates.edit', [$taxClass, $rate]));

        $this->assertDatabaseHas('tax_rates', [
            'id' => $rate->getKey(),
            'name' => 'Standard 2026 Updated',
            'status' => 'inactive',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.master.tax-classes.rates.destroy', [$taxClass, $rate]))
            ->assertRedirect(route('admin.master.tax-classes.show', $taxClass));

        $trashed = TaxRate::withTrashed()->findOrFail($rate->getKey());
        $this->assertTrue($trashed->trashed());

        $this->actingAs($admin)
            ->post(route('admin.master.tax-classes.rates.restore', [$taxClass, $trashed]))
            ->assertRedirect(route('admin.master.tax-classes.show', $taxClass));

        $this->assertFalse($rate->fresh()->trashed());
        $this->assertSame('inactive', $rate->fresh()->status);
    }

    public function test_admin_can_manage_components_and_total_must_match(): void
    {
        $admin = $this->createAdminUser();
        $rate = $this->createTaxRate(totalRate: '18.0000');

        $this->actingAs($admin)
            ->get(route('admin.master.tax-rates.components.create', $rate))
            ->assertOk()
            ->assertSee('Tax Component Information')
            ->assertSee('CGST');

        $this->actingAs($admin)
            ->from(route('admin.master.tax-rates.components.create', $rate))
            ->post(route('admin.master.tax-rates.components.store', $rate), [
                'code' => 'CGST',
                'name' => 'CGST',
                'rate' => '9.0000',
                'jurisdiction_type' => TaxRateComponent::JURISDICTION_CENTRAL,
                'priority' => 10,
            ])
            ->assertRedirect(route('admin.master.tax-rates.components.create', $rate))
            ->assertSessionHasErrors('rate');

        $this->actingAs($admin)
            ->post(route('admin.master.tax-rates.components.store', $rate), [
                'code' => 'IGST',
                'name' => 'IGST',
                'rate' => '18.0000',
                'jurisdiction_type' => TaxRateComponent::JURISDICTION_INTEGRATED,
                'priority' => 10,
            ])
            ->assertRedirect(route('admin.master.tax-classes.rates.edit', [$rate->taxClass, $rate]));

        $component = $rate->components()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.master.tax-rates.components.edit', [$rate, $component]))
            ->assertOk()
            ->assertSee('Tax Component Information')
            ->assertSee('IGST');

        $this->actingAs($admin)
            ->from(route('admin.master.tax-rates.components.edit', [$rate, $component]))
            ->put(route('admin.master.tax-rates.components.update', [$rate, $component]), [
                'code' => 'IGST',
                'name' => 'IGST Bad',
                'rate' => '17.0000',
                'jurisdiction_type' => TaxRateComponent::JURISDICTION_INTEGRATED,
                'priority' => 10,
            ])
            ->assertRedirect(route('admin.master.tax-rates.components.edit', [$rate, $component]))
            ->assertSessionHasErrors('rate');

        $rate->forceFill(['status' => TaxRate::STATUS_INACTIVE])->save();

        $this->actingAs($admin)
            ->delete(route('admin.master.tax-rates.components.destroy', [$rate, $component]))
            ->assertRedirect(route('admin.master.tax-classes.rates.edit', [$rate->taxClass, $rate]));

        $trashed = TaxRateComponent::withTrashed()->findOrFail($component->getKey());
        $this->assertTrue($trashed->trashed());

        $this->actingAs($admin)
            ->post(route('admin.master.tax-rates.components.restore', [$rate, $trashed]))
            ->assertRedirect(route('admin.master.tax-classes.rates.edit', [$rate->taxClass, $rate]));

        $this->assertFalse($component->fresh()->trashed());
    }

    public function test_component_duplicate_validation_includes_trashed_records(): void
    {
        $admin = $this->createAdminUser();
        $rate = $this->createTaxRate(totalRate: '18.0000');
        $component = $rate->components()->create([
            'code' => 'IGST',
            'name' => 'IGST',
            'rate' => '18.0000',
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_INTEGRATED,
        ]);
        $component->delete();

        $this->actingAs($admin)
            ->from(route('admin.master.tax-rates.components.create', $rate))
            ->post(route('admin.master.tax-rates.components.store', $rate), [
                'code' => 'IGST',
                'name' => 'Duplicate IGST',
                'rate' => '18.0000',
                'jurisdiction_type' => TaxRateComponent::JURISDICTION_INTEGRATED,
                'priority' => 0,
            ])
            ->assertRedirect(route('admin.master.tax-rates.components.create', $rate))
            ->assertSessionHasErrors(['code' => 'A matching tax component exists in Trash. Restore the existing component instead of creating a duplicate.']);
    }

    public function test_component_delete_is_blocked_when_active_rate_total_would_no_longer_match(): void
    {
        $admin = $this->createAdminUser();
        $rate = $this->createTaxRate(totalRate: '18.0000');
        $component = $rate->components()->create([
            'code' => 'IGST',
            'name' => 'IGST',
            'rate' => '18.0000',
            'jurisdiction_type' => TaxRateComponent::JURISDICTION_INTEGRATED,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.master.tax-classes.rates.edit', [$rate->taxClass, $rate]))
            ->delete(route('admin.master.tax-rates.components.destroy', [$rate, $component]))
            ->assertRedirect(route('admin.master.tax-classes.rates.edit', [$rate->taxClass, $rate]))
            ->assertSessionHasErrors(['rate' => 'This component cannot be deleted because the remaining active component total would no longer match the tax rate total.']);

        $this->assertFalse($component->fresh()->trashed());
    }

    public function test_inactive_rate_can_stage_multiple_components_and_activate_when_total_matches(): void
    {
        $admin = $this->createAdminUser();
        $rate = $this->createTaxRate(totalRate: '18.0000');
        $rate->forceFill(['status' => TaxRate::STATUS_INACTIVE])->save();

        foreach ([
            ['code' => 'CGST', 'name' => 'CGST', 'rate' => '9.0000', 'jurisdiction_type' => TaxRateComponent::JURISDICTION_CENTRAL],
            ['code' => 'SGST', 'name' => 'SGST', 'rate' => '9.0000', 'jurisdiction_type' => TaxRateComponent::JURISDICTION_STATE],
        ] as $component) {
            $this->actingAs($admin)
                ->post(route('admin.master.tax-rates.components.store', $rate), $component + ['priority' => 0])
                ->assertRedirect(route('admin.master.tax-classes.rates.edit', [$rate->taxClass, $rate]));
        }

        $this->actingAs($admin)
            ->put(route('admin.master.tax-classes.rates.update', [$rate->taxClass, $rate]), [
                'name' => $rate->name,
                'total_rate' => '18.0000',
                'effective_from' => '2026-01-01',
                'effective_to' => null,
                'priority' => 0,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.master.tax-classes.rates.edit', [$rate->taxClass, $rate]));

        $this->assertSame('active', $rate->fresh()->status);
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

    private function createTaxRate(?TaxClass $taxClass = null, string $totalRate = '18.0000'): TaxRate
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

    private function createAdminUser(): User
    {
        $user = $this->createUser('admin-tax-'.Str::random(6).'@example.test');
        $roleId = DB::table('auth_roles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Super Admin',
            'slug' => 'super_admin',
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

        return $user;
    }

    private function createUser(string $email = 'plain-tax-user@example.test'): User
    {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Tax Test User',
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }
}
