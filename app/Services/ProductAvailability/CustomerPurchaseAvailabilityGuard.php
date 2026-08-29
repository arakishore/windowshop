<?php

namespace App\Services\ProductAvailability;

use App\Models\Product;
use App\Models\ProductAvailabilityStatus;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class CustomerPurchaseAvailabilityGuard
{
    public function __construct(private readonly ProductAvailabilityResolver $resolver)
    {
    }

    /**
     * @throws ValidationException
     */
    public function assertVariantCanBePurchased(ProductVariant $variant, float|int|null $requestedQuantity = null, string $errorKey = 'items'): void
    {
        $decision = $this->decision($variant, $requestedQuantity);

        if (! $decision['allowed']) {
            throw ValidationException::withMessages([
                $errorKey => $decision['message'] ?: 'This product is currently unavailable for purchase.',
            ]);
        }
    }

    /**
     * @param iterable<int, ProductVariant> $variants
     *
     * @throws ValidationException
     */
    public function assertCheckoutCanProceed(iterable $variants): void
    {
        foreach ($variants as $variant) {
            $this->assertVariantCanBePurchased($variant);
        }
    }

    /**
     * @return array{
     *     allowed: bool,
     *     status_code: string|null,
     *     status_active: bool,
     *     purchase_allowed: bool,
     *     stock_quantity: float,
     *     requested_quantity: float|null,
     *     short_quantity: float,
     *     stock_limit_applies: bool,
     *     stock_limit: float|null,
     *     message: string|null
     * }
     */
    public function decision(ProductVariant $variant, float|int|null $requestedQuantity = null): array
    {
        $variant->loadMissing(['availabilityStatus', 'product.availabilityStatus']);

        $product = $variant->product;
        $availability = $this->resolver->resolve($variant);
        $status = $variant->availabilityStatus ?: $product?->availabilityStatus;
        $code = $availability['availability_code'];
        $stock = (float) $variant->getRawOriginal('stock_quantity');
        $quantity = $requestedQuantity === null ? null : max(0, (float) $requestedQuantity);
        $short = $quantity === null ? 0.0 : max(0, $quantity - max(0, $stock));

        $base = [
            'allowed' => false,
            'status_code' => $code,
            'status_active' => (bool) $availability['availability_status_active'],
            'purchase_allowed' => (bool) $availability['purchase_allowed'],
            'stock_quantity' => $stock,
            'requested_quantity' => $quantity,
            'short_quantity' => $short,
            'stock_limit_applies' => true,
            'stock_limit' => max(0, $stock),
            'message' => null,
        ];

        if (! $product instanceof Product || $product->deleted_at !== null || $product->status !== 'active') {
            return [...$base, 'message' => 'This product is currently unavailable.'];
        }

        if (! $status instanceof ProductAvailabilityStatus || ! $base['status_active']) {
            return [...$base, 'message' => 'This product is currently unavailable for purchase.'];
        }

        if (! $base['purchase_allowed']) {
            return [...$base, 'message' => 'This product is currently unavailable for purchase.'];
        }

        return match ($code) {
            ProductAvailabilityStatus::CODE_IN_STOCK => $this->inStockDecision($base, $quantity, $stock),
            ProductAvailabilityStatus::CODE_BACKORDER => $this->backorderDecision($base, $quantity, $stock, $short),
            ProductAvailabilityStatus::CODE_PREORDER => [
                ...$base,
                'allowed' => true,
                'stock_limit_applies' => false,
                'stock_limit' => null,
                'message' => 'This is a pre-order item. Availability and fulfilment will be confirmed by the merchant.',
            ],
            ProductAvailabilityStatus::CODE_OUT_OF_STOCK,
            ProductAvailabilityStatus::CODE_COMING_SOON,
            ProductAvailabilityStatus::CODE_DISCONTINUED => [...$base, 'message' => 'This product is currently unavailable for purchase.'],
            default => $this->inStockDecision($base, $quantity, $stock),
        };
    }

    /**
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private function inStockDecision(array $base, ?float $quantity, float $stock): array
    {
        if ($stock <= 0) {
            return [...$base, 'message' => 'This item is currently out of stock.'];
        }

        if ($quantity !== null && $quantity > $stock) {
            return [...$base, 'message' => 'Only '.$this->displayQuantity(max(0, $stock)).' items are currently available.'];
        }

        return [...$base, 'allowed' => true, 'message' => null];
    }

    /**
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private function backorderDecision(array $base, ?float $quantity, float $stock, float $short): array
    {
        $message = null;

        if ($quantity !== null && $short > 0) {
            $message = $stock > 0
                ? $this->displayQuantity(max(0, $stock)).' '.$this->itemWord($stock).' '.$this->verb($stock, 'is', 'are').' currently in stock. '.$this->displayQuantity($short).' '.$this->itemWord($short).' '.$this->verb($short, 'requires', 'require').' confirmation from the merchant.'
                : 'This item is currently not in stock. Your order requires confirmation from the merchant.';
        }

        return [
            ...$base,
            'allowed' => true,
            'stock_limit_applies' => false,
            'stock_limit' => null,
            'message' => $message,
        ];
    }

    private function displayQuantity(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
    }

    private function itemWord(float|int $value): string
    {
        return abs((float) $value - 1.0) < 0.0001 ? 'item' : 'items';
    }

    private function verb(float|int $value, string $singular, string $plural): string
    {
        return abs((float) $value - 1.0) < 0.0001 ? $singular : $plural;
    }
}
