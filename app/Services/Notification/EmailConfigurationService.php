<?php

namespace App\Services\Notification;

use App\Mail\TransactionalNotificationMail;
use App\Models\AdminSetting;
use App\Services\Admin\AdminSettingsService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailConfigurationService
{
    public const GROUP = 'notifications.email';

    public const MAILER = 'windowshop_smtp';

    public function __construct(private readonly AdminSettingsService $settings) {}

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
            'password_configured' => $this->passwordConfigured(),
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
        ];

        foreach ($types as $key => $type) {
            $this->settings->setTyped(self::GROUP, $key, $values[$key] ?? null, $type);
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

    public function send(TransactionalNotificationMail $mail, string $destination): void
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
