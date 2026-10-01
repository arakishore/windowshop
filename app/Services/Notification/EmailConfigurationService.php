<?php

namespace App\Services\Notification;

use App\Mail\TransactionalNotificationMail;
use App\Models\AdminSetting;
use App\Services\Admin\AdminSettingsService;
use App\Services\Marketplace\MarketplaceLogoService;
use App\Services\System\SystemSettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EmailConfigurationService
{
    public const GROUP = 'notifications.email';

    public const MAILER = 'windowshop_smtp';

    public const LOGO_DIRECTORY = 'email/branding';

    public function __construct(
        private readonly AdminSettingsService $settings,
        private readonly SystemSettingService $systemSettings,
        private readonly MarketplaceLogoService $marketplaceLogo,
    ) {}

    /** @return array<string, mixed> */
    public function values(): array
    {
        return [
            'enabled' => (bool) $this->settings->get(self::GROUP, 'enabled', false),
            'transport' => 'smtp',
            'host' => (string) $this->settings->get(self::GROUP, 'smtp.host', ''),
            'port' => (int) $this->settings->get(self::GROUP, 'smtp.port', 587),
            'encryption' => (string) $this->settings->get(self::GROUP, 'smtp.encryption', 'tls'),
            'username' => (string) $this->settings->get(self::GROUP, 'smtp.username', ''),
            'from_name' => (string) $this->settings->get(self::GROUP, 'from_name', ''),
            'from_email' => (string) $this->settings->get(self::GROUP, 'from_email', ''),
            'reply_to' => (string) $this->settings->get(self::GROUP, 'reply_to', ''),
            'admin_notification_email' => (string) $this->settings->get(self::GROUP, 'admin_notification_email', ''),
            'password_configured' => $this->passwordConfigured(),
            'branding.logo_path' => (string) $this->settings->get(self::GROUP, 'branding.logo_path', ''),
            'branding.show_name' => (bool) $this->settings->get(self::GROUP, 'branding.show_name', true),
            'footer.show' => (bool) $this->settings->get(self::GROUP, 'footer.show', true),
            'footer.benefit_1' => (string) $this->settings->get(self::GROUP, 'footer.benefit_1', ''),
            'footer.benefit_2' => (string) $this->settings->get(self::GROUP, 'footer.benefit_2', ''),
            'footer.benefit_3' => (string) $this->settings->get(self::GROUP, 'footer.benefit_3', ''),
            'footer.social.facebook' => (string) $this->settings->get(self::GROUP, 'footer.social.facebook', ''),
            'footer.social.instagram' => (string) $this->settings->get(self::GROUP, 'footer.social.instagram', ''),
            'footer.social.youtube' => (string) $this->settings->get(self::GROUP, 'footer.social.youtube', ''),
            'footer.social.twitter' => (string) $this->settings->get(self::GROUP, 'footer.social.twitter', ''),
            'footer.social.linkedin' => (string) $this->settings->get(self::GROUP, 'footer.social.linkedin', ''),
            'footer.apps.google_play' => (string) $this->settings->get(self::GROUP, 'footer.apps.google_play', ''),
            'footer.apps.app_store' => (string) $this->settings->get(self::GROUP, 'footer.apps.app_store', ''),
            'footer.show_powered_by' => (bool) $this->settings->get(self::GROUP, 'footer.show_powered_by', true),
            'footer.powered_by_text' => (string) $this->settings->get(self::GROUP, 'footer.powered_by_text', 'Powered by {{ marketplace_name }}'),
        ];
    }

    /** @param array<string, mixed> $values */
    public function save(array $values): void
    {
        $types = [
            'enabled' => AdminSetting::TYPE_BOOLEAN,
            'transport' => AdminSetting::TYPE_STRING,
            'smtp.host' => AdminSetting::TYPE_STRING,
            'smtp.port' => AdminSetting::TYPE_INTEGER,
            'smtp.encryption' => AdminSetting::TYPE_STRING,
            'smtp.username' => AdminSetting::TYPE_STRING,
            'from_name' => AdminSetting::TYPE_STRING,
            'from_email' => AdminSetting::TYPE_STRING,
            'reply_to' => AdminSetting::TYPE_STRING,
            'admin_notification_email' => AdminSetting::TYPE_STRING,
        ];

        foreach ($types as $key => $type) {
            $this->settings->setTyped(self::GROUP, $key, $values[$key] ?? null, $type);
        }

        $presentationTypes = [
            'branding.show_name' => AdminSetting::TYPE_BOOLEAN,
            'footer.show' => AdminSetting::TYPE_BOOLEAN,
            'footer.benefit_1' => AdminSetting::TYPE_STRING,
            'footer.benefit_2' => AdminSetting::TYPE_STRING,
            'footer.benefit_3' => AdminSetting::TYPE_STRING,
            'footer.social.facebook' => AdminSetting::TYPE_STRING,
            'footer.social.instagram' => AdminSetting::TYPE_STRING,
            'footer.social.youtube' => AdminSetting::TYPE_STRING,
            'footer.social.twitter' => AdminSetting::TYPE_STRING,
            'footer.social.linkedin' => AdminSetting::TYPE_STRING,
            'footer.apps.google_play' => AdminSetting::TYPE_STRING,
            'footer.apps.app_store' => AdminSetting::TYPE_STRING,
            'footer.show_powered_by' => AdminSetting::TYPE_BOOLEAN,
            'footer.powered_by_text' => AdminSetting::TYPE_STRING,
        ];

        foreach ($presentationTypes as $key => $type) {
            if (array_key_exists($key, $values)) {
                $this->settings->setTyped(self::GROUP, $key, $values[$key], $type);
            }
        }

        $password = trim((string) ($values['smtp.password'] ?? ''));
        if ($password !== '') {
            $this->settings->setTyped(self::GROUP, 'smtp.password', Crypt::encryptString($password), AdminSetting::TYPE_STRING);
        }
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::GROUP, 'enabled', false);
    }

    public function configured(): bool
    {
        $values = $this->values();

        return trim($values['host']) !== ''
            && $values['port'] > 0
            && filter_var($values['from_email'], FILTER_VALIDATE_EMAIL) !== false;
    }

    public function send(TransactionalNotificationMail $mail, string|array $destination): void
    {
        $this->configureMailer();
        Mail::mailer(self::MAILER)->to($destination)->send($mail);
    }

    public function decryptedPassword(): ?string
    {
        $encrypted = $this->settings->get(self::GROUP, 'smtp.password');

        return is_string($encrypted) && $encrypted !== '' ? Crypt::decryptString($encrypted) : null;
    }

    public function passwordConfigured(): bool
    {
        return (string) $this->settings->get(self::GROUP, 'smtp.password', '') !== '';
    }

    public function sanitizedError(Throwable $exception): string
    {
        $message = preg_replace('/(password|username|auth|dsn)\s*[=:]\s*\S+/i', '$1=[redacted]', $exception->getMessage());

        return mb_substr($message ?: 'Email delivery failed.', 0, 1000);
    }

    public function replaceLogo(UploadedFile $file): void
    {
        $current = (string) $this->settings->get(self::GROUP, 'branding.logo_path', '');
        $path = $file->storeAs(self::LOGO_DIRECTORY, 'email-logo-'.uniqid('', true).'.'.$file->extension(), 'public');
        $this->settings->setTyped(self::GROUP, 'branding.logo_path', $path, AdminSetting::TYPE_STRING);
        $this->deleteManagedLogo($current);
    }

    public function removeLogo(): void
    {
        $current = (string) $this->settings->get(self::GROUP, 'branding.logo_path', '');
        $this->settings->setTyped(self::GROUP, 'branding.logo_path', null, AdminSetting::TYPE_STRING);
        $this->deleteManagedLogo($current);
    }

    public function emailLogoUrl(): ?string
    {
        $path = (string) $this->settings->get(self::GROUP, 'branding.logo_path', '');
        if ($this->isManagedLogo($path) && Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return $this->marketplaceLogo->url();
    }

    /** @return array<string, mixed> */
    public function presentation(): array
    {
        $values = $this->values();
        $marketplaceName = $this->systemSettings->marketplaceName();
        $poweredBy = trim($values['footer.powered_by_text']);
        $poweredBy = $poweredBy !== '' ? $poweredBy : 'Powered by {{ marketplace_name }}';
        $poweredBy = preg_replace_callback('/{{\s*([A-Za-z0-9_]+)\s*}}/', fn (array $match): string => $match[1] === 'marketplace_name' ? $marketplaceName : '', $poweredBy) ?: '';

        return [
            'email_logo_url' => $this->emailLogoUrl(),
            'email_logo_configured' => trim($values['branding.logo_path']) !== '',
            'show_brand_name' => $values['branding.show_name'],
            'show_footer' => $values['footer.show'],
            'benefits' => array_values(array_filter([
                trim($values['footer.benefit_1']),
                trim($values['footer.benefit_2']),
                trim($values['footer.benefit_3']),
            ], fn (string $value): bool => $value !== '')),
            'social_links' => $this->httpsLinks([
                'Facebook' => $values['footer.social.facebook'],
                'Instagram' => $values['footer.social.instagram'],
                'YouTube' => $values['footer.social.youtube'],
                'X / Twitter' => $values['footer.social.twitter'],
                'LinkedIn' => $values['footer.social.linkedin'],
            ]),
            'app_links' => $this->httpsLinks([
                'Google Play' => $values['footer.apps.google_play'],
                'App Store' => $values['footer.apps.app_store'],
            ]),
            'legal_links' => [
                'Privacy Policy' => route('storefront.privacy'),
                'Terms & Conditions' => route('storefront.terms'),
                'Contact' => route('storefront.contact'),
            ],
            'show_powered_by' => $values['footer.show_powered_by'],
            'powered_by_text' => $poweredBy,
            'copyright' => '© '.now()->year.' '.$marketplaceName.'. All rights reserved.',
        ];
    }

    private function isManagedLogo(?string $path): bool
    {
        return is_string($path)
            && str_starts_with($path, self::LOGO_DIRECTORY.'/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }

    private function deleteManagedLogo(?string $path): void
    {
        if ($this->isManagedLogo($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /** @param array<string, mixed> $links
     * @return array<string, string>
     */
    private function httpsLinks(array $links): array
    {
        return array_filter($links, fn (mixed $url): bool => is_string($url)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https');
    }

    private function configureMailer(): void
    {
        $values = $this->values();
        $scheme = $values['encryption'] === 'ssl' ? 'smtps' : 'smtp';

        config([
            'mail.mailers.'.self::MAILER => [
                'transport' => 'smtp',
                'scheme' => $scheme,
                'host' => $values['host'],
                'port' => $values['port'],
                'username' => $values['username'] !== '' ? $values['username'] : null,
                'password' => $this->decryptedPassword(),
                'auto_tls' => $values['encryption'] !== 'none',
                'timeout' => null,
            ],
        ]);

        if (method_exists(Mail::getFacadeRoot(), 'purge')) {
            Mail::purge(self::MAILER);
        }
    }
}
