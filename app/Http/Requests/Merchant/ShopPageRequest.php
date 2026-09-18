<?php

namespace App\Http\Requests\Merchant;

use App\Models\ShopPage;
use App\Services\Merchant\MerchantShopContextService;
use App\Services\Merchant\ShopPageInitializer;
use App\Services\Merchant\ShopPageContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ShopPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(MerchantShopContextService::class);
        $merchant = $context->activeMerchantForUser($this->user());

        if ($merchant === null) {
            return false;
        }

        $shop = $context->resolveActiveShop(
            $context->activeShops($merchant),
            $this->session()->get('active_shop_id'),
        );
        $page = $this->route('shopPage');

        return $shop !== null
            && (! $page instanceof ShopPage || (int) $page->shop_id === (int) $shop->getKey());
    }

    protected function prepareForValidation(): void
    {
        $page = $this->route('shopPage');

        if ($page instanceof ShopPage && $page->page_type === ShopPage::TYPE_STANDARD) {
            return;
        }

        $candidate = $this->input('slug') ?: ($page instanceof ShopPage ? $page->slug : $this->input('title'));
        $this->merge(['slug' => Str::slug((string) $candidate)]);
    }

    public function rules(): array
    {
        $page = $this->route('shopPage');
        $standard = $page instanceof ShopPage && $page->page_type === ShopPage::TYPE_STANDARD;
        $shopId = (int) $this->session()->get('active_shop_id');

        return [
            'shop_id' => ['prohibited'],
            'page_type' => ['prohibited'],
            'page_key' => ['prohibited'],
            'published_at' => ['prohibited'],
            'title' => $standard ? ['prohibited'] : ['required', 'string', 'max:180'],
            'slug' => $standard ? ['prohibited'] : [
                'required',
                'string',
                'max:180',
                Rule::notIn(array_keys(ShopPageInitializer::STANDARD_PAGES)),
                Rule::unique('shop_pages', 'slug')
                    ->where('shop_id', $shopId)
                    ->ignore($page instanceof ShopPage ? $page->getKey() : null),
            ],
            'body' => [
                'nullable',
                'string',
                'max:50000',
                'required_if:status,published',
                function (string $attribute, mixed $value, $fail): void {
                    if (is_string($value) && $this->containsUnsafeHtml($value)) {
                        $fail('Page content contains unsafe HTML.');
                    }
                },
            ],
            'status' => ['required', Rule::in([ShopPage::STATUS_DRAFT, ShopPage::STATUS_PUBLISHED])],
        ];
    }

    public function pageData(): array
    {
        $data = $this->validated();

        return [
            'title' => isset($data['title']) ? trim($data['title']) : null,
            'slug' => $data['slug'] ?? null,
            'body' => isset($data['body']) && preg_match('/<\/?[a-z][^>]*>/i', $data['body'])
                ? app(ShopPageContent::class)->render($data['body'])
                : ($data['body'] ?? null),
            'status' => $data['status'],
        ];
    }

    private function containsUnsafeHtml(string $value): bool
    {
        return preg_match('/<\s*(script|iframe|object|embed|link|meta|style)\b/i', $value) === 1
            || preg_match('/\son[a-z]+\s*=/i', $value) === 1
            || preg_match('/(?:href|src)\s*=\s*["\']?\s*javascript:/i', $value) === 1;
    }
}
