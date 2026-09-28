<?php

namespace App\Console\Commands;

use App\Services\Notification\NotificationTemplateLegacyUpgradeService;
use Illuminate\Console\Command;

class UpgradeNotificationTemplateDefaults extends Command
{
    protected $signature = 'notification-templates:upgrade-defaults {--dry-run : Report eligible legacy defaults without changing templates}';

    protected $description = 'Upgrade untouched legacy notification template fields to current defaults';

    public function handle(NotificationTemplateLegacyUpgradeService $upgrade): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = $upgrade->upgrade($dryRun);

        $this->components->info($dryRun ? 'Dry run complete. No templates were modified.' : 'Legacy notification defaults upgraded.');
        $this->table(['Metric', 'Count'], [
            ['Templates scanned', $counts['templates_scanned']],
            ['Subjects eligible', $counts['subjects_eligible']],
            ['Bodies eligible', $counts['bodies_eligible']],
            ['Templates '.($dryRun ? 'that would change' : 'changed'), $counts['templates_changed']],
            ['Customized values preserved', $counts['customized_values_preserved']],
        ]);

        return self::SUCCESS;
    }
}
