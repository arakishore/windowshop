<?php

namespace Database\Seeders;

use App\Enums\BannerPosition;
use App\Enums\BannerTemplateAvailability;
use App\Enums\BannerTemplateCategory;
use App\Models\BannerTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BannerTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $index => $template) {
            $code = strtolower($template['code']);
            $existingUuid = BannerTemplate::withTrashed()->where('code', $code)->value('uuid');

            $bannerTemplate = BannerTemplate::withTrashed()->updateOrCreate(
                ['code' => $code],
                [
                    'uuid' => $existingUuid ?: (string) Str::uuid(),
                    'category' => $template['category']->value,
                    'name' => $template['name'],
                    'description' => $template['description'] ?? 'Ready-made WindowShop promotional banner template.',
                    'default_title' => $template['title'] ?? $template['name'],
                    'default_subtitle' => $template['subtitle'],
                    'default_button_text' => 'Shop Now',
                    'desktop_image_path' => "banner-templates/{$code}/desktop.webp",
                    'mobile_image_path' => "banner-templates/{$code}/mobile.webp",
                    'default_position' => BannerPosition::STORE_HERO->value,
                    'availability' => BannerTemplateAvailability::BOTH->value,
                    'event_code' => $template['event_code'] ?? null,
                    'start_offset_days' => $template['start_offset_days'] ?? null,
                    'end_offset_days' => $template['end_offset_days'] ?? null,
                    'sort_order' => ($index + 1) * 10,
                    'status' => BannerTemplate::STATUS_ACTIVE,
                ],
            );

            if ($bannerTemplate->trashed()) {
                $bannerTemplate->restore();
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            ...$this->generalTemplates(),
            ...$this->festivalTemplates(),
            ...$this->seasonalTemplates(),
            ...$this->fashionTemplates(),
            ...$this->electronicsTemplates(),
            ...$this->groceryTemplates(),
            ...$this->serviceTemplates(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generalTemplates(): array
    {
        return [
            ['code' => 'generic_001', 'category' => BannerTemplateCategory::GENERAL, 'name' => 'Up to 50% OFF', 'subtitle' => 'Save big on selected products.'],
            ['code' => 'generic_002', 'category' => BannerTemplateCategory::GENERAL, 'name' => 'New Arrivals', 'subtitle' => 'Fresh products just arrived.'],
            ['code' => 'generic_003', 'category' => BannerTemplateCategory::GENERAL, 'name' => 'Limited Time Offer', 'subtitle' => "Grab these deals before they're gone."],
            ['code' => 'generic_004', 'category' => BannerTemplateCategory::GENERAL, 'name' => 'Shop the Latest Trends', 'subtitle' => "Discover what's trending today."],
            ['code' => 'generic_005', 'category' => BannerTemplateCategory::GENERAL, 'name' => 'Best Sellers', 'subtitle' => 'Customer favourites you will love.'],
            ['code' => 'generic_006', 'category' => BannerTemplateCategory::GENERAL, 'name' => 'Exclusive Online Deals', 'subtitle' => 'Available only online.'],
            ['code' => 'generic_007', 'category' => BannerTemplateCategory::GENERAL, 'name' => 'Weekend Sale', 'subtitle' => 'Special offers this weekend only.'],
            ['code' => 'generic_008', 'category' => BannerTemplateCategory::GENERAL, 'name' => 'Buy More, Save More', 'subtitle' => 'The more you buy, the more you save.'],
            ['code' => 'generic_009', 'category' => BannerTemplateCategory::GENERAL, 'name' => 'Clearance Sale', 'subtitle' => "Last chance before it's gone."],
            ['code' => 'generic_010', 'category' => BannerTemplateCategory::GENERAL, 'name' => 'Premium Collection', 'subtitle' => 'Premium quality products.'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function festivalTemplates(): array
    {
        return [
            ['code' => 'festival_001', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => 'Diwali Sale', 'subtitle' => 'Celebrate with festive savings.', 'event_code' => 'diwali', 'start_offset_days' => -10, 'end_offset_days' => 2],
            ['code' => 'festival_002', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => 'Holi Offers', 'subtitle' => 'Celebrate with colourful offers.', 'event_code' => 'holi', 'start_offset_days' => -7, 'end_offset_days' => 2],
            ['code' => 'festival_003', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => 'Independence Day Sale', 'subtitle' => 'Celebrate freedom with amazing deals.', 'event_code' => 'independence_day', 'start_offset_days' => -7, 'end_offset_days' => 1],
            ['code' => 'festival_004', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => 'Republic Day Sale', 'subtitle' => 'Exclusive Republic Day savings.', 'event_code' => 'republic_day', 'start_offset_days' => -7, 'end_offset_days' => 1],
            ['code' => 'festival_005', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => 'New Year Sale', 'subtitle' => 'Start the year with great savings.', 'event_code' => 'new_year', 'start_offset_days' => -12, 'end_offset_days' => 5],
            ['code' => 'festival_006', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => 'Christmas Sale', 'subtitle' => 'Celebrate Christmas with special offers.', 'event_code' => 'christmas', 'start_offset_days' => -10, 'end_offset_days' => 2],
            ['code' => 'festival_007', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => 'Eid Special', 'subtitle' => 'Celebrate Eid with exciting offers.', 'event_code' => 'eid', 'start_offset_days' => -7, 'end_offset_days' => 2],
            ['code' => 'festival_008', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => 'Raksha Bandhan', 'subtitle' => 'Celebrate the special bond with gifts.', 'event_code' => 'raksha_bandhan', 'start_offset_days' => -7, 'end_offset_days' => 1],
            ['code' => 'festival_009', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => 'Navratri Collection', 'subtitle' => 'Celebrate Navratri in style.', 'event_code' => 'navratri', 'start_offset_days' => -7, 'end_offset_days' => 2],
            ['code' => 'festival_010', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => 'Ganesh Festival', 'subtitle' => 'Celebrate Ganesh Festival with great offers.', 'event_code' => 'ganesh_festival', 'start_offset_days' => -7, 'end_offset_days' => 2],
            ['code' => 'festival_011', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => 'Wedding Season', 'subtitle' => 'Everything you need for the wedding season.', 'event_code' => 'wedding_season', 'start_offset_days' => -15, 'end_offset_days' => 15],
            ['code' => 'festival_012', 'category' => BannerTemplateCategory::FESTIVAL, 'name' => "Valentine's Special", 'subtitle' => 'Celebrate love with exclusive deals.', 'event_code' => 'valentines_day', 'start_offset_days' => -7, 'end_offset_days' => 1],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function seasonalTemplates(): array
    {
        return [
            ['code' => 'season_001', 'category' => BannerTemplateCategory::SEASONAL, 'name' => 'Summer Collection', 'subtitle' => 'Stay cool with the latest summer collection.'],
            ['code' => 'season_002', 'category' => BannerTemplateCategory::SEASONAL, 'name' => 'Winter Collection', 'subtitle' => 'Warm styles for the season.'],
            ['code' => 'season_003', 'category' => BannerTemplateCategory::SEASONAL, 'name' => 'Monsoon Sale', 'subtitle' => 'Rainy season savings.'],
            ['code' => 'season_004', 'category' => BannerTemplateCategory::SEASONAL, 'name' => 'Spring Collection', 'subtitle' => 'Fresh styles for spring.'],
            ['code' => 'season_005', 'category' => BannerTemplateCategory::SEASONAL, 'name' => 'End of Season Sale', 'subtitle' => 'Huge discounts before the season ends.'],
            ['code' => 'season_006', 'category' => BannerTemplateCategory::SEASONAL, 'name' => 'Back to School', 'subtitle' => 'Everything students need.'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fashionTemplates(): array
    {
        return [
            ['code' => 'fashion_001', 'category' => BannerTemplateCategory::FASHION, 'name' => 'New Fashion Arrivals', 'subtitle' => 'Fresh styles added for your next look.'],
            ['code' => 'fashion_002', 'category' => BannerTemplateCategory::FASHION, 'name' => 'Trending Styles', 'subtitle' => 'Shop looks everyone is talking about.'],
            ['code' => 'fashion_003', 'category' => BannerTemplateCategory::FASHION, 'name' => 'Ethnic Collection', 'subtitle' => 'Traditional styles for every occasion.'],
            ['code' => 'fashion_004', 'category' => BannerTemplateCategory::FASHION, 'name' => 'Casual Wear', 'subtitle' => 'Comfortable everyday fashion picks.'],
            ['code' => 'fashion_005', 'category' => BannerTemplateCategory::FASHION, 'name' => 'Premium Fashion', 'subtitle' => 'Elevated styles with premium quality.'],
            ['code' => 'fashion_006', 'category' => BannerTemplateCategory::FASHION, 'name' => 'Wedding Collection', 'subtitle' => 'Celebrate in styles made for special days.'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function electronicsTemplates(): array
    {
        return [
            ['code' => 'electronics_001', 'category' => BannerTemplateCategory::ELECTRONICS, 'name' => 'Latest Gadgets', 'subtitle' => 'Explore smart picks for modern living.'],
            ['code' => 'electronics_002', 'category' => BannerTemplateCategory::ELECTRONICS, 'name' => 'Smartphone Sale', 'subtitle' => 'Upgrade your phone with exciting offers.'],
            ['code' => 'electronics_003', 'category' => BannerTemplateCategory::ELECTRONICS, 'name' => 'Upgrade Today', 'subtitle' => 'Better tech for work, play, and home.'],
            ['code' => 'electronics_004', 'category' => BannerTemplateCategory::ELECTRONICS, 'name' => 'Tech Deals', 'subtitle' => 'Save on everyday electronics and accessories.'],
            ['code' => 'electronics_005', 'category' => BannerTemplateCategory::ELECTRONICS, 'name' => 'Smart Home Offers', 'subtitle' => 'Make your home smarter with great deals.'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groceryTemplates(): array
    {
        return [
            ['code' => 'grocery_001', 'category' => BannerTemplateCategory::GROCERY, 'name' => 'Fresh Every Day', 'subtitle' => 'Fresh picks delivered for your daily needs.'],
            ['code' => 'grocery_002', 'category' => BannerTemplateCategory::GROCERY, 'name' => 'Daily Essentials', 'subtitle' => 'Stock up on everyday household must-haves.'],
            ['code' => 'grocery_003', 'category' => BannerTemplateCategory::GROCERY, 'name' => 'Grocery Savings', 'subtitle' => 'Save more on your regular grocery basket.'],
            ['code' => 'grocery_004', 'category' => BannerTemplateCategory::GROCERY, 'name' => 'Healthy Choices', 'subtitle' => 'Nutritious picks for a healthier routine.'],
            ['code' => 'grocery_005', 'category' => BannerTemplateCategory::GROCERY, 'name' => 'Family Pack Offers', 'subtitle' => 'Value packs for the whole family.'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serviceTemplates(): array
    {
        return [
            ['code' => 'service_001', 'category' => BannerTemplateCategory::SERVICES, 'name' => 'Free Delivery', 'subtitle' => 'Enjoy free delivery on selected orders.'],
            ['code' => 'service_002', 'category' => BannerTemplateCategory::SERVICES, 'name' => 'Same Day Delivery', 'subtitle' => 'Get eligible orders delivered the same day.'],
            ['code' => 'service_003', 'category' => BannerTemplateCategory::SERVICES, 'name' => 'Easy Returns', 'subtitle' => 'Shop confidently with simple returns.'],
            ['code' => 'service_004', 'category' => BannerTemplateCategory::SERVICES, 'name' => 'Secure Payments', 'subtitle' => 'Pay safely with trusted payment options.'],
            ['code' => 'service_005', 'category' => BannerTemplateCategory::SERVICES, 'name' => 'Trusted Local Stores', 'subtitle' => 'Shop from reliable sellers near you.'],
        ];
    }
}
