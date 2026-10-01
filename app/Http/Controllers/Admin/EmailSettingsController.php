<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TransactionalNotificationMail;
use App\Services\Notification\EmailConfigurationService;
use App\Services\System\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class EmailSettingsController extends Controller
{
    public function __construct(
        private readonly EmailConfigurationService $email,
        private readonly SystemSettingService $systemSettings,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.email', [
            'emailSettings' => $this->email->values(),
            'emailPresentation' => $this->email->presentation(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request->boolean('enabled')));
        $settings = [
            'enabled' => $request->boolean('enabled'),
            'transport' => 'smtp',
            'smtp.host' => data_get($data, 'smtp.host'),
            'smtp.port' => data_get($data, 'smtp.port'),
            'smtp.encryption' => data_get($data, 'smtp.encryption'),
            'smtp.username' => data_get($data, 'smtp.username'),
            'smtp.password' => data_get($data, 'smtp.password'),
            'from_name' => $data['from_name'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            'reply_to' => $data['reply_to'] ?? null,
            'admin_notification_email' => $data['admin_notification_email'] ?? null,
        ];

        if ($request->hasAny(['branding', 'footer'])) {
            $settings = array_merge($settings, [
                'branding.show_name' => $request->boolean('branding.show_name'),
                'footer.show' => $request->boolean('footer.show'),
                'footer.benefit_1' => data_get($data, 'footer.benefit_1'),
                'footer.benefit_2' => data_get($data, 'footer.benefit_2'),
                'footer.benefit_3' => data_get($data, 'footer.benefit_3'),
                'footer.social.facebook' => data_get($data, 'footer.social.facebook'),
                'footer.social.instagram' => data_get($data, 'footer.social.instagram'),
                'footer.social.youtube' => data_get($data, 'footer.social.youtube'),
                'footer.social.twitter' => data_get($data, 'footer.social.twitter'),
                'footer.social.linkedin' => data_get($data, 'footer.social.linkedin'),
                'footer.apps.google_play' => data_get($data, 'footer.apps.google_play'),
                'footer.apps.app_store' => data_get($data, 'footer.apps.app_store'),
                'footer.show_powered_by' => $request->boolean('footer.show_powered_by'),
                'footer.powered_by_text' => data_get($data, 'footer.powered_by_text'),
            ]);
        }

        $this->email->save($settings);

        if ($request->boolean('remove_email_logo')) {
            $this->email->removeLogo();
        } elseif ($request->hasFile('email_logo')) {
            $this->email->replaceLogo($request->file('email_logo'));
        }

        return back()->with('success', 'Email notification settings saved.');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['test_recipient' => ['required', 'email:rfc', 'max:255']]);

        if (! $this->email->enabled() || ! $this->email->configured()) {
            return back()->withErrors(['test_recipient' => 'Save a complete, enabled email configuration before sending a test email.']);
        }

        $settings = $this->email->values();
        $marketplaceName = $this->systemSettings->marketplaceName();

        try {
            $this->email->send(new TransactionalNotificationMail(
                'Email configuration test successful',
                "This test confirms that {$marketplaceName} can send transactional email using the saved SMTP configuration.",
                $marketplaceName,
                $this->email->emailLogoUrl(),
                $marketplaceName,
                $settings['from_email'],
                $settings['from_name'] ?: $marketplaceName,
                $settings['reply_to'] ?: null,
                emailPresentation: $this->email->presentation(),
            ), $data['test_recipient']);
        } catch (Throwable $exception) {
            return back()->withErrors(['test_recipient' => $this->email->sanitizedError($exception)]);
        }

        return back()->with('success', 'Test email sent successfully.');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(bool $enabled): array
    {
        return [
            'enabled' => ['nullable', 'boolean'],
            'smtp.host' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:255'],
            'smtp.port' => [Rule::requiredIf($enabled), 'nullable', 'integer', 'between:1,65535'],
            'smtp.encryption' => ['required', Rule::in(['none', 'tls', 'ssl'])],
            'smtp.username' => ['nullable', 'string', 'max:255'],
            'smtp.password' => ['nullable', 'string', 'max:1000'],
            'from_name' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:255'],
            'from_email' => [Rule::requiredIf($enabled), 'nullable', 'email:rfc', 'max:255'],
            'reply_to' => ['nullable', 'email:rfc', 'max:255'],
            'admin_notification_email' => ['nullable', 'email:rfc', 'max:255'],
            'email_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'remove_email_logo' => ['nullable', 'boolean'],
            'branding.show_name' => ['nullable', 'boolean'],
            'footer.show' => ['nullable', 'boolean'],
            'footer.benefit_1' => ['nullable', 'string', 'max:80'],
            'footer.benefit_2' => ['nullable', 'string', 'max:80'],
            'footer.benefit_3' => ['nullable', 'string', 'max:80'],
            'footer.social.facebook' => ['nullable', 'url:https', 'max:2048'],
            'footer.social.instagram' => ['nullable', 'url:https', 'max:2048'],
            'footer.social.youtube' => ['nullable', 'url:https', 'max:2048'],
            'footer.social.twitter' => ['nullable', 'url:https', 'max:2048'],
            'footer.social.linkedin' => ['nullable', 'url:https', 'max:2048'],
            'footer.apps.google_play' => ['nullable', 'url:https', 'max:2048'],
            'footer.apps.app_store' => ['nullable', 'url:https', 'max:2048'],
            'footer.show_powered_by' => ['nullable', 'boolean'],
            'footer.powered_by_text' => ['nullable', 'string', 'max:255', 'regex:/^(?:(?!{{)(?:.|\n)|{{\s*marketplace_name\s*}})*$/'],
        ];
    }
}
