<?php

namespace App\Services\Merchant;

use App\Models\Shop;

class ShopPageTemplateService
{
    public function render(string $pageKey, Shop $shop): ?string
    {
        $template = config("shop_page_templates.{$pageKey}");

        if (! is_string($template)) {
            return null;
        }

        $contacts = array_values(array_filter([
            trim((string) $shop->email),
            trim((string) $shop->mobile),
        ], fn (string $value): bool => $value !== ''));

        return strtr($template, [
            '{{shop_name}}' => $shop->name,
            '{{shop_contact}}' => $contacts !== []
                ? implode(' or ', $contacts)
                : 'Use the contact details shown on this shop\'s WindowShop page.',
        ]);
    }
}
