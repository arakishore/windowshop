<?php

use App\Models\SystemSetting;
use App\Services\System\SystemSettingKeys;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $groupIds = [
            'localization' => $this->ensureGroup('Localization', 'localization', 20),
            'email' => $this->ensureGroup('Email', 'email', 30),
            'notifications' => $this->ensureGroup('Notifications', 'notifications', 35),
        ];

        foreach ($this->definitions() as $legacyKey => $definition) {
            if (str_starts_with($legacyKey, 'notifications.email.')) {
                $group = 'notifications.email';
                $key = Str::after($legacyKey, 'notifications.email.');
            } else {
                [$group, $key] = explode('.', $legacyKey, 2);
            }
            $canonicalKey = SystemSettingKeys::canonicalKeyForLegacy($group, $key);

            if ($canonicalKey === null || $this->canonicalKeyReserved($canonicalKey)) {
                continue;
            }

            $legacy = DB::table('admin_settings')
                ->where('group', $group)
                ->where('setting_key', $key)
                ->first();

            if ($legacy !== null) {
                $this->insertCanonical(
                    $canonicalKey,
                    $legacy->setting_value,
                    $definition,
                    $groupIds[$definition['group']],
                    $this->canonicalType((string) $legacy->setting_type, $definition['type']),
                );

                continue;
            }

            if ($definition['has_default']) {
                $this->insertCanonical(
                    $canonicalKey,
                    $definition['default'],
                    $definition,
                    $groupIds[$definition['group']],
                    $definition['type'],
                );
            }
        }

        DB::table('admin_settings')
            ->where('group', 'notifications')
            ->where('setting_key', 'like', 'events.%')
            ->orderBy('id')
            ->get()
            ->each(function ($legacy) use ($groupIds): void {
                $canonicalKey = SystemSettingKeys::canonicalKeyForLegacy('notifications', (string) $legacy->setting_key);

                if ($canonicalKey === null || $this->canonicalKeyReserved($canonicalKey)) {
                    return;
                }

                $this->insertCanonical($canonicalKey, $legacy->setting_value, [
                    'group' => 'notifications',
                    'label' => Str::of((string) $legacy->setting_key)->replace(['events.', '.', '_'], ['', ' ', ' '])->headline()->toString(),
                    'type' => SystemSetting::TYPE_BOOLEAN,
                    'description' => 'Global notification event channel preference migrated from legacy admin settings.',
                    'sort_order' => 100,
                    'has_default' => false,
                    'default' => null,
                ], $groupIds['notifications'], $this->canonicalType((string) $legacy->setting_type, SystemSetting::TYPE_BOOLEAN));
            });
    }

    public function down(): void
    {
        // Non-destructive by design: migrated rows cannot be distinguished
        // safely from rows created or edited after deployment. Legacy source
        // rows remain intact and rollback must not delete canonical data.
    }

    private function ensureGroup(string $name, string $slug, int $sortOrder): int
    {
        $existing = DB::table('system_setting_groups')->where('slug', $slug)->first();

        if ($existing !== null) {
            return (int) $existing->id;
        }

        return (int) DB::table('system_setting_groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $sortOrder,
            'status' => SystemSetting::STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function canonicalKeyReserved(string $key): bool
    {
        // system_settings.key is globally unique even for soft-deleted rows.
        // A tombstone therefore reserves the identity and is left untouched;
        // silently restoring it or inserting a duplicate would be unsafe.
        return DB::table('system_settings')->where('key', $key)->exists();
    }

    /** @param array<string, mixed> $definition */
    private function insertCanonical(string $key, ?string $value, array $definition, int $groupId, string $type): void
    {
        $encrypted = $key === SystemSettingKeys::EMAIL_SMTP_PASSWORD;

        // Values are inserted directly so legacy SMTP ciphertext is copied
        // byte-for-byte without calling a decrypting or encrypting service.
        DB::table('system_settings')->insert([
            'uuid' => (string) Str::uuid(),
            'group_id' => $groupId,
            'key' => $key,
            'label' => $definition['label'],
            'value' => $value,
            'value_type' => $encrypted ? SystemSetting::TYPE_ENCRYPTED : $type,
            'is_public' => false,
            'is_encrypted' => $encrypted,
            'description' => $definition['description'],
            'sort_order' => $definition['sort_order'],
            'status' => SystemSetting::STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function canonicalType(string $legacyType, string $fallback): string
    {
        return match ($legacyType) {
            'boolean' => SystemSetting::TYPE_BOOLEAN,
            'integer' => SystemSetting::TYPE_INTEGER,
            'json' => SystemSetting::TYPE_JSON,
            'string' => SystemSetting::TYPE_STRING,
            default => $fallback,
        };
    }

    /** @return array<string, array{group: string, label: string, type: string, description: string, sort_order: int, has_default: bool, default: ?string}> */
    private function definitions(): array
    {
        $definitions = [
            'regional.timezone' => ['localization', 'Default Timezone', 'string', 'Default application timezone.', 70, true, 'Asia/Kolkata'],
            'regional.date_format' => ['localization', 'Date Format', 'string', 'Global date display format.', 80, true, 'd-m-Y'],
            'regional.time_format' => ['localization', 'Time Format', 'string', 'Global time display format.', 90, true, 'h:i A'],
            'regional.financial_year_start_month' => ['localization', 'Financial Year Start Month', 'integer', 'Month number in which the financial year begins.', 100, true, '4'],
            'currency.base_currency' => ['localization', 'Default Currency', 'string', 'Default ISO 4217 currency code.', 60, true, 'INR'],
            'currency.symbol' => ['localization', 'Currency Symbol', 'string', 'Default currency display symbol.', 110, true, '₹'],
            'currency.decimal_places' => ['localization', 'Currency Decimal Places', 'integer', 'Number of decimal places used for currency display.', 120, true, '2'],
            'currency.thousands_separator' => ['localization', 'Currency Thousands Separator', 'string', 'Thousands separator used for currency display.', 130, true, ','],
            'currency.decimal_separator' => ['localization', 'Currency Decimal Separator', 'string', 'Decimal separator used for currency display.', 140, true, '.'],
            'currency.symbol_position' => ['localization', 'Currency Symbol Position', 'string', 'Whether the currency symbol is displayed before or after the amount.', 150, true, 'before'],
            'notifications.email.enabled' => ['email', 'Email Enabled', 'boolean', 'Whether global transactional email delivery is enabled.', 10, false, null],
            'notifications.email.transport' => ['email', 'Email Transport', 'string', 'Global email transport.', 20, false, null],
            'notifications.email.smtp.host' => ['email', 'SMTP Host', 'string', 'SMTP server hostname.', 30, false, null],
            'notifications.email.smtp.port' => ['email', 'SMTP Port', 'integer', 'SMTP server port.', 40, false, null],
            'notifications.email.smtp.encryption' => ['email', 'SMTP Encryption', 'string', 'SMTP connection encryption mode.', 50, false, null],
            'notifications.email.smtp.username' => ['email', 'SMTP Username', 'string', 'SMTP authentication username.', 60, false, null],
            'notifications.email.smtp.password' => ['email', 'SMTP Password', 'encrypted', 'Protected SMTP authentication password.', 70, false, null],
            'notifications.email.from_name' => ['email', 'From Name', 'string', 'Transactional email sender name.', 80, false, null],
            'notifications.email.from_email' => ['email', 'From Email', 'string', 'Transactional email sender address.', 90, false, null],
            'notifications.email.reply_to' => ['email', 'Reply-To Email', 'string', 'Transactional email reply-to address.', 100, false, null],
            'notifications.email.admin_notification_email' => ['email', 'Admin Notification Email', 'string', 'Primary address for administrative and operational notifications.', 110, false, null],
            'notifications.email.branding.logo_path' => ['email', 'Email Logo Path', 'string', 'Dedicated transactional email logo path.', 120, false, null],
            'notifications.email.branding.show_name' => ['email', 'Show Marketplace Name', 'boolean', 'Show the marketplace name in transactional email branding.', 130, false, null],
            'notifications.email.footer.show' => ['email', 'Show Email Footer', 'boolean', 'Show the common transactional email footer.', 140, false, null],
            'notifications.email.footer.benefit_1' => ['email', 'Email Footer Benefit 1', 'string', 'First optional email footer benefit.', 150, false, null],
            'notifications.email.footer.benefit_2' => ['email', 'Email Footer Benefit 2', 'string', 'Second optional email footer benefit.', 160, false, null],
            'notifications.email.footer.benefit_3' => ['email', 'Email Footer Benefit 3', 'string', 'Third optional email footer benefit.', 170, false, null],
            'notifications.email.footer.social.facebook' => ['email', 'Email Footer Facebook URL', 'string', 'Facebook link shown in transactional email.', 180, false, null],
            'notifications.email.footer.social.instagram' => ['email', 'Email Footer Instagram URL', 'string', 'Instagram link shown in transactional email.', 190, false, null],
            'notifications.email.footer.social.youtube' => ['email', 'Email Footer YouTube URL', 'string', 'YouTube link shown in transactional email.', 200, false, null],
            'notifications.email.footer.social.twitter' => ['email', 'Email Footer X / Twitter URL', 'string', 'X or Twitter link shown in transactional email.', 210, false, null],
            'notifications.email.footer.social.linkedin' => ['email', 'Email Footer LinkedIn URL', 'string', 'LinkedIn link shown in transactional email.', 220, false, null],
            'notifications.email.footer.apps.google_play' => ['email', 'Email Footer Google Play URL', 'string', 'Google Play link shown in transactional email.', 230, false, null],
            'notifications.email.footer.apps.app_store' => ['email', 'Email Footer App Store URL', 'string', 'App Store link shown in transactional email.', 240, false, null],
            'notifications.email.footer.show_powered_by' => ['email', 'Show Powered By', 'boolean', 'Show powered-by text in transactional email.', 250, false, null],
            'notifications.email.footer.powered_by_text' => ['email', 'Powered By Text', 'string', 'Powered-by text shown in transactional email.', 260, false, null],
        ];

        return collect($definitions)->map(fn (array $definition): array => [
            'group' => $definition[0],
            'label' => $definition[1],
            'type' => $definition[2],
            'description' => $definition[3],
            'sort_order' => $definition[4],
            'has_default' => $definition[5],
            'default' => $definition[6],
        ])->all();
    }
};
