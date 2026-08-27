<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\Checkout\CheckoutPageService;
use App\Services\Storefront\CustomerOrderPresenter;
use App\Services\Storefront\NavigationService;
use App\Services\Storefront\ProductListingService;
use App\Services\Storefront\StorefrontCustomerContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function __construct(
        private readonly NavigationService $navigation,
        private readonly StorefrontCustomerContext $customerContext,
        private readonly CheckoutPageService $checkoutPage,
        private readonly ProductListingService $productListings,
        private readonly CustomerOrderPresenter $ordersPresenter,
    ) {
    }

    public function dashboard(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        return view('storefront.account.dashboard', $this->accountViewData($customer, [
            'orderCount' => $this->orderCount($customer),
            'addressCount' => $this->checkoutPage->addressesFor($customer)->count(),
            'wishlistCount' => $this->wishlistCount($customer),
        ]));
    }

    public function profile(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        return view('storefront.account.profile', $this->accountViewData($customer, [
            'globalCustomer' => $this->customerContext->customer($request),
        ]));
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        $globalCustomer = $this->customerContext->customer($request);
        abort_unless($globalCustomer instanceof Customer, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);
        $name = trim((string) $data['name']);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'The name field is required.',
            ]);
        }

        DB::transaction(function () use ($globalCustomer, $customer, $name): void {
            $globalCustomer->forceFill(['name' => $name])->save();

            if ((int) $globalCustomer->user_id === (int) $customer->getKey() && $customer->name !== $name) {
                $customer->forceFill(['name' => $name])->save();
            }
        });

        return redirect()
            ->route('storefront.account.profile')
            ->with('success', 'Profile updated.');
    }

    public function addresses(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        return view('storefront.account.addresses', $this->accountViewData($customer, [
            'addresses' => $this->checkoutPage->addressesFor($customer),
        ]));
    }

    public function orders(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        $globalCustomer = $this->customerContext->customer($request);
        abort_unless($globalCustomer instanceof Customer, 403);

        $orders = Order::query()
            ->with([
                'shop:id,name,slug',
                'items' => fn ($query) => $query->orderBy('id'),
                'items.product.primaryImage',
            ])
            ->where('customer_id', $globalCustomer->getKey())
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('storefront.account.orders', $this->accountViewData($customer, [
            'orders' => $orders,
            'presenter' => $this->ordersPresenter,
        ]));
    }

    public function orderDetail(Request $request, Order $order): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        $globalCustomer = $this->customerContext->customer($request);
        abort_unless($globalCustomer instanceof Customer, 403);
        abort_unless((int) $order->customer_id === (int) $globalCustomer->getKey(), 404);

        $order->load([
            'shop.country',
            'shop.state',
            'shop.city',
            'items.product.primaryImage',
            'items.taxComponents',
            'totals',
            'statusHistories',
            'comments' => fn ($query) => $query->where('visibility', OrderComment::VISIBILITY_CUSTOMER)->orderBy('created_at'),
        ]);

        return view('storefront.account.order-detail', $this->accountViewData($customer, [
            'order' => $order,
            'presenter' => $this->ordersPresenter,
        ]));
    }

    public function wishlist(Request $request): View|RedirectResponse
    {
        $customer = $this->customerOrRedirect($request);

        if (! $customer instanceof User) {
            return $customer;
        }

        $globalCustomer = $this->customerContext->customer($request);
        abort_unless($globalCustomer instanceof Customer, 403);

        $wishlistProducts = $this->wishlistItems($globalCustomer)
            ->map(fn (WishlistItem $item): array => $this->productListings->cardData($item->product))
            ->values();

        return view('storefront.account.wishlist', $this->accountViewData($customer, [
            'wishlistProducts' => $wishlistProducts,
            'wishlistedProductIds' => $wishlistProducts->pluck('product_id')->map(fn ($id): int => (int) $id)->all(),
        ]));
    }

    private function customerOrRedirect(Request $request): User|RedirectResponse
    {
        $customer = $this->customerContext->user($request);

        return $customer instanceof User
            ? $customer
            : redirect()->route('storefront.login');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function accountViewData(User $customer, array $data = []): array
    {
        return array_merge([
            'customer' => $customer,
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
        ], $data);
    }

    private function orderCount(User $customer): int
    {
        $globalCustomer = $customer->customer;

        if (! $globalCustomer instanceof Customer) {
            return 0;
        }

        return Order::query()
            ->where('customer_id', $globalCustomer->getKey())
            ->count();
    }

    private function wishlistCount(User $customer): int
    {
        $globalCustomer = $customer->customer;

        if (! $globalCustomer instanceof Customer) {
            return 0;
        }

        return WishlistItem::query()
            ->where('customer_id', $globalCustomer->getKey())
            ->count();
    }

    /**
     * @return Collection<int, WishlistItem>
     */
    private function wishlistItems(Customer $customer): Collection
    {
        $defaultVariantId = ProductVariant::query()
            ->select('id')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('status', 'active')
            ->where('is_sellable', true)
            ->where('is_default', true)
            ->where('mrp', '>', 0)
            ->where('selling_price', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(1);

        return WishlistItem::query()
            ->with(['product' => fn ($query) => $query
                ->select('products.*')
                ->selectSub($defaultVariantId, 'storefront_variant_id')
                ->with([
                    'brand:id,name',
                    'shop:id,merchant_id,name,slug,status',
                    'primaryImage' => fn ($query) => $query
                        ->where('status', 'active')
                        ->select('id', 'product_id', 'image_path', 'thumbnail_path', 'alt_text', 'status'),
                    'storefrontCardVariant:id,product_id,mrp,selling_price,stock_quantity,allow_backorder,is_default,status,is_sellable',
                ])
                ->where('products.status', 'active')
                ->whereHas('merchant', fn (Builder $query) => $query->where('status', 'active'))
                ->whereHas('shop', fn (Builder $query) => $query
                    ->where('status', 'active')
                    ->whereColumn('shops.merchant_id', 'products.merchant_id')
                    ->whereHas('merchant', fn (Builder $query) => $query->where('status', 'active')))
                ->whereExists($defaultVariantId)])
            ->where('customer_id', $customer->getKey())
            ->latest()
            ->get()
            ->filter(fn (WishlistItem $item): bool => $item->product !== null)
            ->values();
    }
}
