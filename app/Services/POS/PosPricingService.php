<?php

namespace App\Services\POS;

use App\Models\MerchantProfile;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTotal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionCoupon;
use App\Models\Shop;
use App\Services\Order\OrderTotalsService;
use App\Services\Promotion\Coupons\CouponResolution;
use App\Services\Promotion\Coupons\CouponResolver;
use App\Services\Promotion\Engine\Data\GeneratedPromotionGift;
use App\Services\Promotion\Engine\Data\PromotionCalculationResult;
use App\Services\Promotion\Engine\PromotionCalculator;
use App\Services\Tax\Exceptions\TaxConfigurationException;
use App\Services\Tax\OrderTaxSnapshotFactory;
use App\Services\Tax\PricingEngine;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PosPricingService
{
    public function __construct(
        private readonly DiscountService $discountService,
        private readonly CashRoundingService $cashRoundingService,
        private readonly PricingEngine $pricingEngine,
        private readonly OrderTaxSnapshotFactory $orderTaxSnapshotFactory,
        private readonly OrderTotalsService $orderTotalsService,
        private readonly PromotionCalculator $promotions,
        private readonly CouponResolver $couponResolver,
    ) {
    }

    /**
     * @param array{
     *     items: array<int, array{product_variant_id: int, quantity: int, discount_type?: string|null, discount_value?: mixed}>,
     *     order_discount?: array{type?: string|null, value?: mixed, reason?: string|null, note?: string|null}|null,
     *     payment_method?: string|null,
     *     amount_paid?: mixed,
     *     coupon_code?: mixed
     * } $data
     * @param array{method?: string, applyTo?: array<int, string>|string} $cashRounding
     * @return array<string, mixed>
     */
    public function price(Shop $shop, array $data, array $cashRounding, CarbonInterface $effectiveAt, ?Customer $customer = null): array
    {
        $shop->loadMissing('merchant.taxSetting');
        $rows = $this->aggregateItems($data['items'] ?? []);
        $variants = $this->variants($shop, $rows);
        $couponResolution = $this->couponResolution($shop, $data['coupon_code'] ?? null, $customer, $effectiveAt);
        $activatedCoupons = $couponResolution?->valid() ? [$couponResolution->coupon] : [];
        $promotionResult = $this->promotions->calculateForVariantRows($shop, $rows, $variants, $customer, $effectiveAt, $activatedCoupons);
        $giftVariants = $this->giftVariants($shop, $promotionResult->generatedGifts);
        $generatedGifts = $this->availableGeneratedGifts($promotionResult->generatedGifts, $giftVariants);
        $promotionResult = $this->promotionResultForAvailableGifts($promotionResult, $generatedGifts);
        $items = $this->buildItems($rows, $variants, $shop->merchant, $effectiveAt, $promotionResult);
        $giftItems = $this->buildGiftItems($generatedGifts, $giftVariants, $shop->merchant, $effectiveAt);
        $pricedItems = [...$items, ...$giftItems];
        $orderDiscount = $this->orderDiscount($items, $data['order_discount'] ?? []);
        $paymentMethod = (string) ($data['payment_method'] ?? Order::PAYMENT_METHOD_CASH);

        $calculated = $this->orderTotalsService->calculate(
            $pricedItems,
            $this->totalsRows(collect($pricedItems), $orderDiscount, $paymentMethod, $cashRounding),
            $data['amount_paid'] ?? 0,
        );

        $summary = $calculated['summary'];
        unset($summary['_line_payable_total'], $summary['_line_discount_total'], $summary['_line_tax_total']);

        return [
            'tax_display_enabled' => (bool) $shop->merchant?->taxSetting?->tax_enabled,
            'has_tax_amount' => (float) $summary['tax_total'] > 0,
            'items' => array_map(fn (OrderItem $item): array => $this->itemPayload($item), $items),
            'generated_gifts' => array_map(fn (OrderItem $item): array => $this->itemPayload($item, true), $giftItems),
            'applied_promotions' => $promotionResult->appliedPromotions(),
            'coupon' => $this->couponState($couponResolution, $promotionResult),
            'summary' => $summary,
            'totals' => $calculated['rows'],
            'order_discount' => $orderDiscount,
            'payment_method' => $paymentMethod,
            'calculated_at' => $effectiveAt->toDateTimeString(),
        ];
    }

    /**
     * @param array<int, array{product_variant_id?: int, quantity?: int, discount_type?: string|null, discount_value?: mixed}> $rows
     * @return array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed}>
     */
    private function aggregateItems(array $rows): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one cart item is required.',
            ]);
        }

        $aggregated = [];

        foreach ($rows as $index => $row) {
            $variantId = (int) ($row['product_variant_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($variantId < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'A valid product variant is required.',
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Quantity must be at least 1.',
                ]);
            }

            if (! isset($aggregated[$variantId])) {
                $aggregated[$variantId] = [
                    'quantity' => 0,
                    'discount_type' => $row['discount_type'] ?? null,
                    'discount_value' => $row['discount_value'] ?? null,
                ];
            }

            $aggregated[$variantId]['quantity'] += $quantity;
        }

        return $aggregated;
    }

    /**
     * @param array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed}> $rows
     * @return array<int, ProductVariant>
     */
    private function variants(Shop $shop, array $rows): array
    {
        $variants = [];

        foreach ($rows as $variantId => $row) {
            $variant = ProductVariant::query()
                ->with([
                    'availabilityStatus',
                    'product.availabilityStatus',
                    'product.brand',
                    'product.category.parent.parent',
                    'product.collections' => fn ($query) => $query
                        ->where('collections.shop_id', $shop->getKey())
                        ->whereNull('collections.deleted_at'),
                    'product.primaryImage',
                    'product.returnPolicy',
                    'product.shop.settings',
                ])
                ->whereKey($variantId)
                ->where('shop_id', $shop->getKey())
                ->firstOrFail();
            $product = $variant->product;

            if ($variant->status !== 'active') {
                throw ValidationException::withMessages([
                    'items' => "{$variant->name} is not available for sale.",
                ]);
            }

            if ((int) $variant->stock_quantity < (int) $row['quantity']) {
                throw ValidationException::withMessages([
                    'items' => "Only {$variant->stock_quantity} unit(s) are available for {$variant->product?->product_name}.",
                ]);
            }

            if (! $product instanceof Product
                || (int) $product->shop_id !== (int) $shop->getKey()
                || (int) $product->merchant_id !== (int) $shop->merchant_id
            ) {
                throw ValidationException::withMessages([
                    'items' => "{$variant->name} is not available for this shop.",
                ]);
            }

            $variants[$variantId] = $variant;
        }

        return $variants;
    }

    /**
     * @param array<int, array{quantity: int, discount_type?: string|null, discount_value?: mixed}> $rows
     * @param array<int, ProductVariant> $variants
     * @return array<int, OrderItem>
     */
    private function buildItems(array $rows, array $variants, MerchantProfile $merchant, CarbonInterface $effectiveAt, ?PromotionCalculationResult $promotionResult = null): array
    {
        $items = [];

        foreach ($rows as $variantId => $row) {
            $quantity = (int) $row['quantity'];
            $variant = $variants[$variantId];
            $unitPrice = $this->money($variant->selling_price);
            $lineSubtotal = $this->money((float) $unitPrice * $quantity);
            $promotionAdjustment = $promotionResult?->line((int) $variantId);
            $discount = $promotionAdjustment?->hasPromotionParticipation()
                ? [
                    'type' => $promotionAdjustment->winningPromotion?->rewardType,
                    'value' => $promotionAdjustment->discountAmount(),
                    'amount' => $promotionAdjustment->discountAmount(),
                ]
                : $this->discountService->calculateLineDiscount($lineSubtotal, [
                    'discount_type' => $row['discount_type'] ?? null,
                    'discount_value' => $row['discount_value'] ?? null,
                ]);
            $product = $variant->product;

            try {
                $pricingResult = $this->pricingEngine->calculateProductLine(
                    product: $product,
                    merchant: $merchant,
                    unitPrice: $unitPrice,
                    quantity: $quantity,
                    effectiveAt: $effectiveAt,
                    discountAmount: $discount['amount'],
                );
            } catch (TaxConfigurationException $exception) {
                throw ValidationException::withMessages([
                    'items' => $exception->getMessage(),
                ]);
            }

            $taxSnapshot = $this->orderTaxSnapshotFactory->fromPricingResult($pricingResult);

            $items[] = new OrderItem(array_merge([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->getKey(),
                'product_name' => $product?->product_name ?? 'Product',
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'quantity' => $quantity,
                'unit_mrp' => $this->money($variant->mrp),
                'unit_price' => $unitPrice,
                'unit_discount' => $quantity > 0 ? $this->money((float) $discount['amount'] / $quantity) : '0.00',
                'item_discount_type' => $discount['type'],
                'item_discount_value' => $discount['value'],
                'metadata' => $promotionAdjustment?->metadata(),
            ], $taxSnapshot->toOrderItemAttributes()));
        }

        return $items;
    }

    private function couponResolution(Shop $shop, mixed $code, ?Customer $customer, CarbonInterface $effectiveAt): ?CouponResolution
    {
        $normalized = $this->couponResolver->normalize($code);
        if ($normalized === '') {
            return null;
        }

        $resolution = $this->couponResolver->resolveForShop($shop, $normalized, $effectiveAt);
        if (! $resolution->valid() || ! $resolution->coupon instanceof PromotionCoupon) {
            return $resolution;
        }

        if (! $customer instanceof Customer && $this->couponRequiresCustomer($resolution->coupon)) {
            return new CouponResolution(
                'customer_required',
                'Select a customer to use this coupon.',
                code: $normalized,
            );
        }

        return $resolution;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function couponState(?CouponResolution $resolution, PromotionCalculationResult $result): ?array
    {
        if (! $resolution instanceof CouponResolution) {
            return null;
        }

        $state = $resolution->toArray();
        $state['won'] = false;
        $state['discount_cents'] = 0;
        $state['discount'] = $this->money(0);

        if (! $resolution->valid() || ! $resolution->coupon instanceof PromotionCoupon) {
            return $state;
        }

        $promotionId = (int) $resolution->coupon->promotion_id;
        $candidateDiscountCents = 0;
        $wonDiscountCents = 0;

        foreach ($result->lineAdjustments as $adjustment) {
            foreach ($adjustment->eligiblePromotions as $candidate) {
                if ((int) ($candidate['id'] ?? 0) !== $promotionId) {
                    continue;
                }

                $candidateDiscountCents += $this->moneyToCents((string) ($candidate['discount_amount'] ?? '0'));
                $candidateDiscountCents += max(0, (int) ($candidate['details']['conflict_benefit_cents'] ?? 0));
            }

            if ($adjustment->winningPromotion?->promotionId === $promotionId) {
                $wonDiscountCents += $adjustment->promotionDiscountCents;
            }
        }

        foreach ($result->generatedGifts as $gift) {
            if ($gift->promotion->promotionId !== $promotionId) {
                continue;
            }

            $candidateDiscountCents += $gift->promotionDiscountCents;
            $wonDiscountCents += $gift->promotionDiscountCents;
        }

        if ($wonDiscountCents > 0) {
            $state['status'] = 'applied';
            $state['message'] = 'Coupon applied.';
            $state['won'] = true;
            $state['discount_cents'] = $wonDiscountCents;
            $state['discount'] = '-'.$this->moneyFromCents($wonDiscountCents);

            return $state;
        }

        if ($candidateDiscountCents > 0) {
            $state['status'] = 'valid_but_not_best';
            $state['message'] = 'Coupon is valid, but a better offer has been applied.';

            return $state;
        }

        $state['status'] = 'not_eligible';
        $state['message'] = 'This coupon is not valid for the items in this sale.';

        return $state;
    }

    private function couponRequiresCustomer(PromotionCoupon $coupon): bool
    {
        $promotion = $coupon->promotion;

        return $promotion instanceof Promotion
            && ((bool) $promotion->new_customer_only
                || (int) ($promotion->per_customer_usage_limit ?? 0) > 0
                || (int) ($coupon->per_customer_usage_limit ?? 0) > 0);
    }

    /**
     * @param array<int, GeneratedPromotionGift> $gifts
     * @param array<int, ProductVariant> $variants
     * @return array<int, OrderItem>
     */
    private function buildGiftItems(array $gifts, array $variants, MerchantProfile $merchant, CarbonInterface $effectiveAt): array
    {
        $items = [];

        foreach ($gifts as $gift) {
            $variant = $variants[$gift->variantId] ?? null;
            if (! $variant instanceof ProductVariant) {
                continue;
            }

            $product = $variant->product;
            $unitPrice = $this->money($variant->selling_price);
            $quantity = max(1, (int) $gift->quantity);
            $lineSubtotal = $this->money((float) $unitPrice * $quantity);

            try {
                $pricingResult = $this->pricingEngine->calculateProductLine(
                    product: $product,
                    merchant: $merchant,
                    unitPrice: $unitPrice,
                    quantity: $quantity,
                    effectiveAt: $effectiveAt,
                    discountAmount: $lineSubtotal,
                );
            } catch (TaxConfigurationException $exception) {
                throw ValidationException::withMessages([
                    'items' => $exception->getMessage(),
                ]);
            }

            $taxSnapshot = $this->orderTaxSnapshotFactory->fromPricingResult($pricingResult);

            $items[] = new OrderItem(array_merge([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->getKey(),
                'product_name' => $product?->product_name ?? 'Free Gift',
                'product_image' => $product?->primaryImage?->image_path,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'quantity' => $quantity,
                'unit_mrp' => $this->money($variant->mrp),
                'unit_price' => $unitPrice,
                'unit_discount' => $lineSubtotal,
                'item_discount_type' => $gift->promotion->rewardType,
                'item_discount_value' => $lineSubtotal,
                'metadata' => $gift->metadata(),
            ], $taxSnapshot->toOrderItemAttributes()));
        }

        return $items;
    }

    /**
     * @param array<int, GeneratedPromotionGift> $gifts
     * @return array<int, ProductVariant>
     */
    private function giftVariants(Shop $shop, array $gifts): array
    {
        $variants = [];

        foreach ($gifts as $gift) {
            if (isset($variants[$gift->variantId])) {
                continue;
            }

            $variant = ProductVariant::query()
                ->with([
                    'availabilityStatus',
                    'product.availabilityStatus',
                    'product.primaryImage',
                ])
                ->whereKey($gift->variantId)
                ->where('shop_id', $shop->getKey())
                ->first();

            if (! $variant instanceof ProductVariant
                || $variant->status !== 'active'
                || ! $variant->is_sellable
                || ! $variant->product instanceof Product
                || $variant->product->status !== 'active'
                || $variant->product->deleted_at !== null) {
                continue;
            }

            $status = $variant->availabilityStatus ?: $variant->product->availabilityStatus;
            if ($status === null || $status->status !== 'active' || ! $status->purchase_allowed) {
                continue;
            }

            $variants[$gift->variantId] = $variant;
        }

        return $variants;
    }

    /**
     * @param array<int, GeneratedPromotionGift> $gifts
     * @param array<int, ProductVariant> $variants
     * @return array<int, GeneratedPromotionGift>
     */
    private function availableGeneratedGifts(array $gifts, array $variants): array
    {
        return array_values(array_filter(
            $gifts,
            fn (GeneratedPromotionGift $gift): bool => isset($variants[$gift->variantId])
                && (float) $variants[$gift->variantId]->stock_quantity >= max(1, (int) $gift->quantity),
        ));
    }

    /**
     * @param array<int, GeneratedPromotionGift> $availableGifts
     */
    private function promotionResultForAvailableGifts(PromotionCalculationResult $result, array $availableGifts): PromotionCalculationResult
    {
        if (count($availableGifts) === count($result->generatedGifts)) {
            return $result;
        }

        $availablePromotionIds = array_fill_keys(array_map(
            fn (GeneratedPromotionGift $gift): int => $gift->promotion->promotionId,
            $availableGifts,
        ), true);
        $lineAdjustments = [];

        foreach ($result->lineAdjustments as $variantId => $adjustment) {
            $promotionId = $adjustment->winningPromotion?->promotionId;

            if ($promotionId !== null
                && $adjustment->winningPromotion?->rewardType === 'free_gift'
                && ! isset($availablePromotionIds[$promotionId])) {
                $lineAdjustments[$variantId] = new \App\Services\Promotion\Engine\Data\PromotionLineAdjustment(
                    line: $adjustment->line,
                    baseLineSubtotalCents: $adjustment->baseLineSubtotalCents,
                    promotionDiscountCents: 0,
                    finalLineSubtotalCents: $adjustment->baseLineSubtotalCents,
                    winningPromotion: null,
                    eligiblePromotions: $adjustment->eligiblePromotions,
                );

                continue;
            }

            $lineAdjustments[$variantId] = $adjustment;
        }

        return new PromotionCalculationResult($result->shopId, $lineAdjustments, $availableGifts);
    }

    /**
     * @param array<int, OrderItem> $items
     * @param array<string, mixed> $discount
     * @return array{type: string|null, value: string|null, amount: string, reason: string|null, note: string|null}
     */
    private function orderDiscount(array $items, array $discount): array
    {
        $discountableSubtotal = collect($items)->sum(
            fn (OrderItem $item): float => (float) $item->line_subtotal - (float) $item->line_discount
        );

        return $this->discountService->calculateOrderDiscount($discountableSubtotal, $discount);
    }

    /**
     * @param Collection<int, OrderItem> $items
     * @param array{type: string|null, value: string|null, amount: string, reason: string|null, note: string|null} $orderDiscount
     * @param array{method?: string, applyTo?: array<int, string>|string} $cashRounding
     * @return array<int, array<string, mixed>>
     */
    private function totalsRows(Collection $items, array $orderDiscount, string $paymentMethod, array $cashRounding): array
    {
        $rows = [];
        $itemDiscount = $this->money($items->sum(fn (OrderItem $item): float => (float) $item->line_discount));

        if ((float) $itemDiscount > 0) {
            $discountDescriptor = $this->itemDiscountDescriptor($items);
            $rows[] = [
                'code' => OrderTotal::CODE_ITEM_DISCOUNT,
                'title' => $discountDescriptor['title'],
                'amount' => -1 * (float) $itemDiscount,
                'sort_order' => 20,
                'source' => $discountDescriptor['source'],
            ];
        }

        if ((float) $orderDiscount['amount'] > 0) {
            $rows[] = [
                'code' => OrderTotal::CODE_ORDER_DISCOUNT,
                'title' => 'Order Discount',
                'amount' => -1 * (float) $orderDiscount['amount'],
                'sort_order' => 30,
                'source' => 'pos',
                'metadata' => [
                    'type' => $orderDiscount['type'],
                    'value' => $orderDiscount['value'],
                    'reason' => $orderDiscount['reason'],
                    'note' => $orderDiscount['note'],
                ],
            ];
        }

        $roundingAdjustment = $this->cashRoundingService->adjustment(
            $items->sum(fn (OrderItem $item): float => (float) $item->line_total) - (float) $orderDiscount['amount'],
            $paymentMethod,
            $cashRounding,
        );

        if ($roundingAdjustment !== 0.0) {
            $rows[] = [
                'code' => OrderTotal::CODE_ROUNDING,
                'title' => 'Round Off',
                'amount' => $roundingAdjustment,
                'sort_order' => 90,
                'source' => 'pos',
                'metadata' => [
                    'method' => $cashRounding['method'] ?? 'nearest',
                    'payment_method' => $paymentMethod,
                ],
            ];
        }

        return $rows;
    }

    /**
     * @param Collection<int, OrderItem> $items
     * @return array{title: string, source: string}
     */
    private function itemDiscountDescriptor(Collection $items): array
    {
        $hasPromotionDiscount = false;
        $hasManualDiscount = false;

        foreach ($items as $item) {
            if ((float) $item->line_discount <= 0) {
                continue;
            }

            if ($this->promotionMetadata($item) !== null) {
                $hasPromotionDiscount = true;
            } else {
                $hasManualDiscount = true;
            }
        }

        return match (true) {
            $hasPromotionDiscount && $hasManualDiscount => ['title' => 'Offer / Item Discount', 'source' => 'pos'],
            $hasPromotionDiscount => ['title' => 'Offer Discount', 'source' => 'promotion'],
            default => ['title' => 'Item Discount', 'source' => 'pos'],
        };
    }

    private function itemPayload(OrderItem $item, bool $generatedGift = false): array
    {
        $metadata = $this->decodedMetadata($item);
        $imagePath = (string) ($item->product_image ?? '');

        return [
            'product_variant_id' => (int) $item->product_variant_id,
            'product_name' => $item->product_name,
            'image_url' => $imagePath !== '' && Storage::disk('public')->exists($imagePath) ? Storage::disk('public')->url($imagePath) : null,
            'variant_name' => $item->variant_name,
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'quantity' => (int) $item->quantity,
            'unit_mrp' => (string) $item->unit_mrp,
            'unit_price' => (string) $item->unit_price,
            'line_subtotal' => (string) $item->line_subtotal,
            'line_discount' => (string) $item->line_discount,
            'tax_enabled' => (bool) $item->tax_enabled,
            'tax_resolution_source' => $item->tax_resolution_source,
            'tax_class_code' => $item->tax_class_code,
            'tax_class_name' => $item->tax_class_name,
            'tax_rate_name' => $item->tax_rate_name,
            'tax_rate' => $item->tax_rate ? (string) $item->tax_rate : null,
            'price_mode' => $item->price_mode,
            'taxable_amount' => $item->taxable_amount ? (string) $item->taxable_amount : null,
            'line_tax' => (string) $item->line_tax,
            'line_total' => (string) $item->line_total,
            'promotion' => is_array($metadata) ? ($metadata['promotion'] ?? null) : null,
            'metadata' => $metadata,
            'is_generated_gift' => $generatedGift,
        ];
    }

    private function promotionMetadata(OrderItem $item): ?array
    {
        $metadata = $this->decodedMetadata($item);

        return is_array($metadata) && is_array($metadata['promotion'] ?? null)
            ? $metadata['promotion']
            : null;
    }

    private function decodedMetadata(OrderItem $item): ?array
    {
        $metadata = $item->metadata;
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return is_array($metadata) ? $metadata : null;
    }

    private function money(float|string|int $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    private function moneyToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function moneyFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
