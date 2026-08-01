<?php

namespace App\Services\ProductAvailability;

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
    public function assertVariantCanBePurchased(ProductVariant $variant): void
    {
        $availability = $this->resolver->resolve($variant);

        if (! $availability['can_purchase']) {
            throw ValidationException::withMessages([
                'items' => "This product is currently unavailable for purchase.",
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
}
