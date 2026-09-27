<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\SystemSettingGroup;
use App\Services\Admin\AdminSettingsService;
use App\Services\Notification\EmailConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class StorefrontContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_contact_page_uses_configured_contact_and_social_links_without_the_old_form(): void
    {
        $this->systemSetting('contact.support_email', 'help@windowshop.test');
        $this->systemSetting('contact.phone', '+91 98765 43210');
        $this->systemSetting('contact.whatsapp', '+91 99887 76655');
        $this->systemSetting('contact.office_address', "WindowShop Office\nNashik, Maharashtra");
        $this->systemSetting('contact.support_hours', 'Monday - Saturday, 9:00 AM - 7:00 PM');
        $this->systemSetting('contact.whatsapp_hours', 'Available during business hours');

        $adminSettings = app(AdminSettingsService::class);
        foreach (['facebook', 'instagram', 'twitter', 'youtube', 'linkedin'] as $network) {
            $adminSettings->set(EmailConfigurationService::GROUP, 'footer.social.'.$network, 'https://example.test/'.$network);
        }

        $this->get(route('storefront.contact'))
            ->assertOk()
            ->assertSee('How can we help?')
            ->assertSee('href="https://wa.me/919988776655"', false)
            ->assertSee('href="tel:+919876543210"', false)
            ->assertSee('href="mailto:help@windowshop.test"', false)
            ->assertSee('WindowShop Office')
            ->assertSee('Monday - Saturday, 9:00 AM - 7:00 PM')
            ->assertSee('https://example.test/facebook', false)
            ->assertSee('https://example.test/linkedin', false)
            ->assertDontSee('id="contact-name"', false)
            ->assertDontSee('id="contact-message"', false)
            ->assertDontSee('Send Message');
    }

    public function test_missing_optional_contact_settings_fail_gracefully(): void
    {
        $this->get(route('storefront.contact'))
            ->assertOk()
            ->assertSee('WhatsApp support is not currently available.')
            ->assertSee('Telephone support is not currently available.')
            ->assertSee('Email support is not currently available.')
            ->assertSee('Social links are not currently available.')
            ->assertSee('Office address is not currently listed.')
            ->assertSee('Support hours are not currently listed.')
            ->assertDontSee('href="https://wa.me/', false);
    }

    private function systemSetting(string $key, string $value): void
    {
        $group = SystemSettingGroup::query()->firstOrCreate(
            ['slug' => 'general'],
            ['name' => 'General', 'sort_order' => 10, 'status' => 'active'],
        );

        SystemSetting::query()->create([
            'group_id' => $group->getKey(),
            'key' => $key,
            'label' => $key,
            'value' => $value,
            'value_type' => SystemSetting::TYPE_STRING,
            'is_public' => true,
            'is_encrypted' => false,
            'sort_order' => 10,
            'status' => SystemSetting::STATUS_ACTIVE,
        ]);
    }
}
