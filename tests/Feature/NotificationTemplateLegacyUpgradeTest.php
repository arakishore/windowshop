<?php

namespace Tests\Feature;

use App\Models\NotificationTemplate;
use App\Notifications\NotificationChannelName;
use App\Services\Notification\NotificationEventCatalogue;
use App\Services\Notification\NotificationTemplateDefaults;
use App\Services\Notification\NotificationTemplateLegacyDefaults;
use App\Services\Notification\NotificationTemplateLegacyUpgradeService;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class NotificationTemplateLegacyUpgradeTest extends TestCase
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

    public function test_upgrade_matches_legacy_fields_independently_and_preserves_unrelated_state(): void
    {
        $email = $this->template('order.shipped.customer', NotificationChannelName::EMAIL);
        $emailIdentity = [$email->uuid, $email->event_key, $email->channel];
        $this->applyLegacy($email);
        $email->update([
            'metadata' => ['branding' => 'shop', 'seeded' => true, 'email' => ['cc' => ['copy@example.test'], 'bcc' => ['hidden@example.test']], 'other' => 'preserve-me'],
            'is_active' => false,
        ]);

        $customSubject = $this->template('order.packed.customer', NotificationChannelName::EMAIL);
        $this->applyLegacy($customSubject);
        $customSubject->update(['subject' => 'Admin subject']);

        $customBody = $this->template('order.ready_for_dispatch.customer', NotificationChannelName::EMAIL);
        $this->applyLegacy($customBody);
        $customBody->update(['body' => 'Admin body']);

        $sms = $this->template('order.processing.customer', NotificationChannelName::SMS);
        $this->applyLegacy($sms);
        $whatsApp = $this->template('order.in_transit.customer', NotificationChannelName::WHATSAPP);
        $this->applyLegacy($whatsApp);

        $unknown = $this->template('order.delivered.customer', NotificationChannelName::EMAIL);
        $unknown->update(['subject' => 'Unknown old subject', 'body' => 'Unknown old body']);

        $counts = app(NotificationTemplateLegacyUpgradeService::class)->upgrade();

        $this->assertSame(2, $counts['subjects_eligible']);
        $this->assertSame(4, $counts['bodies_eligible']);
        $this->assertSame(5, $counts['templates_changed']);
        $this->assertSame(4, $counts['customized_values_preserved']);

        $this->assertCurrent($email->fresh());
        $this->assertSame($emailIdentity, [$email->fresh()->uuid, $email->fresh()->event_key, $email->fresh()->channel]);
        $this->assertFalse($email->fresh()->is_active);
        $this->assertSame(['copy@example.test'], data_get($email->fresh()->metadata, 'email.cc'));
        $this->assertSame(['hidden@example.test'], data_get($email->fresh()->metadata, 'email.bcc'));
        $this->assertSame('preserve-me', data_get($email->fresh()->metadata, 'other'));

        $this->assertSame('Admin subject', $customSubject->fresh()->subject);
        $this->assertSame($this->current($customSubject)['body'], $customSubject->fresh()->body);
        $this->assertSame($this->current($customBody)['subject'], $customBody->fresh()->subject);
        $this->assertSame('Admin body', $customBody->fresh()->body);
        $this->assertCurrent($sms->fresh());
        $this->assertCurrent($whatsApp->fresh());
        $this->assertSame('Unknown old subject', $unknown->fresh()->subject);
        $this->assertSame('Unknown old body', $unknown->fresh()->body);

        $secondRun = app(NotificationTemplateLegacyUpgradeService::class)->upgrade();
        $this->assertSame(0, $secondRun['subjects_eligible']);
        $this->assertSame(0, $secondRun['bodies_eligible']);
        $this->assertSame(0, $secondRun['templates_changed']);
    }

    public function test_dry_run_reports_eligible_fields_without_writing(): void
    {
        $template = $this->template('order.confirmed.customer', NotificationChannelName::EMAIL);
        $this->applyLegacy($template);
        $before = $template->fresh();

        $this->artisan('notification-templates:upgrade-defaults', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run complete. No templates were modified.')
            ->assertSuccessful();

        $after = $template->fresh();
        $this->assertSame($before->subject, $after->subject);
        $this->assertSame($before->body, $after->body);
        $this->assertTrue($before->updated_at->equalTo($after->updated_at));
    }

    public function test_normal_seeder_still_preserves_rows_and_fresh_rows_use_current_defaults(): void
    {
        $template = $this->template('order.cancelled.customer', NotificationChannelName::EMAIL);
        $template->update(['subject' => 'Admin subject', 'body' => 'Admin body', 'is_active' => false]);

        $this->seed(NotificationTemplateSeeder::class);

        $this->assertSame('Admin subject', $template->fresh()->subject);
        $this->assertSame('Admin body', $template->fresh()->body);
        $this->assertFalse($template->fresh()->is_active);
        $this->assertTrue(app(NotificationEventCatalogue::class)->find('order.cancelled.customer')->mandatory(NotificationChannelName::EMAIL));

        $fresh = $this->template('customer.registered', NotificationChannelName::EMAIL);
        $this->assertCurrent($fresh);
    }

    public function test_explicit_legacy_map_covers_every_catalogue_event_and_channel(): void
    {
        $legacyDefaults = app(NotificationTemplateLegacyDefaults::class);

        foreach (app(NotificationEventCatalogue::class)->all() as $event) {
            foreach (array_keys($event->channels) as $channel) {
                $legacy = $legacyDefaults->for($event->key, $channel);
                $recipient = match ($event->audience) {
                    'merchant' => '{{ merchant_name }}',
                    'customer' => '{{ customer_name }}',
                    default => 'there',
                };
                $brand = $event->branding === 'shop' ? '{{ shop_name }}' : '{{ marketplace_name }}';
                $expectedBody = "Hello {$recipient},\n\n{$event->description}";
                if ($channel === NotificationChannelName::EMAIL) {
                    $expectedBody .= "\n\nPowered by {{ marketplace_name }}";
                }

                $this->assertNotNull($legacy, "Missing legacy mapping for {$event->key}:{$channel}");
                $this->assertSame($expectedBody, $legacy['body'], "Incorrect legacy body for {$event->key}:{$channel}");
                $this->assertSame($channel === NotificationChannelName::EMAIL ? "{$brand}: {$event->label}" : null, $legacy['subject']);
            }
        }

        $this->assertNull($legacyDefaults->for('unknown.event', NotificationChannelName::EMAIL));
    }

    private function applyLegacy(NotificationTemplate $template): void
    {
        $legacy = app(NotificationTemplateLegacyDefaults::class)->for($template->event_key, $template->channel);
        $template->update($legacy);
    }

    private function assertCurrent(NotificationTemplate $template): void
    {
        $current = $this->current($template);
        $this->assertSame($current['subject'], $template->subject);
        $this->assertSame($current['body'], $template->body);
    }

    /** @return array<string, mixed> */
    private function current(NotificationTemplate $template): array
    {
        $event = app(NotificationEventCatalogue::class)->find($template->event_key);

        return app(NotificationTemplateDefaults::class)->for($event, $template->channel);
    }

    private function template(string $eventKey, string $channel): NotificationTemplate
    {
        return NotificationTemplate::query()->where('event_key', $eventKey)->where('channel', $channel)->firstOrFail();
    }
}
