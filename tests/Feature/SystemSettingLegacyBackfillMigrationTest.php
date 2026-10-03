<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\System\SystemSettingKeys;
use App\Services\System\SystemSettingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class SystemSettingLegacyBackfillMigrationTest extends TestCase
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

    public function test_legacy_static_and_dynamic_values_migrate_without_touching_legacy_or_scoped_settings(): void
    {
        $this->deleteCanonical([
            'regional.date_format',
            'notifications.email.admin_notification_email',
            'notifications.events.order.new.admin.email.enabled',
        ]);
        $this->legacy('regional', 'date_format', 'd/m/Y');
        $this->legacy('notifications.email', 'admin_notification_email', 'operations@example.test');
        $this->legacy('notifications', 'events.order.new.admin.email.enabled', '1', 'boolean');
        [$merchantId, $shopId] = $this->scopedSettingsFixture();
        $legacyBefore = DB::table('admin_settings')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $merchantBefore = DB::table('merchant_settings')->where('merchant_id', $merchantId)->first();
        $shopBefore = DB::table('shop_settings')->where('shop_id', $shopId)->first();

        $this->runBackfill();

        $this->assertSame('d/m/Y', $this->canonicalValue('regional.date_format'));
        $this->assertSame('operations@example.test', $this->canonicalValue('notifications.email.admin_notification_email'));
        $this->assertSame('1', $this->canonicalValue('notifications.events.order.new.admin.email.enabled'));
        $this->assertSame('boolean', DB::table('system_settings')->where('key', 'notifications.events.order.new.admin.email.enabled')->value('value_type'));
        $this->assertSame($legacyBefore, DB::table('admin_settings')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertEquals($merchantBefore, DB::table('merchant_settings')->where('merchant_id', $merchantId)->first());
        $this->assertEquals($shopBefore, DB::table('shop_settings')->where('shop_id', $shopId)->first());
    }

    public function test_existing_canonical_values_including_blank_false_and_zero_win_and_survive_rerun(): void
    {
        $currency = DB::table('system_settings')->where('key', SystemSettingKeys::DEFAULT_CURRENCY)->first();
        DB::table('system_settings')->where('id', $currency->id)->update(['value' => 'CAD']);
        $currency = DB::table('system_settings')->where('id', $currency->id)->first();
        $timezone = DB::table('system_settings')->where('key', SystemSettingKeys::DEFAULT_TIMEZONE)->first();
        DB::table('system_settings')->where('id', $timezone->id)->update(['value' => 'Pacific/Midway']);
        $timezone = DB::table('system_settings')->where('id', $timezone->id)->first();
        $this->replaceCanonical('regional.date_format', '', SystemSetting::TYPE_STRING);
        $this->replaceCanonical('notifications.email.enabled', '0', SystemSetting::TYPE_BOOLEAN);
        $this->replaceCanonical('notifications.email.smtp.port', '0', SystemSetting::TYPE_INTEGER);
        $this->legacy('currency', 'base_currency', 'USD');
        $this->legacy('regional', 'timezone', 'UTC');
        $this->legacy('regional', 'date_format', 'Y-m-d');
        $this->legacy('notifications.email', 'enabled', '1', 'boolean');
        $this->legacy('notifications.email', 'smtp.port', '587', 'integer');

        $this->runBackfill();
        $this->runBackfill();

        $this->assertSame('CAD', $this->canonicalValue(SystemSettingKeys::DEFAULT_CURRENCY));
        $this->assertSame($currency->uuid, DB::table('system_settings')->where('key', SystemSettingKeys::DEFAULT_CURRENCY)->value('uuid'));
        $this->assertSame('Pacific/Midway', $this->canonicalValue(SystemSettingKeys::DEFAULT_TIMEZONE));
        $this->assertSame($timezone->uuid, DB::table('system_settings')->where('key', SystemSettingKeys::DEFAULT_TIMEZONE)->value('uuid'));
        $this->assertSame('', $this->canonicalValue('regional.date_format'));
        $this->assertSame('0', $this->canonicalValue('notifications.email.enabled'));
        $this->assertSame('0', $this->canonicalValue('notifications.email.smtp.port'));
    }

    public function test_legacy_currency_and_timezone_backfill_existing_canonical_names_when_absent(): void
    {
        $this->deleteCanonical([SystemSettingKeys::DEFAULT_CURRENCY, SystemSettingKeys::DEFAULT_TIMEZONE]);
        $this->legacy('currency', 'base_currency', 'EUR');
        $this->legacy('regional', 'timezone', 'Europe/Paris');

        $this->runBackfill();

        $this->assertSame('EUR', $this->canonicalValue(SystemSettingKeys::DEFAULT_CURRENCY));
        $this->assertSame('Europe/Paris', $this->canonicalValue(SystemSettingKeys::DEFAULT_TIMEZONE));
        $this->assertDatabaseMissing('system_settings', ['key' => 'currency.base_currency']);
        $this->assertDatabaseMissing('system_settings', ['key' => 'regional.timezone']);
    }

    public function test_smtp_ciphertext_is_copied_byte_for_byte_without_decryption_or_exposure(): void
    {
        $this->deleteCanonical([SystemSettingKeys::EMAIL_SMTP_PASSWORD]);
        $ciphertext = base64_encode(random_bytes(96));
        $this->legacy('notifications.email', 'smtp.password', $ciphertext);

        $this->runBackfill();

        $stored = DB::table('system_settings')->where('key', SystemSettingKeys::EMAIL_SMTP_PASSWORD)->first();
        $this->assertTrue(hash_equals(hash('sha256', $ciphertext), hash('sha256', (string) $stored->value)));
        $this->assertSame(SystemSetting::TYPE_ENCRYPTED, $stored->value_type);
        $this->assertSame(1, (int) $stored->is_encrypted);
        $this->assertSame(0, (int) $stored->is_public);
        $this->assertNull(app(SystemSettingService::class)->get(SystemSettingKeys::EMAIL_SMTP_PASSWORD));
        $this->assertNull(app(SystemSettingService::class)->secret(SystemSettingKeys::EMAIL_SMTP_PASSWORD));
    }

    public function test_backfill_is_idempotent_and_does_not_duplicate_groups_or_change_existing_identity(): void
    {
        $this->deleteCanonical(['notifications.email.from_email']);
        $this->legacy('notifications.email', 'from_email', 'sender@example.test');

        $this->runBackfill();
        $setting = DB::table('system_settings')->where('key', 'notifications.email.from_email')->first();
        $groupCounts = DB::table('system_setting_groups')->whereIn('slug', ['localization', 'email', 'notifications'])->select('slug', DB::raw('count(*) as aggregate'))->groupBy('slug')->pluck('aggregate', 'slug')->all();
        $this->runBackfill();
        $rerun = DB::table('system_settings')->where('key', 'notifications.email.from_email')->first();

        $this->assertSame($setting->uuid, $rerun->uuid);
        $this->assertSame($setting->value, $rerun->value);
        $this->assertSame(1, DB::table('system_settings')->where('key', 'notifications.email.from_email')->count());
        $this->assertSame($groupCounts, DB::table('system_setting_groups')->whereIn('slug', ['localization', 'email', 'notifications'])->select('slug', DB::raw('count(*) as aggregate'))->groupBy('slug')->pluck('aggregate', 'slug')->all());
        $this->assertSame(['email' => 1, 'localization' => 1, 'notifications' => 1], array_map('intval', $groupCounts));
    }

    public function test_soft_deleted_canonical_key_remains_an_untouched_tombstone(): void
    {
        $this->deleteCanonical(['notifications.email.reply_to']);
        $uuid = (string) Str::uuid();
        $deletedAt = now()->subDay()->format('Y-m-d H:i:s');
        DB::table('system_settings')->insert([
            'uuid' => $uuid,
            'key' => 'notifications.email.reply_to',
            'label' => 'Deleted Reply To',
            'value' => 'deleted-canonical@example.test',
            'value_type' => SystemSetting::TYPE_STRING,
            'is_public' => false,
            'is_encrypted' => false,
            'status' => SystemSetting::STATUS_INACTIVE,
            'deleted_at' => $deletedAt,
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
        ]);
        $this->legacy('notifications.email', 'reply_to', 'legacy@example.test');

        $this->runBackfill();

        $row = DB::table('system_settings')->where('key', 'notifications.email.reply_to')->first();
        $this->assertSame(1, DB::table('system_settings')->where('key', 'notifications.email.reply_to')->count());
        $this->assertSame($uuid, $row->uuid);
        $this->assertSame('deleted-canonical@example.test', $row->value);
        $this->assertSame(SystemSetting::STATUS_INACTIVE, $row->status);
        $this->assertNotNull($row->deleted_at);
    }

    private function runBackfill(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_10_03_000001_backfill_admin_settings_into_system_settings.php');
        $migration->up();
    }

    private function legacy(string $group, string $key, ?string $value, string $type = 'string'): void
    {
        DB::table('admin_settings')->insert([
            'group' => $group,
            'setting_key' => $key,
            'setting_value' => $value,
            'setting_type' => $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array<int, string> $keys */
    private function deleteCanonical(array $keys): void
    {
        DB::table('system_settings')->whereIn('key', $keys)->delete();
    }

    private function canonicalValue(string $key): ?string
    {
        return DB::table('system_settings')->where('key', $key)->value('value');
    }

    private function replaceCanonical(string $key, ?string $value, string $type): void
    {
        $this->deleteCanonical([$key]);
        DB::table('system_settings')->insert([
            'uuid' => (string) Str::uuid(),
            'key' => $key,
            'label' => Str::headline($key),
            'value' => $value,
            'value_type' => $type,
            'is_public' => false,
            'is_encrypted' => false,
            'status' => SystemSetting::STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array{int, int} */
    private function scopedSettingsFixture(): array
    {
        $user = User::query()->create([
            'name' => 'Scoped Merchant',
            'email' => 'scoped-'.Str::random(8).'@example.test',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $merchant = MerchantProfile::query()->create([
            'user_id' => $user->getKey(),
            'business_name' => 'Scoped Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
        ]);
        $category = ProductCategory::query()->create([
            'name' => 'Scoped Category',
            'slug' => 'scoped-category-'.Str::random(5),
            'status' => 'active',
        ]);
        $shop = Shop::query()->create([
            'merchant_id' => $merchant->getKey(),
            'root_product_category_id' => $category->getKey(),
            'name' => 'Scoped Shop',
            'slug' => 'scoped-shop-'.Str::random(5),
            'address_line_1' => 'Road',
            'status' => 'active',
        ]);
        DB::table('merchant_settings')->insert([
            'merchant_id' => $merchant->getKey(),
            'group' => 'notifications',
            'setting_key' => 'events.order.confirmed.customer.email.enabled',
            'setting_value' => '0',
            'setting_type' => 'boolean',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('shop_settings')->insert([
            'shop_id' => $shop->getKey(),
            'group' => 'notifications',
            'setting_key' => 'events.order.confirmed.customer.email.enabled',
            'setting_value' => '1',
            'setting_type' => 'boolean',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$merchant->getKey(), $shop->getKey()];
    }
}
