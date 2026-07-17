<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Product\Code128BarcodeSvgService;
use App\Services\Product\ProductBarcodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BarcodeLabelController extends Controller
{
    private const TEMPLATES = [
        'a4_30' => ['group' => 'A4 sheets (laser / inkjet)', 'label' => '30 per sheet - 63.5 x 25.4 mm', 'columns' => 3, 'width' => '63.5mm', 'height' => '25.4mm', 'page' => 'a4'],
        'a4_40' => ['group' => 'A4 sheets (laser / inkjet)', 'label' => '40 per sheet - 45.7 x 25.4 mm', 'columns' => 4, 'width' => '45.7mm', 'height' => '25.4mm', 'page' => 'a4'],
        'a4_24' => ['group' => 'A4 sheets (laser / inkjet)', 'label' => '24 per sheet - 63.5 x 33.9 mm', 'columns' => 3, 'width' => '63.5mm', 'height' => '33.9mm', 'page' => 'a4'],
        'roll_30x20' => ['group' => 'Label rolls (thermal)', 'label' => 'Roll - 30 x 20 mm', 'columns' => 1, 'width' => '30mm', 'height' => '20mm', 'page' => 'thermal'],
        'roll_38x25' => ['group' => 'Label rolls (thermal)', 'label' => 'Roll - 38 x 25 mm', 'columns' => 1, 'width' => '38mm', 'height' => '25mm', 'page' => 'thermal'],
        'roll_40x30' => ['group' => 'Label rolls (thermal)', 'label' => 'Roll - 40 x 30 mm', 'columns' => 1, 'width' => '40mm', 'height' => '30mm', 'page' => 'thermal'],
        'roll_50x25' => ['group' => 'Label rolls (thermal)', 'label' => 'Roll - 50 x 25 mm', 'columns' => 1, 'width' => '50mm', 'height' => '25mm', 'page' => 'thermal'],
        'roll_58x40' => ['group' => 'Label rolls (thermal)', 'label' => 'Roll - 58 x 40 mm', 'columns' => 1, 'width' => '58mm', 'height' => '40mm', 'page' => 'thermal'],
        'roll_100x50' => ['group' => 'Label rolls (thermal)', 'label' => 'Roll - 100 x 50 mm', 'columns' => 1, 'width' => '100mm', 'height' => '50mm', 'page' => 'thermal'],
    ];

    private const TEMPLATE_ALIASES = [
        'a4-30' => 'a4_30',
        'a4-40' => 'a4_40',
        'a4-24' => 'a4_24',
        'roll-30x20' => 'roll_30x20',
        'roll-38x25' => 'roll_38x25',
        'roll-40x30' => 'roll_40x30',
        'roll-50x25' => 'roll_50x25',
        'roll-58x40' => 'roll_58x40',
        'roll-100x50' => 'roll_100x50',
        'thermal_50_25' => 'roll_50x25',
        'thermal_50_30' => 'roll_50x25',
    ];

    private const DEFAULT_OPTIONS = [
        'product_name' => true,
        'variant_name' => true,
        'sku' => true,
        'selling_price' => true,
        'barcode' => true,
        'shop_name' => true,
    ];

    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
        private readonly Code128BarcodeSvgService $barcodeSvgService,
        private readonly ProductBarcodeService $barcodeService,
    ) {
    }

    public function index(Request $request): View
    {
        $shop = $this->activeShop($request);
        $filters = [
            'q' => trim((string) $request->query('q', '')),
        ];

        return view('merchant.barcodes.index', [
            'activeShop' => $shop,
            'filters' => $filters,
            'variants' => $this->variants($shop, $filters['q']),
            'templates' => self::TEMPLATES,
            'contentOptions' => self::DEFAULT_OPTIONS,
        ]);
    }

    public function generateMissing(Request $request): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $count = $this->barcodeService->generateMissingForShop($shop, $request->user());

        return redirect()
            ->route('merchant.barcodes.labels.index', ['q' => $request->input('q')])
            ->with($count > 0 ? 'success' : 'info', $count > 0
                ? "{$count} missing barcode(s) generated successfully."
                : 'All variants in this shop already have barcodes.');
    }

    public function print(Request $request): View|RedirectResponse
    {
        $shop = $this->activeShop($request);
        $data = $request->validate([
            'template' => ['nullable', 'string', 'max:40'],
            'variants' => ['nullable', 'array'],
            'variants.*.quantity' => ['nullable', 'integer', 'min:0', 'max:500'],
            'variant_ids' => ['nullable', 'array'],
            'variant_ids.*' => ['integer'],
            'bulk_quantity' => ['nullable', 'integer', 'min:0', 'max:500'],
            'options' => ['nullable', 'array'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        $templateKey = $this->templateKey((string) ($data['template'] ?? ''));

        $bulkQuantity = (int) ($data['bulk_quantity'] ?? 0);
        $quantities = collect($data['variants'] ?? [])
            ->map(fn (array $row): int => (int) ($row['quantity'] ?? 0))
            ->filter(fn (int $quantity): bool => $quantity > 0);

        if ($quantities->isEmpty() && $bulkQuantity > 0) {
            $fallbackIds = collect($data['variant_ids'] ?? array_keys($data['variants'] ?? []))
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();

            if ($fallbackIds->isEmpty()) {
                $fallbackIds = $this->variants($shop, (string) ($data['q'] ?? ''))
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values();
            }

            $quantities = $fallbackIds->mapWithKeys(fn (int $variantId): array => [$variantId => $bulkQuantity]);
        }

        $variantIds = $quantities->keys()->map(fn ($id): int => (int) $id)->values();
        $variants = ProductVariant::query()
            ->with(['product.shop', 'shop'])
            ->whereIn('id', $variantIds)
            ->where('shop_id', $shop->getKey())
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $variants->each(function (ProductVariant $variant) use ($request): void {
            if ($variant->barcode === null || trim((string) $variant->barcode) === '') {
                $this->barcodeService->generateForVariant($variant, $request->user());
            }
        });

        $variants = $variants->map(fn (ProductVariant $variant): ProductVariant => $variant->refresh()->load('product'))->keyBy('id');
        $labels = $variantIds
            ->flatMap(function (int $variantId) use ($variants, $quantities): array {
                $variant = $variants->get($variantId);
                if (! $variant instanceof ProductVariant) {
                    return [];
                }

                return array_fill(0, $quantities->get($variantId), $variant);
            })
            ->values();
        if ($labels->isEmpty()) {
            return view('merchant.barcodes.print-empty', [
                'message' => 'No printable variants were found. Search for a product, set Label Quantity, then try Print Preview again.',
                'backUrl' => route('merchant.barcodes.labels.index', ['q' => $request->input('q')]),
            ]);
        }

        $selectedOptions = collect(self::DEFAULT_OPTIONS)
            ->map(fn (bool $default, string $key): bool => (bool) ($data['options'][$key] ?? false))
            ->all();

        return view('merchant.barcodes.print', [
            'activeShop' => $shop,
            'template' => self::TEMPLATES[$templateKey],
            'labels' => $labels,
            'options' => $selectedOptions,
            'barcodeSvgService' => $this->barcodeSvgService,
        ]);
    }

    private function templateKey(string $key): string
    {
        $key = trim($key);

        return self::TEMPLATE_ALIASES[$key] ?? (array_key_exists($key, self::TEMPLATES) ? $key : 'a4_30');
    }

    private function activeShop(Request $request): Shop
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);

        $shop = $this->shopContextService->resolveActiveShop(
            $this->shopContextService->activeShops($merchant),
            $request->session()->get('active_shop_id'),
        );

        abort_unless($shop instanceof Shop, 403);

        return $shop;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    private function variants(Shop $shop, string $query): Collection
    {
        return ProductVariant::query()
            ->with('product')
            ->where('shop_id', $shop->getKey())
            ->whereHas('product', fn ($productQuery) => $productQuery
                ->where('shop_id', $shop->getKey())
                ->where('status', 'active'))
            ->when($query !== '', function ($variantQuery) use ($query): void {
                $variantQuery->where(function ($nested) use ($query): void {
                    $nested
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%")
                        ->orWhere('barcode', 'like', "%{$query}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('product_name', 'like', "%{$query}%"));
                });
            })
            ->orderBy('id')
            ->limit(100)
            ->get();
    }
}
