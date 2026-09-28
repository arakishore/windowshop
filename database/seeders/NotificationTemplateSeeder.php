<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Services\Notification\NotificationEventCatalogue;
use App\Services\Notification\NotificationTemplateDefaults;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    public function run(NotificationEventCatalogue $catalogue, NotificationTemplateDefaults $defaults): void
    {
        foreach ($catalogue->all() as $event) {
            foreach (array_keys($event->channels) as $channel) {
                $attributes = $defaults->for($event, $channel);

                NotificationTemplate::query()->firstOrCreate(
                    ['event_key' => $event->templateKey, 'channel' => $channel],
                    $attributes,
                );
            }
        }
    }
}
