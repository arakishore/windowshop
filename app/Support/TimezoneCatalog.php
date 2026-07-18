<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class TimezoneCatalog
{
    /**
     * @return Collection<int, array{value: string, label: string}>
     */
    public function all(): Collection
    {
        $path = resource_path('data/timezones.json');

        if (! File::exists($path)) {
            return collect();
        }

        $items = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return collect($items)
            ->filter(fn (array $item): bool => isset($item['value'], $item['label']))
            ->map(fn (array $item): array => [
                'value' => (string) $item['value'],
                'label' => (string) $item['label'],
            ])
            ->values();
    }

    public function has(string $timezone): bool
    {
        return $this->all()->contains(fn (array $item): bool => $item['value'] === $timezone);
    }
}
