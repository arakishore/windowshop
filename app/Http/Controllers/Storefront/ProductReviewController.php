<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\SaveProductReviewRequest;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\ProductReview;
use App\Services\Review\ProductReviewEligibilityService;
use App\Services\Review\ProductReviewImageService;
use App\Services\Storefront\NavigationService;
use App\Services\Storefront\StorefrontCustomerContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ProductReviewController extends Controller
{
    public function __construct(
        private readonly StorefrontCustomerContext $customers,
        private readonly ProductReviewEligibilityService $eligibility,
        private readonly ProductReviewImageService $images,
        private readonly NavigationService $navigation,
    ) {}

    public function create(Request $request, OrderItem $orderItem): View|RedirectResponse
    {
        $customer = $this->customer($request);
        if (! $customer) {
            return $this->loginRedirect($request);
        }
        $this->eligibility->authorize($customer, $orderItem);
        abort_if($orderItem->reviewIncludingDeleted()->exists(), 409, 'This order item already has a review. Please contact support if it was removed.');

        return $this->form($request, $customer, $orderItem, new ProductReview);
    }

    public function store(SaveProductReviewRequest $request, OrderItem $orderItem): RedirectResponse
    {
        $customer = $this->customer($request);
        if (! $customer) {
            return $this->loginRedirect($request);
        }
        $this->eligibility->authorize($customer, $orderItem);
        abort_if($orderItem->reviewIncludingDeleted()->exists(), 409, 'This order item already has a review. Please contact support if it was removed.');

        $storedImages = collect();

        try {
            DB::transaction(function () use ($request, $customer, $orderItem, &$storedImages): void {
                $review = ProductReview::query()->create([
                    ...$request->safe()->only(['rating', 'title', 'review_text']),
                    'customer_id' => $customer->getKey(),
                    'order_id' => $orderItem->order_id,
                    'order_item_id' => $orderItem->getKey(),
                    'product_id' => $orderItem->product_id,
                    'product_variant_id' => $orderItem->product_variant_id,
                    'status' => ProductReview::STATUS_PENDING,
                ]);
                $storedImages = $this->images->store($review, $request->file('images', []));
            });
        } catch (Throwable $exception) {
            $storedImages->each(fn ($image) => $this->images->deleteFiles($image));
            throw $exception;
        }

        return redirect()->route('storefront.account.orders.show', $orderItem->order)->with('success', 'Your review was submitted for approval.');
    }

    public function edit(Request $request, ProductReview $review): View|RedirectResponse
    {
        $customer = $this->customer($request);
        if (! $customer) {
            return $this->loginRedirect($request);
        }
        abort_unless((int) $review->customer_id === (int) $customer->getKey(), 403);
        $this->eligibility->authorize($customer, $review->orderItem);

        return $this->form($request, $customer, $review->orderItem, $review);
    }

    public function update(SaveProductReviewRequest $request, ProductReview $review): RedirectResponse
    {
        $customer = $this->customer($request);
        if (! $customer) {
            return $this->loginRedirect($request);
        }
        abort_unless((int) $review->customer_id === (int) $customer->getKey(), 403);
        $this->eligibility->authorize($customer, $review->orderItem);
        $removeIds = collect($request->validated('remove_image_ids', []))->map(fn ($id): int => (int) $id);
        $removedImages = $review->images()->whereKey($removeIds)->get();
        $storedImages = collect();

        try {
            DB::transaction(function () use ($request, $review, $removedImages, &$storedImages): void {
                $review->update([
                    ...$request->safe()->only(['rating', 'title', 'review_text']),
                    'status' => ProductReview::STATUS_PENDING,
                    'moderated_by' => null,
                    'moderated_at' => null,
                ]);
                $review->images()->whereKey($removedImages->modelKeys())->delete();
                $remainingCount = $review->images()->count();
                $storedImages = $this->images->store($review, $request->file('images', []), $remainingCount);
            });
        } catch (Throwable $exception) {
            $storedImages->each(fn ($image) => $this->images->deleteFiles($image));
            throw $exception;
        }

        $removedImages->each(fn ($image) => $this->images->deleteFiles($image));

        return redirect()->route('storefront.account.orders.show', $review->order)->with('success', 'Your updated review was submitted for approval.');
    }

    private function form(Request $request, Customer $customer, OrderItem $item, ProductReview $review): View
    {
        $item->loadMissing(['order', 'product.primaryImage']);
        $review->loadMissing('images');

        return view('storefront.account.product-review', [
            'storefrontNavigationCategories' => $this->navigation->getMarketplaceCategories(),
            'customer' => $request->user(), 'globalCustomer' => $customer,
            'item' => $item, 'review' => $review,
        ]);
    }

    private function customer(Request $request): ?Customer
    {
        return $this->customers->customer($request);
    }

    private function loginRedirect(Request $request): RedirectResponse
    {
        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('storefront.login');
    }
}
