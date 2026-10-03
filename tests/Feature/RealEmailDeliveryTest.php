<?php

namespace Tests\Feature;

use App\Mail\TransactionalNotificationMail;
use App\Models\NotificationTemplate;
use App\Models\Shop;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\DeliveryResult;
use App\Notifications\NotificationMessage;
use App\Services\Notification\EmailConfigurationService;
use App\Services\Notification\NotificationEventCatalogue;
use App\Services\Notification\NotificationTemplateRenderer;
use App\Services\Notification\NotificationTemplateService;
use App\Services\Notification\OrderEmailPresenter;
use App\Services\System\SystemSettingService;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use PDO;
use Tests\TestCase;

class RealEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', static fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTemplateSeeder::class);
    }

    public function test_disabled_and_incomplete_email_configuration_do_not_send(): void
    {
        Mail::fake();
        $message = $this->message('customer.registered');

        $this->assertSame(DeliveryResult::SKIPPED, app(EmailChannel::class)->send($message)->status);
        app(EmailConfigurationService::class)->save(['enabled' => true]);
        $this->assertSame(DeliveryResult::NOT_CONFIGURED, app(EmailChannel::class)->send($message)->status);
        Mail::assertNothingSent();
    }

    public function test_configured_channel_renders_safe_shop_email_with_sender_and_reply_to(): void
    {
        Mail::fake();
        $shop = $this->shop();
        $this->configure();
        $message = new NotificationMessage(
            'order.confirmed.customer', 'customer', 'email', 'customer@example.test',
            shopId: $shop->getKey(), context: ['customer_name' => '<script>alert(1)</script>', 'order_number' => 'ORD-1', 'shop_name' => $shop->name],
        );

        $result = app(EmailChannel::class)->send($message);

        $this->assertSame(DeliveryResult::SENT, $result->status);
        $mailer = config('mail.mailers.'.EmailConfigurationService::MAILER);
        $this->assertSame('smtp.example.test', $mailer['host']);
        $this->assertSame(587, $mailer['port']);
        $this->assertSame('mailer', $mailer['username']);
        $this->assertSame('smtp', $mailer['scheme']);
        $this->assertTrue(hash_equals('secret-value', $mailer['password'] ?? ''));
        Mail::assertSent(TransactionalNotificationMail::class, function (TransactionalNotificationMail $mail): bool {
            $html = $mail->render();

            return $mail->hasTo('customer@example.test')
                && $mail->brandName === 'Email Shop'
                && str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;')
                && ! str_contains($html, '<script>alert(1)</script>')
                && $mail->envelope()->from->address === 'sender@example.test'
                && $mail->envelope()->replyTo[0]->address === 'reply@example.test';
        });
    }

    public function test_marketplace_branding_and_mandatory_inactive_template_remain_deliverable(): void
    {
        Mail::fake();
        $this->configure();
        NotificationTemplate::query()->where('event_key', 'customer.registered')->where('channel', 'email')->update(['is_active' => false]);

        $result = app(EmailChannel::class)->send($this->message('customer.registered'));

        $this->assertSame(DeliveryResult::SENT, $result->status);
        Mail::assertSent(TransactionalNotificationMail::class, fn (TransactionalNotificationMail $mail): bool => $mail->brandName === 'WindowShop');
    }

    public function test_admin_merchant_registration_email_renders_business_details_and_review_action(): void
    {
        Mail::fake();
        $this->configure();
        $message = new NotificationMessage(
            'merchant.registered.admin',
            'admin',
            'email',
            'admin@example.test',
            context: [
                'business_name' => 'Public Seller Store',
                'owner_name' => 'Public Seller',
                'email' => 'seller@example.test',
                'mobile' => '9876543210',
                'registration_datetime' => '01 Oct 2026, 10:30 AM',
                'verification_status' => 'pending',
                'registration_source' => 'storefront',
                'review_merchant_url' => 'https://windowshop.test/admin/merchants/example',
            ],
            metadata: ['action' => ['label' => 'Review Merchant', 'url' => 'https://windowshop.test/admin/merchants/example']],
        );

        $this->assertSame(DeliveryResult::SENT, app(EmailChannel::class)->send($message)->status);

        Mail::assertSent(TransactionalNotificationMail::class, function (TransactionalNotificationMail $mail): bool {
            $html = $mail->render();

            return $mail->hasTo('admin@example.test')
                && $mail->notificationSubject === 'New Merchant Registration - Public Seller Store'
                && str_contains($html, 'Public Seller Store')
                && str_contains($html, 'Public Seller')
                && str_contains($html, 'seller@example.test')
                && str_contains($html, '9876543210')
                && str_contains($html, '01 Oct 2026, 10:30 AM')
                && str_contains($html, 'pending')
                && str_contains($html, 'storefront')
                && str_contains($html, 'Review Merchant')
                && str_contains($html, 'https://windowshop.test/admin/merchants/example');
        });
    }

    public function test_transport_exception_returns_failed_without_exposing_credentials(): void
    {
        $configuration = Mockery::mock(EmailConfigurationService::class);
        $configuration->shouldReceive('enabled')->once()->andReturnTrue();
        $configuration->shouldReceive('configured')->once()->andReturnTrue();
        $configuration->shouldReceive('values')->once()->andReturn([
            'from_email' => 'sender@example.test', 'from_name' => 'Sender', 'reply_to' => '',
        ]);
        $configuration->shouldReceive('emailLogoUrl')->once()->andReturn('https://example.test/logo.png');
        $configuration->shouldReceive('presentation')->once()->andReturn([]);
        $configuration->shouldReceive('send')->once()->andThrow(new \RuntimeException('password=secret-value connection refused'));
        $configuration->shouldReceive('sanitizedError')->once()->andReturn('password=[redacted] connection refused');
        $channel = new EmailChannel(
            $configuration,
            app(NotificationTemplateService::class),
            app(NotificationTemplateRenderer::class),
            app(NotificationEventCatalogue::class),
            app(SystemSettingService::class),
            app(OrderEmailPresenter::class),
        );

        $result = $channel->send($this->message('customer.registered'));

        $this->assertSame(DeliveryResult::FAILED, $result->status);
        $this->assertSame('password=[redacted] connection refused', $result->error);
        $this->assertStringNotContainsString('secret-value', (string) $result->error);
    }

    public function test_optional_inactive_template_is_skipped(): void
    {
        Mail::fake();
        $this->configure();
        NotificationTemplate::query()->where('event_key', 'order.confirmed.customer')->where('channel', 'email')->update(['is_active' => false]);

        $result = app(EmailChannel::class)->send($this->message('order.confirmed.customer'));

        $this->assertSame(DeliveryResult::SKIPPED, $result->status);
        Mail::assertNothingSent();
    }

    public function test_password_is_encrypted_hidden_preserved_and_replaced(): void
    {
        $service = app(EmailConfigurationService::class);
        $service->save(['enabled' => false, 'smtp.password' => 'first-secret']);
        $setting = SystemSetting::query()->where('key', 'notifications.email.smtp.password')->firstOrFail();
        $first = $setting->getRawOriginal('value');
        $payload = app(SystemSettingService::class)->secret('notifications.email.smtp.password');

        $this->assertFalse(hash_equals('first-secret', (string) $first));
        $this->assertFalse(str_contains((string) $first, 'canonical-smtp:v1:'));
        $this->assertTrue(str_starts_with((string) $payload, 'canonical-smtp:v1:'));
        $this->assertTrue(hash_equals('first-secret', substr((string) $payload, strlen('canonical-smtp:v1:'))));
        $this->assertTrue($setting->is_encrypted);
        $this->assertFalse($setting->is_public);
        $this->assertSame(SystemSetting::TYPE_ENCRYPTED, $setting->value_type);
        $this->assertNull(app(SystemSettingService::class)->get('notifications.email.smtp.password'));
        $this->assertArrayNotHasKey('smtp.password', $service->values());
        $this->assertSame('Configured', $setting->toArray()['value']);
        $service->save(['enabled' => false, 'smtp.password' => '']);
        $this->assertSame($first, $setting->fresh()->getRawOriginal('value'));
        $service->save(['enabled' => false, 'smtp.password' => 'second-secret']);
        $replacement = $setting->fresh()->getRawOriginal('value');
        $this->assertNotSame($first, $replacement);
        $this->assertTrue(hash_equals('second-secret', $service->decryptedPassword() ?? ''));
    }

    public function test_admin_page_validates_saves_and_sends_test_email(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $legacyBefore = DB::table('admin_settings')->where('group', EmailConfigurationService::GROUP)->count();

        $this->actingAs($admin)->get(route('admin.email-settings.edit'))
            ->assertOk()
            ->assertDontSee('first-secret')
            ->assertSee('Admin Notification Email')
            ->assertSee('Primary email address for WindowShop administrative and operational notifications.');
        $this->assertSame($legacyBefore, DB::table('admin_settings')->where('group', EmailConfigurationService::GROUP)->count());
        $this->actingAs($admin)->put(route('admin.email-settings.update'), ['enabled' => 1, 'smtp' => ['host' => '', 'port' => 70000, 'encryption' => 'bad']])->assertSessionHasErrors(['smtp.host', 'smtp.port', 'smtp.encryption']);
        $this->actingAs($admin)->put(route('admin.email-settings.update'), [
            'enabled' => 1,
            'smtp' => ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'mailer', 'password' => 'admin-secret'],
            'from_name' => 'WindowShop Mail', 'from_email' => 'sender@example.test', 'reply_to' => 'reply@example.test',
            'admin_notification_email' => 'notifications@example.test',
        ])->assertSessionHas('success');
        $this->assertDatabaseMissing('admin_settings', ['group' => EmailConfigurationService::GROUP]);
        $this->assertSame('smtp.example.test', app(EmailConfigurationService::class)->values()['host']);
        $this->assertSame('notifications@example.test', app(EmailConfigurationService::class)->values()['admin_notification_email']);
        $this->assertTrue(hash_equals('admin-secret', app(EmailConfigurationService::class)->decryptedPassword() ?? ''));
        $ciphertext = SystemSetting::query()->where('key', 'notifications.email.smtp.password')->value('value');
        $page = $this->actingAs($admin)->get(route('admin.email-settings.edit'));
        $page->assertOk()->assertSee('value="notifications@example.test"', false);
        $this->assertFalse(str_contains($page->getContent(), 'admin-secret'));
        $this->actingAs($admin)->put(route('admin.email-settings.update'), [
            'enabled' => 1,
            'smtp' => ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'mailer', 'password' => ''],
            'from_name' => 'WindowShop Mail', 'from_email' => 'sender@example.test', 'reply_to' => 'reply@example.test',
            'admin_notification_email' => 'notifications@example.test',
        ])->assertSessionHas('success');
        $this->assertSame($ciphertext, SystemSetting::query()->where('key', 'notifications.email.smtp.password')->value('value'));
        $this->actingAs($admin)->put(route('admin.email-settings.update'), [
            'enabled' => 0,
            'smtp' => ['encryption' => 'tls'],
            'admin_notification_email' => 'not-an-email',
        ])->assertSessionHasErrors('admin_notification_email');
        $this->assertSame('notifications@example.test', app(EmailConfigurationService::class)->values()['admin_notification_email']);
        $this->actingAs($admin)->post(route('admin.email-settings.test'), ['test_recipient' => 'invalid'])->assertSessionHasErrors('test_recipient');
        $this->actingAs($admin)->post(route('admin.email-settings.test'), ['test_recipient' => 'test@example.test'])->assertSessionHas('success');
        Mail::assertSent(TransactionalNotificationMail::class, fn (TransactionalNotificationMail $mail): bool => $mail->hasTo('test@example.test'));
    }

    private function configure(): void
    {
        app(EmailConfigurationService::class)->save([
            'enabled' => true, 'transport' => 'smtp', 'smtp.host' => 'smtp.example.test', 'smtp.port' => 587,
            'smtp.encryption' => 'tls', 'smtp.username' => 'mailer', 'smtp.password' => 'secret-value',
            'from_name' => 'WindowShop Mail', 'from_email' => 'sender@example.test', 'reply_to' => 'reply@example.test',
        ]);
    }

    private function message(string $key): NotificationMessage
    {
        return new NotificationMessage($key, 'customer', 'email', 'customer@example.test', context: ['customer_name' => 'Customer', 'marketplace_name' => 'WindowShop']);
    }

    private function shop(): Shop
    {
        $userId = DB::table('users')->insertGetId(['uuid' => (string) Str::uuid(), 'name' => 'Merchant', 'email' => 'merchant@example.test', 'password' => Hash::make('password'), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $merchantId = DB::table('merchant_profiles')->insertGetId(['uuid' => (string) Str::uuid(), 'user_id' => $userId, 'business_name' => 'Email Shop', 'verification_status' => 'approved', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $categoryId = DB::table('product_categories')->insertGetId(['uuid' => (string) Str::uuid(), 'name' => 'Root', 'slug' => 'email-root', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return Shop::query()->create(['merchant_id' => $merchantId, 'root_product_category_id' => $categoryId, 'name' => 'Email Shop', 'slug' => 'email-shop', 'address_line_1' => 'Road', 'status' => 'active']);
    }

    private function admin(): User
    {
        $user = User::query()->create(['name' => 'Email Admin', 'email' => 'email-admin@example.test', 'password' => Hash::make('password'), 'status' => 'active']);
        $roleId = DB::table('auth_roles')->insertGetId(['name' => 'Super Admin', 'slug' => 'super_admin', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('auth_user_roles')->insert(['user_id' => $user->getKey(), 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }
}
