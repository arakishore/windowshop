<?php

namespace App\Services\Merchant;

use App\Models\ShopPage;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class ShopPageInitializer
{
    public function __construct(private readonly ShopPageTemplateService $templates)
    {
    }

    public const STANDARD_PAGES = [
        'about' => 'About Us',
        'privacy' => 'Privacy Policy',
        'terms' => 'Terms & Conditions',
        'policies' => 'Shop Policies',
    ];

    public function initialize(int $shopId): void
    {
        $shop = Shop::query()->findOrFail($shopId);
        $now = now();
        $pages = [];

        foreach (self::STANDARD_PAGES as $key => $title) {
            $pages[] = [
                'shop_id' => $shopId,
                'page_type' => ShopPage::TYPE_STANDARD,
                'page_key' => $key,
                'title' => $title,
                'slug' => $key,
                'body' => null,
                'status' => ShopPage::STATUS_DRAFT,
                'published_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('shop_pages')->insertOrIgnore($pages);
        $this->populateEmptyTemplates($shop);
    }

    private function populateEmptyTemplates(Shop $shop): void
    {
        foreach (['privacy', 'terms'] as $key) {
            ShopPage::query()
                ->where('shop_id', $shop->getKey())
                ->where('page_type', ShopPage::TYPE_STANDARD)
                ->where('page_key', $key)
                ->where(function ($query): void {
                    $query->whereNull('body')->orWhere('body', '');
                })
                ->update(['body' => $this->templates->render($key, $shop)]);
        }
    }

    public function initializeMany(iterable $shopIds): void
    {
        foreach ($shopIds as $shopId) {
            $this->initialize((int) $shopId);
        }
    }
}
