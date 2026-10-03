<?php

namespace App\Services\Notification;

use App\Mail\TransactionalNotificationMail;
use App\Models\SystemSetting;
use App\Services\Marketplace\MarketplaceLogoService;
use App\Services\System\SystemSettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EmailConfigurationService
{
    public const GROUP = 'notifications.email';

    public const MAILER = 'windowshop_smtp';

    public const LOGO_DIRECTORY = 'email/branding';

    public function __construct(
        private readonly SystemSettingService $systemSettings,
        private readonly MarketplaceLogoService $marketplaceLogo,
    ) {}

    /** @return array<string, mixed> */
    public function values(): array
    {
        return [
            'enabled' => (bool) $this->systemSettings->emailSetting('enabled', false),
            'transport' => (string) $this->systemSettings->emailSetting('transport', 'smtp'),
            'host' => (string) $this->systemSettings->emailSetting('smtp.host', ''),
            'port' => (int) $this->systemSettings->emailSetting('smtp.port', 587),
            'encryption' => (string) $this->systemSettings->emailSetting('smtp.encryption', 'tls'),
            'username' => (string) $this->systemSettings->emailSetting('smtp.username', ''),
            'from_name' => (string) $this->systemSettings->emailSetting('from_name', ''),
            'from_email' => (string) $this->systemSettings->emailSetting('from_email', ''),
            'reply_to' => (string) $this->systemSettings->emailSetting('reply_to', ''),
            'admin_notification_email' => (string) $this->systemSettings->emailSetting('admin_notification_email', ''),
            'password_configured' => $this->passwordConfigured(),
            'branding.logo_path' => (string) $this->systemSettings->emailSetting('branding.logo_path', ''),
            'branding.show_name' => (bool) $this->systemSettings->emailSetting('branding.show_name', true),
            'footer.show' => (bool) $this->systemSettings->emailSetting('footer.show', true),
            'footer.benefit_1' => (string) $this->systemSettings->emailSetting('footer.benefit_1', ''),
            'footer.benefit_2' => (string) $this->systemSettings->emailSetting('footer.benefit_2', ''),
            'footer.benefit_3' => (string) $this->systemSettings->emailSetting('footer.benefit_3', ''),
            'footer.social.facebook' => (string) $this->systemSettings->emailSetting('footer.social.facebook', ''),
            'footer.social.instagram' => (string) $this->systemSettings->emailSetting('footer.social.instagram', ''),
            'footer.social.youtube' => (string) $this->systemSettings->emailSetting('footer.social.youtube', ''),
            'footer.social.twitter' => (string) $this->systemSettings->emailSetting('footer.social.twitter', ''),
            'footer.social.linkedin' => (string) $this->systemSettings->emailSetting('footer.social.linkedin', ''),
            'footer.apps.google_play' => (string) $this->systemSettings->emailSetting('footer.apps.google_play', ''),
            'footer.apps.app_store' => (string) $this->systemSettings->emailSetting('footer.apps.app_store', ''),
            'footer.show_powered_by' => (bool) $this->systemSettings->emailSetting('footer.show_powered_by', true),
            'footer.powered_by_text' => (string) $this->systemSettings->emailSetting('footer.powered_by_text', 'Powered by {{ marketplace_name }}'),
        ];
    }

    /** @param array<string, mixed> $values */
    public function save(array $values): void
    {
        $types = [
            'enabled' => SystemSetting::TYPE_BOOLEAN,
            'transport' => SystemSetting::TYPE_STRING,
            'smtp.host' => SystemSetting::TYPE_STRING,
            'smtp.port' => SystemSetting::TYPE_INTEGER,
            'smtp.encryption' => SystemSetting::TYPE_STRING,
            'smtp.username' => SystemSetting::TYPE_STRING,
            'from_name' => SystemSetting::TYPE_STRING,
            'from_email' => SystemSetting::TYPE_STRING,
            'reply_to' => SystemSetting::TYPE_STRING,
            'admin_notification_email' => SystemSetting::TYPE_STRING,
        ];

        foreach ($types as $key => $type) {
            $this->systemSettings->setEmailSetting($key, $values[$key] ?? null, $type);
        }

        $presentationTypes = [
            'branding.show_name' => SystemSetting::TYPE_BOOLEAN,
            'footer.show' => SystemSetting::TYPE_BOOLEAN,
            'footer.benefit_1' => SystemSetting::TYPE_STRING,
            'footer.benefit_2' => SystemSetting::TYPE_STRING,
            'footer.benefit_3' => SystemSetting::TYPE_STRING,
            'footer.social.facebook' => SystemSetting::TYPE_STRING,
            'footer.social.instagram' => SystemSetting::TYPE_STRING,
            'footer.social.youtube' => SystemSetting::TYPE_STRING,
            'footer.social.twitter' => SystemSetting::TYPE_STRING,
            'footer.social.linkedin' => SystemSetting::TYPE_STRING,
            'footer.apps.google_play' => SystemSetting::TYPE_STRING,
            'footer.apps.app_store' => SystemSetting::TYPE_STRING,
            'footer.show_powered_by' => SystemSetting::TYPE_BOOLEAN,
            'footer.powered_by_text' => SystemSetting::TYPE_STRING,
        ];

        foreach ($presentationTypes as $key => $type) {
            if (array_key_exists($key, $values)) {
                $this->systemSettings->setEmailSetting($key, $values[$key], $type);
            }
        }

        $password = trim((string) ($values['smtp.password'] ?? ''));
        if ($password !== '') {
            $this->systemSettings->setEmailPassword($password);
        }
    }

    public function enabled(): bool
    {
        return (bool) $this->systemSettings->emailSetting('enabled', false);
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
        return $this->systemSettings->emailPassword();
    }

    public function passwordConfigured(): bool
    {
        return $this->systemSettings->emailPasswordConfigured();
    }

    public function sanitizedError(Throwable $exception): string
    {
        $message = preg_replace('/(password|username|auth|dsn)\s*[=:]\s*\S+/i', '$1=[redacted]', $exception->getMessage());

        return mb_substr($message ?: 'Email delivery failed.', 0, 1000);
    }

    public function replaceLogo(UploadedFile $file): void
    {
        $current = (string) $this->systemSettings->emailSetting('branding.logo_path', '');
        $path = $file->storeAs(self::LOGO_DIRECTORY, 'email-logo-'.uniqid('', true).'.'.$file->extension(), 'public');
        $this->systemSettings->setEmailSetting('branding.logo_path', $path);
        $this->deleteManagedLogo($current);
    }

    public function removeLogo(): void
    {
        $current = (string) $this->systemSettings->emailSetting('branding.logo_path', '');
        $this->systemSettings->setEmailSetting('branding.logo_path', null);
        $this->deleteManagedLogo($current);
    }

    public function emailLogoUrl(): ?string
    {
        $path = (string) $this->systemSettings->emailSetting('branding.logo_path', '');
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
