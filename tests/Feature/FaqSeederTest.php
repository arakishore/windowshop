<?php

namespace Tests\Feature;

use App\Models\Faq;
use Database\Seeders\FaqSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class FaqSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn (string $left, string $right): int => strcmp($left, $right));
        }
    }

    public function test_first_run_creates_default_catalogue(): void
    {
        $this->seed(FaqSeeder::class);

        $expected = count((new FaqSeeder)->catalogue());

        $this->assertGreaterThan(0, $expected);
        $this->assertSame($expected, Faq::query()->count());

        foreach (['general', 'customer', 'order', 'merchant'] as $category) {
            $this->assertTrue(
                Faq::query()->where('category', $category)->where('status', 'active')->exists(),
                "Expected active FAQs for category [{$category}]."
            );
        }

        Faq::query()->each(function (Faq $faq): void {
            $this->assertNotNull($faq->uuid);
            $this->assertNotNull($faq->question);
            $this->assertNotNull($faq->answer);
        });
    }

    public function test_second_run_creates_no_duplicates(): void
    {
        $this->seed(FaqSeeder::class);
        $count = Faq::query()->count();

        $this->seed(FaqSeeder::class);

        $this->assertSame($count, Faq::query()->count());
    }

    public function test_admin_edited_faq_is_preserved_on_rerun(): void
    {
        $this->seed(FaqSeeder::class);

        $faq = Faq::query()->firstOrFail();
        $faq->forceFill([
            'answer' => 'Admin edited answer.',
            'status' => Faq::STATUS_INACTIVE,
            'sort_order' => 999,
        ])->save();

        $this->seed(FaqSeeder::class);

        $faq->refresh();
        $this->assertSame('Admin edited answer.', $faq->answer);
        $this->assertSame(Faq::STATUS_INACTIVE, $faq->status);
        $this->assertSame(999, $faq->sort_order);
    }

    public function test_seeded_catalogue_entries_are_valid(): void
    {
        $entries = (new FaqSeeder)->catalogue();
        $allowedCategories = array_keys(Faq::categories());

        $this->assertNotEmpty($entries);

        $identities = [];

        foreach ($entries as $entry) {
            $this->assertContains($entry['category'], $allowedCategories);
            $this->assertContains($entry['status'], [Faq::STATUS_ACTIVE, Faq::STATUS_INACTIVE]);
            $this->assertLessThanOrEqual(255, mb_strlen($entry['question']));
            $this->assertLessThanOrEqual(2000, mb_strlen($entry['answer']));

            $identities[] = $entry['category']."\0".$entry['question'];
        }

        $this->assertSame(count($identities), count(array_unique($identities)), 'Catalogue identity (category + question) must be unique.');
    }

    public function test_seeded_faqs_appear_on_public_faq_page(): void
    {
        $this->seed(FaqSeeder::class);

        $response = $this->get(route('storefront.faq'))->assertOk();

        $response->assertSee('What is WindowShop?');
        $response->assertSee('How do I place an order?');
        $response->assertDontSee('No FAQs are available at the moment. Please contact us if you need help.');
    }
}
