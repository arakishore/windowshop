<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Database\Seeders\MasterData\PublicContactSettingSeeder;
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
        $this->seed(PublicContactSettingSeeder::class);
        $groupId = SystemSetting::query()->where('key', 'contact.support_email')->value('group_id');
        SystemSetting::query()->create([
            'group_id' => $groupId,
            'key' => 'marketplace_name',
            'label' => 'Marketplace Name',
            'value' => 'LocalSquare',
            'value_type' => SystemSetting::TYPE_STRING,
            'is_public' => true,
            'is_encrypted' => false,
            'sort_order' => 10,
            'status' => SystemSetting::STATUS_ACTIVE,
        ]);
        $this->setting('contact.support_email', 'help@windowshop.test');
        $this->setting('contact.phone', '+91 98765 43210');
        $this->setting('contact.whatsapp', '+91 99887 76655');
        $this->setting('contact.office_address', "WindowShop Office\nNashik, Maharashtra");
        $this->setting('contact.support_hours', 'Monday - Saturday, 9:00 AM - 7:00 PM');
        $this->setting('contact.whatsapp_hours', 'Available during business hours');
        foreach (['facebook', 'instagram', 'twitter', 'youtube', 'linkedin'] as $network) {
            $this->setting('social.'.$network, 'https://example.test/'.$network);
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
            ->assertSee('&copy; '.now()->year.' LocalSquare. All Rights Reserved.', false)
            ->assertDontSee('Amerce')
            ->assertDontSee('600 N Michigan Ave')
            ->assertDontSee('hi.amere@gmail.com')
            ->assertDontSee('id="contact-name"', false)
            ->assertDontSee('id="contact-message"', false)
            ->assertDontSee('Send Message');
    }

    public function test_missing_optional_contact_settings_fail_gracefully(): void
    {
        $this->seed(PublicContactSettingSeeder::class);

        $this->get(route('storefront.contact'))
            ->assertOk()
            ->assertSee('WhatsApp support is not currently available.')
            ->assertSee('Telephone support is not currently available.')
            ->assertSee('Email support is not currently available.')
            ->assertSee('Social links are not currently available.')
            ->assertSee('Office address is not currently listed.')
            ->assertSee('Support hours are not currently listed.')
            ->assertDontSee('href="https://wa.me/', false)
            ->assertDontSee('600 N Michigan Ave')
            ->assertDontSee('(+01) 1234 8888')
            ->assertDontSee('hi.amere@gmail.com')
            ->assertDontSee('Amerce');
    }

    public function test_contact_page_mobile_menu_shows_configured_support_email(): void
    {
        $this->seed(PublicContactSettingSeeder::class);
        $this->setting('contact.support_email', 'help@windowshop.test');

        $this->get(route('storefront.contact'))
            ->assertOk()
            ->assertSee('mailto:help@windowshop.test', false)
            ->assertSee('Need Help?');
    }

    public function test_contact_page_mobile_menu_hides_missing_support_email_without_exception(): void
    {
        $this->seed(PublicContactSettingSeeder::class);
        SystemSetting::query()->where('key', 'contact.support_email')->firstOrFail()->update(['value' => '']);

        $this->get(route('storefront.contact'))
            ->assertOk()
            ->assertSee('Email support is not currently available.')
            ->assertDontSee('mailto:help@windowshop.test', false);
    }

    public function test_storefront_home_mobile_menu_loads_without_undefined_array_key(): void
    {
        $this->seed(PublicContactSettingSeeder::class);

        $this->get(route('storefront.home'))->assertOk();
    }

    public function test_public_contact_seeder_preserves_existing_values(): void
    {
        $this->seed(PublicContactSettingSeeder::class);
        $setting = SystemSetting::query()->where('key', 'contact.phone')->firstOrFail();
        $setting->update(['value' => '+44 20 1234 5678']);

        $this->seed(PublicContactSettingSeeder::class);

        $this->assertSame('+44 20 1234 5678', $setting->fresh()->value);
        $this->assertSame('marketplace', $setting->fresh()->group->slug);
        $this->assertTrue($setting->fresh()->is_public);
        $this->assertSame(11, SystemSetting::query()->where(function ($query): void {
            $query->where('key', 'like', 'contact.%')->orWhere('key', 'like', 'social.%');
        })->count());
    }

    private function setting(string $key, string $value): void
    {
        SystemSetting::query()->where('key', $key)->firstOrFail()->update(['value' => $value]);
    }
}
