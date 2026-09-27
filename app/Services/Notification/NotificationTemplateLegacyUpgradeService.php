<?php

namespace App\Services\Notification;

use App\Models\NotificationTemplate;

class NotificationTemplateLegacyUpgradeService
{
    public function __construct(
        private readonly NotificationEventCatalogue $catalogue,
        private readonly NotificationTemplateDefaults $defaults,
        private readonly NotificationTemplateLegacyDefaults $legacyDefaults,
    ) {}

    /** @return array{templates_scanned: int, subjects_eligible: int, bodies_eligible: int, templates_changed: int, customized_values_preserved: int} */
    public function upgrade(bool $dryRun = false): array
    {
        $counts = [
            'templates_scanned' => 0,
            'subjects_eligible' => 0,
            'bodies_eligible' => 0,
            'templates_changed' => 0,
            'customized_values_preserved' => 0,
        ];

        NotificationTemplate::query()->orderBy('id')->each(function (NotificationTemplate $template) use (&$counts, $dryRun): void {
            $counts['templates_scanned']++;
            $event = $this->catalogue->find($template->event_key);
            $legacy = $this->legacyDefaults->for($template->event_key, $template->channel);

            if ($event === null || $legacy === null) {
                return;
            }

            $current = $this->defaults->for($event, $template->channel);
            $changes = [];

            if ($template->subject === $legacy['subject'] && $template->subject !== $current['subject']) {
                $counts['subjects_eligible']++;
                $changes['subject'] = $current['subject'];
            } elseif ($template->subject !== $legacy['subject'] && $template->subject !== $current['subject']) {
                $counts['customized_values_preserved']++;
            }

            if ($template->body === $legacy['body'] && $template->body !== $current['body']) {
                $counts['bodies_eligible']++;
                $changes['body'] = $current['body'];
            } elseif ($template->body !== $legacy['body'] && $template->body !== $current['body']) {
                $counts['customized_values_preserved']++;
            }

            if ($changes === []) {
                return;
            }

            $counts['templates_changed']++;
            if (! $dryRun) {
                $template->update($changes);
            }
        });

        return $counts;
    }
}
