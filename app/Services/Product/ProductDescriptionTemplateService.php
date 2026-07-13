<?php

namespace App\Services\Product;

use App\Models\Product;
use App\Models\ProductDescriptionTemplate;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;

class ProductDescriptionTemplateService
{
    /**
     * @var array<int, string>
     */
    public const PLACEHOLDERS = [
        'product_name',
        'brand',
        'shop_name',
        'product_category',
        'category_path',
        'material',
        'pattern',
        'fit',
        'sleeve',
        'neck',
        'occasion',
        'colors',
        'sizes',
        'mrp',
        'selling_price',
        'category',
        'color',
    ];

    /**
     * @return array<int, string>
     */
    private const GENERATED_FIELDS = [
        'short_description',
        'description',
        'meta_title',
        'meta_description',
    ];

    /**
     * @param array<string, mixed> $values
     * @return array{found: bool, message: ?string, template: ?ProductDescriptionTemplate, short_description: ?string, description: ?string, meta_title: ?string, meta_description: ?string}
     */
    public function generateForCategory(ProductCategory|int|null $category, array $values): array
    {
        $categoryId = $category instanceof ProductCategory ? $category->getKey() : $category;

        if ($categoryId === null) {
            return $this->missingTemplateResult('Select a category before generating a description.');
        }

        $template = $this->activeTemplateForCategory((int) $categoryId, true);

        if (! $template instanceof ProductDescriptionTemplate) {
            return $this->missingTemplateResult('No active description template is available for the selected category.');
        }

        return $this->generateFromTemplate($template, $values);
    }

    /**
     * @param array<string, mixed> $values
     * @return array{found: bool, message: ?string, template: ProductDescriptionTemplate, short_description: ?string, description: ?string, meta_title: ?string, meta_description: ?string}
     */
    public function generateFromTemplate(ProductDescriptionTemplate $template, array $values): array
    {
        $normalizedValues = $this->normalizeValues($template, $values);
        $shortDescription = $this->renderTemplate($template->short_description_template, $normalizedValues);

        return [
            'found' => true,
            'message' => null,
            'template' => $template,
            'short_description' => $shortDescription,
            'description' => $this->renderTemplate($template->description_template, $normalizedValues),
            'meta_title' => $this->renderTemplate($template->meta_title_template ?: '{product_name} | {brand}', $normalizedValues),
            'meta_description' => $this->renderTemplate($template->meta_description_template ?: $shortDescription, $normalizedValues),
        ];
    }

    public function activeTemplateForCategory(int $categoryId, bool $includeAncestors = false): ?ProductDescriptionTemplate
    {
        $template = ProductDescriptionTemplate::query()
            ->where('product_category_id', $categoryId)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($template instanceof ProductDescriptionTemplate || ! $includeAncestors) {
            return $template;
        }

        $parentId = ProductCategory::query()
            ->whereKey($categoryId)
            ->value('parent_id');

        return $parentId ? $this->activeTemplateForCategory((int) $parentId, true) : null;
    }

    public function findTemplateForProduct(Product $product): ?ProductDescriptionTemplate
    {
        return $this->activeTemplateForCategory((int) $product->product_category_id, true);
    }

    /**
     * @return array<string, string>
     */
    public function buildPlaceholderValues(Product $product): array
    {
        $product->loadMissing(['brand', 'shop', 'category.parent']);
        $defaultVariant = $product->variants()
            ->where('is_default', true)
            ->first() ?? $product->variants()->orderBy('sort_order')->first();

        $categoryPath = $product->category ? $this->categoryPath($product->category) : '';

        return [
            'product_name' => (string) $product->product_name,
            'brand' => (string) ($product->brand?->name ?? ''),
            'shop_name' => (string) ($product->shop?->name ?? ''),
            'product_category' => (string) ($product->category?->name ?? ''),
            'category_path' => $categoryPath,
            'category' => (string) ($product->category?->name ?? ''),
            'material' => '',
            'pattern' => '',
            'fit' => '',
            'sleeve' => '',
            'neck' => '',
            'occasion' => '',
            'colors' => '',
            'color' => '',
            'sizes' => '',
            'mrp' => $defaultVariant?->mrp ? number_format((float) $defaultVariant->mrp, 2, '.', '') : '',
            'selling_price' => $defaultVariant?->selling_price ? number_format((float) $defaultVariant->selling_price, 2, '.', '') : '',
        ];
    }

