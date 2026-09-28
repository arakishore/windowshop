<?php

namespace App\Services\Notification;

use App\Models\NotificationTemplate;
use App\Services\System\SystemSettingService;

class NotificationTemplateRenderer
{
    public function __construct(private readonly SystemSettingService $systemSettings) {}

    /** @return array{subject: ?string, body: string} */
    public function render(NotificationTemplate $template, array $variables): array
    {
        $variables['marketplace_name'] = $variables['marketplace_name'] ?? $this->systemSettings->marketplaceName();
        $allowed = array_fill_keys($template->variables ?? [], true);
        $replace = static function (array $matches) use ($variables, $allowed): string {
            $key = $matches[1];

            if (! isset($allowed[$key])) {
                return '';
            }

            $value = $variables[$key] ?? '';

            return is_scalar($value) ? (string) $value : '';
        };

        return [
            'subject' => $template->subject === null ? null : preg_replace_callback('/{{\s*([A-Za-z0-9_]+)\s*}}/', $replace, $template->subject),
            'body' => preg_replace_callback('/{{\s*([A-Za-z0-9_]+)\s*}}/', $replace, $template->body),
        ];
    }
}
