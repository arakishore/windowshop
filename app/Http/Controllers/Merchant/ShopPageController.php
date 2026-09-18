<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\ShopPageRequest;
use App\Models\Shop;
use App\Models\ShopPage;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopPageController extends Controller
{
    public function __construct(private readonly MerchantShopContextService $shopContext)
    {
    }

    public function index(Request $request): View
    {
        $shop = $this->activeShop($request);
        $pages = $shop->pages()
            ->orderByRaw("CASE WHEN page_type = 'standard' THEN 0 ELSE 1 END")
            ->orderBy('title')
            ->paginate((int) config('admin.pagination.per_page', 15));

        return view('merchant.shop-pages.index', compact('shop', 'pages'));
    }

    public function create(Request $request): View
    {
        return view('merchant.shop-pages.form', [
            'shop' => $this->activeShop($request),
            'page' => new ShopPage(['page_type' => ShopPage::TYPE_CUSTOM, 'status' => ShopPage::STATUS_DRAFT]),
        ]);
    }

    public function store(ShopPageRequest $request): RedirectResponse
    {
        $shop = $this->activeShop($request);
        $data = $request->pageData();
        $page = $shop->pages()->create([
            ...$data,
            'page_type' => ShopPage::TYPE_CUSTOM,
            'page_key' => null,
            'published_at' => $data['status'] === ShopPage::STATUS_PUBLISHED ? now() : null,
        ]);

        return redirect()->route('merchant.shop-pages.edit', $page)->with('success', 'Shop page created.');
    }

    public function edit(Request $request, ShopPage $shopPage): View
    {
        return view('merchant.shop-pages.form', [
            'shop' => $this->authorizePage($request, $shopPage),
            'page' => $shopPage,
        ]);
    }

    public function update(ShopPageRequest $request, ShopPage $shopPage): RedirectResponse
    {
        $this->authorizePage($request, $shopPage);
        $data = $request->pageData();
        $attributes = [
            'body' => $data['body'],
            'status' => $data['status'],
            'published_at' => $data['status'] === ShopPage::STATUS_PUBLISHED
                ? ($shopPage->published_at ?? now())
                : null,
        ];

        if ($shopPage->page_type === ShopPage::TYPE_CUSTOM) {
            $attributes['title'] = $data['title'];
            $attributes['slug'] = $data['slug'];
        }

        $shopPage->forceFill($attributes)->save();

        return redirect()->route('merchant.shop-pages.edit', $shopPage)->with('success', 'Shop page saved.');
    }

    public function preview(Request $request, ShopPage $shopPage): View
    {
        return view('merchant.shop-pages.preview', [
            'shop' => $this->authorizePage($request, $shopPage),
            'page' => $shopPage,
        ]);
    }

    public function destroy(Request $request, ShopPage $shopPage): RedirectResponse
    {
        $this->authorizePage($request, $shopPage);
        abort_unless($shopPage->page_type === ShopPage::TYPE_CUSTOM, 403);

        $shopPage->delete();

        return redirect()->route('merchant.shop-pages.index')->with('success', 'Custom page deleted.');
    }

    private function activeShop(Request $request): Shop
    {
        $merchant = $this->shopContext->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);

        $shop = $this->shopContext->resolveActiveShop(
            $this->shopContext->activeShops($merchant),
            $request->session()->get('active_shop_id'),
        );
        abort_unless($shop instanceof Shop, 403);

        return $shop;
    }

    private function authorizePage(Request $request, ShopPage $page): Shop
    {
        $shop = $this->activeShop($request);
        abort_unless((int) $page->shop_id === (int) $shop->getKey(), 404);

        return $shop;
    }
}
