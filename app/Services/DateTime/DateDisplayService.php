<?php

namespace App\Services\DateTime;

use App\Services\Admin\AdminSettingsService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

class DateDisplayService
{
    /**
     * @var array{timezone: string, date_format: string, time_format: string}|null
     */
    private ?array $regionalSettings = null;

    public function __construct(private readonly AdminSettingsService $settings)
    {
    }

    public function date(mixed $value, string $fallback = '—'): string
    {
        $date = $this->parseWithoutTimezoneShift($value);

        return $date ? $date->format($this->dateFormat()) : $fallback;
    }

    public function time(mixed $value, string $fallback = '—'): string
    {
        $date = $this->parseUtcTimestamp($value);

        return $date ? $date->setTimezone($this->timezone())->format($this->timeFormat()) : $fallback;
    }

    public function dateTime(mixed $value, string $fallback = '—'): string
    {
        $date = $this->parseUtcTimestamp($value);

        return $date ? $date->setTimezone($this->timezone())->format($this->dateFormat().' '.$this->timeFormat()) : $fallback;
    }

    private function parseWithoutTimezoneShift(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance($value);
            }

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
            }

            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseUtcTimestamp(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return CarbonImmutable::instance($value)->setTimezone('UTC');
            }

            return CarbonImmutable::parse((string) $value, 'UTC');
        } catch (Throwable) {
            return null;
        }
    }

    private function timezone(): DateTimeZone
    {
        $timezone = $this->regional()['timezone'];

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $timezone = 'UTC';
        }

        return new DateTimeZone($timezone);
    }

    private function dateFormat(): string
    {
        $format = $this->regional()['date_format'];

        return in_array($format, ['d-m-Y', 'd/m/Y', 'Y-m-d', 'd M Y'], true) ? $format : 'd-m-Y';
    }

    private function timeFormat(): string
    {
        $format = $this->regional()['time_format'];

        return in_array($format, ['h:i A', 'H:i'], true) ? $format : 'h:i A';
    }

    /**
     * @return array{timezone: string, date_format: string, time_format: string}
     */
    private function regional(): array
    {
        if ($this->regionalSettings !== null) {
            return $this->regionalSettings;
        }

        $settings = $this->settings->all('regional');

        return $this->regionalSettings = [
            'timezone' => (string) ($settings->get('timezone') ?: 'UTC'),
            'date_format' => (string) ($settings->get('date_format') ?: 'd-m-Y'),
            'time_format' => (string) ($settings->get('time_format') ?: 'h:i A'),
        ];
    }
}
