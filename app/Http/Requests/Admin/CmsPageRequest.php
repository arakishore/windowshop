<?php

namespace App\Http\Requests\Admin;

use App\Models\CmsPage;
use App\Services\Merchant\ShopPageContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CmsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $page = $this->route('cmsPage');
        if ($page instanceof CmsPage && $page->page_type === CmsPage::TYPE_STANDARD) {
            return;
        }

        $candidate = $this->input('slug') ?: ($page instanceof CmsPage ? $page->slug : $this->input('title'));
        $this->merge(['slug' => Str::slug((string) $candidate)]);
    }

    public function rules(): array
    {
        $page = $this->route('cmsPage');
        $standard = $page instanceof CmsPage && $page->page_type === CmsPage::TYPE_STANDARD;

        return [
            'page_type' => ['prohibited'],
            'page_key' => ['prohibited'],
            'published_at' => ['prohibited'],
            'title' => $standard ? ['prohibited'] : ['required', 'string', 'max:180'],
            'slug' => $standard ? ['prohibited'] : [
                'required', 'string', 'max:180',
                Rule::notIn(array_column(config('cms_pages.standard'), 'slug')),
                Rule::unique('cms_pages', 'slug')->ignore($page instanceof CmsPage ? $page->getKey() : null),
            ],
            'body' => [
                'nullable', 'string', 'max:50000', 'required_if:status,published',
                function (string $attribute, mixed $value, $fail): void {
                    if ($this->input('status') === CmsPage::STATUS_PUBLISHED && is_string($value)
                        && trim(strip_tags(app(ShopPageContent::class)->render($value))) === '') {
                        $fail('Page content is required to publish.');
                    }
                },
            ],
            'status' => ['required', Rule::in([CmsPage::STATUS_DRAFT, CmsPage::STATUS_PUBLISHED])],
        ];
    }

    public function pageData(): array
    {
        $data = $this->validated();

        return [
            'title' => isset($data['title']) ? trim($data['title']) : null,
            'slug' => $data['slug'] ?? null,
            'body' => isset($data['body']) ? app(ShopPageContent::class)->render($data['body']) : null,
            'status' => $data['status'],
        ];
    }
}
