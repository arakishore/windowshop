<?php

namespace Tests\Feature;

use App\Models\PostalCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminPostalCodeMasterTest extends TestCase
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

    public function test_import_command_streams_csv_and_skips_duplicate_location_records(): void
    {
        $path = base_path('storage/framework/testing/postal-codes-test.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, implode(PHP_EOL, [
            'circlename,regionname,divisionname,officename,pincode,officetype,delivery,district,statename,latitude,longitude',
            '"Maharashtra Circle","Mumbai Region","Mumbai Division","Fort H.O",400001,HO,Delivery,MUMBAI,MAHARASHTRA,18.9322450,72.8328750',
            '"Maharashtra Circle","Mumbai Region","Mumbai Division","Fort H.O",400001,HO,Delivery,MUMBAI,MAHARASHTRA,18.9322450,72.8328750',
            '"Maharashtra Circle","Mumbai Region","Mumbai Division","Ballard Estate S.O",400001,PO,"Non Delivery",MUMBAI,MAHARASHTRA,NA,NA',
        ]));

        $this->artisan('postal-codes:import', ['path' => $path, '--chunk' => 2])
            ->expectsOutput('Total rows processed: 3')
            ->expectsOutput('Inserted rows: 2')
            ->expectsOutput('Skipped/duplicate rows: 1')
            ->expectsOutput('Failed rows: 0')
            ->assertExitCode(0);

        $this->assertSame(2, PostalCode::query()->count());
        $this->assertDatabaseHas('postal_codes', [
            'postal_code' => '400001',
            'office_name' => 'Fort H.O',
            'shipping_enabled' => true,
            'status' => PostalCode::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('postal_codes', [
            'postal_code' => '400001',
            'office_name' => 'Ballard Estate S.O',
            'shipping_enabled' => false,
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->artisan('postal-codes:import', ['path' => $path, '--chunk' => 2])
            ->expectsOutput('Total rows processed: 3')
            ->expectsOutput('Inserted rows: 0')
            ->expectsOutput('Skipped/duplicate rows: 3')
            ->expectsOutput('Failed rows: 0')
            ->assertExitCode(0);

        $this->assertSame(2, PostalCode::query()->count());
    }

    public function test_admin_can_filter_create_update_delete_and_restore_postal_codes(): void
    {
        $admin = $this->createUserWithRole('super_admin');
        $existing = PostalCode::query()->create($this->postalCodeData([
            'postal_code' => '400001',
            'office_name' => 'Fort H.O',
            'district' => 'MUMBAI',
            'state' => 'MAHARASHTRA',
        ]));
        PostalCode::query()->create($this->postalCodeData([
            'source_key' => sha1('560001|bangalore g.p.o|ho|bengaluru|karnataka'),
            'postal_code' => '560001',
            'office_name' => 'Bangalore G.P.O',
            'office_type' => 'HO',
            'district' => 'BENGALURU',
            'state' => 'KARNATAKA',
            'shipping_enabled' => false,
            'delivery_status' => 'Non Delivery',
        ]));

        $this->actingAs($admin)
            ->get(route('admin.master.postal-codes.index', ['search' => 'Fort', 'state' => 'MAHARASHTRA', 'district' => 'MUMBAI', 'shipping_enabled' => '1']))
            ->assertOk()
            ->assertSee('Fort H.O')
            ->assertDontSee('Bangalore G.P.O');

        $this->actingAs($admin)
            ->post(route('admin.master.postal-codes.store'), [
                'postal_code' => '411001',
                'office_name' => 'Pune H.O',
                'office_type' => 'HO',
                'delivery_status' => 'Delivery',
                'shipping_enabled' => '1',
                'district' => 'PUNE',
                'state' => 'MAHARASHTRA',
                'circle_name' => 'Maharashtra Circle',
                'region_name' => 'Pune Region',
                'division_name' => 'Pune City East Division',
                'latitude' => '18.5204300',
                'longitude' => '73.8567400',
                'status' => PostalCode::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('admin.master.postal-codes.index'));

        $created = PostalCode::query()->where('postal_code', '411001')->firstOrFail();
        $this->assertTrue($created->shipping_enabled);

        $this->actingAs($admin)
            ->put(route('admin.master.postal-codes.update', $created), [
                'postal_code' => '411001',
                'office_name' => 'Pune City H.O',
                'office_type' => 'HO',
                'delivery_status' => 'Delivery',
                'shipping_enabled' => '0',
                'district' => 'PUNE',
                'state' => 'MAHARASHTRA',
                'circle_name' => 'Maharashtra Circle',
                'region_name' => 'Pune Region',
                'division_name' => 'Pune City East Division',
                'latitude' => null,
                'longitude' => null,
                'status' => PostalCode::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('admin.master.postal-codes.edit', $created));

        $created->refresh();
        $this->assertSame('Pune City H.O', $created->office_name);
        $this->assertFalse($created->shipping_enabled);
        $this->assertSame(PostalCode::STATUS_INACTIVE, $created->status);

        $this->actingAs($admin)
            ->delete(route('admin.master.postal-codes.destroy', $existing))
            ->assertRedirect(route('admin.master.postal-codes.index'));

        $this->assertSoftDeleted('postal_codes', ['id' => $existing->getKey()]);

        $this->actingAs($admin)
            ->post(route('admin.master.postal-codes.restore', $existing))
            ->assertRedirect(route('admin.master.postal-codes.index', ['status' => 'trash']));

        $this->assertFalse($existing->fresh()->trashed());
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function postalCodeData(array $overrides = []): array
    {
        $data = [
            'source_key' => sha1('400001|fort h.o|ho|mumbai|maharashtra'),
            'circle_name' => 'Maharashtra Circle',
            'region_name' => 'Mumbai Region',
            'division_name' => 'Mumbai Division',
            'office_name' => 'Fort H.O',
            'postal_code' => '400001',
            'office_type' => 'HO',
            'delivery_status' => 'Delivery',
            'shipping_enabled' => true,
            'district' => 'MUMBAI',
            'state' => 'MAHARASHTRA',
            'latitude' => '18.9322450',
            'longitude' => '72.8328750',
            'status' => PostalCode::STATUS_ACTIVE,
        ];

        return array_replace($data, $overrides);
    }

    private function createUserWithRole(string $roleSlug): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($roleSlug).' User',
            'email' => $roleSlug.'-'.Str::random(8).'@example.test',
            'mobile' => '9'.random_int(100000000, 999999999),
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $roleId = DB::table('auth_roles')->where('slug', $roleSlug)->value('id')
            ?? DB::table('auth_roles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => Str::headline($roleSlug),
                'slug' => $roleSlug,
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
}
