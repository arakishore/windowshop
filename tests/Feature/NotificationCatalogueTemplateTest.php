<?php

namespace Tests\Feature;

use App\Models\NotificationDeliveryLog;
use App\Models\NotificationTemplate;
use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationMessage;
use App\Notifications\RecipientContactSource;
use App\Services\Notification\NotificationEventCatalogue;
use App\Services\Notification\NotificationPreferenceResolver;
use App\Services\Notification\NotificationTemplateRenderer;
use App\Services\Notification\NotificationTemplateService;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class NotificationCatalogueTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['notification_templates', 'shop_settings', 'merchant_settings', 'admin_settings', 'system_settings'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('merchant_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('group');
            $table->string('setting_key');
            $table->longText('setting_value')->nullable();
            $table->string('setting_type')->default('string');
            $table->timestamps();
            $table->unique(['merchant_id', 'group', 'setting_key']);
        });
        Schema::create('shop_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->string('group');
            $table->string('setting_key');
            $table->longText('setting_value')->nullable();
            $table->string('setting_type')->default('string');
            $table->timestamps();
            $table->unique(['shop_id', 'group', 'setting_key']);
        });
        Schema::create('admin_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('setting_key');
            $table->longText('setting_value')->nullable();
            $table->string('setting_type')->default('string');
            $table->timestamps();
            $table->unique(['group', 'setting_key']);
        });
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->string('value_type')->default('string');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_key');
            $table->string('channel');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->json('variables')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['event_key', 'channel']);
        });
    }

    public function test_catalogue_contains_only_the_frozen_phase_1b_event_keys(): void
    {
        $keys = app(NotificationEventCatalogue::class)->all()->keys()->all();

        $this->assertCount(27, $keys);
        $this->assertContains('customer.registered', $keys);
        $this->assertContains('order.ready_for_dispatch.customer', $keys);
        $this->assertContains('order.message.customer', $keys);
        $this->assertNotContains('order.pending.customer', $keys);
    }

    public function test_catalogue_exposes_status_defaults_mandatory_rules_and_policy_metadata(): void
    {
        $catalogue = app(NotificationEventCatalogue::class);

        $this->assertTrue($catalogue->find('order.confirmed.customer')->defaultEnabled('email'));
        $this->assertFalse($catalogue->find('order.processing.customer')->defaultEnabled('email'));
        $this->assertTrue($catalogue->find('order.cancelled.customer')->mandatory('email'));
        $this->assertSame('auto_after_delivered', $catalogue->find('order.completed.customer')->policy['suppress_when']);
        $this->assertSame('order_snapshot', $catalogue->find('order.placed.customer')->contactSource);
    }

    public function test_merchant_lifecycle_and_operational_events_have_distinct_recipient_policies(): void
    {
        $catalogue = app(NotificationEventCatalogue::class);

        foreach (['merchant.account_created', 'merchant.approved', 'merchant.rejected', 'merchant.suspended', 'merchant.reactivated'] as $key) {
            $this->assertSame(RecipientContactSource::MERCHANT_PRIMARY, $catalogue->find($key)->contactSource);
        }

        $this->assertSame(RecipientContactSource::MERCHANT_PRIMARY_PLUS_ADDITIONAL, $catalogue->find('order.new.merchant')->contactSource);
        $this->assertSame(RecipientContactSource::MERCHANT_PRIMARY_PLUS_ADDITIONAL, $catalogue->find('payment.upi_submitted.merchant')->contactSource);
    }

    public function test_order_message_is_catalogued_as_per_message_opt_in(): void
    {
        $event = app(NotificationEventCatalogue::class)->find('order.message.customer');

        $this->assertSame('per_message_opt_in', $event->policy['delivery_control']);
        $this->assertFalse($event->defaultEnabled(NotificationChannelName::EMAIL));
        $this->assertFalse($event->defaultEnabled(NotificationChannelName::SMS));
        $this->assertFalse($event->defaultEnabled(NotificationChannelName::WHATSAPP));
    }

    public function test_unknown_events_and_unsupported_channels_fail_closed(): void
    {
        $resolver = app(NotificationPreferenceResolver::class);

        $this->assertFalse($resolver->enabled(new NotificationMessage('unknown.event', 'customer', 'email')));

        $this->expectException(InvalidArgumentException::class);
        $resolver->setForMerchant(1, 'unknown.event', 'email', true);
    }

    public function test_preference_precedence_is_mandatory_then_shop_then_merchant_then_default(): void
    {
        $resolver = app(NotificationPreferenceResolver::class);
        $message = new NotificationMessage('order.processing.customer', 'customer', 'email', shopId: 10, merchantId: 20);

        $this->assertFalse($resolver->enabled($message));
        $resolver->setForMerchant(20, 'order.processing.customer', 'email', true);
        $this->assertTrue($resolver->enabled($message));
        $resolver->setForShop(10, 'order.processing.customer', 'email', false);
        $this->assertFalse($resolver->enabled($message));

        \DB::table('shop_settings')->insert([
            'shop_id' => 10,
            'group' => 'notifications',
            'setting_key' => 'events.order.cancelled.customer.email.enabled',
            'setting_value' => '0',
            'setting_type' => 'boolean',
        ]);
        $this->assertTrue($resolver->enabled(new NotificationMessage('order.cancelled.customer', 'customer', 'email', shopId: 10)));

        $this->expectException(InvalidArgumentException::class);
        $resolver->setForShop(10, 'order.cancelled.customer', 'email', false);
    }

    public function test_global_admin_preference_ignores_shop_and_merchant_scope(): void
    {
        $resolver = app(NotificationPreferenceResolver::class);
        $message = new NotificationMessage('order.new.admin', 'admin', 'email', shopId: 10, merchantId: 20);

        $this->assertFalse($resolver->enabled($message));
        $resolver->setGlobal('order.new.admin', 'email', true);
        $this->assertTrue($resolver->enabled($message));

        $this->expectException(InvalidArgumentException::class);
        $resolver->setForShop(10, 'order.new.admin', 'email', false);
    }

    public function test_seeder_creates_every_supported_template_and_preserves_admin_edits(): void
    {
        $this->seed(NotificationTemplateSeeder::class);
        $expected = app(NotificationEventCatalogue::class)->all()->sum(fn ($event): int => count($event->channels));
        $this->assertDatabaseCount('notification_templates', $expected);

        $template = NotificationTemplate::query()->where('event_key', 'order.placed.customer')->where('channel', 'email')->firstOrFail();
        $template->update(['subject' => 'Admin-customized subject']);
        $this->seed(NotificationTemplateSeeder::class);

        $this->assertSame('Admin-customized subject', $template->fresh()->subject);
        $this->assertDatabaseCount('notification_templates', $expected);
    }

    public function test_template_lookup_respects_active_flag_and_catalogue(): void
    {
        $this->seed(NotificationTemplateSeeder::class);
        $service = app(NotificationTemplateService::class);
        $template = $service->findActive('order.placed.customer', 'email');

        $this->assertNotNull($template);
        $template->update(['is_active' => false]);
        $this->assertNull($service->findActive('order.placed.customer', 'email'));
        $this->assertNull($service->findActive('made.up', 'email'));
    }

    public function test_safe_renderer_substitutes_only_explicit_tokens_and_uses_dynamic_marketplace_name(): void
    {
        $this->seed(NotificationTemplateSeeder::class);
        \DB::table('system_settings')->insert(['key' => 'marketplace_name', 'value' => 'Local Bazaar', 'value_type' => 'string']);
        $template = NotificationTemplate::query()->where('event_key', 'order.placed.customer')->where('channel', 'email')->firstOrFail();
        $template->update(['body' => 'Hi {{ customer_name }} from {{ marketplace_name }} {{ forbidden }} <?php throw new Exception; ?>']);

        $rendered = app(NotificationTemplateRenderer::class)->render($template->fresh(), ['customer_name' => 'Asha', 'forbidden' => 'secret']);

        $this->assertStringContainsString('Hi Asha from Local Bazaar', $rendered['body']);
        $this->assertStringNotContainsString('secret', $rendered['body']);
        $this->assertStringContainsString('<?php throw new Exception; ?>', $rendered['body']);
    }

    public function test_seeded_branding_distinguishes_shop_and_marketplace_templates(): void
    {
        $this->seed(NotificationTemplateSeeder::class);

        $shop = NotificationTemplate::query()->where('event_key', 'order.placed.customer')->where('channel', 'email')->firstOrFail();
        $marketplace = NotificationTemplate::query()->where('event_key', 'customer.registered')->where('channel', 'email')->firstOrFail();
        $this->assertStringContainsString('{{ shop_name }}', $shop->subject);
        $this->assertStringContainsString('{{ marketplace_name }}', $shop->body);
        $this->assertStringContainsString('{{ marketplace_name }}', $marketplace->subject);
    }

    public function test_delivery_log_masks_normal_serialization_without_destroying_raw_destination(): void
    {
        $log = new NotificationDeliveryLog(['destination' => 'customer@example.test']);
        $this->assertSame('c***@example.test', $log->toArray()['destination']);
        $this->assertSame('customer@example.test', $log->destination);

        $log->destination = '+91 98765 43210';
        $this->assertSame('***3210', $log->toArray()['destination']);
    }
}
