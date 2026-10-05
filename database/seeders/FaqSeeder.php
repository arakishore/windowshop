<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use JsonException;

class FaqSeeder extends Seeder
{
    /**
     * Seed the default FAQ catalogue.
     *
     * Insert-missing-only using the stable natural identity
     * (category + question). Existing rows — including Admin-edited
     * answers, statuses and sort orders — are never updated, so this
     * seeder is safe to rerun via DatabaseSeeder.
     */
    public function run(): void
    {
        $entries = $this->catalogue();
        $created = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            if (! $this->valid($entry)) {
                $skipped++;

                continue;
            }

            $created += Faq::query()->firstOrCreate(
                [
                    'category' => $entry['category'],
                    'question' => $entry['question'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'answer' => $entry['answer'],
                    'sort_order' => (int) ($entry['sort_order'] ?? 0),
                    'status' => $entry['status'] ?? Faq::STATUS_ACTIVE,
                ],
            )->wasRecentlyCreated ? 1 : 0;
        }

        if ($skipped > 0 && $this->command) {
            $this->command->warn("FaqSeeder skipped {$skipped} malformed catalogue entries.");
        }

        if ($this->command) {
            $this->command->info("FaqSeeder created {$created} FAQs (".count($entries).' catalogue entries).');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalogue(): array
    {
        $path = base_path('docs/data/faq.json');

        try {
            $decoded = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new JsonException("FaqSeeder could not parse catalogue [{$path}]: {$exception->getMessage()}", 0, $exception);
        }

        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function valid(mixed $entry): bool
    {
        if (! is_array($entry)) {
            return false;
        }

        $category = $entry['category'] ?? null;
        $question = $entry['question'] ?? null;
        $answer = $entry['answer'] ?? null;
        $sortOrder = $entry['sort_order'] ?? 0;
        $status = $entry['status'] ?? Faq::STATUS_ACTIVE;

        if (! is_string($category) || ! in_array($category, array_keys(Faq::categories()), true)) {
            return false;
        }

        if (! is_string($question) || trim($question) === '' || mb_strlen($question) > 255) {
            return false;
        }

        if (! is_string($answer) || trim($answer) === '' || mb_strlen($answer) > 2000) {
            return false;
        }

        if (! is_int($sortOrder) && ! (is_string($sortOrder) && ctype_digit($sortOrder))) {
            return false;
        }

        if ((int) $sortOrder < 0) {
            return false;
        }

        if (! is_string($status) || ! in_array($status, [Faq::STATUS_ACTIVE, Faq::STATUS_INACTIVE], true)) {
            return false;
        }

        return true;
    }
}
