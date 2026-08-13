<?php

namespace App\Console\Commands;

use App\Models\PostalCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportPostalCodes extends Command
{
    protected $signature = 'postal-codes:import
        {path=docs/data/india-pincodes.csv : CSV path relative to the project root or an absolute path}
        {--chunk=1000 : Number of rows inserted per database batch}';

    protected $description = 'Import postal code master data from the government PIN-code CSV.';

    public function handle(): int
    {
        $path = $this->argument('path');
        $csvPath = File::isFile($path) ? $path : base_path($path);

        if (! File::isFile($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");

            return self::FAILURE;
        }

        $chunkSize = max(100, (int) $this->option('chunk'));
        $handle = fopen($csvPath, 'rb');

        if ($handle === false) {
            $this->error("Unable to open CSV file: {$csvPath}");

            return self::FAILURE;
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers)) {
            fclose($handle);
            $this->error('CSV file is empty.');

            return self::FAILURE;
        }

        $headers = array_map(fn (string $header): string => strtolower(trim($header)), $headers);
        $expectedHeaders = ['circlename', 'regionname', 'divisionname', 'officename', 'pincode', 'officetype', 'delivery', 'district', 'statename', 'latitude', 'longitude'];
        $missingHeaders = array_diff($expectedHeaders, $headers);

        if ($missingHeaders !== []) {
            fclose($handle);
            $this->error('CSV is missing required header(s): '.implode(', ', $missingHeaders));

            return self::FAILURE;
        }

        $processed = 0;
        $inserted = 0;
        $skipped = 0;
        $failed = 0;
        $batch = [];
        $batchKeys = [];

        while (($row = fgetcsv($handle)) !== false) {
            $processed++;

            try {
                $record = $this->recordFromRow($headers, $row);
            } catch (Throwable) {
                $failed++;
                continue;
            }

            if ($record === null) {
                $failed++;
                continue;
            }

            if (isset($batchKeys[$record['source_key']])) {
                $skipped++;
                continue;
            }

            $batchKeys[$record['source_key']] = true;
            $batch[] = $record;

            if (count($batch) >= $chunkSize) {
                [$batchInserted, $batchSkipped] = $this->insertBatch($batch);
                $inserted += $batchInserted;
                $skipped += $batchSkipped;
                $batch = [];
                $batchKeys = [];
            }
        }

        fclose($handle);

        if ($batch !== []) {
            [$batchInserted, $batchSkipped] = $this->insertBatch($batch);
            $inserted += $batchInserted;
            $skipped += $batchSkipped;
        }

        $this->info("Total rows processed: {$processed}");
        $this->info("Inserted rows: {$inserted}");
        $this->info("Skipped/duplicate rows: {$skipped}");
        $this->info("Failed rows: {$failed}");

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string|null> $row
     * @return array<string, mixed>|null
     */
    private function recordFromRow(array $headers, array $row): ?array
    {
        $data = [];

        foreach ($headers as $index => $header) {
            $data[$header] = $this->clean($row[$index] ?? null);
        }

        $postalCode = $data['pincode'] ?? null;
        $officeName = $data['officename'] ?? null;

        if ($postalCode === null || $officeName === null) {
            return null;
        }

        $now = now();
        $deliveryStatus = $data['delivery'] ?? null;

        return [
            'source_key' => $this->sourceKey($data),
            'circle_name' => $data['circlename'] ?? null,
            'region_name' => $data['regionname'] ?? null,
            'division_name' => $data['divisionname'] ?? null,
            'office_name' => $officeName,
            'postal_code' => $postalCode,
            'office_type' => $data['officetype'] ?? null,
            'delivery_status' => $deliveryStatus,
            'shipping_enabled' => strcasecmp((string) $deliveryStatus, 'Delivery') === 0,
            'district' => $data['district'] ?? null,
            'state' => $data['statename'] ?? null,
            'latitude' => $this->decimalOrNull($data['latitude'] ?? null, -90, 90),
            'longitude' => $this->decimalOrNull($data['longitude'] ?? null, -180, 180),
            'status' => PostalCode::STATUS_ACTIVE,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $batch
     * @return array{0: int, 1: int}
     */
    private function insertBatch(array $batch): array
    {
        return DB::transaction(function () use ($batch): array {
            $keys = array_column($batch, 'source_key');
            $existing = PostalCode::withTrashed()
                ->whereIn('source_key', $keys)
                ->pluck('source_key')
                ->all();
            $existingLookup = array_fill_keys($existing, true);
            $newRows = array_values(array_filter(
                $batch,
                fn (array $row): bool => ! isset($existingLookup[$row['source_key']]),
            ));

            if ($newRows !== []) {
                PostalCode::query()->insert($newRows);
            }

            return [count($newRows), count($batch) - count($newRows)];
        });
    }

    /**
     * @param array<string, string|null> $data
     */
    private function sourceKey(array $data): string
    {
        return sha1(implode('|', [
            strtolower((string) ($data['pincode'] ?? '')),
            strtolower((string) ($data['officename'] ?? '')),
            strtolower((string) ($data['officetype'] ?? '')),
            strtolower((string) ($data['district'] ?? '')),
            strtolower((string) ($data['statename'] ?? '')),
        ]));
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function decimalOrNull(?string $value, float $minimum, float $maximum): ?string
    {
        if ($value === null || strtoupper($value) === 'NA' || ! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if ($number < $minimum || $number > $maximum) {
            return null;
        }

        return number_format($number, 7, '.', '');
    }
}
