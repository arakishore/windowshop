<?php

namespace App\Notifications;

final readonly class NotificationEventDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $audience,
        public string $category,
        public string $description,
        public array $channels,
        public string $templateKey,
        public array $variables,
        public string $contactSource,
        public string $branding,
        public string $preferenceScope,
        public array $policy = [],
    ) {}

    public function supports(string $channel): bool
    {
        return array_key_exists($channel, $this->channels);
    }

    public function defaultEnabled(string $channel): bool
    {
        return (bool) ($this->channels[$channel]['default_enabled'] ?? false);
    }

    public function mandatory(string $channel): bool
    {
        return (bool) ($this->channels[$channel]['mandatory'] ?? false);
    }
}
