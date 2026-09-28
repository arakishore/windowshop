<?php

namespace Tests\Feature;

use App\Mail\TransactionalNotificationMail;
use App\Models\AdminSetting;
use App\Models\NotificationTemplate;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\DeliveryResult;
use App\Notifications\NotificationMessage;
use App\Services\Notification\EmailConfigurationService;
use App\Services\Notification\NotificationEventCatalogue;
use App\Services\Notification\OrderEmailPresenter;
use App\Services\Notification\NotificationTemplateRenderer;
use App\Services\Notification\NotificationTemplateService;
use App\Services\System\SystemSettingService;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
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
        $setting = AdminSetting::query()->where('group', EmailConfigurationService::GROUP)->where('setting_key', 'smtp.password')->firstOrFail();
        $first = $setting->setting_value;

        $this->assertNotSame('first-secret', $first);
        $this->assertSame('first-secret', Crypt::decryptString($first));
        $this->assertArrayNotHasKey('smtp.password', $service->values());
        $this->assertSame('Configured', $setting->toArray()['setting_value']);
        $service->save(['enabled' => false, 'smtp.password' => '']);
        $this->assertSame($first, $setting->fresh()->setting_value);
        $service->save(['enabled' => false, 'smtp.password' => 'second-secret']);
        $this->assertSame('second-secret', Crypt::decryptString($setting->fresh()->setting_value));
    }

    public function test_admin_page_validates_saves_and_sends_test_email(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.email-settings.edit'))->assertOk()->assertDontSee('first-secret');
        $this->actingAs($admin)->put(route('admin.email-settings.update'), ['enabled' => 1, 'smtp' => ['host' => '', 'port' => 70000, 'encryption' => 'bad']])->assertSessionHasErrors(['smtp.host', 'smtp.port', 'smtp.encryption']);
        $this->actingAs($admin)->put(route('admin.email-settings.update'), [
            'enabled' => 1,
            'smtp' => ['host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls', 'username' => 'mailer', 'password' => 'admin-secret'],
            'from_name' => 'WindowShop Mail', 'from_email' => 'sender@example.test', 'reply_to' => 'reply@example.test',
        ])->assertSessionHas('success');
        $this->assertSame('smtp.example.test', app(EmailConfigurationService::class)->values()['host']);
        $this->assertSame('admin-secret', app(EmailConfigurationService::class)->decryptedPassword());
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
