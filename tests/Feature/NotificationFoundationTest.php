<?php

namespace Tests\Feature;

use App\Models\MerchantProfile;
use App\Models\MerchantSetting;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Contracts\NotificationChannel;
use App\Notifications\DeliveryResult;
use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationChannelRegistry;
use App\Notifications\NotificationMessage;
use App\Notifications\ProviderMode;
use App\Services\Merchant\MerchantSettingsService;
use App\Services\Notification\AdditionalMerchantRecipientService;
use App\Services\Notification\NotificationDeliveryLogger;
use App\Services\Notification\NotificationManager;
use App\Services\Notification\NotificationPreferenceResolver;
use App\Services\Notification\NotificationProviderModeResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class NotificationFoundationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notification_delivery_logs');
        Schema::dropIfExists('merchant_settings');
        Schema::create('merchant_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('group', 50);
            $table->string('setting_key');
            $table->longText('setting_value')->nullable();
            $table->string('setting_type', 30)->default('string');
            $table->timestamps();
            $table->unique(['merchant_id', 'group', 'setting_key']);
        });
        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('delivery_key')->nullable()->unique();
            $table->string('notification_key');
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->string('related_type')->nullable();
            $table->string('related_id')->nullable();
            $table->string('channel');
            $table->string('destination')->nullable();
            $table->string('provider_mode')->nullable();
            $table->string('provider')->nullable();
            $table->string('status');
            $table->string('error_summary', 1000)->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function test_manager_routes_supported_channels_and_persists_delivery_results(): void
    {
        $merchant = $this->merchant();

        foreach (NotificationChannelName::all() as $channelName) {
            app(NotificationPreferenceResolver::class)->setForMerchant($merchant->getKey(), 'order.confirmed.customer', $channelName, true);
            $channel = new class($channelName) implements NotificationChannel
            {
                public function __construct(private readonly string $channel) {}

                public function name(): string
                {
                    return $this->channel;
                }

                public function send(NotificationMessage $message): DeliveryResult
                {
                    return DeliveryResult::sent(ProviderMode::WINDOWSHOP, 'test-provider', ['routed' => $message->channel]);
                }
            };

            $manager = new NotificationManager(
                new NotificationChannelRegistry([$channel]),
                app(NotificationPreferenceResolver::class),
                app(NotificationDeliveryLogger::class),
            );
            $result = $manager->send($this->message($channelName, $merchant->getKey()));

            $this->assertSame(DeliveryResult::SENT, $result->status);
            $this->assertDatabaseHas('notification_delivery_logs', [
                'notification_key' => 'order.confirmed.customer',
                'channel' => $channelName,
                'status' => DeliveryResult::SENT,
                'provider' => 'test-provider',
            ]);
        }
    }

    public function test_unsupported_channel_is_rejected_without_delivery_log(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(NotificationManager::class)->send($this->message('fax'));

        $this->assertDatabaseCount('notification_delivery_logs', 0);
    }

    public function test_sms_and_whatsapp_never_fake_success_without_provider_implementation(): void
    {
        $modes = app(NotificationProviderModeResolver::class);

        foreach ([new SmsChannel($modes), new WhatsAppChannel($modes)] as $channel) {
            $disabled = $channel->send($this->message($channel->name()));
            $this->assertSame(DeliveryResult::SKIPPED, $disabled->status);

            $merchant = $this->merchant();
            $modes->set($merchant->getKey(), $channel->name(), ProviderMode::WINDOWSHOP);
            $notConfigured = $channel->send($this->message($channel->name(), $merchant->getKey()));
            $this->assertSame(DeliveryResult::NOT_CONFIGURED, $notConfigured->status);
        }
    }

    public function test_disabled_preference_is_logged_as_skipped_without_calling_channel(): void
    {
        $merchant = $this->merchant();
        $preferences = app(NotificationPreferenceResolver::class);
        $preferences->setForMerchant($merchant->getKey(), 'order.processing.customer', NotificationChannelName::EMAIL, false);
        $channel = new class implements NotificationChannel
        {
            public function name(): string
            {
                return NotificationChannelName::EMAIL;
            }

            public function send(NotificationMessage $message): DeliveryResult
            {
                throw new \RuntimeException('Must not be called.');
            }
        };
        $manager = new NotificationManager(new NotificationChannelRegistry([$channel]), $preferences, app(NotificationDeliveryLogger::class));

        $result = $manager->send(new NotificationMessage('order.processing.customer', 'customer', NotificationChannelName::EMAIL, 'customer@example.test', merchantId: $merchant->getKey()));

        $this->assertSame(DeliveryResult::SKIPPED, $result->status);
        $this->assertDatabaseHas('notification_delivery_logs', ['status' => DeliveryResult::SKIPPED]);
    }

    public function test_channel_failure_is_captured_and_recorded(): void
    {
        $channel = new class implements NotificationChannel
        {
            public function name(): string
            {
                return NotificationChannelName::EMAIL;
            }

            public function send(NotificationMessage $message): DeliveryResult
            {
                throw new \RuntimeException('Test transport failure');
            }
        };
        $manager = new NotificationManager(new NotificationChannelRegistry([$channel]), app(NotificationPreferenceResolver::class), app(NotificationDeliveryLogger::class));

        $result = $manager->send($this->message(NotificationChannelName::EMAIL));

        $this->assertSame(DeliveryResult::FAILED, $result->status);
        $this->assertDatabaseHas('notification_delivery_logs', [
            'status' => DeliveryResult::FAILED,
            'error_summary' => 'Test transport failure',
        ]);
    }

    public function test_merchant_secrets_are_encrypted_redacted_decrypted_and_preserved_on_blank_update(): void
    {
        $merchant = $this->merchant();
        $settings = app(MerchantSettingsService::class);
        $settings->setSecret($merchant->getKey(), 'notifications', 'sms.api_key', 'plain-test-secret');
        $stored = MerchantSetting::query()->where('merchant_id', $merchant->getKey())->where('setting_key', 'sms.api_key')->firstOrFail();

        $this->assertNotSame('plain-test-secret', $stored->getRawOriginal('setting_value'));
        $this->assertStringNotContainsString('plain-test-secret', $stored->getRawOriginal('setting_value'));
        $this->assertSame('plain-test-secret', $settings->secret($merchant->getKey(), 'notifications', 'sms.api_key'));
        $this->assertSame('Configured', $settings->all($merchant->getKey(), 'notifications')->get('sms.api_key'));
        $this->assertSame('Configured', $stored->toArray()['setting_value']);

        $ciphertext = $stored->getRawOriginal('setting_value');
        $settings->setSecret($merchant->getKey(), 'notifications', 'sms.api_key', '');
        $this->assertSame($ciphertext, $stored->fresh()->getRawOriginal('setting_value'));
    }

    public function test_provider_modes_and_additional_recipients_are_stored_and_resolved(): void
    {
        $merchant = $this->merchant();
        $modes = app(NotificationProviderModeResolver::class);

        foreach ([NotificationChannelName::SMS, NotificationChannelName::WHATSAPP] as $channel) {
            foreach (ProviderMode::all() as $mode) {
                $modes->set($merchant->getKey(), $channel, $mode);
                $this->assertSame($mode, $modes->resolve($channel, $merchant->getKey()));
            }
        }

        $recipients = app(AdditionalMerchantRecipientService::class);
        $this->assertSame(
            ['owner@example.test', 'manager@example.test'],
            $recipients->set($merchant->getKey(), NotificationChannelName::EMAIL, [' Owner@Example.test ', 'manager@example.test', 'owner@example.test']),
        );
        $this->assertSame(['owner@example.test', 'manager@example.test'], $recipients->get($merchant->getKey(), NotificationChannelName::EMAIL));
        $this->assertSame(['+919422945125'], $recipients->set($merchant->getKey(), NotificationChannelName::WHATSAPP, ['+91 94229 45125']));
    }

    public function test_manager_refuses_delivery_inside_an_open_transaction(): void
    {
        DB::beginTransaction();

        try {
            $this->expectException(LogicException::class);
            app(NotificationManager::class)->send($this->message(NotificationChannelName::EMAIL));
        } finally {
            DB::rollBack();
        }
    }

    public function test_uuid_related_entity_id_flows_through_and_persists_in_delivery_log(): void
    {
        $relatedId = (string) Str::uuid();
        $message = new NotificationMessage(
            'order.placed.customer',
            'customer',
            NotificationChannelName::EMAIL,
            'customer@example.test',
            relatedType: 'order',
            relatedId: $relatedId,
        );

        app(NotificationManager::class)->send($message);

        $this->assertDatabaseHas('notification_delivery_logs', [
            'notification_key' => 'order.placed.customer',
            'related_type' => 'order',
            'related_id' => $relatedId,
        ]);
    }

    public function test_same_occurrence_is_idempotent_while_different_occurrence_is_delivered(): void
    {
        $calls = 0;
        $channel = new class($calls) implements NotificationChannel
        {
            public function __construct(private int &$calls) {}

            public function name(): string
            {
                return NotificationChannelName::EMAIL;
            }

            public function send(NotificationMessage $message): DeliveryResult
            {
                $this->calls++;

                return DeliveryResult::notConfigured();
            }
        };
        $manager = new NotificationManager(new NotificationChannelRegistry([$channel]), app(NotificationPreferenceResolver::class), app(NotificationDeliveryLogger::class));

        foreach (['occurrence-a', 'occurrence-a', 'occurrence-b'] as $occurrence) {
            $manager->send(new NotificationMessage('order.placed.customer', 'customer', 'email', 'customer@example.test', occurrenceId: $occurrence));
        }

        $this->assertSame(2, $calls);
        $this->assertDatabaseCount('notification_delivery_logs', 2);
    }

    private function message(string $channel, ?int $merchantId = null): NotificationMessage
    {
        return new NotificationMessage('order.confirmed.customer', 'customer', $channel, 'destination@example.test', merchantId: $merchantId);
    }

    private function merchant(): MerchantProfile
    {
        $merchant = new MerchantProfile;
        $merchant->setAttribute('id', 1);

        return $merchant;
    }
}
