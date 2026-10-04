<?php

namespace Tests\Feature;

use Tests\TestCase;

class CookieNoticeTest extends TestCase
{
    public function test_cookie_notice_partial_contains_information_and_dismissal_controls(): void
    {
        $html = file_get_contents(resource_path('views/storefront/partials/cookie-notice.blade.php'));
        $this->assertStringContainsString('We use cookies', $html);
        $this->assertStringContainsString('Cookie Policy', $html);
        $this->assertStringContainsString('Privacy Policy', $html);
        $this->assertStringContainsString('Got it', $html);
        $this->assertStringContainsString('windowshop_cookie_notice_v1', $html);
        $this->assertStringContainsString('Cookie Settings', file_get_contents(resource_path('views/storefront/partials/footer.blade.php')));
        $this->assertStringNotContainsString('Accept All', $html);
        $this->assertStringNotContainsString('Reject All', $html);
    }

    public function test_cookie_policy_view_contains_factual_fallback_content(): void
    {
        $html = file_get_contents(resource_path('views/storefront/pages/cookie-policy.blade.php'));
        $this->assertStringContainsString('Necessary cookies', $html);
        $this->assertStringContainsString('OpenStreetMap', $html);
        $this->assertStringContainsString('analytics or advertising cookies', $html);
        $this->assertSame('/cookie-policy', parse_url(route('storefront.cookie-policy'), PHP_URL_PATH));
    }
}
