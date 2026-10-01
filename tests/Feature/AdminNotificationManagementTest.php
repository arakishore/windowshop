<?php

namespace Tests\Feature;

use App\Mail\TransactionalNotificationMail;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\DeliveryResult;
use App\Notifications\NotificationMessage;
use App\Services\Notification\EmailConfigurationService;
use App\Services\Notification\NotificationPreferenceResolver;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AdminNotificationManagementTest extends TestCase
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

    public function test_admin_can_list_filter_and_non_admin_cannot_access_templates(): void
    {
        $admin = $this->admin();
        $email = $this->template('customer.registered', 'email');
        $sms = $this->template('customer.registered', 'sms');
        $whatsapp = $this->template('customer.registered', 'whatsapp');

        $this->actingAs($admin)->get(route('admin.notification-templates.index'))
            ->assertOk()->assertSee('Customer registered')->assertSee('Mandatory')
            ->assertSee(route('admin.notification-templates.edit', $email))
            ->assertDontSee(route('admin.notification-templates.edit', $sms))
            ->assertDontSee('name="channel" class="form-select"', false);
        $this->actingAs($admin)->get(route('admin.notification-templates.index', ['channel' => 'sms']))
            ->assertOk()->assertSee('SMS templates are available')->assertSee(route('admin.notification-templates.edit', $sms))->assertDontSee(route('admin.notification-templates.edit', $email));
        $this->actingAs($admin)->get(route('admin.notification-templates.index', ['channel' => 'whatsapp']))
            ->assertOk()->assertSee('WhatsApp templates are available')->assertSee(route('admin.notification-templates.edit', $whatsapp))->assertDontSee(route('admin.notification-templates.edit', $email));
        $this->actingAs($admin)->get(route('admin.notification-templates.index', ['recipient' => 'admin', 'channel' => 'email', 'rule' => 'optional']))
            ->assertOk()->assertSee('New order for admin')->assertDontSee('Customer registered');
        $sms->update(['is_active' => false]);
        $this->actingAs($admin)->get(route('admin.notification-templates.index', ['channel' => 'sms', 'search' => 'Customer registered', 'recipient' => 'customer', 'rule' => 'configurable', 'status' => 'inactive']))
            ->assertOk()->assertSee(route('admin.notification-templates.edit', $sms))
            ->assertSee(route('admin.notification-templates.index', ['search' => 'Customer registered', 'recipient' => 'customer', 'rule' => 'configurable', 'status' => 'inactive', 'channel' => 'whatsapp']));

        $customer = User::query()->create(['name' => 'Customer', 'email' => 'customer-access@example.test', 'password' => Hash::make('password'), 'status' => 'active']);
        $this->actingAs($customer)->get(route('admin.notification-templates.index'))->assertForbidden();
    }

    public function test_template_edit_validates_placeholders_mandatory_lock_and_preserves_admin_edits_on_seed(): void
    {
        $admin = $this->admin();
        $mandatory = $this->template('customer.registered', 'email');
        $this->actingAs($admin)->get(route('admin.notification-templates.edit', $mandatory))
            ->assertOk()->assertSee('{{ customer_name }}')->assertSee('{{ marketplace_name }}')
            ->assertSee('Subject')->assertSee('CC')->assertSee('BCC')->assertSee('Preview Email');
        foreach (['sms', 'whatsapp'] as $channel) {
            $channelTemplate = $this->template('customer.registered', $channel);
            $this->actingAs($admin)->get(route('admin.notification-templates.edit', $channelTemplate))
                ->assertOk()->assertSee(ucfirst($channel))->assertSee('Body')
                ->assertDontSee('Subject')->assertDontSee('CC')->assertDontSee('BCC')->assertDontSee('Preview Email');
        }
        $this->actingAs($admin)->put(route('admin.notification-templates.update', $mandatory), [
            'subject' => 'Welcome {{ customer_name }}', 'body' => 'Hello {{ unknown_value }}', 'is_active' => 0,
        ])->assertSessionHasErrors('body');
        $this->actingAs($admin)->put(route('admin.notification-templates.update', $mandatory), [
            'subject' => 'Custom welcome {{ customer_name }}', 'body' => 'Hello {{ customer_name }} from {{ marketplace_name }}', 'is_active' => 0,
        ])->assertSessionHas('success');
        $this->assertTrue($mandatory->fresh()->is_active);
        $this->assertSame('Custom welcome {{ customer_name }}', $mandatory->fresh()->subject);
        $this->seed(NotificationTemplateSeeder::class);
        $this->assertSame('Custom welcome {{ customer_name }}', $mandatory->fresh()->subject);

        $optional = $this->template('order.confirmed.customer', 'email');
        $this->actingAs($admin)->put(route('admin.notification-templates.update', $optional), [
            'subject' => $optional->subject, 'body' => $optional->body, 'is_active' => 0,
        ])->assertSessionHas('success');
        $this->assertFalse($optional->fresh()->is_active);
    }

    public function test_cc_bcc_are_validated_normalized_and_applied_without_replacing_to(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $template = $this->template('customer.registered', 'email');
        $this->actingAs($admin)->put(route('admin.notification-templates.update', $template), [
            'subject' => $template->subject, 'body' => $template->body,
            'email_cc' => 'Copy@Example.test, copy@example.test, customer@example.test',
            'email_bcc' => "Hidden@Example.test\ncopy@example.test",
            'is_active' => 1,
        ])->assertSessionHas('success');
        $template->refresh();
        $this->assertSame(['copy@example.test', 'customer@example.test'], data_get($template->metadata, 'email.cc'));
        $this->assertSame(['hidden@example.test', 'copy@example.test'], data_get($template->metadata, 'email.bcc'));

        $this->configureEmail();
        $result = app(EmailChannel::class)->send(new NotificationMessage('customer.registered', 'customer', 'email', 'customer@example.test', context: ['customer_name' => 'Customer']));
        $this->assertSame(DeliveryResult::SENT, $result->status);
        Mail::assertSent(TransactionalNotificationMail::class, function (TransactionalNotificationMail $mail): bool {
            $envelope = $mail->envelope();

            return $mail->hasTo('customer@example.test')
                && collect($envelope->cc)->pluck('address')->all() === ['copy@example.test']
                && collect($envelope->bcc)->pluck('address')->all() === ['hidden@example.test'];
        });

        $this->actingAs($admin)->put(route('admin.notification-templates.update', $template), [
            'subject' => $template->subject, 'body' => $template->body, 'email_cc' => 'not-an-email',
        ])->assertSessionHasErrors('email_cc');
    }

    public function test_admin_event_keeps_template_cc_bcc_and_deduplicates_primary_recipient(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $template = $this->template('merchant.registered.admin', 'email');
        $this->actingAs($admin)->put(route('admin.notification-templates.update', $template), [
            'subject' => $template->subject,
            'body' => $template->body,
            'email_cc' => 'review@example.test, admin@example.test',
            'email_bcc' => 'audit@example.test, review@example.test, admin@example.test',
            'is_active' => 1,
        ])->assertSessionHas('success');
        $this->configureEmail();

        $result = app(EmailChannel::class)->send(new NotificationMessage(
            'merchant.registered.admin',
            'admin',
            'email',
            'admin@example.test',
            context: [
                'business_name' => 'Business',
                'owner_name' => 'Owner',
                'email' => 'owner@example.test',
                'mobile' => '9876543210',
                'registration_datetime' => '01 Oct 2026, 10:30 AM',
                'verification_status' => 'pending',
                'registration_source' => 'storefront',
                'review_merchant_url' => 'https://example.test/admin/merchants/example',
            ],
        ));

        $this->assertSame(DeliveryResult::SENT, $result->status);
        Mail::assertSent(TransactionalNotificationMail::class, function (TransactionalNotificationMail $mail): bool {
            $envelope = $mail->envelope();

            return $mail->hasTo('admin@example.test')
                && collect($envelope->cc)->pluck('address')->all() === ['review@example.test']
                && collect($envelope->bcc)->pluck('address')->all() === ['audit@example.test'];
        });
    }

    public function test_preview_uses_safe_real_layout_without_sending_or_logging(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $template = $this->template('customer.registered', 'email');
        $this->actingAs($admin)->put(route('admin.notification-templates.preview', $template), [
            'subject' => 'Welcome {{ customer_name }}',
            'body' => '@php throw new Exception("unsafe") @endphp Hello {{ customer_name }}',
            'is_active' => 1,
        ])->assertOk()->assertSee('Preview only')->assertSee('Sample Customer')->assertSee('@php throw new Exception', false);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('notification_delivery_logs', 0);
    }

    public function test_preview_renders_fresh_default_content(): void
    {
        Mail::fake();
        $template = $this->template('customer.registered', 'email');

        $this->actingAs($this->admin())->put(route('admin.notification-templates.preview', $template), [
            'subject' => $template->subject,
            'body' => $template->body,
            'is_active' => 1,
        ])->assertOk()
            ->assertSee('Welcome to WindowShop')
            ->assertSee('Your account has been created successfully.');

        Mail::assertNothingSent();
    }

    public function test_rules_matrix_reflects_catalogue_and_global_admin_preference_can_change(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.notification-rules.index'))
            ->assertOk()->assertSee('Mandatory / locked')->assertSee('Merchant configurable')->assertSee('New order for admin')
            ->assertSee('Default for Order processing')
            ->assertSee('notification-rules-table')->assertSee("jQuery('#notification-rules-table').DataTable", false)
            ->assertSee('datatables.min.js')->assertSee('responsive.min.js');
        $this->actingAs($admin)->put(route('admin.notification-rules.global.update'), ['event_key' => 'order.new.admin', 'enabled' => 1])->assertSessionHas('success');
        $this->assertTrue(app(NotificationPreferenceResolver::class)->enabled(new NotificationMessage('order.new.admin', 'admin', 'email')));

        $resolver = app(NotificationPreferenceResolver::class);
        $this->assertFalse($resolver->defaultEnabled('order.processing.customer', 'email'));
        $this->actingAs($admin)->put(route('admin.notification-rules.global.update'), ['event_key' => 'order.processing.customer', 'enabled' => 1])->assertSessionHas('success');
        $this->assertTrue($resolver->defaultEnabled('order.processing.customer', 'email'));
        $this->actingAs($admin)->put(route('admin.notification-rules.global.update'), ['event_key' => 'order.processing.customer', 'enabled' => 0])->assertSessionHas('success');
        $this->assertFalse($resolver->defaultEnabled('order.processing.customer', 'email'));

        $this->actingAs($admin)->put(route('admin.notification-rules.global.update'), ['event_key' => 'order.cancelled.customer', 'enabled' => 0])->assertNotFound();
        $this->actingAs($admin)->put(route('admin.notification-rules.global.update'), ['event_key' => 'unknown.event', 'enabled' => 1])->assertNotFound();

        $nonAdmin = User::query()->create(['name' => 'Merchant', 'email' => 'merchant-rule@example.test', 'password' => Hash::make('password'), 'status' => 'active']);
        $this->actingAs($nonAdmin)->put(route('admin.notification-rules.global.update'), ['event_key' => 'order.processing.customer', 'enabled' => 1])->assertForbidden();
    }

    private function template(string $event, string $channel): NotificationTemplate
    {
        return NotificationTemplate::query()->where('event_key', $event)->where('channel', $channel)->firstOrFail();
    }

    private function configureEmail(): void
    {
        app(EmailConfigurationService::class)->save([
            'enabled' => true, 'transport' => 'smtp', 'smtp.host' => 'smtp.example.test', 'smtp.port' => 587,
            'smtp.encryption' => 'tls', 'smtp.username' => '', 'smtp.password' => '',
            'from_name' => 'WindowShop', 'from_email' => 'sender@example.test', 'reply_to' => '',
        ]);
    }

    private function admin(): User
    {
        $user = User::query()->create(['name' => 'Notification Admin', 'email' => 'notification-admin-'.Str::random(5).'@example.test', 'password' => Hash::make('password'), 'status' => 'active']);
        $roleId = DB::table('auth_roles')->insertGetId(['name' => 'Super Admin', 'slug' => 'super_admin', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('auth_user_roles')->insert(['user_id' => $user->getKey(), 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }
}
