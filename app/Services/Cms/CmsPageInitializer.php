<?php

namespace App\Services\Cms;

use App\Models\CmsPage;
use App\Services\Merchant\ShopPageContent;
use App\Services\System\SystemSettingService;

class CmsPageInitializer
{
    public function __construct(
        private readonly SystemSettingService $settings,
        private readonly ShopPageContent $content,
    ) {}

    public function initialize(): void
    {
        $name = e($this->settings->marketplaceName());

        foreach (config('cms_pages.standard', []) as $key => $template) {
            CmsPage::query()->firstOrCreate(
                ['page_key' => $key],
                [
                    'page_type' => CmsPage::TYPE_STANDARD,
                    'title' => $template['title'],
                    'slug' => $template['slug'],
                    'body' => $this->content->render(str_replace('{{marketplace_name}}', $name, $template['body'])),
                    'status' => CmsPage::STATUS_DRAFT,
                    'published_at' => null,
                ],
            );
        }
    }
}
