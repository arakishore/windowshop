<?php

namespace App\Services\Storefront;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\PromotionReward;
use App\Models\PromotionTarget;
use App\Services\Admin\AdminSettingsService;
use App\Services\Promotion\Engine\Data\PromotionLineInput;
use App\Services\Promotion\Engine\PromotionRepository;
use App\Services\Promotion\Engine\PromotionTargetMatcher;
use Illuminate\Support\Collection;

class ProductPromotionPresenter
{
    /**
     * @var array<int, Collection<int, Promotion>>
     */
    private array $promotionsByShop = [];

    public function __construct(
        private readonly PromotionRepository $promotions,
        private readonly PromotionTargetMatcher $matcher,
        private readonly AdminSettingsService $settings,
    ) {
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    public function forProduct(Product $product): array
    {
        $variant = $product->storefrontCardVariant;

        if (! $variant || ! $product->shop_id) {
            return $this->empty();
        }

        $line = $this->lineInput($product);

        foreach ($this->promotionsForShop((int) $product->shop_id) as $promotion) {
            $reward = $promotion->rewards->first();

            if (! $reward instanceof PromotionReward || ! $this->matchesDisplayTarget($promotion, $reward, $line)) {
                continue;
            }

            return $this->display($promotion, $reward);
        }

        return $this->empty();
    }

    /**
     * @return Collection<int, Promotion>
     */
    private function promotionsForShop(int $shopId): Collection
    {
        return $this->promotionsByShop[$shopId] ??= $this->promotions->automaticActiveForShop($shopId, now());
    }

    private function matchesDisplayTarget(Promotion $promotion, PromotionReward $reward, PromotionLineInput $line): bool
    {
        $roles = match ($reward->reward_type) {
            PromotionReward::TYPE_BUY_X_GET_Y_FREE,
            PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT => [
                PromotionTarget::ROLE_BUY,
                PromotionTarget::ROLE_GET,
                PromotionTarget::ROLE_ELIGIBLE,
            ],
            PromotionReward::TYPE_FREE_GIFT => [
                PromotionTarget::ROLE_ELIGIBLE,
                PromotionTarget::ROLE_GIFT,
            ],
            default => [PromotionTarget::ROLE_ELIGIBLE],
        };

        foreach ($roles as $role) {
            if ($this->matcher->matches($promotion, $line, $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    public function forPromotion(Promotion $promotion): array
    {
        $reward = $promotion->rewards->first();

        if (! $reward instanceof PromotionReward) {
            return $this->empty();
        }

        return $this->display($promotion, $reward);
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    private function display(Promotion $promotion, PromotionReward $reward): array
    {
        return match ($reward->reward_type) {
            PromotionReward::TYPE_PERCENTAGE_DISCOUNT => $this->percentageDiscount($reward),
            PromotionReward::TYPE_FIXED_DISCOUNT => $this->fixedDiscount($reward),
            PromotionReward::TYPE_FIXED_PRICE => $this->fixedPrice($reward),
            PromotionReward::TYPE_FIXED_BUNDLE_PRICE => $this->fixedBundlePrice($reward),
            PromotionReward::TYPE_BUY_X_GET_Y_FREE => $this->buyXGetYFree($reward),
            PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT => $this->buyXGetYDiscount($reward),
            PromotionReward::TYPE_QUANTITY_DISCOUNT => $this->quantityDiscount($promotion, $reward),
            PromotionReward::TYPE_TIER_PRICING => $this->tierPricing($reward),
            PromotionReward::TYPE_FREE_GIFT => [
                'promotion_label' => 'FREE GIFT',
                'promotion_text' => 'Free gift with this product',
                'promotion_icon' => 'icon-Gift',
            ],
            default => $this->empty(),
        };
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    private function percentageDiscount(PromotionReward $reward): array
    {
        $percent = $this->formatNumber($reward->value_percent);

        return [
            'promotion_label' => $percent !== null ? $percent.'% OFF' : null,
            'promotion_text' => $percent !== null ? 'Extra '.$percent.'% off available' : null,
            'promotion_icon' => null,
        ];
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    private function fixedDiscount(PromotionReward $reward): array
    {
        if (! $reward->value_amount) {
            return $this->empty();
        }

        return [
            'promotion_label' => 'FLAT '.$this->money((float) $reward->value_amount).' OFF',
            'promotion_text' => 'Flat '.$this->money((float) $reward->value_amount).' off available',
            'promotion_icon' => null,
        ];
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    private function fixedPrice(PromotionReward $reward): array
    {
        if (! $reward->value_amount) {
            return $this->empty();
        }

        return [
            'promotion_label' => 'SPECIAL PRICE '.$this->moneyCompact((float) $reward->value_amount),
            'promotion_text' => 'Special price available',
            'promotion_icon' => null,
        ];
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    private function fixedBundlePrice(PromotionReward $reward): array
    {
        if (! $reward->bundle_quantity || ! $reward->bundle_price) {
            return $this->empty();
        }

        $quantity = (int) $reward->bundle_quantity;
        $price = $this->money((float) $reward->bundle_price);

        return [
            'promotion_label' => $quantity.' FOR '.$price,
            'promotion_text' => 'Buy '.$quantity.' for '.$price,
            'promotion_icon' => null,
        ];
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    private function buyXGetYFree(PromotionReward $reward): array
    {
        $buy = max(1, (int) $reward->buy_quantity);
        $get = max(1, (int) $reward->get_quantity);

        return [
            'promotion_label' => 'BUY '.$buy.' GET '.$get.' FREE',
            'promotion_text' => 'Buy '.$buy.' and get '.$get.' free',
            'promotion_icon' => null,
        ];
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    private function buyXGetYDiscount(PromotionReward $reward): array
    {
        $buy = max(1, (int) $reward->buy_quantity);
        $get = max(1, (int) $reward->get_quantity);
        $percent = $this->formatNumber($reward->value_percent);

        if ($percent === null) {
            return [
                'promotion_label' => 'BUY '.$buy.' GET '.$get.' OFFER',
                'promotion_text' => 'Buy '.$buy.' and save on '.$get,
                'promotion_icon' => null,
            ];
        }

        return [
            'promotion_label' => 'BUY '.$buy.' GET '.$get.' '.$percent.'% OFF',
            'promotion_text' => 'Buy '.$buy.' and get '.$get.' at '.$percent.'% off',
            'promotion_icon' => null,
        ];
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    private function quantityDiscount(Promotion $promotion, PromotionReward $reward): array
    {
        $minimum = $promotion->conditions
            ->where('condition_type', 'minimum_quantity')
            ->first()?->value_numeric;
        $minimum = $minimum ? (int) $minimum : null;

        if ($reward->value_type === 'amount' && $reward->value_amount) {
            $save = $this->money((float) $reward->value_amount);

            return [
                'promotion_label' => $minimum ? 'BUY '.$minimum.'+ SAVE' : 'BUY MORE SAVE',
                'promotion_text' => ($minimum ? 'Buy '.$minimum.'+ and save ' : 'Save ').$save,
                'promotion_icon' => null,
            ];
        }

        $percent = $this->formatNumber($reward->value_percent);

        return [
            'promotion_label' => $minimum ? 'BUY '.$minimum.'+ SAVE' : ($percent ? $percent.'% OFF' : 'BUY MORE SAVE'),
            'promotion_text' => $percent
                ? ($minimum ? 'Buy '.$minimum.'+ and save '.$percent.'%' : 'Save '.$percent.'%')
                : 'Quantity offer available',
            'promotion_icon' => null,
        ];
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    private function tierPricing(PromotionReward $reward): array
    {
        $tiers = collect($reward->tier_config ?? [])
            ->map(fn (array $tier): int => (int) ($tier['min_quantity'] ?? 0))
            ->filter(fn (int $quantity): bool => $quantity > 0)
            ->sort()
            ->values();

        $minimum = $tiers->first();

        return [
            'promotion_label' => $minimum ? 'BUY '.$minimum.'+ SAVE' : 'VOLUME PRICE',
            'promotion_text' => $minimum ? 'Volume pricing starts at '.$minimum.' units' : 'Volume pricing available',
            'promotion_icon' => null,
        ];
    }

    private function lineInput(Product $product): PromotionLineInput
    {
        $variant = $product->storefrontCardVariant;

        return new PromotionLineInput(
            variantId: (int) $variant->getKey(),
            productId: (int) $product->getKey(),
            shopId: (int) $product->shop_id,
            quantity: '1.00',
            baseUnitPrice: (string) $variant->selling_price,
            categoryIds: $this->categoryIds($product),
            brandId: $product->brand_id ? (int) $product->brand_id : null,
            collectionIds: $product->relationLoaded('collections')
                ? $product->collections->pluck('id')->map(fn ($id): int => (int) $id)->all()
                : [],
        );
    }

    /**
     * @return array<int, int>
     */
    private function categoryIds(Product $product): array
    {
        $ids = collect([$product->product_category_id, $product->root_product_category_id])
            ->filter()
            ->map(fn ($id): int => (int) $id);

        $category = $product->relationLoaded('category') ? $product->category : null;

        while ($category instanceof ProductCategory) {
            $ids->push((int) $category->getKey());
            $category = $category->relationLoaded('parent') ? $category->parent : null;
        }

        return $ids->unique()->values()->all();
    }

    private function formatNumber(float|string|int|null $value): ?string
    {
        if ($value === null || (float) $value <= 0) {
            return null;
        }

        $number = (float) $value;

        return floor($number) === $number
            ? (string) (int) $number
            : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    private function money(float $value): string
    {
        $currency = $this->settings->currencyConfig();
        $amount = number_format(
            $value,
            (int) ($currency['decimal_places'] ?? 2),
            (string) ($currency['decimal_separator'] ?? '.'),
            (string) ($currency['thousands_separator'] ?? ','),
        );
        $symbol = (string) ($currency['symbol'] ?? 'INR ');

        return ($currency['symbol_position'] ?? 'before') === 'before'
            ? $symbol.$amount
            : $amount.' '.$symbol;
    }

    private function moneyCompact(float $value): string
    {
        $currency = $this->settings->currencyConfig();
        $decimals = (int) ($currency['decimal_places'] ?? 2);
        $displayDecimals = floor($value) === $value ? 0 : $decimals;
        $amount = number_format(
            $value,
            $displayDecimals,
            (string) ($currency['decimal_separator'] ?? '.'),
            (string) ($currency['thousands_separator'] ?? ','),
        );
        $symbol = (string) ($currency['symbol'] ?? 'INR ');

        return ($currency['symbol_position'] ?? 'before') === 'before'
            ? $symbol.$amount
            : $amount.' '.$symbol;
    }

    /**
     * @return array{promotion_label: string|null, promotion_text: string|null, promotion_icon: string|null}
     */
    private function empty(): array
    {
        return [
            'promotion_label' => null,
            'promotion_text' => null,
            'promotion_icon' => null,
        ];
    }
}
