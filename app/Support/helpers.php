<?php

use App\Services\DateTime\DateDisplayService;
use App\Services\DateTime\BusinessTimeService;

if (! function_exists('app_date')) {
    function app_date(mixed $value, string $fallback = '—'): string
    {
        return app(DateDisplayService::class)->date($value, $fallback);
    }
}

if (! function_exists('app_time')) {
    function app_time(mixed $value, string $fallback = '—'): string
    {
        return app(DateDisplayService::class)->time($value, $fallback);
    }
}

if (! function_exists('app_datetime')) {
    function app_datetime(mixed $value, string $fallback = '—'): string
    {
        return app(DateDisplayService::class)->dateTime($value, $fallback);
    }
}

if (! function_exists('app_datetime_local')) {
    function app_datetime_local(mixed $value, string $fallback = ''): string
    {
        return app(BusinessTimeService::class)->toLocalDateTimeInput($value) ?? $fallback;
    }
}
