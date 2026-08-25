<?php

namespace App\Services\DateTime;

use App\Services\Admin\AdminSettingsService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;

class BusinessTimeService
{
    public function __construct(private readonly AdminSettingsService $settings)
    {
    }

    public function timezoneName(): string
    {
        $timezone = (string) $this->settings->get('regional', 'timezone', 'Asia/Kolkata');

        return in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
    }

    public function timezone(): DateTimeZone
    {
        return new DateTimeZone($this->timezoneName());
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dateRangeToUtc(?string $from, ?string $to): array
    {
        $from = trim((string) ($from ?? ''));
        $to = trim((string) ($to ?? ''));
        $timezone = $this->timezoneName();
        $start = $from !== ''
            ? CarbonImmutable::parse($from, $timezone)->startOfDay()
            : now($timezone)->toImmutable()->startOfDay();
        $end = $to !== ''
            ? CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()
            : $start->addDay();

        return [$start->setTimezone('UTC'), $end->setTimezone('UTC')];
    }

    public function toUtcFromLocalInput(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        return CarbonImmutable::parse($value, $this->timezoneName())->setTimezone('UTC');
    }

    public function toLocalDateTimeInput(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse((string) $value, 'UTC');

        return $date->setTimezone($this->timezone())->format('Y-m-d\TH:i');
    }
}
