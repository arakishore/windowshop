<?php

namespace App\Services\Product;

use App\Models\ProductDescriptionTemplate;
use App\Models\ProductCategory;

class ProductDescriptionTemplateService
{
    /**
     * @var array<int, string>
     */
    public const PLACEHOLDERS = [
        'product_name',
        'category',
        'brand',
        'color',
        'material',
        'sizes',
        'shop_name',
    ];

    /**
     * @param array<string, mixed> $values
     * @return array{found: bool, message: ?string, short_description: ?string, description: ?string}
     */
    public function generateForCategory(ProductCategory|int|null $category, array $values): array
    {
        $categoryId = $category instanceof ProductCategory ? $category->getKey() : $category;

        if ($categoryId === null) {
            return $this->missingTemplateResult('Select a category before generating a description.');
        }

        $template = $this->activeTemplateForCategory((int) $categoryId);

        if (! $template instanceof ProductDescriptionTemplate) {
            return $this->missingTemplateResult('No active description template is available for the selected category.');
        }

        return $this->generateFromTemplate($template, $values);
    }

    /**
     * @param array<string, mixed> $values
     * @return array{found: bool, message: ?string, short_description: ?string, description: ?string}
     */
    public function generateFromTemplate(ProductDescriptionTemplate $template, array $values): array
    {
        $normalizedValues = $this->normalizeValues($template, $values);

        return [
            'found' => true,
            'message' => null,
            'short_description' => $this->render($template->short_description_template, $normalizedValues),
            'description' => $this->render($template->description_template, $normalizedValues),
        ];
    }

    public function activeTemplateForCategory(int $categoryId): ?ProductDescriptionTemplate
    {
        return ProductDescriptionTemplate::query()
            ->where('product_category_id', $categoryId)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    public function sampleValues(?ProductCategory $category = null): array
    {
        return [
            'product_name' => 'Cotton Kurti - Red',
            'category' => $category?->name ?? 'Apparel',
            'brand' => 'WindowShop',
            'color' => 'Red',
            'material' => 'Cotton',
            'sizes' => 'M, L, XL',
            'shop_name' => 'WindowShop Store',
        ];
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function normalizeValues(ProductDescriptionTemplate $template, array $values): array
    {
        $categoryName = $template->category?->name;
        $normalized = [];

        foreach (self::PLACEHOLDERS as $placeholder) {
            $value = $values[$placeholder] ?? null;

            if ($placeholder === 'category' && ($value === null || trim((string) $value) === '')) {
                $value = $categoryName;
            }

            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map(
                    fn (mixed $item): string => trim((string) $item),
                    $value,
                )));
            }

            $normalized[$placeholder] = trim((string) ($value ?? ''));
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $values
     */
    private function render(string $template, array $values): string
    {
        $replacements = [];

        foreach (self::PLACEHOLDERS as $placeholder) {
            $replacements['{'.$placeholder.'}'] = $values[$placeholder] ?? '';
        }

        return $this->cleanText(strtr($template, $replacements));
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+([,.;:!?])/', '$1', $text) ?? $text;
        $text = preg_replace('/([,;:])\s*([,.;:!?])/', '$2', $text) ?? $text;
        $text = preg_replace('/\s+\b(by|from|at|with|in)\./i', '.', $text) ?? $text;
        $text = preg_replace('/\(\s*\)/', '', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array{found: bool, message: string, short_description: null, description: null}
     */
    private function missingTemplateResult(string $message): array
    {
        return [
            'found' => false,
            'message' => $message,
            'short_description' => null,
            'description' => null,
        ];
    }
}