    /**
     * @return array{found: bool, message: ?string, template: ?ProductDescriptionTemplate, short_description: ?string, description: ?string, meta_title: ?string, meta_description: ?string}
     */
    public function generateForProduct(Product $product): array
    {
        $template = $this->findTemplateForProduct($product);

        if (! $template instanceof ProductDescriptionTemplate) {
            return $this->missingTemplateResult('No active description template is available for the selected product category.');
        }

        return $this->generateFromTemplate($template, $this->buildPlaceholderValues($product));
    }

    public function applyToProduct(Product $product, bool $overwrite = false): Product
    {
        $generated = $this->generateForProduct($product);

        if (! $generated['found']) {
            return $product;
        }

        $updates = [];

        foreach (self::GENERATED_FIELDS as $field) {
            if ($overwrite || blank($product->{$field})) {
                $updates[$field] = $generated[$field];
            }
        }

        if ($updates !== []) {
            $product->forceFill($updates)->save();
        }

        return $product->refresh();
    }

    /**
     * @return array<string, string>
     */
    public function sampleValues(?ProductCategory $category = null): array
    {
        return [
            'product_name' => 'Cotton Kurti - Red',
            'category' => $category?->name ?? 'Apparel',
            'product_category' => $category?->name ?? 'Apparel',
            'category_path' => $category ? $this->categoryPath($category) : 'Apparel',
            'brand' => 'WindowShop',
            'color' => 'Red',
            'colors' => 'Red, Blue',
            'material' => 'Cotton',
            'pattern' => 'Solid',
            'fit' => 'Regular Fit',
            'sleeve' => 'Three-quarter Sleeve',
            'neck' => 'Round Neck',
            'occasion' => 'Everyday Wear',
            'sizes' => 'M, L, XL',
            'shop_name' => 'WindowShop Store',
            'mrp' => '1299.00',
            'selling_price' => '999.00',
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
    public function renderTemplate(string $template, array $values): string
    {
        $lines = preg_split('/\R/', $template) ?: [$template];
        $renderedLines = [];

        foreach ($lines as $line) {
            if ($this->lineShouldBeRemoved($line, $values)) {
                continue;
            }

            $renderedLines[] = $this->replacePlaceholders($line, $values);
        }

        return $this->cleanText(implode("\n", $renderedLines));
    }

    private function cleanText(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+([,.;:!?])/', '$1', $text) ?? $text;
        $text = preg_replace('/([,;:])\s*([,.;:!?])/', '$2', $text) ?? $text;
        $text = preg_replace('/\s+\b(by|from|at|with|in)\./i', '.', $text) ?? $text;
        $text = preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $text) ?? $text;
        $text = preg_replace('/^\s*[-*]\s*$/m', '', $text) ?? $text;
        $text = preg_replace('/\(\s*\)/', '', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array{found: bool, message: string, template: null, short_description: null, description: null, meta_title: null, meta_description: null}
     */
    private function missingTemplateResult(string $message): array
    {
        return [
            'found' => false,
            'message' => $message,
            'template' => null,
            'short_description' => null,
            'description' => null,
            'meta_title' => null,
            'meta_description' => null,
        ];
    }

    /**
     * @param array<string, string> $values
     */
    private function lineShouldBeRemoved(string $line, array $values): bool
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $line, $matches);
        $isBullet = (bool) preg_match('/^\s*[-*]\s*/', $line);

        foreach ($matches[1] ?? [] as $placeholder) {
            if (! array_key_exists($placeholder, $values)) {
                return true;
            }

            if ($isBullet && trim((string) $values[$placeholder]) === '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $values
     */
    private function replacePlaceholders(string $line, array $values): string
    {
        $replacements = [];

        foreach ($values as $placeholder => $value) {
            $replacements['{'.$placeholder.'}'] = $value;
        }

        return strtr($line, $replacements);
    }

    private function categoryPath(ProductCategory $category): string
    {
        $category->loadMissing('parent');
        $names = [];
        $visited = [];
        $current = $category;

        while ($current && ! in_array($current->getKey(), $visited, true)) {
            $visited[] = $current->getKey();
            array_unshift($names, $current->name);
            $current = $current->parent;

            if ($current) {
                $current->loadMissing('parent');
            }
        }

        return implode(' > ', $names);
    }
}
