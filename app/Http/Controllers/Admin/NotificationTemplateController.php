<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationEventDefinition;
use App\Notifications\NotificationMessage;
use App\Services\Notification\EmailConfigurationService;
use App\Services\Notification\NotificationEventCatalogue;
use App\Services\Notification\NotificationPreferenceResolver;
use App\Services\Notification\NotificationTemplateRenderer;
use App\Services\System\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NotificationTemplateController extends Controller
{
    public function __construct(
        private readonly NotificationEventCatalogue $catalogue,
        private readonly NotificationTemplateRenderer $renderer,
        private readonly NotificationPreferenceResolver $preferences,
        private readonly SystemSettingService $systemSettings,
        private readonly EmailConfigurationService $emailConfiguration,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'recipient' => ['nullable', Rule::in(['customer', 'merchant', 'admin'])],
            'channel' => ['nullable', Rule::in(NotificationChannelName::all())],
            'rule' => ['nullable', Rule::in(['mandatory', 'configurable', 'optional'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $selectedChannel = $filters['channel'] ?? NotificationChannelName::EMAIL;
        $filters['channel'] = $selectedChannel;

        $templates = NotificationTemplate::query()->orderBy('event_key')->orderBy('channel')->get();
        $rows = $templates->map(function (NotificationTemplate $template) {
            $event = $this->catalogue->find($template->event_key);

            return $event ? ['template' => $template, 'event' => $event, 'rule' => $this->rule($event, $template->channel)] : null;
        })->filter()->filter(function (array $row) use ($filters): bool {
            $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
            if ($search !== '' && ! str_contains(mb_strtolower($row['event']->label.' '.$row['event']->key), $search)) {
                return false;
            }

            return $row['template']->channel === $filters['channel']
                && (! isset($filters['recipient']) || $row['event']->audience === $filters['recipient'])
                && (! isset($filters['rule']) || $row['rule'] === $filters['rule'])
                && (! isset($filters['status']) || $row['template']->is_active === ($filters['status'] === 'active'));
        })->values();

        return view('admin.notification-templates.index', compact('rows', 'filters', 'selectedChannel'));
    }

    public function edit(NotificationTemplate $notificationTemplate): View
    {
        $event = $this->eventFor($notificationTemplate);

        return view('admin.notification-templates.edit', [
            'template' => $notificationTemplate,
            'event' => $event,
            'rule' => $this->rule($event, $notificationTemplate->channel),
            'emailCc' => implode(', ', data_get($notificationTemplate->metadata, 'email.cc', [])),
            'emailBcc' => implode(', ', data_get($notificationTemplate->metadata, 'email.bcc', [])),
        ]);
    }

    public function update(Request $request, NotificationTemplate $notificationTemplate): RedirectResponse
    {
        $event = $this->eventFor($notificationTemplate);
        $data = $this->validatedTemplate($request, $notificationTemplate, $event->variables);
        $metadata = $notificationTemplate->metadata ?? [];
        $metadata['email'] = [
            'cc' => $this->emails((string) ($data['email_cc'] ?? ''), 'email_cc'),
            'bcc' => $this->emails((string) ($data['email_bcc'] ?? ''), 'email_bcc'),
        ];

        $notificationTemplate->update([
            'subject' => $notificationTemplate->channel === NotificationChannelName::EMAIL ? $data['subject'] : null,
            'body' => $data['body'],
            'is_active' => $event->mandatory($notificationTemplate->channel) ? true : $request->boolean('is_active'),
            'metadata' => $metadata,
        ]);

        return redirect()->route('admin.notification-templates.edit', $notificationTemplate)->with('success', 'Notification template updated.');
    }

    public function preview(Request $request, NotificationTemplate $notificationTemplate): View
    {
        abort_unless($notificationTemplate->channel === NotificationChannelName::EMAIL, 404);
        $event = $this->eventFor($notificationTemplate);
        $data = $this->validatedTemplate($request, $notificationTemplate, $event->variables);
        $previewTemplate = $notificationTemplate->replicate()->forceFill(['subject' => $data['subject'], 'body' => $data['body']]);
        $rendered = $this->renderer->render($previewTemplate, $this->sampleData($event->variables));
        $marketplaceName = $this->systemSettings->marketplaceName();
        $brandName = $event->branding === 'shop' ? 'Sample Shop' : $marketplaceName;
        $html = view('emails.transactional-notification', [
            'notificationSubject' => $rendered['subject'] ?: $event->label,
            'notificationBody' => $rendered['body'],
            'brandName' => $brandName,
            'brandLogoUrl' => $event->branding === 'marketplace' ? $this->emailConfiguration->emailLogoUrl() : null,
            'marketplaceName' => $marketplaceName,
            'emailPresentation' => $this->emailConfiguration->presentation(),
        ])->render();

        return view('admin.notification-templates.preview', compact('html', 'rendered', 'notificationTemplate'));
    }

    public function rules(): View
    {
        $events = $this->catalogue->all();
        $globalEmailEnabled = $events->filter(fn ($event) => $event->preferenceScope === 'global' && $event->supports(NotificationChannelName::EMAIL))
            ->mapWithKeys(fn ($event) => [$event->key => $this->preferences->enabled(new NotificationMessage($event->key, $event->audience, NotificationChannelName::EMAIL))]);

        return view('admin.notification-templates.rules', compact('events', 'globalEmailEnabled'));
    }

    public function updateGlobalRule(Request $request): RedirectResponse
    {
        $data = $request->validate(['event_key' => ['required', 'string'], 'enabled' => ['required', 'boolean']]);
        $event = $this->catalogue->find($data['event_key']);
        abort_unless($event?->preferenceScope === 'global' && $event->supports(NotificationChannelName::EMAIL), 404);
        $this->preferences->setGlobal($event->key, NotificationChannelName::EMAIL, (bool) $data['enabled']);

        return back()->with('success', 'Global notification rule updated.');
    }

    private function validatedTemplate(Request $request, NotificationTemplate $template, array $allowedVariables): array
    {
        $data = $request->validate([
            'subject' => [$template->channel === NotificationChannelName::EMAIL ? 'required' : 'nullable', 'nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
            'email_cc' => ['nullable', 'string', 'max:2000'],
            'email_bcc' => ['nullable', 'string', 'max:2000'],
        ]);
        $unknown = collect([$data['subject'] ?? '', $data['body']])->flatMap(function (string $value): array {
            preg_match_all('/{{\s*([A-Za-z0-9_]+)\s*}}/', $value, $matches);

            return $matches[1];
        })->unique()->diff($allowedVariables)->values();
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages(['body' => 'Unknown template variable(s): '.$unknown->map(fn ($name) => '{{ '.$name.' }}')->implode(', ')]);
        }

        return $data;
    }

    /** @return array<int, string> */
    private function emails(string $value, string $field): array
    {
        $emails = collect(preg_split('/[,;\r\n]+/', $value) ?: [])->map(fn ($email) => mb_strtolower(trim($email)))->filter()->unique()->values();
        $invalid = $emails->reject(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));
        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([$field => 'Every address must be a valid email address.']);
        }

        return $emails->all();
    }

    private function eventFor(NotificationTemplate $template): NotificationEventDefinition
    {
        return $this->catalogue->find($template->event_key) ?? abort(404);
    }

    private function rule(NotificationEventDefinition $event, string $channel): string
    {
        return $event->mandatory($channel) ? 'mandatory' : ($event->preferenceScope === 'global' ? 'optional' : 'configurable');
    }

    /** @return array<string, string> */
    private function sampleData(array $variables): array
    {
        $samples = ['customer_name' => 'Sample Customer', 'merchant_name' => 'Sample Merchant', 'shop_name' => 'Sample Shop', 'order_number' => 'WS-10001', 'marketplace_name' => $this->systemSettings->marketplaceName(), 'message' => 'This is a sample notification message.'];

        return collect($variables)->mapWithKeys(fn ($variable) => [$variable => $samples[$variable] ?? 'Sample Value'])->all();
    }
}
