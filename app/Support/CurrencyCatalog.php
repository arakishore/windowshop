<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class CurrencyCatalog
{
    /**
     * @return Collection<int, array{
     *     code: string,
     *     name: string,
     *     label: string,
     *     symbol: string,
     *     symbol_first: bool,
     *     decimals: int,
     *     thousands_separator: string,
     *     decimal_separator: string
     * }>
     */
    public function all(): Collection
    {
        $path = resource_path('data/currencies.json');

        if (! File::exists($path)) {
            return collect();
        }

        $items = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return collect($items)
            ->filter(fn (array $item): bool => isset(
                $item['code'],
                $item['name'],
                $item['label'],
                $item['symbol'],
                $item['symbol_first'],
                $item['decimals'],
                $item['thousands_separator'],
                $item['decimal_separator'],
            ))
            ->map(fn (array $item): array => [
                'code' => (string) $item['code'],
                'name' => (string) $item['name'],
                'label' => (string) $item['label'],
                'symbol' => (string) $item['symbol'],
                'symbol_first' => (bool) $item['symbol_first'],
                'decimals' => (int) $item['decimals'],
                'thousands_separator' => (string) $item['thousands_separator'],
                'decimal_separator' => (string) $item['decimal_separator'],
            ])
            ->values();
    }

    public function find(string $code): ?array
    {
        $code = strtoupper($code);

        return $this->all()->first(fn (array $item): bool => $item['code'] === $code);
    }

    public function has(string $code): bool
    {
        return $this->find($code) !== null;
    }
}
