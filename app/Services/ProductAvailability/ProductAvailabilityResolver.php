<?php

namespace App\Services\ProductAvailability;

use App\Models\Product;
use App\Models\ProductAvailabilityStatus;
use App\Models\ProductVariant;

class ProductAvailabilityResolver
{
    /**
     * @return array{
     *     availability_status_id: int|null,
     *     availability_code: string|null,
     *     availability_label: string,
     *     availability_status_active: bool,
     *     purchase_allowed: bool,
     *     badge_type: string,
     *     stock_quantity: int,
     *     is_in_stock: bool,
     *     can_purchase: bool,
     *     availability: array{code: string|null, label: string, description: string|null, badge_type: string, purchase_allowed: bool}
     * }
     */
    public function resolve(Product|ProductVariant $item): array
    {
        $variant = $item instanceof ProductVariant ? $item->loadMissing(['product.availabilityStatus', 'availabilityStatus']) : null;
        $product = $item instanceof Product ? $item->loadMissing('availabilityStatus') : $variant->product;
        $stockQuantity = $variant instanceof ProductVariant ? (int) $variant->stock_quantity : (int) ($product->variants()->sum('stock_quantity'));
        $rawStatus = $variant?->availabilityStatus ?: $product->availabilityStatus;
        $status = $this->effectiveStatus($product, $variant);
        $isInStock = $stockQuantity > 0;
        $purchaseAllowed = (bool) $status?->purchase_allowed;
        $code = $status?->code;
        $canPurchase = $purchaseAllowed && match ($code) {
            ProductAvailabilityStatus::CODE_IN_STOCK => $isInStock,
            ProductAvailabilityStatus::CODE_BACKORDER,
            ProductAvailabilityStatus::CODE_PREORDER => true,
            ProductAvailabilityStatus::CODE_OUT_OF_STOCK,
            ProductAvailabilityStatus::CODE_COMING_SOON,
            ProductAvailabilityStatus::CODE_DISCONTINUED => false,
            null => false,
            default => $isInStock,
        };
        $label = $rawStatus?->name ?: ($isInStock ? 'In Stock' : 'Out of Stock');
        $badgeType = in_array($status?->badge_type, ProductAvailabilityStatus::badgeTypes(), true)
            ? (string) $status->badge_type
            : ProductAvailabilityStatus::BADGE_SECONDARY;

        return [
            'availability_status_id' => $status?->getKey(),
            'availability_code' => $code,
            'availability_label' => $label,
            'availability_status_active' => $status !== null,
            'purchase_allowed' => $purchaseAllowed,
            'badge_type' => $badgeType,
            'stock_quantity' => $stockQuantity,
            'is_in_stock' => $isInStock,
            'can_purchase' => $canPurchase,
            'availability' => [
                'code' => $status?->code,
                'label' => $label,
                'description' => $status?->customer_description,
                'badge_type' => $badgeType,
                'purchase_allowed' => $purchaseAllowed,
            ],
        ];
    }

    private function effectiveStatus(Product $product, ?ProductVariant $variant = null): ?ProductAvailabilityStatus
    {
        $status = $variant?->availabilityStatus ?: $product->availabilityStatus;

        if ($status instanceof ProductAvailabilityStatus && $status->status === ProductAvailabilityStatus::STATUS_ACTIVE && $status->deleted_at === null) {
            return $status;
        }

        return null;
    }
}
