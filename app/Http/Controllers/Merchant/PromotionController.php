<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StorePromotionRequest;
use App\Http\Requests\Merchant\UpdatePromotionRequest;
use App\Models\Brand;
use App\Models\Collection as ProductCollection;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionCondition;
use App\Models\PromotionCoupon;
use App\Models\PromotionReward;
use App\Models\PromotionTarget;
use App\Models\PromotionTemplate;
use App\Models\Shop;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function __construct(
        private readonly MerchantShopContextService $shopContextService,
    ) {
    }

    public function index(Request $request): View
    {
        $shop = $this->activeShop($request);
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', ''),
            'activation_type' => (string) $request->query('activation_type', ''),
        ];

        $baseQuery = Promotion::query()
            ->with(['template', 'coupons', 'rewards', 'conditions', 'targets'])
            ->where('merchant_id', $shop->merchant_id)
            ->where('shop_id', $shop->getKey())
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(fn ($query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%"));
            })
            ->when(in_array($filters['status'], array_keys($this->statuses()), true), fn ($query) => $query->where('status', $filters['status']))
            ->when(in_array($filters['activation_type'], array_keys($this->activationTypes()), true), fn ($query) => $query->where('activation_type', $filters['activation_type']));

        $starterPromotions = (clone $baseQuery)
            ->where('origin', Promotion::ORIGIN_SYSTEM)
            ->orderBy('created_at')
            ->get();

        $customPromotions = (clone $baseQuery)
            ->where('origin', Promotion::ORIGIN_MERCHANT)
            ->orderByDesc('created_at')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('merchant.promotions.index', [
            'starterPromotions' => $starterPromotions,
            'customPromotions' => $customPromotions,
            'filters' => $filters,
            'statuses' => $this->statuses(),
            'activationTypes' => $this->activationTypes(),
            'activeShop' => $shop,
        ]);
    }

    public function create(Request $request): View
    {
        $shop = $this->activeShop($request);

        return view('merchant.promotions.create', [
            'promotion' => new Promotion([
                'status' => Promotion::STATUS_DRAFT,
                'activation_type' => Promotion::ACTIVATION_AUTOMATIC,
                'refund_policy_mode' => Promotion::POLICY_INHERIT,
                'exchange_policy_mode' => Promotion::POLICY_INHERIT,
            ]),
            ...$this->formData($shop),
        ]);
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $data = $request->validated();
        $template = PromotionTemplate::query()->active()->findOrFail($data['promotion_template_id']);
        $this->validateTemplateConfiguration($template, $data);
        $this->validateTargets($shop, $data, $template);
        $this->validateCoupon($shop, $data);

        $promotion = DB::transaction(function () use ($shop, $data, $template): Promotion {
            $promotion = Promotion::query()->create($this->promotionAttributes($shop, $data, $template));
            $this->replaceConfiguration($promotion, $shop, $data, $template);

            return $promotion;
        });

        return redirect()
            ->route('merchant.promotions.edit', $promotion)
            ->with('success', 'Offer created successfully.');
    }

    public function edit(Request $request, Promotion $promotion): View
    {
        $shop = $this->authorizePromotion($request, $promotion);

        return view('merchant.promotions.edit', [
            'promotion' => $promotion->load(['template', 'rewards', 'conditions', 'targets', 'coupons']),
            ...$this->formData($shop),
        ]);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        $shop = $this->authorizePromotion($request, $promotion);
        $data = $request->validated();
        $template = $promotion->template()->where('status', PromotionTemplate::STATUS_ACTIVE)->firstOrFail();
        $this->validateTemplateConfiguration($template, $data);
        $this->validateTargets($shop, $data, $template);
        $this->validateCoupon($shop, $data, $promotion);

        DB::transaction(function () use ($promotion, $shop, $data, $template): void {
            $promotion->forceFill($this->promotionAttributes($shop, $data, $template, $promotion))->save();
            $this->replaceConfiguration($promotion, $shop, $data, $template);
        });

        return redirect()
            ->route('merchant.promotions.edit', $promotion)
            ->with('success', 'Offer updated successfully.');
    }

    public function destroy(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizePromotion($request, $promotion);

        if ($promotion->isSystemStarter()) {
            return redirect()
                ->route('merchant.promotions.index')
                ->with('error', 'Starter offers cannot be deleted. You can deactivate them instead.');
        }

        $promotion->forceFill([
            'deleted_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ])->save();
        $promotion->delete();

        return redirect()
            ->route('merchant.promotions.index')
            ->with('success', 'Offer deleted successfully.');
    }

    public function toggleStatus(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->authorizePromotion($request, $promotion);

        $nextStatus = $promotion->status === Promotion::STATUS_ACTIVE
            ? Promotion::STATUS_INACTIVE
            : Promotion::STATUS_ACTIVE;

        if ($nextStatus === Promotion::STATUS_ACTIVE) {
            $promotion->loadMissing(['template', 'rewards', 'conditions', 'targets']);

            if (! $promotion->isSetupComplete()) {
                $message = $promotion->setupIssues()[0] ?? 'Complete the offer setup before activating it.';

                return back()
                    ->withErrors(['promotion' => $message])
                    ->with('error', $message);
            }
        }

        $promotion->forceFill([
            'status' => $nextStatus,
            'updated_by' => Auth::id(),
        ])->save();

        return back()->with('success', 'Offer status updated successfully.');
    }

    private function promotionAttributes(Shop $shop, array $data, PromotionTemplate $template, ?Promotion $promotion = null): array
    {
        $refundMode = $data['refund_policy_mode'];
        $exchangeMode = $data['exchange_policy_mode'];

        return [
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->getKey(),
            'name' => trim((string) $data['name']),
            'slug' => $this->uniqueSlug($shop, (string) $data['name'], $promotion),
            'description' => $this->nullable($data['description'] ?? null),
            'status' => $data['status'],
            'activation_type' => $data['activation_type'],
            'origin' => $promotion?->origin ?? Promotion::ORIGIN_MERCHANT,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_combinable' => (bool) ($data['is_combinable'] ?? false),
            'priority' => (int) ($data['priority'] ?? 0),
            'total_usage_limit' => $this->nullableInt($data['total_usage_limit'] ?? null),
            'per_customer_usage_limit' => $this->nullableInt($data['per_customer_usage_limit'] ?? null),
            'new_customer_only' => (bool) ($data['new_customer_only'] ?? false),
            'refund_policy_mode' => $refundMode,
            'refund_window_days' => $refundMode === Promotion::POLICY_ALLOWED ? $this->nullableInt($data['refund_window_days'] ?? null) : null,
            'exchange_policy_mode' => $exchangeMode,
            'exchange_window_days' => $exchangeMode === Promotion::POLICY_ALLOWED ? $this->nullableInt($data['exchange_window_days'] ?? null) : null,
            'updated_by' => Auth::id(),
            ...($promotion ? [] : [
                'promotion_template_id' => $template->getKey(),
                'created_by' => Auth::id(),
            ]),
        ];
    }

    private function replaceConfiguration(Promotion $promotion, Shop $shop, array $data, PromotionTemplate $template): void
    {
        $promotion->conditions()->delete();
        $promotion->rewards()->delete();
        $promotion->targets()->delete();
        $promotion->coupons()->delete();

        $promotion->rewards()->create($this->rewardAttributes($data, $template));

        foreach ($this->conditionAttributes($data, $template) as $condition) {
            $promotion->conditions()->create($condition);
        }

        foreach ($this->targetAttributes($data, $template) as $target) {
            $promotion->targets()->create($target);
        }

        if ($data['activation_type'] === Promotion::ACTIVATION_COUPON) {
            $promotion->coupons()->create([
                'shop_id' => $shop->getKey(),
                'code' => Str::upper(trim((string) $data['coupon_code'])),
                'status' => $data['coupon_status'] ?? PromotionCoupon::STATUS_ACTIVE,
                'starts_at' => $data['coupon_starts_at'] ?? null,
                'ends_at' => $data['coupon_ends_at'] ?? null,
                'usage_limit' => $this->nullableInt($data['coupon_usage_limit'] ?? null),
                'per_customer_usage_limit' => $this->nullableInt($data['coupon_per_customer_usage_limit'] ?? null),
            ]);
        }
    }

    private function rewardAttributes(array $data, PromotionTemplate $template): array
    {
        $rewardType = $template->reward_type;

        return [
            'reward_type' => $rewardType,
            'value_type' => $data['value_type'] ?? null,
            'value_amount' => in_array($rewardType, [PromotionReward::TYPE_FIXED_DISCOUNT, PromotionReward::TYPE_FIXED_PRICE], true)
                || ($rewardType === PromotionReward::TYPE_QUANTITY_DISCOUNT && ($data['value_type'] ?? null) === 'amount')
                ? $this->nullableMoney($data['value_amount'] ?? null)
                : null,
            'value_percent' => in_array($rewardType, [PromotionReward::TYPE_PERCENTAGE_DISCOUNT, PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT], true)
                || ($rewardType === PromotionReward::TYPE_QUANTITY_DISCOUNT && ($data['value_type'] ?? null) === 'percent')
                ? $this->nullableMoney($data['value_percent'] ?? null)
                : null,
            'max_discount_amount' => $rewardType === PromotionReward::TYPE_PERCENTAGE_DISCOUNT
                ? $this->nullableMoney($data['max_discount_amount'] ?? null)
                : null,
            'buy_quantity' => in_array($rewardType, [PromotionReward::TYPE_BUY_X_GET_Y_FREE, PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT], true)
                ? $this->nullableInt($data['buy_quantity'] ?? null)
                : null,
            'get_quantity' => in_array($rewardType, [PromotionReward::TYPE_BUY_X_GET_Y_FREE, PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT], true)
                ? $this->nullableInt($data['get_quantity'] ?? null)
                : null,
            'bundle_quantity' => $rewardType === PromotionReward::TYPE_FIXED_BUNDLE_PRICE ? $this->nullableInt($data['bundle_quantity'] ?? null) : null,
            'bundle_price' => $rewardType === PromotionReward::TYPE_FIXED_BUNDLE_PRICE ? $this->nullableMoney($data['bundle_price'] ?? null) : null,
            'tier_config' => $rewardType === PromotionReward::TYPE_TIER_PRICING ? array_values($data['tier_config'] ?? []) : null,
            'metadata' => null,
        ];
    }

    private function conditionAttributes(array $data, PromotionTemplate $template): array
    {
        $conditions = [];

        if ($template->reward_type === PromotionReward::TYPE_QUANTITY_DISCOUNT) {
            $conditions[] = [
                'condition_type' => PromotionCondition::TYPE_MINIMUM_QUANTITY,
                'operator' => '>=',
                'value_numeric' => $this->nullableMoney($data['minimum_quantity'] ?? null),
                'sort_order' => 10,
            ];
        }

        if ($template->reward_type === PromotionReward::TYPE_FREE_GIFT) {
            $conditions[] = [
                'condition_type' => PromotionCondition::TYPE_MINIMUM_ELIGIBLE_SUBTOTAL,
                'operator' => '>=',
                'value_numeric' => $this->nullableMoney($data['minimum_eligible_subtotal'] ?? null),
                'sort_order' => 10,
            ];
        }

        return $conditions;
    }

    private function targetAttributes(array $data, PromotionTemplate $template): array
    {
        if (in_array($template->reward_type, [PromotionReward::TYPE_BUY_X_GET_Y_FREE, PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT], true)) {
            return [
                ...$this->targetsForRole(PromotionTarget::ROLE_BUY, $data['buy_target_scope'] ?? 'all', $data, 'buy_'),
                ...$this->targetsForRole(PromotionTarget::ROLE_GET, $data['get_target_scope'] ?? 'all', $data, 'get_'),
            ];
        }

        if ($template->reward_type === PromotionReward::TYPE_FREE_GIFT) {
            return [
                ...$this->targetsForRole(PromotionTarget::ROLE_ELIGIBLE, $data['target_scope'] ?? 'all', $data),
                ...$this->targetsForRole(PromotionTarget::ROLE_GIFT, 'products', $data, 'gift_'),
            ];
        }

        return $this->targetsForRole(PromotionTarget::ROLE_ELIGIBLE, $data['target_scope'] ?? 'all', $data);
    }

    private function targetsForRole(string $role, string $scope, array $data, string $prefix = ''): array
    {
        if ($scope === PromotionTarget::TYPE_ALL) {
            return [[
                'target_role' => $role,
                'target_type' => PromotionTarget::TYPE_ALL,
                'target_id' => null,
                'sort_order' => 10,
            ]];
        }

        $field = $prefix.$this->targetTypeForScope($scope).'_ids';
        $type = $this->targetTypeForScope($scope);

        if ($prefix === 'gift_' && $scope === 'products') {
            $field = 'gift_product_ids';
        }

        return collect($data[$field] ?? [])
            ->unique()
            ->values()
            ->map(fn ($id, $index): array => [
                'target_role' => $role,
                'target_type' => $type,
                'target_id' => (int) $id,
                'sort_order' => ($index + 1) * 10,
            ])
            ->all();
    }

    private function validateTemplateConfiguration(PromotionTemplate $template, array $data): void
    {
        $errors = [];

        match ($template->reward_type) {
            PromotionReward::TYPE_PERCENTAGE_DISCOUNT => $this->requireFields($errors, $data, ['value_percent']),
            PromotionReward::TYPE_FIXED_DISCOUNT, PromotionReward::TYPE_FIXED_PRICE => $this->requireFields($errors, $data, ['value_amount']),
            PromotionReward::TYPE_FIXED_BUNDLE_PRICE => $this->requireFields($errors, $data, ['bundle_quantity', 'bundle_price']),
            PromotionReward::TYPE_BUY_X_GET_Y_FREE => $this->requireFields($errors, $data, ['buy_quantity', 'get_quantity']),
            PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT => $this->requireFields($errors, $data, ['buy_quantity', 'get_quantity', 'value_percent']),
            PromotionReward::TYPE_QUANTITY_DISCOUNT => $this->validateQuantityDiscount($errors, $data),
            PromotionReward::TYPE_TIER_PRICING => $this->validateTierPricing($errors, $data),
            PromotionReward::TYPE_FREE_GIFT => $this->requireFields($errors, $data, ['minimum_eligible_subtotal']),
            default => $errors['promotion_template_id'] = 'Unsupported promotion template.',
        };

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateTargets(Shop $shop, array $data, PromotionTemplate $template): void
    {
        $targetGroups = in_array($template->reward_type, [PromotionReward::TYPE_BUY_X_GET_Y_FREE, PromotionReward::TYPE_BUY_X_GET_Y_DISCOUNT], true)
            ? [
                ['scope' => $data['buy_target_scope'] ?? 'all', 'prefix' => 'buy_'],
                ['scope' => $data['get_target_scope'] ?? 'all', 'prefix' => 'get_'],
            ]
            : [
                ['scope' => $data['target_scope'] ?? 'all', 'prefix' => ''],
            ];

        if ($template->reward_type === PromotionReward::TYPE_FREE_GIFT) {
            $targetGroups[] = ['scope' => 'products', 'prefix' => 'gift_'];
        }

        $errors = [];

        foreach ($targetGroups as $group) {
            if (! $group['scope']) {
                continue;
            }

            $scope = $group['scope'];
            $prefix = $group['prefix'];

            if ($scope === 'all') {
                continue;
            }

            $field = $prefix === 'gift_' && $scope === 'products'
                ? 'gift_product_ids'
                : $prefix.$this->targetTypeForScope($scope).'_ids';
            $ids = collect($data[$field] ?? [])->filter()->unique()->values();
            if ($ids->isEmpty()) {
                $errors[$field] = 'Choose at least one target.';
                continue;
            }

            $validCount = $this->validTargetCount($shop, $scope, $ids->all());
            if ($validCount !== $ids->count()) {
                $errors[$field] = 'Selected targets must belong to the active shop.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateCoupon(Shop $shop, array $data, ?Promotion $promotion = null): void
    {
        if (($data['activation_type'] ?? '') !== Promotion::ACTIVATION_COUPON) {
            return;
        }

        $code = Str::upper(trim((string) $data['coupon_code']));
        $exists = PromotionCoupon::query()
            ->where('shop_id', $shop->getKey())
            ->where('code', $code)
            ->when($promotion !== null, fn ($query) => $query->where('promotion_id', '!=', $promotion->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'coupon_code' => 'This coupon code is already used for the active shop.',
            ]);
        }
    }

    private function validTargetCount(Shop $shop, string $scope, array $ids): int
    {
        return match ($scope) {
            'products' => Product::query()
                ->where('merchant_id', $shop->merchant_id)
                ->where('shop_id', $shop->getKey())
                ->whereNull('deleted_at')
                ->whereIn('id', $ids)
                ->count(),
            'categories' => ProductCategory::query()
                ->whereIn('id', $ids)
                ->get()
                ->filter(fn (ProductCategory $category): bool => $category->rootCategoryId() === (int) $shop->root_product_category_id)
                ->count(),
            'brands' => Brand::query()
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->whereIn('id', $ids)
                ->whereHas('products', fn ($query) => $query
                    ->where('merchant_id', $shop->merchant_id)
                    ->where('shop_id', $shop->getKey())
                    ->where('status', '!=', 'archived')
                    ->whereNull('deleted_at'))
                ->count(),
            'collections' => ProductCollection::query()
                ->where('merchant_id', $shop->merchant_id)
                ->where('shop_id', $shop->getKey())
                ->whereNull('deleted_at')
                ->whereIn('id', $ids)
                ->count(),
            'variants' => ProductVariant::query()
                ->where('shop_id', $shop->getKey())
                ->whereNull('deleted_at')
                ->whereIn('id', $ids)
                ->count(),
            default => 0,
        };
    }

    private function targetTypeForScope(string $scope): string
    {
        return match ($scope) {
            'products' => PromotionTarget::TYPE_PRODUCT,
            'variants' => PromotionTarget::TYPE_VARIANT,
            'categories' => PromotionTarget::TYPE_CATEGORY,
            'brands' => PromotionTarget::TYPE_BRAND,
            'collections' => PromotionTarget::TYPE_COLLECTION,
            default => PromotionTarget::TYPE_ALL,
        };
    }

    private function requireFields(array &$errors, array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (($data[$field] ?? null) === null || $data[$field] === '') {
                $errors[$field] = 'This field is required for the selected offer type.';
            }
        }
    }

    private function validateQuantityDiscount(array &$errors, array $data): void
    {
        $this->requireFields($errors, $data, ['minimum_quantity', 'value_type']);

        if (($data['value_type'] ?? null) === 'percent') {
            $this->requireFields($errors, $data, ['value_percent']);
        } elseif (($data['value_type'] ?? null) === 'amount') {
            $this->requireFields($errors, $data, ['value_amount']);
        }
    }

    private function validateTierPricing(array &$errors, array $data): void
    {
        if (empty($data['tier_config']) || ! is_array($data['tier_config'])) {
            $errors['tier_config'] = 'At least one pricing tier is required.';
        }
    }

    private function formData(Shop $shop): array
    {
        return [
            'templates' => PromotionTemplate::query()->active()->orderBy('sort_order')->orderBy('name')->get(),
            'statuses' => $this->statuses(),
            'activationTypes' => $this->activationTypes(),
            'policyModes' => $this->policyModes(),
            'products' => Product::query()
                ->where('merchant_id', $shop->merchant_id)
                ->where('shop_id', $shop->getKey())
                ->whereNull('deleted_at')
                ->where('status', '!=', 'archived')
                ->orderBy('product_name')
                ->get(['id', 'product_name', 'slug']),
            'categories' => $this->categoryOptions($shop),
            'brands' => $this->brandOptions($shop),
            'collections' => ProductCollection::query()
                ->where('merchant_id', $shop->merchant_id)
                ->where('shop_id', $shop->getKey())
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'activeShop' => $shop,
        ];
    }

    private function categoryOptions(Shop $shop): Collection
    {
        $categories = ProductCategory::query()
            ->with('parent.parent')
            ->where(function ($query) use ($shop): void {
                $query->where('id', $shop->root_product_category_id)
                    ->orWhereHas('parent', fn ($query) => $query->where('id', $shop->root_product_category_id)->orWhereHas('parent', fn ($query) => $query->where('id', $shop->root_product_category_id)));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $categories->mapWithKeys(fn (ProductCategory $category): array => [
            $category->getKey() => $category->full_path,
        ]);
    }

    private function brandOptions(Shop $shop): Collection
    {
        return Brand::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereHas('products', fn ($query) => $query
                ->where('merchant_id', $shop->merchant_id)
                ->where('shop_id', $shop->getKey())
                ->where('status', '!=', 'archived')
                ->whereNull('deleted_at'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->unique('id')
            ->values();
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

    private function authorizePromotion(Request $request, Promotion $promotion): Shop
    {
        $shop = $this->activeShop($request);
        abort_unless((int) $promotion->shop_id === (int) $shop->getKey(), 404);
        abort_unless((int) $promotion->merchant_id === (int) $shop->merchant_id, 404);

        return $shop;
    }

    private function uniqueSlug(Shop $shop, string $name, ?Promotion $ignore = null): string
    {
        $base = Str::slug($name) ?: 'offer';
        $slug = $base;
        $suffix = 2;

        while (Promotion::query()
            ->where('shop_id', $shop->getKey())
            ->where('slug', $slug)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function statuses(): array
    {
        return [
            Promotion::STATUS_DRAFT => ['label' => 'Draft', 'badge_class' => 'bg-light text-body border'],
            Promotion::STATUS_ACTIVE => ['label' => 'Active', 'badge_class' => 'bg-success'],
            Promotion::STATUS_INACTIVE => ['label' => 'Inactive', 'badge_class' => 'bg-warning'],
        ];
    }

    private function activationTypes(): array
    {
        return [
            Promotion::ACTIVATION_AUTOMATIC => 'Automatic',
            Promotion::ACTIVATION_COUPON => 'Coupon Code',
        ];
    }

    private function policyModes(): array
    {
        return [
            Promotion::POLICY_INHERIT => 'Inherit Shop Policy',
            Promotion::POLICY_ALLOWED => 'Allowed',
            Promotion::POLICY_NOT_ALLOWED => 'Not Allowed',
        ];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableMoney(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : number_format((float) $value, 2, '.', '');
    }
}
