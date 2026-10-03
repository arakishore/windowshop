<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\SystemSetting;
use App\Models\SystemSettingGroup;
use App\Services\System\SystemSettingKeys;
use App\Services\System\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class SystemSettingServiceTest extends TestCase
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

    public function test_canonical_reads_has_and_typed_values_distinguish_blank_and_false_from_missing(): void
    {
        $settings = app(SystemSettingService::class);

        $settings->set('test.blank', '', SystemSetting::TYPE_STRING);
        $settings->set('test.false', false, SystemSetting::TYPE_BOOLEAN);
        $settings->set('test.integer', 12, SystemSetting::TYPE_INTEGER);
        $settings->set('test.array', ['one', 'two'], SystemSetting::TYPE_ARRAY);

        $this->assertTrue($settings->has('test.blank'));
        $this->assertTrue($settings->has('test.false'));
        $this->assertFalse($settings->has('test.missing'));
        $this->assertSame('', $settings->string('test.blank', 'fallback'));
        $this->assertFalse($settings->boolean('test.false', true));
        $this->assertSame(12, $settings->integer('test.integer'));
        $this->assertSame(['one', 'two'], $settings->array('test.array'));
    }

    public function test_write_update_upsert_and_subsequent_reads_are_fresh(): void
    {
        $settings = app(SystemSettingService::class);

        $this->assertSame('missing', $settings->get('test.fresh', 'missing'));
        $first = $settings->set('test.fresh', 'first');
        $this->assertSame('first', $settings->get('test.fresh'));

        $second = $settings->upsert('test.fresh', 'second');
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame('second', $settings->get('test.fresh'));

        SystemSetting::query()->whereKey($second->getKey())->update(['value' => 'third']);
        $this->assertSame('third', $settings->get('test.fresh'));
    }

    public function test_group_reads_are_typed_and_mask_encrypted_values(): void
    {
        $group = SystemSettingGroup::query()->create([
            'name' => 'Test',
            'slug' => 'test',
            'status' => 'active',
        ]);
        $settings = app(SystemSettingService::class);
        $settings->set('test.enabled', true, SystemSetting::TYPE_BOOLEAN, ['group_id' => $group->getKey()]);
        $settings->setEncrypted('test.secret', 'group-secret', ['group_id' => $group->getKey()]);

        $values = $settings->group('test');

        $this->assertTrue($values->get('test.enabled'));
        $this->assertSame('Configured', $values->get('test.secret'));
        $this->assertNotSame('group-secret', $values->get('test.secret'));
    }

    public function test_encrypted_values_round_trip_only_through_secret_api_and_blank_preserves_ciphertext(): void
    {
        $settings = app(SystemSettingService::class);
        $setting = $settings->setEncrypted('test.secret', 'plain-secret');
        $ciphertext = $setting->getRawOriginal('value');

        $this->assertNotSame('plain-secret', $ciphertext);
        $this->assertStringNotContainsString('plain-secret', (string) $ciphertext);
        $this->assertNull($settings->get('test.secret'));
        $this->assertSame('plain-secret', $settings->secret('test.secret'));
        $this->assertTrue($settings->secretConfigured('test.secret'));
        $this->assertSame('Configured', $setting->toArray()['value']);

        $settings->setEncrypted('test.secret', '');

        $this->assertSame($ciphertext, $setting->fresh()->getRawOriginal('value'));
        $this->assertSame('plain-secret', $settings->secret('test.secret'));
    }

    public function test_approved_legacy_mapping_is_centralized(): void
    {
        $this->assertSame(SystemSettingKeys::DEFAULT_CURRENCY, SystemSettingKeys::canonicalKeyForLegacy('currency', 'base_currency'));
        $this->assertSame(SystemSettingKeys::DEFAULT_TIMEZONE, SystemSettingKeys::canonicalKeyForLegacy('regional', 'timezone'));
        $this->assertSame('notifications.events.order.new.admin.email.enabled', SystemSettingKeys::canonicalKeyForLegacy('notifications', 'events.order.new.admin.email.enabled'));
        $this->assertNull(SystemSettingKeys::canonicalKeyForLegacy('merchant', 'shop_specific'));
    }

    public function test_regional_and_currency_reads_prefer_canonical_values(): void
    {
        AdminSetting::query()->create([
            'group' => 'currency',
            'setting_key' => 'base_currency',
            'setting_value' => 'USD',
            'setting_type' => AdminSetting::TYPE_STRING,
        ]);
        AdminSetting::query()->create([
            'group' => 'regional',
            'setting_key' => 'timezone',
            'setting_value' => 'UTC',
            'setting_type' => AdminSetting::TYPE_STRING,
        ]);

        $settings = app(SystemSettingService::class);
        $settings->setRegionalCurrency('currency', 'base_currency', 'EUR');
        $settings->setRegionalCurrency('regional', 'timezone', 'Asia/Kolkata');

        $this->assertSame('EUR', $settings->currencyConfig()['currency']);
        $this->assertSame('Asia/Kolkata', $settings->regionalConfig()['timezone']);
    }

    public function test_regional_and_currency_reads_fall_back_only_when_canonical_rows_are_absent(): void
    {
        SystemSetting::withTrashed()->whereIn('key', [
            SystemSettingKeys::DEFAULT_CURRENCY,
            SystemSettingKeys::DEFAULT_TIMEZONE,
        ])->forceDelete();
        AdminSetting::query()->create([
            'group' => 'currency',
            'setting_key' => 'base_currency',
            'setting_value' => 'USD',
            'setting_type' => AdminSetting::TYPE_STRING,
        ]);
        AdminSetting::query()->create([
            'group' => 'regional',
            'setting_key' => 'timezone',
            'setting_value' => 'UTC',
            'setting_type' => AdminSetting::TYPE_STRING,
        ]);

        $settings = app(SystemSettingService::class);

        $this->assertSame('USD', $settings->currencyConfig()['currency']);
        $this->assertSame('UTC', $settings->regionalConfig()['timezone']);
    }

    public function test_blank_and_zero_canonical_values_do_not_fall_back_to_legacy(): void
    {
        AdminSetting::query()->create([
            'group' => 'currency',
            'setting_key' => 'symbol',
            'setting_value' => '$',
            'setting_type' => AdminSetting::TYPE_STRING,
        ]);
        AdminSetting::query()->create([
            'group' => 'currency',
            'setting_key' => 'decimal_places',
            'setting_value' => '3',
            'setting_type' => AdminSetting::TYPE_INTEGER,
        ]);

        $settings = app(SystemSettingService::class);
        $settings->setRegionalCurrency('currency', 'symbol', '');
        $settings->setRegionalCurrency('currency', 'decimal_places', 0);

        $currency = $settings->currencyConfig();

        $this->assertSame('', $currency['symbol']);
        $this->assertSame(0, $currency['decimal_places']);
    }
}
