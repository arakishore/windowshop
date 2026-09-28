<?php

namespace App\Notifications\Channels;

use App\Mail\TransactionalNotificationMail;
use App\Models\Order;
use App\Models\Shop;
use App\Notifications\Contracts\NotificationChannel;
use App\Notifications\DeliveryResult;
use App\Notifications\NotificationChannelName;
use App\Notifications\NotificationMessage;
use App\Notifications\ProviderMode;
use App\Services\Notification\EmailConfigurationService;
use App\Services\Notification\OrderEmailPresenter;
use App\Services\Notification\NotificationEventCatalogue;
use App\Services\Notification\NotificationTemplateRenderer;
use App\Services\Notification\NotificationTemplateService;
use App\Services\System\SystemSettingService;
use Throwable;

class EmailChannel implements NotificationChannel
{
    public function __construct(
        private readonly EmailConfigurationService $configuration,
        private readonly NotificationTemplateService $templates,
        private readonly NotificationTemplateRenderer $renderer,
        private readonly NotificationEventCatalogue $catalogue,
        private readonly SystemSettingService $systemSettings,
        private readonly OrderEmailPresenter $orderEmails,
    ) {}

    public function name(): string
    {
        return NotificationChannelName::EMAIL;
    }

    public function send(NotificationMessage $message): DeliveryResult
    {
        if (! $this->configuration->enabled()) {
            return DeliveryResult::skipped(ProviderMode::WINDOWSHOP);
        }

        if (! $this->configuration->configured() || ! filter_var($message->destination, FILTER_VALIDATE_EMAIL)) {
            return DeliveryResult::notConfigured(ProviderMode::WINDOWSHOP);
        }

        $definition = $this->catalogue->find($message->key);
        $template = $this->templates->findForDelivery($message->key, $message->channel);
        if ($definition === null || $template === null) {
            return $definition?->mandatory($message->channel)
                ? DeliveryResult::notConfigured(ProviderMode::WINDOWSHOP)
                : DeliveryResult::skipped(ProviderMode::WINDOWSHOP);
        }

        $rendered = $this->renderer->render($template, $message->context);
        $settings = $this->configuration->values();
        $marketplaceName = $this->systemSettings->marketplaceName();
        $shop = $definition->branding === 'shop' && $message->shopId
            ? Shop::query()->find($message->shopId)
            : null;
        $brandName = $shop?->name ?: $marketplaceName;
        $brandLogo = $shop?->logo_path ? asset('storage/'.$shop->logo_path) : $this->configuration->emailLogoUrl();
        $to = mb_strtolower((string) $message->destination);
        $additionalTo = $this->recipients(data_get($message->metadata, 'email_routing.additional_to', []), [$to]);
        $toRecipients = [$to, ...$additionalTo];
        $cc = $this->recipients([
            ...data_get($template->metadata, 'email.cc', []),
            ...data_get($message->metadata, 'email_routing.cc', []),
        ], $toRecipients);
        $bcc = $this->recipients(data_get($template->metadata, 'email.bcc', []), [$to, ...$cc]);
        $bcc = $this->recipients([
            ...$bcc,
            ...data_get($message->metadata, 'email_routing.bcc', []),
        ], [...$toRecipients, ...$cc]);
        $orderEmail = null;
        if (in_array($message->key, ['order.placed.customer', 'order.new.merchant', 'order.new.admin'], true) && $message->relatedType === 'order' && $message->relatedId) {
            $order = Order::query()->where('uuid', $message->relatedId)->first();
            $orderEmail = $order ? $this->orderEmails->present($order, $message->key, $message->context) : null;
            if ($orderEmail !== null) {
                $rendered['subject'] = $orderEmail['subject'];
            }
        }

        try {
            $this->configuration->send(new TransactionalNotificationMail(
                (string) ($rendered['subject'] ?: $definition->label),
                $rendered['body'],
                $brandName,
                $brandLogo,
                $marketplaceName,
                $settings['from_email'],
                $settings['from_name'] ?: $marketplaceName,
                $settings['reply_to'] ?: null,
                $cc,
                $bcc,
                $this->configuration->presentation(),
                $orderEmail,
            ), $toRecipients);

            return DeliveryResult::sent(ProviderMode::WINDOWSHOP, 'laravel-mail');
        } catch (Throwable $exception) {
            return DeliveryResult::failed($this->configuration->sanitizedError($exception), ProviderMode::WINDOWSHOP, 'laravel-mail');
        }
    }

    /** @param array<int, mixed> $recipients */
    private function recipients(array $recipients, array $excluded): array
    {
        return collect($recipients)
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) && ! in_array($email, $excluded, true))
            ->unique()
            ->values()
            ->all();
    }
}
