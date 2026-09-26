<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TransactionalNotificationMail;
use App\Services\Marketplace\MarketplaceLogoService;
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
        private readonly MarketplaceLogoService $marketplaceLogo,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.email', ['emailSettings' => $this->email->values()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request->boolean('enabled')));
        $this->email->save([
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
        ]);

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
                $this->marketplaceLogo->url(),
                $marketplaceName,
                $settings['from_email'],
                $settings['from_name'] ?: $marketplaceName,
                $settings['reply_to'] ?: null,
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
        ];
    }
}
