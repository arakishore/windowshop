<?php

namespace Tests\Feature;

use App\Enums\UserRegistrationSource;
use App\Models\MerchantProfile;
use App\Models\Order;
use App\Models\OrderTotal;
use App\Models\Product;
use App\Models\User;
use App\Services\Merchant\MerchantCustomerService;
use App\Services\Product\ProductVariantManagementService;
use Database\Seeders\MasterData\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class MerchantPosTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        $pdo = DB::connection()->getPdo();

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->sqliteCreateCollation(
                'utf8mb4_unicode_ci',
                fn (string $left, string $right): int => strcmp($left, $right),
            );
        }
    }

    public function test_merchant_can_view_pos_with_active_shop_products(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $this->createPosProduct($shopId, 'Linen Shirt', 'White / M', 'LIN-M-WHT', 'POSBAR001');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.pos.index'));

        $response->assertOk();
        $response->assertSee('Merchant POS');
        $response->assertSee('POS for');
        $response->assertSee('POS Shop');
        $response->assertDontSee('Merchant Panel');
        $response->assertDontSee('Active Shop');
        $response->assertSee('Clear cart');
        $response->assertSee('Reprint last receipt');
        $response->assertSee('Held orders');
        $response->assertSee('Keyboard shortcuts');
        $response->assertSee('Search product name, SKU, or scan barcode');
        $response->assertSee('--pos-product-tile-size: 180px', false);
        $response->assertSee('data-play-add-sound="1"', false);
        $response->assertSee('Order time');
        $response->assertSee('Linen Shirt');
        $response->assertSee('White / M');
        $response->assertSee('Walk-in Customer');
        $response->assertSee('Complete Sale');
    }

    public function test_pos_page_loads_searchable_barcode_data_for_client_filtering(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $this->createPosProduct($shopId, 'Leather Wallet', 'Tan', 'WALLET-TAN', 'BAR-WALLET');
        $this->createPosProduct($shopId, 'Canvas Sneaker', 'Black / 8', 'SHOE-BLK-8', 'BAR-SHOE');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.pos.index', ['search' => 'BAR-WALLET']));

        $response->assertOk();
        $response->assertSee('Leather Wallet');
        $response->assertSee('BAR-WALLET');
        $response->assertSee('Tan');
        $response->assertSee('Canvas Sneaker');
        $response->assertSee('BAR-SHOE');
    }

    public function test_pos_search_returns_exact_barcode_match_before_text_results(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Blue Comfort Track Pants', 'Black / XL', 'TRACK-BLACK-XL', '0008901234567');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->getJson(route('merchant.pos.search', ['q' => "  0008901234567\r\n", 'scanner_mode' => 1]));

        $response
            ->assertOk()
            ->assertJsonPath('match_type', 'barcode')
            ->assertJsonPath('exact_match', true)
            ->assertJsonPath('auto_add', true)
            ->assertJsonPath('item.variant_id', $fixture['variant_id'])
            ->assertJsonPath('item.barcode', '0008901234567');
    }

    public function test_pos_search_falls_back_to_exact_sku_when_barcode_does_not_match(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Classic Polo T-Shirt', 'Black / XL', 'POLO-BLK-XL', 'BAR-POLO');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->getJson(route('merchant.pos.search', ['q' => 'POLO-BLK-XL']));

        $response
            ->assertOk()
            ->assertJsonPath('match_type', 'sku')
            ->assertJsonPath('exact_match', true)
            ->assertJsonPath('auto_add', true)
            ->assertJsonPath('item.variant_id', $fixture['variant_id']);
    }

    public function test_pos_search_product_name_results_do_not_auto_add(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $this->createPosProduct($shopId, 'Regular Fit Shirt', 'White / M', 'REG-WHT-M', 'BAR-REG');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->getJson(route('merchant.pos.search', ['q' => 'Regular']));

        $response
            ->assertOk()
            ->assertJsonPath('match_type', 'text')
            ->assertJsonPath('exact_match', false)
            ->assertJsonPath('auto_add', false)
            ->assertJsonCount(1, 'items');
    }

    public function test_pos_search_does_not_return_barcode_from_another_shop(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        [, $otherShopId] = $this->merchantShopFixture();
        $this->createPosProduct($otherShopId, 'Other Shop Product', 'Default', 'OTHER-SKU', 'OTHER-BAR');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->getJson(route('merchant.pos.search', ['q' => 'OTHER-BAR', 'scanner_mode' => 1]));

        $response
            ->assertOk()
            ->assertJsonPath('match_type', 'none')
            ->assertJsonPath('auto_add', false);
    }

    public function test_pos_search_ignores_inactive_and_deleted_products_or_variants(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $inactiveProduct = $this->createPosProduct($shopId, 'Inactive Product', 'Default', 'INACTIVE-PROD', 'INACTIVE-PROD-BAR');
        $inactiveVariant = $this->createPosProduct($shopId, 'Inactive Variant', 'Default', 'INACTIVE-VAR', 'INACTIVE-VAR-BAR');
        $deletedProduct = $this->createPosProduct($shopId, 'Deleted Product', 'Default', 'DELETED-PROD', 'DELETED-PROD-BAR');

        DB::table('products')->where('id', $inactiveProduct['product_id'])->update(['status' => 'inactive']);
        DB::table('product_variants')->where('id', $inactiveVariant['variant_id'])->update(['status' => 'inactive']);
        DB::table('products')->where('id', $deletedProduct['product_id'])->update(['deleted_at' => now()]);

        foreach (['INACTIVE-PROD-BAR', 'INACTIVE-VAR-BAR', 'DELETED-PROD-BAR'] as $barcode) {
            $this
                ->actingAs(User::query()->findOrFail($userId))
                ->withSession(['active_shop_id' => $shopId])
                ->getJson(route('merchant.pos.search', ['q' => $barcode, 'scanner_mode' => 1]))
                ->assertOk()
                ->assertJsonPath('match_type', 'none');
        }
    }

    public function test_pos_search_reports_duplicate_barcode_conflict_in_same_shop(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $first = $this->createPosProduct($shopId, 'First Barcode Product', 'Default', 'DUP-ONE', 'DUP-BAR');
        $this->createVariant($shopId, $first['product_id'], 'Second Duplicate', 'DUP-TWO', 'DUP-BAR');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->getJson(route('merchant.pos.search', ['q' => 'DUP-BAR', 'scanner_mode' => 1]));

        $response
            ->assertStatus(409)
            ->assertJsonPath('match_type', 'barcode')
            ->assertJsonPath('auto_add', false);
    }

    public function test_variant_update_rejects_duplicate_active_barcode_in_same_shop(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $first = $this->createPosProduct($shopId, 'First Product', 'Default', 'FIRST-SKU', 'FIRST-BAR');
        $second = $this->createPosProduct($shopId, 'Second Product', 'Default', 'SECOND-SKU', 'SECOND-BAR');
        $product = Product::query()->findOrFail($second['product_id']);

        $this->expectException(ValidationException::class);

        app(ProductVariantManagementService::class)->updateVariants(
            $product,
            [
                $second['variant_id'] => [
                    'sku' => 'SECOND-SKU',
                    'barcode' => 'FIRST-BAR',
                    'mrp' => 1299,
                    'selling_price' => 999,
                    'stock_quantity' => 12,
                    'low_stock_threshold' => 0,
                    'status' => 'active',
                ],
            ],
            User::query()->findOrFail($userId),
        );
    }

    public function test_variant_update_rejects_duplicate_barcode_from_another_shop(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        [, $otherShopId] = $this->merchantShopFixture();
        $this->createPosProduct($shopId, 'First Product', 'Default', 'FIRST-SKU', 'GLOBAL-BAR');
        $second = $this->createPosProduct($otherShopId, 'Second Product', 'Default', 'SECOND-SKU', 'OTHER-BAR');
        $product = Product::query()->findOrFail($second['product_id']);

        $this->expectException(ValidationException::class);

        app(ProductVariantManagementService::class)->updateVariants(
            $product,
            [
                $second['variant_id'] => [
                    'sku' => 'SECOND-SKU',
                    'barcode' => 'GLOBAL-BAR',
                    'mrp' => 1299,
                    'selling_price' => 999,
                    'stock_quantity' => 12,
                    'low_stock_threshold' => 0,
                    'status' => 'active',
                ],
            ],
            User::query()->findOrFail($userId),
        );
    }

    public function test_merchant_can_generate_missing_barcodes_for_product_variants(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Barcode Needed Product', 'Default', 'NEED-BAR', 'TEMP-BAR');
        DB::table('product_variants')->where('id', $fixture['variant_id'])->update(['barcode' => null]);
        $product = Product::query()->findOrFail($fixture['product_id']);

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.products.barcodes.generate', $product));

        $response->assertRedirect(route('merchant.products.edit', ['product' => $product, 'tab' => 'variants']));
        $barcode = DB::table('product_variants')->where('id', $fixture['variant_id'])->value('barcode');
        $this->assertIsString($barcode);
        $this->assertStringStartsWith('PS', $barcode);
    }

    public function test_merchant_can_generate_missing_barcodes_for_active_shop(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $first = $this->createPosProduct($shopId, 'First Missing Barcode', 'Default', 'MISS-ONE', 'TEMP-ONE');
        $second = $this->createPosProduct($shopId, 'Second Missing Barcode', 'Default', 'MISS-TWO', 'TEMP-TWO');
        DB::table('product_variants')->whereIn('id', [$first['variant_id'], $second['variant_id']])->update(['barcode' => null]);

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.barcodes.generate-missing'));

        $response->assertRedirect(route('merchant.barcodes.labels.index'));
        $barcodes = DB::table('product_variants')
            ->whereIn('id', [$first['variant_id'], $second['variant_id']])
            ->pluck('barcode')
            ->all();

        $this->assertCount(2, array_filter($barcodes));
        $this->assertNotSame($barcodes[0], $barcodes[1]);
        $this->assertStringStartsWith('PS', $barcodes[0]);
        $this->assertStringStartsWith('PS', $barcodes[1]);
    }

    public function test_merchant_can_open_barcode_label_selection_and_print_preview(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Print Label Shirt', 'Red / M', 'PRINT-RED-M', 'PRINTBAR001');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.barcodes.labels.index', ['q' => 'Print Label']))
            ->assertOk()
            ->assertSee('Barcode Labels')
            ->assertSee('Print Label Shirt')
            ->assertSee('PRINTBAR001')
            ->assertSee('30 per sheet - 63.5 x 25.4 mm')
            ->assertSee('Roll - 30 x 20 mm')
            ->assertSee('Quantity means how many labels to print');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.barcodes.labels.print'), [
                'template' => 'roll_50x25',
                'options' => [
                    'product_name' => 1,
                    'variant_name' => 1,
                    'sku' => 1,
                    'selling_price' => 1,
                    'barcode' => 1,
                    'shop_name' => 1,
                ],
                'variants' => [
                    $fixture['variant_id'] => ['quantity' => 2],
                ],
            ]);

        $response
            ->assertOk()
            ->assertSee('Print Barcode Labels')
            ->assertSee('PRINTBAR001')
            ->assertDontSee('Merchant Menu');
        $this->assertSame(2, substr_count($response->getContent(), 'Print Label Shirt'));
    }

    public function test_barcode_label_print_uses_top_quantity_when_row_quantities_are_zero(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Bulk Label Shirt', 'Blue / L', 'BULK-BLUE-L', 'BULKBAR001');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.barcodes.labels.print'), [
                'template' => 'a4_30',
                'bulk_quantity' => 3,
                'options' => [
                    'product_name' => 1,
                    'variant_name' => 1,
                    'sku' => 1,
                    'selling_price' => 1,
                    'barcode' => 1,
                    'shop_name' => 1,
                ],
                'variants' => [
                    $fixture['variant_id'] => [
                        'selected' => 1,
                        'quantity' => 0,
                    ],
                ],
            ]);

        $response
            ->assertOk()
            ->assertSee('BULKBAR001');
        $this->assertSame(3, substr_count($response->getContent(), 'Bulk Label Shirt'));
    }

    public function test_barcode_label_print_uses_variant_ids_with_top_quantity(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Simple Print Shirt', 'Green / M', 'SIMPLE-GREEN-M', 'SIMPLEBAR001');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.barcodes.labels.print'), [
                'template' => 'a4_30',
                'bulk_quantity' => 2,
                'variant_ids' => [$fixture['variant_id']],
                'options' => [
                    'product_name' => 1,
                    'variant_name' => 1,
                    'sku' => 1,
                    'selling_price' => 1,
                    'barcode' => 1,
                    'shop_name' => 1,
                ],
            ]);

        $response
            ->assertOk()
            ->assertSee('SIMPLEBAR001');
        $this->assertSame(2, substr_count($response->getContent(), 'Simple Print Shirt'));
    }

    public function test_barcode_label_print_falls_back_to_current_search_when_variant_ids_are_missing(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $this->createPosProduct($shopId, 'Search Fallback Shirt', 'Black / S', 'SEARCH-FALLBACK-S', 'SEARCHFALLBACK001');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.barcodes.labels.print'), [
                'template' => 'a4_30',
                'bulk_quantity' => 2,
                'q' => 'Search Fallback',
                'options' => [
                    'product_name' => 1,
                    'variant_name' => 1,
                    'sku' => 1,
                    'selling_price' => 1,
                    'barcode' => 1,
                    'shop_name' => 1,
                ],
            ]);

        $response
            ->assertOk()
            ->assertSee('SEARCHFALLBACK001');
        $this->assertSame(2, substr_count($response->getContent(), 'Search Fallback Shirt'));
    }

    public function test_barcode_label_print_shows_clear_empty_page_instead_of_redirecting(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.barcodes.labels.print'), [
                'template' => 'a4_30',
                'bulk_quantity' => 1,
                'q' => 'nothing matches this product',
            ])
            ->assertOk()
            ->assertSee('No labels to print');
    }

    public function test_barcode_label_print_generates_missing_barcodes_for_selected_variants(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Missing Barcode Label', 'Default', 'MISSING-LABEL', 'TEMP-MISSING');
        DB::table('product_variants')->where('id', $fixture['variant_id'])->update(['barcode' => null]);

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.barcodes.labels.print'), [
                'template' => 'a4_30',
                'bulk_quantity' => 1,
                'variant_ids' => [$fixture['variant_id']],
                'options' => [
                    'product_name' => 1,
                    'variant_name' => 1,
                    'sku' => 1,
                    'selling_price' => 1,
                    'barcode' => 1,
                    'shop_name' => 1,
                ],
            ]);

        $response
            ->assertOk()
            ->assertSee('Missing Barcode Label');
        $barcode = DB::table('product_variants')->where('id', $fixture['variant_id'])->value('barcode');
        $this->assertIsString($barcode);
        $this->assertStringStartsWith('PS', $barcode);
        $response->assertSee($barcode);
    }

    public function test_pos_cards_prefer_variant_mapped_images(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Cotton T-Shirt', 'Red / M', 'TSHIRT-RED-M', 'BAR-RED');
        $blueVariantId = $this->createVariant($shopId, $fixture['product_id'], 'Blue / M', 'TSHIRT-BLUE-M', 'BAR-BLUE');
        [$redValueId, $blueValueId] = $this->createImageAttributeMapping((int) $fixture['root_category_id']);

        DB::table('product_variant_attributes')->insert([
            [
                'product_variant_id' => $fixture['variant_id'],
                'product_attribute_group_id' => $fixture['attribute_group_id'],
                'product_attribute_group_value_id' => $redValueId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_variant_id' => $blueVariantId,
                'product_attribute_group_id' => $fixture['attribute_group_id'],
                'product_attribute_group_value_id' => $blueValueId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $this->attachMappedImage($fixture['product_id'], 'products/pos-red-shirt.webp', $redValueId);
        $this->attachMappedImage($fixture['product_id'], 'products/pos-blue-shirt.webp', $blueValueId);

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.pos.index'));

        $response->assertOk();
        $response->assertSee('products/pos-red-shirt.webp');
        $response->assertSee('products/pos-blue-shirt.webp');
    }

    public function test_pos_checkout_creates_cash_order_and_deducts_stock(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Linen Shirt', 'White / M', 'LIN-M-WHT', 'POSBAR001');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 2500,
                'elapsed_seconds' => 138,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 2],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Sale completed successfully.')
            ->assertJsonPath('order.grand_total', '1998.00')
            ->assertJsonPath('order.change_amount', '502.00')
            ->assertJsonPath('order.elapsed_seconds', 138);
        $receiptUrl = $response->json('order.receipt_url');
        $this->assertIsString($receiptUrl);
        $this->assertDatabaseHas('orders', [
            'shop_id' => $shopId,
            'created_source' => 'pos',
            'fulfilment_type' => 'counter',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'grand_total' => 1998,
            'amount_paid' => 2500,
            'change_amount' => 502,
            'elapsed_seconds' => 138,
        ]);
        $orderId = (int) DB::table('orders')->where('shop_id', $shopId)->value('id');
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_variant_id' => $fixture['variant_id'],
            'quantity' => 2,
            'line_total' => 1998,
        ]);
        $this->assertDatabaseHas('order_totals', [
            'order_id' => $orderId,
            'code' => 'grand_total',
            'amount' => 1998,
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $orderId,
            'from_status' => null,
            'to_status' => 'completed',
        ]);
        $this->assertSame(10, (int) DB::table('product_variants')->where('id', $fixture['variant_id'])->value('stock_quantity'));

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get($receiptUrl)
            ->assertOk()
            ->assertSee('POS Shop')
            ->assertDontSee('WindowShop POS')
            ->assertSee('Invoice :')
            ->assertSee('Date :')
            ->assertSee('Payment')
            ->assertSee('Thank you for shopping with us.')
            ->assertSee('Linen Shirt')
            ->assertSee('1,998.00');
    }

    public function test_pos_checkout_rejects_insufficient_stock_without_creating_order(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Linen Shirt', 'White / M', 'LIN-M-WHT', 'POSBAR001');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 25000,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 20],
                ],
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(12, (int) DB::table('product_variants')->where('id', $fixture['variant_id'])->value('stock_quantity'));
    }

    public function test_pos_checkout_supports_upi_payment_details(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Linen Shirt', 'White / M', 'LIN-M-WHT', 'POSBAR001');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'pickup',
                'payment_method' => 'upi',
                'payment_reference' => 'PHONEPE-REF-1',
                'upi_txn' => 'UPI123456',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('order.payment_method', 'upi')
            ->assertJsonPath('order.payment_reference', 'PHONEPE-REF-1')
            ->assertJsonPath('order.upi_txn', 'UPI123456');

        $this->assertDatabaseHas('orders', [
            'shop_id' => $shopId,
            'fulfilment_type' => 'pickup',
            'payment_method' => 'upi',
            'payment_reference' => 'PHONEPE-REF-1',
            'upi_txn' => 'UPI123456',
            'amount_paid' => 999,
            'change_amount' => 0,
        ]);
    }

    public function test_pos_uses_merchant_payment_settings_for_dropdown_and_checkout(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Payment Settings Shirt', 'White / M', 'PAY-M-WHT', 'PAYBAR001');
        $merchantId = $this->merchantIdForShop($shopId);

        $this->setMerchantSetting($merchantId, 'payment', 'default_payment_method', 'upi', 'string');
        $this->setMerchantSetting($merchantId, 'payment', 'allow_cash', '1', 'boolean');
        $this->setMerchantSetting($merchantId, 'payment', 'allow_upi', '1', 'boolean');
        $this->setMerchantSetting($merchantId, 'payment', 'allow_card', '0', 'boolean');
        $this->setMerchantSetting($merchantId, 'payment', 'allow_credit', '0', 'boolean');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.pos.index'))
            ->assertOk()
            ->assertSee('<option value="upi" selected>UPI</option>', false)
            ->assertDontSee('<option value="card"', false)
            ->assertDontSee('<option value="credit"', false);

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'payment_method' => 'card',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');
    }

    public function test_pos_credit_sale_is_saved_as_unpaid(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Credit Shirt', 'White / M', 'CRED-M-WHT', 'CREDBAR001');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 0,
                'fulfilment_type' => 'counter',
                'payment_method' => 'credit',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('order.payment_method', 'credit');

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('order.id'),
            'payment_method' => Order::PAYMENT_METHOD_CREDIT,
            'payment_status' => Order::PAYMENT_UNPAID,
            'amount_paid' => '0.00',
        ]);
    }

    public function test_pos_cash_rounding_setting_is_applied_to_saved_totals(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Rounded Shirt', 'White / M', 'ROUND-M-WHT', 'ROUNDBAR001');
        $merchantId = $this->merchantIdForShop($shopId);

        DB::table('product_variants')
            ->where('id', $fixture['variant_id'])
            ->update(['selling_price' => 999.25]);
        $this->setMerchantSetting($merchantId, 'pos', 'cash_rounding.method', 'up', 'string');
        $this->setMerchantSetting($merchantId, 'pos', 'cash_rounding.apply_to', 'cash', 'string');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 1000,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('order.grand_total', '1000.00');

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('order.id'),
            'subtotal' => '999.25',
            'rounding_adjustment' => '0.75',
            'grand_total' => '1000.00',
        ]);
        $this->assertDatabaseHas('order_totals', [
            'order_id' => $response->json('order.id'),
            'code' => OrderTotal::CODE_ROUNDING,
            'amount' => '0.75',
        ]);
    }

    public function test_pos_pricing_endpoint_returns_zero_tax_payload_when_tax_is_disabled(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'No Tax Shirt', 'Default', 'NO-TAX', 'BAR-NO-TAX');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.pricing'), [
                'payment_method' => Order::PAYMENT_METHOD_CASH,
                'amount_paid' => 1000,
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('pricing.tax_display_enabled', false)
            ->assertJsonPath('pricing.summary.subtotal', '999.00')
            ->assertJsonPath('pricing.summary.tax_total', '0.00')
            ->assertJsonPath('pricing.summary.grand_total', '999.00')
            ->assertJsonPath('pricing.items.0.tax_resolution_source', 'tax_disabled');
    }

    public function test_pos_pricing_endpoint_displays_inclusive_tax_without_adding_it_again(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Inclusive Tax Shirt', 'Default', 'INC-TAX', 'BAR-INC-TAX');
        $this->enablePosTax($shopId, $fixture['product_category_id'], true);

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.pricing'), [
                'payment_method' => Order::PAYMENT_METHOD_CASH,
                'amount_paid' => 1000,
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('pricing.tax_display_enabled', true)
            ->assertJsonPath('pricing.items.0.price_mode', 'inclusive')
            ->assertJsonPath('pricing.items.0.line_subtotal', '999.00')
            ->assertJsonPath('pricing.items.0.line_tax', '47.57')
            ->assertJsonPath('pricing.items.0.line_total', '999.00')
            ->assertJsonPath('pricing.summary.tax_total', '47.57')
            ->assertJsonPath('pricing.summary.grand_total', '999.00');
    }

    public function test_pos_pricing_endpoint_displays_exclusive_tax_separately(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Exclusive Tax Shirt', 'Default', 'EXC-TAX', 'BAR-EXC-TAX');
        $this->enablePosTax($shopId, $fixture['product_category_id'], false);

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.pricing'), [
                'payment_method' => Order::PAYMENT_METHOD_CASH,
                'amount_paid' => 1100,
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('pricing.tax_display_enabled', true)
            ->assertJsonPath('pricing.items.0.price_mode', 'exclusive')
            ->assertJsonPath('pricing.items.0.line_subtotal', '999.00')
            ->assertJsonPath('pricing.items.0.line_tax', '49.95')
            ->assertJsonPath('pricing.items.0.line_total', '1048.95')
            ->assertJsonPath('pricing.summary.tax_total', '49.95')
            ->assertJsonPath('pricing.summary.grand_total', '1049.00');
    }

    public function test_pos_pricing_endpoint_updates_quantity_line_discount_and_order_discount_totals(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Discount Tax Shirt', 'Default', 'DISC-TAX', 'BAR-DISC-TAX');
        $this->enablePosTax($shopId, $fixture['product_category_id'], false);

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.pricing'), [
                'payment_method' => Order::PAYMENT_METHOD_CASH,
                'amount_paid' => 2000,
                'order_discount' => ['type' => 'amount', 'value' => 100],
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 2, 'discount_type' => 'percent', 'discount_value' => 10],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('pricing.items.0.line_subtotal', '1998.00')
            ->assertJsonPath('pricing.items.0.line_discount', '199.80')
            ->assertJsonPath('pricing.items.0.line_tax', '89.91')
            ->assertJsonPath('pricing.summary.subtotal', '1998.00')
            ->assertJsonPath('pricing.summary.discount_total', '299.80')
            ->assertJsonPath('pricing.summary.tax_total', '89.91')
            ->assertJsonPath('pricing.summary.rounding_adjustment', '-0.11')
            ->assertJsonPath('pricing.summary.grand_total', '1788.00');
    }

    public function test_pos_pricing_ignores_fake_browser_totals_and_checkout_matches_payload(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Server Priced Shirt', 'Default', 'SERVER-TAX', 'BAR-SERVER-TAX');
        $this->enablePosTax($shopId, $fixture['product_category_id'], false);
        $payload = [
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'amount_paid' => 2000,
            'subtotal' => 1,
            'tax_total' => 999,
            'grand_total' => 1,
            'items' => [
                [
                    'product_variant_id' => $fixture['variant_id'],
                    'quantity' => 1,
                    'line_tax' => 999,
                    'line_total' => 1,
                ],
            ],
        ];

        $pricing = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.pricing'), $payload);

        $pricing
            ->assertOk()
            ->assertJsonPath('pricing.summary.subtotal', '999.00')
            ->assertJsonPath('pricing.summary.tax_total', '49.95')
            ->assertJsonPath('pricing.summary.grand_total', '1049.00');

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                ...$payload,
                'fulfilment_type' => 'counter',
            ]);

        $checkout
            ->assertOk()
            ->assertJsonPath('order.grand_total', $pricing->json('pricing.summary.grand_total'));
    }

    public function test_pos_discount_settings_are_enforced_at_checkout(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Discount Disabled Shirt', 'White / M', 'NODISC-M-WHT', 'NODISCBAR001');
        $merchantId = $this->merchantIdForShop($shopId);

        $this->setMerchantSetting($merchantId, 'pos', 'order.allow_order_discount', '0', 'boolean');
        $this->setMerchantSetting($merchantId, 'pos', 'order.allow_item_discount', '0', 'boolean');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'order_discount' => ['type' => 'percent', 'value' => 10],
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_discount');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1, 'discount_type' => 'percent', 'discount_value' => 10],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.discount_value');
    }

    public function test_pos_checkout_applies_percent_line_discount(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Discount Shirt', 'White / M', 'DISC-M-WHT', 'DISCBAR001');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 2000,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 2, 'discount_type' => 'percent', 'discount_value' => 10],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('order.grand_total', '1798.00');

        $orderId = (int) DB::table('orders')->where('shop_id', $shopId)->value('id');
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_variant_id' => $fixture['variant_id'],
            'item_discount_type' => 'percent',
            'item_discount_value' => 10,
            'line_discount' => 199.80,
            'line_total' => 1798.20,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'subtotal' => 1998,
            'discount_total' => 199.80,
            'rounding_adjustment' => -0.20,
            'grand_total' => 1798,
        ]);
        $this->assertDatabaseHas('order_totals', [
            'order_id' => $orderId,
            'code' => OrderTotal::CODE_ROUNDING,
            'amount' => -0.20,
        ]);
    }

    public function test_pos_checkout_applies_amount_line_discount_and_order_discounts(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $first = $this->createPosProduct($shopId, 'Amount Shirt', 'White / M', 'AMT-M-WHT', 'AMTBAR001');
        $secondVariantId = $this->createVariant($shopId, $first['product_id'], 'Black / M', 'AMT-M-BLK', 'AMTBAR002');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 2000,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'order_discount' => [
                    'type' => 'amount',
                    'value' => 100,
                    'reason' => 'Customer Loyalty',
                    'note' => 'Regular buyer',
                ],
                'items' => [
                    ['product_variant_id' => $first['variant_id'], 'quantity' => 1, 'discount_type' => 'amount', 'discount_value' => 100],
                    ['product_variant_id' => $secondVariantId, 'quantity' => 1, 'discount_type' => 'percent', 'discount_value' => 10],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('order.grand_total', '1698.00');

        $orderId = (int) DB::table('orders')->where('shop_id', $shopId)->value('id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'subtotal' => 1998,
            'discount_total' => 299.90,
            'order_discount_type' => 'amount',
            'order_discount_value' => 100,
            'order_discount_amount' => 100,
            'order_discount_reason' => 'Customer Loyalty',
            'rounding_adjustment' => -0.10,
            'grand_total' => 1698,
        ]);
        $this->assertDatabaseHas('order_totals', [
            'order_id' => $orderId,
            'code' => 'item_discount',
            'amount' => -199.90,
        ]);
        $this->assertDatabaseHas('order_totals', [
            'order_id' => $orderId,
            'code' => 'order_discount',
            'amount' => -100,
        ]);
        $this->assertDatabaseHas('order_totals', [
            'order_id' => $orderId,
            'code' => OrderTotal::CODE_ROUNDING,
            'amount' => -0.10,
        ]);
    }

    public function test_pos_checkout_applies_percent_order_discount_after_line_discounts(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Order Discount Shirt', 'White / M', 'OD-M-WHT', 'ODBAR001');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 1000,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'order_discount' => ['type' => 'percent', 'value' => 10],
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1, 'discount_type' => 'amount', 'discount_value' => 99],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('order.grand_total', '810.00');

        $this->assertDatabaseHas('orders', [
            'shop_id' => $shopId,
            'subtotal' => 999,
            'discount_total' => 189,
            'order_discount_type' => 'percent',
            'order_discount_value' => 10,
            'order_discount_amount' => 90,
            'grand_total' => 810,
        ]);
    }

    public function test_pos_checkout_rejects_invalid_discounts(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Invalid Discount Shirt', 'White / M', 'BAD-M-WHT', 'BADBAR001');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1, 'discount_type' => 'percent', 'discount_value' => 101],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.discount_value');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'order_discount' => ['type' => 'amount', 'value' => 1000],
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1, 'discount_type' => 'amount', 'discount_value' => 999],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order_discount.discount_value');
    }

    public function test_pos_receipt_shows_item_and_order_discount_totals(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Receipt Discount Shirt', 'White / M', 'REC-M-WHT', 'RECBAR001');
        $merchantId = (int) DB::table('shops')->where('id', $shopId)->value('merchant_id');

        DB::table('merchant_profiles')
            ->where('id', $merchantId)
            ->update(['gst_number' => '27ABCDE1234F1Z5']);

        foreach ([
            'receipt.line_item.show_sku' => ['1', 'boolean'],
            'receipt.footer' => ['Footer note', 'string'],
            'receipt.return_policy' => ['Returns within 7 days.', 'string'],
        ] as $key => [$value, $type]) {
            DB::table('merchant_settings')->insert([
                'merchant_id' => $merchantId,
                'group' => 'pos',
                'setting_key' => $key,
                'setting_value' => $value,
                'setting_type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 1000,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'order_discount' => ['type' => 'amount', 'value' => 50],
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1, 'discount_type' => 'amount', 'discount_value' => 100],
                ],
            ]);

        $receiptUrl = $response->json('order.receipt_url');
        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get($receiptUrl)
            ->assertOk()
            ->assertSee('Line Discount')
            ->assertSee('Item Discount')
            ->assertSee('Order Discount')
            ->assertSee('GSTIN : 27ABCDE1234F1Z5')
            ->assertSee('<div class="receipt-qr">QR</div>', false)
            ->assertSee('SKU: REC-M-WHT')
            ->assertSee('Footer note')
            ->assertSee('Returns within 7 days.')
            ->assertSee('849.00');

        foreach (['receipt.show_gst_number', 'receipt.show_qr_code'] as $key) {
            DB::table('merchant_settings')->insert([
                'merchant_id' => $merchantId,
                'group' => 'pos',
                'setting_key' => $key,
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get($receiptUrl)
            ->assertOk()
            ->assertDontSee('GSTIN : 27ABCDE1234F1Z5')
            ->assertDontSee('<div class="receipt-qr">QR</div>', false);
    }

    public function test_pos_receipt_hides_tax_for_tax_disabled_order(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Receipt No Tax Shirt', 'Default', 'REC-NO-TAX', 'REC-NO-TAX-BAR');

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 1000,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ]);

        $checkout->assertOk();

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get($checkout->json('order.receipt_url'))
            ->assertOk()
            ->assertDontSee('Taxable')
            ->assertDontSee('Tax</span>', false)
            ->assertDontSee('tax pricing');

        $order = Order::query()->findOrFail($checkout->json('order.id'));
        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.sales.show', $order))
            ->assertOk()
            ->assertDontSee('Taxable')
            ->assertDontSee('Tax</th>', false)
            ->assertDontSee('tax pricing');
    }

    public function test_pos_receipt_displays_saved_inclusive_tax_snapshot(): void
    {
        $sale = $this->createTaxedPosOrder(true);

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->get($sale['receipt_url'])
            ->assertOk()
            ->assertSee('Taxable')
            ->assertSee('951.43')
            ->assertSee('GST 5% POS 5% Included')
            ->assertSee('47.57')
            ->assertSee('CGST 2.5%')
            ->assertSee('SGST 2.5%')
            ->assertSee('Inclusive tax pricing')
            ->assertSee('999.00');
    }

    public function test_pos_receipt_displays_saved_exclusive_tax_snapshot_after_merchant_tax_is_disabled(): void
    {
        $sale = $this->createTaxedPosOrder(false);

        DB::table('merchant_tax_settings')
            ->where('merchant_id', $this->merchantIdForShop($sale['shop_id']))
            ->update(['tax_enabled' => false, 'updated_at' => now()]);

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->get($sale['receipt_url'])
            ->assertOk()
            ->assertSee('GST 5% POS 5%')
            ->assertSee('49.95')
            ->assertSee('CGST 2.5%')
            ->assertSee('SGST 2.5%')
            ->assertSee('Exclusive tax pricing')
            ->assertSee('1,049.00');
    }

    public function test_pos_receipt_shows_saved_tax_amount_when_component_rows_are_missing(): void
    {
        $sale = $this->createTaxedPosOrder(false);
        DB::table('order_item_tax_components')->where('order_item_id', $sale['order']->items->first()->getKey())->delete();

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->get($sale['receipt_url'])
            ->assertOk()
            ->assertSee('GST 5% POS 5%')
            ->assertSee('49.95')
            ->assertDontSee('CGST 2.5%')
            ->assertDontSee('SGST 2.5%');
    }

    public function test_sales_order_detail_displays_saved_tax_snapshot_and_saved_totals(): void
    {
        $sale = $this->createTaxedPosOrder(false);

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->get(route('merchant.sales.show', $sale['order']))
            ->assertOk()
            ->assertSee('Taxable')
            ->assertSee('999.00')
            ->assertSee('49.95')
            ->assertSee('GST 5% POS 5%')
            ->assertSee('CGST 2.5%')
            ->assertSee('SGST 2.5%')
            ->assertSee('Exclusive tax pricing')
            ->assertSee('1,049.00');
    }

    public function test_receipt_and_order_detail_ignore_later_tax_master_changes(): void
    {
        $sale = $this->createTaxedPosOrder(false);
        $orderItem = $sale['order']->items->first();

        DB::table('tax_classes')->where('id', $orderItem->tax_class_id)->update([
            'name' => 'GST 20% POS',
            'status' => 'deleted',
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tax_rates')->where('id', $orderItem->tax_rate_id)->update([
            'name' => 'GST 20% POS',
            'total_rate' => '20.0000',
            'status' => 'deleted',
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->get($sale['receipt_url'])
            ->assertOk()
            ->assertSee('GST 5% POS 5%')
            ->assertDontSee('GST 20% POS')
            ->assertDontSee('20%');

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->get(route('merchant.sales.show', $sale['order']))
            ->assertOk()
            ->assertSee('GST 5% POS 5%')
            ->assertDontSee('GST 20% POS')
            ->assertDontSee('20%');
    }

    public function test_pos_can_search_customer_and_add_shipping_address(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $customerId = $this->createCustomer($shopId, 'Delivery Buyer', '9876543210', 'delivery@example.test');
        $customerUuid = (string) DB::table('merchant_customers')->where('id', $customerId)->value('uuid');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->getJson(route('merchant.pos.customers', ['q' => '9876543210']))
            ->assertOk()
            ->assertJsonPath('customers.0.merchant_customer_id', $customerId)
            ->assertJsonPath('customers.0.customer_id', (int) DB::table('merchant_customers')->where('id', $customerId)->value('customer_id'))
            ->assertJsonPath('customers.0.route_key', $customerUuid)
            ->assertJsonPath('customers.0.name', 'Delivery Buyer');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.customers.addresses.store', $customerUuid), [
                'label' => 'Home',
                'recipient_name' => 'Delivery Buyer',
                'recipient_mobile_country_code' => '+91',
                'recipient_mobile' => '9876543210',
                'address_line_1' => 'Delivery Line One',
                'landmark' => 'Near Market',
                'postal_code' => '560001',
                'is_default_shipping' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('address.label', 'Home')
            ->assertJsonPath('address.address_line_1', 'Delivery Line One');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->getJson(route('merchant.pos.customers.addresses', $customerUuid))
            ->assertOk()
            ->assertJsonCount(1, 'addresses')
            ->assertJsonPath('addresses.0.is_default_shipping', true);
    }

    public function test_pos_can_quick_add_walk_in_customer_by_mobile(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $merchantId = $this->merchantIdForShop($shopId);

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.customers.store'), [
                'mobile_country_code' => '+91',
                'mobile' => '9876543210',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('customer.name', 'Walk-in Customer - 9876543210')
            ->assertJsonPath('customer.mobile', '9876543210');

        $customerId = (int) $response->json('customer.merchant_customer_id');
        $globalCustomerId = (int) $response->json('customer.customer_id');
        $this->assertDatabaseHas('merchant_customers', [
            'id' => $customerId,
            'merchant_id' => $merchantId,
            'status' => 'active',
        ]);
        $this->assertSame($globalCustomerId, (int) DB::table('merchant_customers')->where('id', $customerId)->value('customer_id'));
        $this->assertGreaterThan(0, $globalCustomerId);
        $this->assertDatabaseHas('customers', [
            'id' => $globalCustomerId,
            'email' => null,
            'mobile' => '9876543210',
            'status' => 'active',
        ]);

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.customers.store'), [
                'name' => 'Duplicate Buyer',
                'mobile_country_code' => '+91',
                'mobile' => '9876543210',
            ])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('customer.merchant_customer_id', $customerId)
            ->assertJsonPath('customer.customer_id', $globalCustomerId);

        $this->assertSame(1, DB::table('merchant_customers')
            ->where('merchant_id', $merchantId)
            ->where('customer_id', $globalCustomerId)
            ->count());
        $this->assertSame(1, DB::table('customers')
            ->where('mobile_normalized', '919876543210')
            ->count());
    }

    public function test_pos_customer_with_real_email_stores_it_and_reuses_unique_email(): void
    {
        [$firstUserId, $firstShopId] = $this->merchantShopFixture();
        [$secondUserId, $secondShopId] = $this->merchantShopFixture();

        $first = $this
            ->actingAs(User::query()->findOrFail($firstUserId))
            ->withSession(['active_shop_id' => $firstShopId])
            ->postJson(route('merchant.pos.customers.store'), [
                'name' => 'Email POS Buyer',
                'mobile' => '9876543210',
                'email' => 'EmailBuyer@Example.TEST',
            ]);

        $first->assertCreated();
        $globalCustomerId = (int) $first->json('customer.customer_id');
        $this->assertSame($globalCustomerId, (int) DB::table('merchant_customers')->where('id', $first->json('customer.merchant_customer_id'))->value('customer_id'));
        $this->assertDatabaseHas('customers', [
            'id' => $globalCustomerId,
            'email' => 'emailbuyer@example.test',
            'mobile' => '9876543210',
        ]);

        $second = $this
            ->actingAs(User::query()->findOrFail($secondUserId))
            ->withSession(['active_shop_id' => $secondShopId])
            ->postJson(route('merchant.pos.customers.store'), [
                'name' => 'Same Email POS Buyer',
                'mobile' => '9123456780',
                'email' => 'emailbuyer@example.test',
            ]);

        $second->assertCreated();
        $this->assertSame($globalCustomerId, (int) $second->json('customer.customer_id'));
        $this->assertSame($globalCustomerId, (int) DB::table('merchant_customers')->where('id', $second->json('customer.merchant_customer_id'))->value('customer_id'));
        $this->assertSame(1, DB::table('customers')->where('email', 'emailbuyer@example.test')->count());
    }

    public function test_two_pos_customers_without_email_can_create_two_null_email_users(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();

        foreach (['9876543210', '9123456780'] as $mobile) {
            $this
                ->actingAs(User::query()->findOrFail($userId))
                ->withSession(['active_shop_id' => $shopId])
                ->postJson(route('merchant.pos.customers.store'), [
                    'name' => 'No Email Buyer '.$mobile,
                    'mobile' => $mobile,
                ])
                ->assertCreated();
        }

        $this->assertSame(2, DB::table('customers')
            ->whereNull('email')
            ->count());
    }

    public function test_pos_customer_reuses_existing_web_user_without_overwriting_registration_source(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $merchantId = $this->merchantIdForShop($shopId);
        $webUserId = (int) DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Existing Web Buyer',
            'email' => 'existing-web-buyer@example.test',
            'mobile' => '9422945125',
            'password' => Hash::make('secret-password'),
            'status' => 'active',
            'registration_source' => UserRegistrationSource::WEB->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.customers.store'), [
                'name' => 'POS Buyer',
                'mobile' => '+91 9422945125',
            ]);

        $response->assertCreated()->assertJsonPath('created', true);
        $customerId = (int) $response->json('customer.merchant_customer_id');
        $globalCustomerId = (int) $response->json('customer.customer_id');

        $this->assertDatabaseHas('merchant_customers', [
            'id' => $customerId,
            'merchant_id' => $merchantId,
        ]);
        $this->assertSame($globalCustomerId, (int) DB::table('merchant_customers')->where('id', $customerId)->value('customer_id'));
        $this->assertSame($webUserId, (int) DB::table('customers')->where('id', $globalCustomerId)->value('user_id'));
        $this->assertSame(UserRegistrationSource::WEB->value, DB::table('users')->where('id', $webUserId)->value('registration_source'));
        $this->assertSame(1, DB::table('users')->where('mobile', '9422945125')->count());
    }

    public function test_pos_customer_same_global_user_can_be_linked_to_second_merchant(): void
    {
        [$firstUserId, $firstShopId] = $this->merchantShopFixture();
        [$secondUserId, $secondShopId] = $this->merchantShopFixture();

        $first = $this
            ->actingAs(User::query()->findOrFail($firstUserId))
            ->withSession(['active_shop_id' => $firstShopId])
            ->postJson(route('merchant.pos.customers.store'), [
                'name' => 'Shared POS Buyer',
                'mobile' => '9422945125',
            ]);
        $first->assertCreated();
        $globalCustomerId = (int) $first->json('customer.customer_id');

        $second = $this
            ->actingAs(User::query()->findOrFail($secondUserId))
            ->withSession(['active_shop_id' => $secondShopId])
            ->postJson(route('merchant.pos.customers.store'), [
                'name' => 'Shared POS Buyer',
                'mobile' => '91-9422945125',
            ]);

        $second->assertCreated();
        $this->assertSame($globalCustomerId, (int) $second->json('customer.customer_id'));
        $this->assertSame($globalCustomerId, (int) DB::table('merchant_customers')->where('id', $second->json('customer.merchant_customer_id'))->value('customer_id'));
        $this->assertNotSame($first->json('customer.merchant_customer_id'), $second->json('customer.merchant_customer_id'));
        $this->assertSame(2, DB::table('merchant_customers')->where('customer_id', $globalCustomerId)->count());
        $this->assertSame(1, DB::table('customers')->where('mobile_normalized', '919422945125')->count());
    }

    public function test_pos_identity_creation_reuses_existing_merchant_customer_relation(): void
    {
        [, $shopId] = $this->merchantShopFixture();
        $merchant = MerchantProfile::query()->findOrFail($this->merchantIdForShop($shopId));
        $this->createCustomer($shopId, 'Existing Duplicate', '9422945125', 'existing-duplicate@example.test');

        $relation = app(MerchantCustomerService::class)->createFromPos($merchant, [
            'name' => 'Duplicate POS Buyer',
            'mobile' => '+91 9422945125',
            'status' => 'active',
        ]);

        $this->assertSame(1, DB::table('customers')->where('mobile_normalized', '919422945125')->count());
        $this->assertSame(1, DB::table('merchant_customers')
            ->join('customers', 'customers.id', '=', 'merchant_customers.customer_id')
            ->where('merchant_id', $merchant->getKey())
            ->where('customers.mobile_normalized', '919422945125')
            ->count());
        $this->assertSame('Existing Duplicate', $relation->name);
    }

    public function test_pos_checkout_uses_global_customer_id_from_search_payload_when_ids_differ(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Mismatch Shirt', 'Blue / M', 'MIS-M-BLU', 'MISBAR001');
        $this->createGlobalCustomer('Unlinked Customer', '9000000001', 'unlinked@example.test');
        $globalCustomerId = $this->createGlobalCustomer('Mismatch Buyer', '9422945125', 'mismatch@example.test');
        $merchantCustomerId = $this->createMerchantCustomerRelation($shopId, $globalCustomerId, 'CUS-MISMATCH');

        $this->assertNotSame($globalCustomerId, $merchantCustomerId);

        $search = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->getJson(route('merchant.pos.customers', ['q' => '9422945125']));

        $search
            ->assertOk()
            ->assertJsonPath('customers.0.merchant_customer_id', $merchantCustomerId)
            ->assertJsonPath('customers.0.customer_id', $globalCustomerId)
            ->assertJsonPath('customers.0.customer_code', 'CUS-MISMATCH')
            ->assertJsonMissingPath('customers.0.id');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'customer_id' => $search->json('customers.0.customer_id'),
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('order.customer_name', 'Mismatch Buyer');

        $this->assertDatabaseHas('orders', [
            'shop_id' => $shopId,
            'customer_id' => $globalCustomerId,
            'customer_name' => 'Mismatch Buyer',
        ]);
        $this->assertDatabaseMissing('orders', [
            'shop_id' => $shopId,
            'customer_id' => $merchantCustomerId,
        ]);
    }

    public function test_pos_same_global_customer_payload_is_explicit_for_two_merchants(): void
    {
        [$firstUserId, $firstShopId] = $this->merchantShopFixture();
        [$secondUserId, $secondShopId] = $this->merchantShopFixture();
        $firstFixture = $this->createPosProduct($firstShopId, 'First Merchant Shirt', 'Black / M', 'FST-M-BLK', 'FSTBAR001');
        $secondFixture = $this->createPosProduct($secondShopId, 'Second Merchant Shirt', 'White / M', 'SND-M-WHT', 'SNDBAR001');
        $this->createGlobalCustomer('Padding Customer A', '9000000001', 'padding-a@example.test');
        $this->createGlobalCustomer('Padding Customer B', '9000000002', 'padding-b@example.test');
        $globalCustomerId = $this->createGlobalCustomer('Shared Global Buyer', '9422945124', 'shared-global@example.test');
        $firstMerchantCustomerId = $this->createMerchantCustomerRelation($firstShopId, $globalCustomerId, 'CUS-SHARED-A');
        $secondMerchantCustomerId = $this->createMerchantCustomerRelation($secondShopId, $globalCustomerId, 'CUS-SHARED-B');

        $this->assertNotSame($globalCustomerId, $firstMerchantCustomerId);
        $this->assertNotSame($globalCustomerId, $secondMerchantCustomerId);

        $firstSearch = $this
            ->actingAs(User::query()->findOrFail($firstUserId))
            ->withSession(['active_shop_id' => $firstShopId])
            ->getJson(route('merchant.pos.customers', ['q' => '9422945124']));

        $firstSearch
            ->assertOk()
            ->assertJsonPath('customers.0.customer_id', $globalCustomerId)
            ->assertJsonPath('customers.0.merchant_customer_id', $firstMerchantCustomerId);

        $secondSearch = $this
            ->actingAs(User::query()->findOrFail($secondUserId))
            ->withSession(['active_shop_id' => $secondShopId])
            ->getJson(route('merchant.pos.customers', ['q' => '9422945124']));

        $secondSearch
            ->assertOk()
            ->assertJsonPath('customers.0.customer_id', $globalCustomerId)
            ->assertJsonPath('customers.0.merchant_customer_id', $secondMerchantCustomerId);

        foreach ([[$firstUserId, $firstShopId, $firstFixture], [$secondUserId, $secondShopId, $secondFixture]] as [$userId, $shopId, $fixture]) {
            $this
                ->actingAs(User::query()->findOrFail($userId))
                ->withSession(['active_shop_id' => $shopId])
                ->postJson(route('merchant.pos.checkout'), [
                    'amount_paid' => 999,
                    'fulfilment_type' => 'counter',
                    'customer_id' => $globalCustomerId,
                    'payment_method' => 'cash',
                    'items' => [
                        ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                    ],
                ])
                ->assertOk();
        }

        $this->assertSame(2, Order::query()->where('customer_id', $globalCustomerId)->count());
        $this->assertSame(1, DB::table('customers')->where('mobile_normalized', '919422945124')->count());
    }

    public function test_pos_quick_create_payload_uses_global_customer_id_for_immediate_checkout(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Created Buyer Shirt', 'Green / M', 'CRT-M-GRN', 'CRTBAR001');
        $this->createGlobalCustomer('Existing Global Only', '9000000001', 'existing-global@example.test');

        $customerResponse = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.customers.store'), [
                'name' => 'Created Checkout Buyer',
                'mobile' => '9422945123',
                'email' => 'created-checkout@example.test',
            ]);

        $customerResponse
            ->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('customer.name', 'Created Checkout Buyer')
            ->assertJsonMissingPath('customer.id');

        $merchantCustomerId = (int) $customerResponse->json('customer.merchant_customer_id');
        $globalCustomerId = (int) $customerResponse->json('customer.customer_id');
        $this->assertGreaterThan(0, $merchantCustomerId);
        $this->assertGreaterThan(0, $globalCustomerId);
        $this->assertNotSame($merchantCustomerId, $globalCustomerId);

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'customer_id' => $globalCustomerId,
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('order.customer_name', 'Created Checkout Buyer');

        $this->assertDatabaseHas('orders', [
            'shop_id' => $shopId,
            'customer_id' => $globalCustomerId,
            'customer_email' => 'created-checkout@example.test',
        ]);
    }

    public function test_pos_checkout_rejects_customer_without_active_merchant_relationship(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Foreign Buyer Shirt', 'Grey / M', 'FRN-M-GRY', 'FRNBAR001');
        $globalCustomerId = $this->createGlobalCustomer('Foreign Buyer', '9422945122', 'foreign@example.test');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'customer_id' => $globalCustomerId,
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');

        $this->assertDatabaseMissing('orders', [
            'shop_id' => $shopId,
            'customer_id' => $globalCustomerId,
        ]);
    }

    public function test_pos_delivery_checkout_requires_address_and_stores_snapshots(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Delivery Shirt', 'White / M', 'DEL-M-WHT', 'DELBAR001');
        $merchantCustomerId = $this->createCustomer($shopId, 'Snapshot Buyer', '9876543210', 'snapshot@example.test');
        $customerId = (int) DB::table('merchant_customers')->where('id', $merchantCustomerId)->value('customer_id');
        $addressId = $this->createCustomerAddress($merchantCustomerId, 'Snapshot Line One');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'delivery',
                'customer_id' => $customerId,
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shipping_address_id');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'delivery',
                'customer_id' => $customerId,
                'shipping_address_id' => $addressId,
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('order.fulfilment_type', 'delivery')
            ->assertJsonPath('order.customer_name', 'Snapshot Buyer')
            ->assertJsonPath('order.shipping_address_line_1', 'Snapshot Line One');

        $this->assertDatabaseHas('orders', [
            'shop_id' => $shopId,
            'customer_id' => $customerId,
            'fulfilment_type' => 'delivery',
            'customer_name' => 'Snapshot Buyer',
            'customer_mobile' => '9876543210',
            'customer_email' => 'snapshot@example.test',
            'shipping_recipient_name' => 'Snapshot Buyer',
            'shipping_mobile_country_code' => '+91',
            'shipping_mobile' => '9876543210',
            'shipping_address_line_1' => 'Snapshot Line One',
            'shipping_postal_code' => '560001',
        ]);
    }

    public function test_recent_sales_returns_completed_pos_orders_for_active_shop(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Linen Shirt', 'White / M', 'LIN-M-WHT', 'POSBAR001');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 1500,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertOk();

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->getJson(route('merchant.pos.recent-sales'));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'sales')
            ->assertJsonPath('sales.0.grand_total', '999.00');

        $this->assertStringContainsString('/merchant/pos/orders/', $response->json('sales.0.receipt_url'));
        $this->assertStringContainsString('print=1', $response->json('sales.0.print_url'));
    }

    public function test_merchant_can_refund_sale_with_line_level_restock_override(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $first = $this->createPosProduct($shopId, 'Knorr Chicken Stock Cubes 8pk', 'Default', 'KNOR-CB', 'KNOR-BAR');
        $second = $this->createPosProduct($shopId, 'KitKat 4-Finger 41.5g', 'Default', 'KITKAT-4', 'KITKAT-BAR');
        $merchantId = $this->merchantIdForShop($shopId);
        $reasonId = (int) DB::table('merchant_return_reasons')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'code' => 'wrong_item',
            'name' => 'Wrong item sold',
            'sort_order' => 2,
            'restock_by_default' => true,
            'requires_manager_override' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 2997,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $first['variant_id'], 'quantity' => 2],
                    ['product_variant_id' => $second['variant_id'], 'quantity' => 1],
                ],
            ]);

        $checkout->assertOk();
        $order = Order::query()->with('items')->findOrFail($checkout->json('order.id'));
        $firstItem = $order->items->firstWhere('product_variant_id', $first['variant_id']);
        $secondItem = $order->items->firstWhere('product_variant_id', $second['variant_id']);

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.sales.refund', $order))
            ->assertOk()
            ->assertSee('Restock')
            ->assertDontSee('do NOT restock');

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.sales.refund.process', $order), [
                'return_reason_id' => $reasonId,
                'refund_method' => 'cash',
                'notes' => 'Customer returned sealed items.',
                'items' => [
                    $firstItem->getKey() => ['quantity' => 2, 'restock' => 0],
                    $secondItem->getKey() => ['quantity' => 1, 'restock' => 1],
                ],
            ]);

        $response->assertRedirect(route('merchant.sales.show', $order));
        $this->assertSame(10, (int) DB::table('product_variants')->where('id', $first['variant_id'])->value('stock_quantity'));
        $this->assertSame(12, (int) DB::table('product_variants')->where('id', $second['variant_id'])->value('stock_quantity'));
        $this->assertDatabaseHas('order_refunds', [
            'order_id' => $order->getKey(),
            'return_reason_id' => $reasonId,
            'reason_name' => 'Wrong item sold',
            'refund_total' => '2997.00',
        ]);
        $this->assertSame('Customer returned sealed items.', DB::table('order_refunds')
            ->where('order_id', $order->getKey())
            ->value('metadata->notes'));
        $this->assertSame(Order::PAYMENT_REFUNDED, $order->refresh()->payment_status);
    }

    public function test_merchant_can_exchange_sale_using_original_discounted_return_value(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $original = $this->createPosProduct($shopId, 'Original Exchange Shirt', 'Blue / M', 'EX-ORIG', 'EX-ORIG-BAR');
        $replacement = $this->createPosProduct($shopId, 'Replacement Exchange Shirt', 'Black / L', 'EX-NEW', 'EX-NEW-BAR');
        DB::table('product_variants')->where('id', $replacement['variant_id'])->update(['selling_price' => 1200]);

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 1800,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $original['variant_id'], 'quantity' => 2, 'discount_type' => 'amount', 'discount_value' => 198],
                ],
            ]);

        $checkout->assertOk();
        DB::table('product_variants')->where('id', $original['variant_id'])->update(['selling_price' => 5000]);

        $order = Order::query()->with('items')->findOrFail($checkout->json('order.id'));
        $originalItem = $order->items->firstWhere('product_variant_id', $original['variant_id']);

        $response = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'notes' => 'Customer changed size.',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $replacement['variant_id'], 'quantity' => 1],
                ],
            ]);

        $exchangeUuid = (string) DB::table('order_exchanges')->value('uuid');
        $response->assertRedirect(route('merchant.sales.exchange.receipt', $exchangeUuid));
        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $order->getKey(),
            'returned_total' => '900.00',
            'replacement_total' => '1200.00',
            'difference_amount' => '300.00',
            'amount_collected' => '300.00',
            'settlement_type' => 'collect_extra',
        ]);
        $this->assertDatabaseHas('order_exchange_return_items', [
            'order_item_id' => $originalItem->getKey(),
            'quantity' => 1,
            'unit_return_value' => '900.00',
            'line_total' => '900.00',
            'restocked' => true,
        ]);
        $replacementOrderId = (int) DB::table('order_exchanges')->value('replacement_order_id');
        $this->assertDatabaseHas('orders', [
            'id' => $replacementOrderId,
            'created_source' => Order::SOURCE_EXCHANGE_REPLACEMENT,
            'amount_paid' => '1200.00',
            'remarks' => 'Replacement for exchange against '.$order->order_number,
        ]);
        $this->assertSame('300.00', number_format((float) DB::table('order_exchanges')->where('original_order_id', $order->getKey())->value('amount_collected'), 2, '.', ''));
        $this->assertSame(11, (int) DB::table('product_variants')->where('id', $original['variant_id'])->value('stock_quantity'));
        $this->assertSame(11, (int) DB::table('product_variants')->where('id', $replacement['variant_id'])->value('stock_quantity'));
    }

    public function test_exchange_returned_value_includes_prorated_tax_without_double_counting(): void
    {
        $sale = $this->createExchangeSale(false, 1, 1200);
        $order = $sale['order'];
        $originalItem = $order->items->first();

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1, 'restock' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $sale['replacement']['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $order->getKey(),
            'returned_total' => '1048.95',
            'replacement_total' => '1260.00',
            'difference_amount' => '211.05',
            'amount_collected' => '211.05',
        ]);
        $this->assertDatabaseHas('order_exchange_return_items', [
            'order_item_id' => $originalItem->getKey(),
            'unit_return_value' => '1048.95',
            'line_tax' => '49.95',
            'line_total' => '1048.95',
        ]);
    }

    public function test_exchange_settlement_uses_inclusive_saved_line_total_without_adding_tax_again(): void
    {
        $sale = $this->createExchangeSale(true, 1, 0);
        $order = $sale['order'];
        $originalItem = $order->items->first();

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $sale['replacement']['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $order->getKey(),
            'returned_total' => '999.00',
            'replacement_total' => '0.00',
            'difference_amount' => '-999.00',
            'amount_refunded' => '999.00',
        ]);
        $this->assertDatabaseHas('order_exchange_return_items', [
            'order_item_id' => $originalItem->getKey(),
            'unit_return_value' => '999.00',
            'line_tax' => '47.57',
            'line_total' => '999.00',
        ]);
    }

    public function test_exchange_settlement_uses_tax_disabled_saved_line_total(): void
    {
        $sale = $this->createExchangeSale(null, 1, 0);
        $order = $sale['order'];
        $originalItem = $order->items->first();

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $sale['replacement']['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $order->getKey(),
            'returned_total' => '999.00',
            'replacement_total' => '0.00',
            'difference_amount' => '-999.00',
            'amount_refunded' => '999.00',
        ]);
        $this->assertDatabaseHas('order_exchange_return_items', [
            'order_item_id' => $originalItem->getKey(),
            'unit_return_value' => '999.00',
            'line_tax' => '0.00',
            'line_total' => '999.00',
        ]);
    }

    public function test_exchange_partial_quantity_prorates_saved_line_total_and_tax_snapshot(): void
    {
        $sale = $this->createExchangeSale(false, 2, 0);
        $order = $sale['order'];
        $originalItem = $order->items->first();

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $sale['replacement']['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $order->getKey(),
            'returned_total' => '1048.95',
            'replacement_total' => '0.00',
            'difference_amount' => '-1048.95',
            'amount_refunded' => '1048.95',
        ]);
        $this->assertDatabaseHas('order_exchange_return_items', [
            'order_item_id' => $originalItem->getKey(),
            'quantity' => 1,
            'unit_return_value' => '1048.95',
            'line_tax' => '49.95',
            'line_total' => '1048.95',
        ]);
    }

    public function test_exchange_full_quantity_uses_full_saved_line_total_for_settlement(): void
    {
        $sale = $this->createExchangeSale(false, 2, 0);
        $order = $sale['order'];
        $originalItem = $order->items->first();

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 2],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $sale['replacement']['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $order->getKey(),
            'returned_total' => '2097.90',
            'replacement_total' => '0.00',
            'difference_amount' => '-2097.90',
            'amount_refunded' => '2097.90',
        ]);
        $this->assertDatabaseHas('order_exchange_return_items', [
            'order_item_id' => $originalItem->getKey(),
            'quantity' => 2,
            'unit_return_value' => '1048.95',
            'line_tax' => '99.90',
            'line_total' => '2097.90',
        ]);
    }

    public function test_exchange_partial_quantity_prorates_from_saved_line_total_before_rounding(): void
    {
        $sale = $this->createExchangeSale(null, 3, 0);
        $order = $sale['order'];
        $originalItem = $order->items->first();

        DB::table('order_items')->where('id', $originalItem->getKey())->update([
            'line_total' => '100.00',
            'line_tax' => '0.00',
            'updated_at' => now(),
        ]);

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 2],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $sale['replacement']['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $order->getKey(),
            'returned_total' => '66.67',
            'difference_amount' => '-66.67',
            'amount_refunded' => '66.67',
        ]);
        $this->assertDatabaseHas('order_exchange_return_items', [
            'order_item_id' => $originalItem->getKey(),
            'quantity' => 2,
            'unit_return_value' => '33.34',
            'line_total' => '66.67',
        ]);
    }

    public function test_exchange_multiple_partial_returns_do_not_exceed_saved_line_total(): void
    {
        $sale = $this->createExchangeSale(null, 3, 0);
        $order = $sale['order'];
        $originalItem = $order->items->first();

        DB::table('order_items')->where('id', $originalItem->getKey())->update([
            'line_total' => '10.00',
            'line_tax' => '0.00',
            'updated_at' => now(),
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this
                ->actingAs(User::query()->findOrFail($sale['user_id']))
                ->withSession(['active_shop_id' => $sale['shop_id']])
                ->post(route('merchant.sales.exchange.process', $order), [
                    'settlement_method' => 'cash',
                    'returned_items' => [
                        $originalItem->getKey() => ['quantity' => 1],
                    ],
                    'replacement_items' => [
                        ['product_variant_id' => $sale['replacement']['variant_id'], 'quantity' => 1],
                    ],
                ])
                ->assertRedirect();
        }

        $returnedTotals = DB::table('order_exchanges')
            ->where('original_order_id', $order->getKey())
            ->orderBy('id')
            ->pluck('returned_total')
            ->map(fn ($value): string => number_format((float) $value, 2, '.', ''))
            ->all();

        $this->assertSame(['3.33', '3.33', '3.34'], $returnedTotals);
        $this->assertSame('10.00', number_format((float) DB::table('order_exchange_return_items')
            ->where('order_item_id', $originalItem->getKey())
            ->sum('line_total'), 2, '.', ''));
    }

    public function test_exchange_replacement_order_is_operationally_paid_for_exclusive_tax_total(): void
    {
        $sale = $this->createExchangeSale(false, 1, 100);
        $order = $sale['order'];
        $originalItem = $order->items->first();

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $sale['replacement']['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $replacementOrderId = (int) DB::table('order_exchanges')
            ->where('original_order_id', $order->getKey())
            ->value('replacement_order_id');

        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $order->getKey(),
            'returned_total' => '1048.95',
            'replacement_total' => '105.00',
            'difference_amount' => '-943.95',
            'amount_refunded' => '943.95',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $replacementOrderId,
            'grand_total' => '105.00',
            'amount_paid' => '105.00',
            'change_amount' => '0.00',
            'payment_status' => Order::PAYMENT_PAID,
        ]);
    }

    public function test_exchange_settlement_ignores_later_tax_master_changes_for_original_return(): void
    {
        $sale = $this->createExchangeSale(false, 1, 0);
        $order = $sale['order'];
        $originalItem = $order->items->first();

        DB::table('tax_classes')->where('id', $originalItem->tax_class_id)->update([
            'name' => 'GST 20% POS',
            'status' => 'deleted',
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tax_rates')->where('id', $originalItem->tax_rate_id)->update([
            'name' => 'GST 20% POS',
            'total_rate' => '20.0000',
            'status' => 'deleted',
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->where('id', $sale['replacement']['product_id'])->update([
            'tax_mode' => 'exempt',
            'tax_class_id' => null,
            'updated_at' => now(),
        ]);

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $sale['replacement']['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $order->getKey(),
            'returned_total' => '1048.95',
            'replacement_total' => '0.00',
            'difference_amount' => '-1048.95',
            'amount_refunded' => '1048.95',
        ]);
        $this->assertDatabaseHas('order_exchange_return_items', [
            'order_item_id' => $originalItem->getKey(),
            'line_tax' => '49.95',
            'line_total' => '1048.95',
        ]);
    }

    public function test_exchange_legacy_tax_snapshot_does_not_get_added_to_saved_line_total(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $original = $this->createPosProduct($shopId, 'Legacy Taxed Exchange Shirt', 'Blue / M', 'EX-TAX-OLD', 'EX-TAX-OLD-BAR');
        $replacement = $this->createPosProduct($shopId, 'Legacy Taxed Replacement Shirt', 'Black / L', 'EX-TAX-NEW', 'EX-TAX-NEW-BAR');
        DB::table('product_variants')->where('id', $replacement['variant_id'])->update(['selling_price' => 1200]);

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 1800,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $original['variant_id'], 'quantity' => 2, 'discount_type' => 'amount', 'discount_value' => 198],
                ],
            ]);
        $checkout->assertOk();

        $order = Order::query()->with('items')->findOrFail($checkout->json('order.id'));
        $originalItem = $order->items->firstWhere('product_variant_id', $original['variant_id']);
        DB::table('order_items')->where('id', $originalItem->getKey())->update(['line_tax' => '180.00']);
        $order = $order->fresh('items');
        $originalItem = $order->items->firstWhere('product_variant_id', $original['variant_id']);

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1, 'restock' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $replacement['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $order->getKey(),
            'returned_total' => '900.00',
            'replacement_total' => '1200.00',
            'difference_amount' => '300.00',
            'amount_collected' => '300.00',
        ]);
        $this->assertDatabaseHas('order_exchange_return_items', [
            'order_item_id' => $originalItem->getKey(),
            'unit_return_value' => '900.00',
            'line_tax' => '90.00',
            'line_total' => '900.00',
        ]);
    }

    public function test_exchange_replacement_orders_are_excluded_from_sales_and_collection_totals(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $original = $this->createPosProduct($shopId, 'Report Exchange Shirt', 'Blue / M', 'EX-REP-OLD', 'EX-REP-OLD-BAR');
        $replacement = $this->createPosProduct($shopId, 'Report Replacement Shirt', 'Black / L', 'EX-REP-NEW', 'EX-REP-NEW-BAR');
        DB::table('product_variants')->where('id', $replacement['variant_id'])->update(['selling_price' => 1200]);

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 1800,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $original['variant_id'], 'quantity' => 2, 'discount_type' => 'amount', 'discount_value' => 198],
                ],
            ]);
        $checkout->assertOk();

        $order = Order::query()->with('items')->findOrFail($checkout->json('order.id'));
        $originalItem = $order->items->firstWhere('product_variant_id', $original['variant_id']);

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1, 'restock' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $replacement['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $replacementOrderNumber = (string) DB::table('orders')
            ->where('created_source', Order::SOURCE_EXCHANGE_REPLACEMENT)
            ->value('order_number');

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.sales.index'))
            ->assertOk()
            ->assertSee('1,800.00')
            ->assertDontSee('3,000.00')
            ->assertDontSee($replacementOrderNumber);

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.dashboard'))
            ->assertOk()
            ->assertSee('INR 1,800')
            ->assertDontSee('INR 3,000')
            ->assertDontSee($replacementOrderNumber);
    }

    public function test_sales_history_report_uses_saved_exclusive_tax_snapshots_after_tax_master_changes(): void
    {
        $sale = $this->createTaxedPosOrder(false);
        $orderItem = $sale['order']->items->first();

        DB::table('tax_classes')->where('id', $orderItem->tax_class_id)->update([
            'name' => 'GST 20% Report',
            'status' => 'deleted',
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tax_rates')->where('id', $orderItem->tax_rate_id)->update([
            'name' => 'GST 20% Report',
            'total_rate' => '20.0000',
            'status' => 'deleted',
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tax_rate_components')->whereIn('code', ['CGST', 'SGST'])->update([
            'name' => 'Changed Component',
            'rate' => '10.0000',
            'updated_at' => now(),
        ]);
        DB::table('product_variants')->where('id', $sale['fixture']['variant_id'])->update([
            'selling_price' => '777.00',
            'updated_at' => now(),
        ]);

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->get(route('merchant.sales.index'))
            ->assertOk()
            ->assertSee('Subtotal')
            ->assertSee('999.00')
            ->assertSee('Tax collected')
            ->assertSee('49.95')
            ->assertSee('Tax Summary')
            ->assertSee('GST 5% POS')
            ->assertSee('5%')
            ->assertSee('Exclusive')
            ->assertSee('Component Summary')
            ->assertSee('CGST')
            ->assertSee('SGST')
            ->assertDontSee('GST 20% Report')
            ->assertDontSee('Changed Component')
            ->assertDontSee('777.00');
    }

    public function test_sales_history_report_uses_saved_inclusive_tax_snapshots(): void
    {
        $sale = $this->createTaxedPosOrder(true);

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->get(route('merchant.sales.index'))
            ->assertOk()
            ->assertSee('Tax collected')
            ->assertSee('47.57')
            ->assertSee('GST 5% POS')
            ->assertSee('Inclusive')
            ->assertSee('951.43')
            ->assertSee('999.00')
            ->assertSee('CGST')
            ->assertSee('SGST');
    }

    public function test_merchant_dashboard_shows_saved_tax_and_discount_totals_for_today(): void
    {
        $sale = $this->createTaxedPosOrder(false);

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->get(route('merchant.dashboard'))
            ->assertOk()
            ->assertSee('Revenue Today')
            ->assertSee('INR 1,049.00')
            ->assertSee("Today's Tax")
            ->assertSee('INR 49.95')
            ->assertSee("Today's Discount")
            ->assertSee('INR 0.00');
    }

    public function test_sales_history_report_handles_tax_disabled_saved_orders(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Report No Tax Shirt', 'Default', 'REP-NO-TAX', 'REP-NO-TAX-BAR');

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 1000,
                'fulfilment_type' => 'counter',
                'payment_method' => Order::PAYMENT_METHOD_CASH,
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ]);

        $checkout->assertOk();

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.sales.index'))
            ->assertOk()
            ->assertSee('Tax collected')
            ->assertSee('0.00')
            ->assertDontSee('Tax Summary')
            ->assertDontSee('Component Summary')
            ->assertDontSee('No tax snapshots found.')
            ->assertDontSee('No component snapshots found.');
    }

    public function test_sales_history_report_tax_summaries_respect_date_filters(): void
    {
        $sale = $this->createTaxedPosOrder(false);
        $oldOrder = $sale['order'];

        DB::table('orders')->where('id', $oldOrder->getKey())->update([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $checkout = $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 1100,
                'fulfilment_type' => 'counter',
                'payment_method' => Order::PAYMENT_METHOD_CASH,
                'items' => [
                    ['product_variant_id' => $sale['fixture']['variant_id'], 'quantity' => 1],
                ],
            ]);

        $checkout->assertOk();
        $newOrder = Order::query()->findOrFail($checkout->json('order.id'));

        $this
            ->actingAs(User::query()->findOrFail($sale['user_id']))
            ->withSession(['active_shop_id' => $sale['shop_id']])
            ->get(route('merchant.sales.index', ['from' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('Sales History (1)')
            ->assertSee($newOrder->order_number)
            ->assertDontSee($oldOrder->order_number)
            ->assertSee('Tax collected')
            ->assertSee('49.95')
            ->assertDontSee('99.90');
    }

    public function test_tax_engine_golden_reporting_audit_uses_immutable_snapshots(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $user = User::query()->findOrFail($userId);
        $merchantId = $this->merchantIdForShop($shopId);

        $inclusive5 = $this->createPosProduct($shopId, 'Golden GST 5 Inclusive', 'Default', 'GOLD-5-INC', 'GOLD-5-INC-BAR');
        $exclusive5 = $this->createPosProduct($shopId, 'Golden GST 5 Exclusive', 'Default', 'GOLD-5-EXC', 'GOLD-5-EXC-BAR');
        $noTax = $this->createPosProduct($shopId, 'Golden No Tax', 'Default', 'GOLD-NO-TAX', 'GOLD-NO-TAX-BAR');
        $exempt = $this->createPosProduct($shopId, 'Golden Product Exempt', 'Default', 'GOLD-EXEMPT', 'GOLD-EXEMPT-BAR');
        $override18 = $this->createPosProduct($shopId, 'Golden GST 18 Override', 'Default', 'GOLD-18-OVR', 'GOLD-18-OVR-BAR');
        $refund5 = $this->createPosProduct($shopId, 'Golden GST 5 Refund', 'Default', 'GOLD-5-REF', 'GOLD-5-REF-BAR');
        $exchange5 = $this->createPosProduct($shopId, 'Golden GST 5 Exchange', 'Default', 'GOLD-5-EXCH', 'GOLD-5-EXCH-BAR');
        $replacement = $this->createPosProduct($shopId, 'Golden Exchange Replacement', 'Default', 'GOLD-REPL', 'GOLD-REPL-BAR');

        $gst5ClassId = $this->enablePosTax($shopId, $inclusive5['product_category_id'], true);
        $orderInclusive5 = $this->checkoutGoldenOrder($user, $shopId, $inclusive5['variant_id'], 1000);

        $this->setMerchantTaxMode($merchantId, $gst5ClassId, false);
        $orderExclusive5 = $this->checkoutGoldenOrder($user, $shopId, $exclusive5['variant_id'], 1100);

        DB::table('merchant_tax_settings')->where('merchant_id', $merchantId)->update([
            'tax_enabled' => false,
            'updated_at' => now(),
        ]);
        $orderNoTax = $this->checkoutGoldenOrder($user, $shopId, $noTax['variant_id'], 1000);

        $this->setMerchantTaxMode($merchantId, $gst5ClassId, false);
        DB::table('products')->where('id', $exempt['product_id'])->update([
            'tax_mode' => 'exempt',
            'tax_class_id' => null,
            'updated_at' => now(),
        ]);
        $orderExempt = $this->checkoutGoldenOrder($user, $shopId, $exempt['variant_id'], 1000);

        $gst18ClassId = $this->enablePosTax($shopId, $override18['product_category_id'], false, '18.0000', 'GST 18% POS', 'GST_18_POS', '9.0000');
        DB::table('product_categories')->where('id', $override18['product_category_id'])->update([
            'default_tax_class_id' => $gst5ClassId,
            'updated_at' => now(),
        ]);
        DB::table('products')->where('id', $override18['product_id'])->update([
            'tax_mode' => 'override',
            'tax_class_id' => $gst18ClassId,
            'updated_at' => now(),
        ]);
        $orderOverride18 = $this->checkoutGoldenOrder($user, $shopId, $override18['variant_id'], 1200);

        $this->setMerchantTaxMode($merchantId, $gst5ClassId, false);
        DB::table('product_categories')->where('id', $refund5['product_category_id'])->update([
            'default_tax_class_id' => $gst5ClassId,
            'updated_at' => now(),
        ]);
        $orderRefund5 = $this->checkoutGoldenOrder($user, $shopId, $refund5['variant_id'], 1100);
        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.sales.refund.process', $orderRefund5), [
                'return_reason_id' => $this->returnReasonId($merchantId),
                'refund_method' => 'cash',
                'items' => [
                    $orderRefund5->items->first()->getKey() => ['quantity' => 1, 'restock' => 1],
                ],
            ])
            ->assertRedirect();

        DB::table('product_variants')->where('id', $replacement['variant_id'])->update([
            'selling_price' => 500,
            'updated_at' => now(),
        ]);
        $orderExchange5 = $this->checkoutGoldenOrder($user, $shopId, $exchange5['variant_id'], 1100);
        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.sales.exchange.process', $orderExchange5), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $orderExchange5->items->first()->getKey() => ['quantity' => 1, 'restock' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $replacement['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $replacementOrderNumber = (string) DB::table('orders')
            ->where('created_source', Order::SOURCE_EXCHANGE_REPLACEMENT)
            ->value('order_number');

        DB::table('tax_classes')->whereIn('id', [$gst5ClassId, $gst18ClassId])->update([
            'name' => 'Changed GST',
            'status' => 'deleted',
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tax_rates')->whereIn('tax_class_id', [$gst5ClassId, $gst18ClassId])->update([
            'name' => 'Changed GST Rate',
            'total_rate' => '28.0000',
            'status' => 'deleted',
            'deleted_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tax_rate_components')->update([
            'name' => 'Changed Component',
            'rate' => '14.0000',
            'updated_at' => now(),
        ]);
        DB::table('product_variants')->whereIn('id', [
            $inclusive5['variant_id'],
            $exclusive5['variant_id'],
            $noTax['variant_id'],
            $exempt['variant_id'],
            $override18['variant_id'],
            $refund5['variant_id'],
            $exchange5['variant_id'],
        ])->update([
            'selling_price' => '777.00',
            'updated_at' => now(),
        ]);

        $salesTotals = DB::table('orders')
            ->where('shop_id', $shopId)
            ->where('created_source', Order::SOURCE_POS)
            ->selectRaw('COUNT(*) as transactions, SUM(grand_total) as grand_total, SUM(tax_total) as tax_total, SUM(discount_total) as discount_total')
            ->first();

        $this->assertSame(7, (int) $salesTotals->transactions);
        $this->assertSame('7323.00', number_format((float) $salesTotals->grand_total, 2, '.', ''));
        $this->assertSame('377.24', number_format((float) $salesTotals->tax_total, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $salesTotals->discount_total, 2, '.', ''));

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.sales.index'))
            ->assertOk()
            ->assertSee('Sales History (7)')
            ->assertSee('7,323.00')
            ->assertSee('377.24')
            ->assertSee('GST 5% POS')
            ->assertSee('GST 18% POS')
            ->assertSee('Inclusive')
            ->assertSee('Exclusive')
            ->assertSee('951.43')
            ->assertSee('2,997.00')
            ->assertSee('179.82')
            ->assertSee('CGST')
            ->assertSee('SGST')
            ->assertDontSee('Changed GST')
            ->assertDontSee('Changed Component')
            ->assertDontSee('777.00')
            ->assertDontSee($replacementOrderNumber);

        $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.dashboard'))
            ->assertOk()
            ->assertSee('Revenue Today')
            ->assertSee('INR 7,323.00')
            ->assertSee("Today's Tax")
            ->assertSee('INR 377.24')
            ->assertSee("Today's Discount")
            ->assertSee('INR 0.00')
            ->assertDontSee($replacementOrderNumber);

        $this->assertTrue($orderInclusive5->fresh()->payment_status === Order::PAYMENT_PAID);
        $this->assertTrue($orderExclusive5->fresh()->payment_status === Order::PAYMENT_PAID);
        $this->assertTrue($orderNoTax->fresh()->payment_status === Order::PAYMENT_PAID);
        $this->assertTrue($orderExempt->fresh()->payment_status === Order::PAYMENT_PAID);
        $this->assertTrue($orderOverride18->fresh()->payment_status === Order::PAYMENT_PAID);
        $this->assertTrue(in_array($orderRefund5->fresh()->payment_status, [Order::PAYMENT_REFUNDED, Order::PAYMENT_PARTIALLY_REFUNDED], true));
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderExempt->getKey(),
            'tax_resolution_source' => 'product_exempt',
            'line_tax' => '0.00',
            'line_total' => '999.00',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderOverride18->getKey(),
            'tax_resolution_source' => 'product_override',
            'tax_class_name' => 'GST 18% POS',
            'line_tax' => '179.82',
            'line_total' => '1178.82',
        ]);
        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $orderExchange5->getKey(),
            'returned_total' => '1048.95',
            'replacement_total' => '525.00',
        ]);
    }

    public function test_hidden_exchange_return_reason_cannot_appear_in_refund_or_exchange_selectors(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $merchantId = $this->merchantIdForShop($shopId);
        $product = $this->createPosProduct($shopId, 'Hidden Reason Shirt', 'Blue / M', 'EX-HIDE-OLD', 'EX-HIDE-OLD-BAR');
        DB::table('merchant_return_reasons')->insert([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'code' => 'exchange',
            'name' => 'Exchange',
            'sort_order' => 9,
            'restock_by_default' => true,
            'requires_manager_override' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $product['variant_id'], 'quantity' => 1],
                ],
            ]);
        $checkout->assertOk();
        $order = Order::query()->findOrFail($checkout->json('order.id'));

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.sales.refund', $order))
            ->assertOk()
            ->assertDontSee('>Exchange<', false);

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.sales.exchange', $order))
            ->assertOk()
            ->assertDontSee('return_reason_id')
            ->assertDontSee('>Exchange<', false);
    }

    public function test_exchange_page_defaults_to_search_and_dropdown_replacement_selection(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $original = $this->createPosProduct($shopId, 'Search Exchange Shirt', 'Blue / M', 'EX-SCAN-OLD', 'EX-SCAN-OLD-BAR');
        $this->createPosProduct($shopId, 'Dropdown Replacement Shirt', 'Black / L', 'EX-DROP-NEW', 'EX-DROP-NEW-BAR');

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $original['variant_id'], 'quantity' => 1],
                ],
            ]);
        $checkout->assertOk();

        $order = Order::query()->findOrFail($checkout->json('order.id'));

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.sales.exchange', $order))
            ->assertOk()
            ->assertSee('How exchange works')
            ->assertSee('Example only')
            ->assertSee('Do not restock if')
            ->assertSee('Scan old item')
            ->assertSee('Original prices')
            ->assertSee('MRP')
            ->assertSee('Selling')
            ->assertSee('Paid / item')
            ->assertSee('Search replacement product')
            ->assertSee('Scan barcode, search SKU, or product name')
            ->assertSee('Choose replacement product')
            ->assertSee('js-replacement-dropdown')
            ->assertSee('Restock')
            ->assertDontSee('do NOT restock')
            ->assertSee(route('merchant.pos.search'), false)
            ->assertDontSee('js-replacement-variant');
    }

    public function test_exchange_page_can_use_search_only_replacement_selection(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $merchantId = $this->merchantIdForShop($shopId);
        $this->setMerchantSetting($merchantId, 'pos', 'exchange.replacement_selector', 'search', 'string');
        $original = $this->createPosProduct($shopId, 'Search Only Exchange Shirt', 'Blue / M', 'EX-SEARCH-OLD', 'EX-SEARCH-OLD-BAR');
        $this->createPosProduct($shopId, 'Hidden Dropdown Replacement Shirt', 'Black / L', 'EX-SEARCH-NEW', 'EX-SEARCH-NEW-BAR');

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $original['variant_id'], 'quantity' => 1],
                ],
            ]);
        $checkout->assertOk();

        $order = Order::query()->findOrFail($checkout->json('order.id'));

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.sales.exchange', $order))
            ->assertOk()
            ->assertSee('Search replacement product')
            ->assertDontSee('Choose replacement product')
            ->assertSee('id="exchange_replacement_search"', false)
            ->assertDontSee('id="exchange_replacement_dropdown"', false);
    }

    public function test_exchange_page_can_use_dropdown_only_replacement_selection(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $merchantId = $this->merchantIdForShop($shopId);
        $this->setMerchantSetting($merchantId, 'pos', 'exchange.replacement_selector', 'dropdown', 'string');
        $original = $this->createPosProduct($shopId, 'Dropdown Only Exchange Shirt', 'Blue / M', 'EX-DROPO-OLD', 'EX-DROPO-OLD-BAR');
        $this->createPosProduct($shopId, 'Visible Dropdown Replacement Shirt', 'Black / L', 'EX-DROPO-NEW', 'EX-DROPO-NEW-BAR');

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $original['variant_id'], 'quantity' => 1],
                ],
            ]);
        $checkout->assertOk();

        $order = Order::query()->findOrFail($checkout->json('order.id'));

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->get(route('merchant.sales.exchange', $order))
            ->assertOk()
            ->assertSee('Choose replacement product')
            ->assertSee('id="exchange_replacement_dropdown"', false)
            ->assertDontSee('Search replacement product')
            ->assertDontSee('id="exchange_replacement_search"', false);
    }

    public function test_exchange_quantity_excludes_refunded_and_previously_exchanged_items(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $original = $this->createPosProduct($shopId, 'Limited Exchange Shirt', 'Blue / M', 'EX-LIMIT', 'EX-LIMIT-BAR');
        $replacement = $this->createPosProduct($shopId, 'Limited Replacement Shirt', 'Black / L', 'EX-LIMIT-NEW', 'EX-LIMIT-NEW-BAR');
        $merchantId = $this->merchantIdForShop($shopId);
        $reasonId = (int) DB::table('merchant_return_reasons')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'code' => 'wrong_item',
            'name' => 'Wrong item sold',
            'sort_order' => 2,
            'restock_by_default' => true,
            'requires_manager_override' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 1998,
                'fulfilment_type' => 'counter',
                'payment_method' => 'cash',
                'items' => [
                    ['product_variant_id' => $original['variant_id'], 'quantity' => 2],
                ],
            ]);
        $checkout->assertOk();

        $order = Order::query()->with('items')->findOrFail($checkout->json('order.id'));
        $originalItem = $order->items->firstWhere('product_variant_id', $original['variant_id']);

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.sales.refund.process', $order), [
                'return_reason_id' => $reasonId,
                'refund_method' => 'cash',
                'items' => [
                    $originalItem->getKey() => ['quantity' => 1],
                ],
            ])
            ->assertRedirect(route('merchant.sales.show', $order));

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $replacement['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => 'cash',
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $replacement['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertSessionHasErrors('returned_items.'.$originalItem->getKey().'.quantity');
    }

    public function test_exchange_balance_on_credit_sale_becomes_credit_adjustment(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $original = $this->createPosProduct($shopId, 'Credit Original Shirt', 'Blue / M', 'EX-CREDIT', 'EX-CREDIT-BAR');
        $replacement = $this->createPosProduct($shopId, 'Credit Replacement Shirt', 'Black / L', 'EX-CREDIT-NEW', 'EX-CREDIT-NEW-BAR');
        DB::table('product_variants')->where('id', $replacement['variant_id'])->update(['selling_price' => 500]);

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 999,
                'fulfilment_type' => 'counter',
                'payment_method' => Order::PAYMENT_METHOD_CREDIT,
                'items' => [
                    ['product_variant_id' => $original['variant_id'], 'quantity' => 1],
                ],
            ]);
        $checkout->assertOk();

        $order = Order::query()->with('items')->findOrFail($checkout->json('order.id'));
        $originalItem = $order->items->firstWhere('product_variant_id', $original['variant_id']);

        $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->post(route('merchant.sales.exchange.process', $order), [
                'settlement_method' => Order::PAYMENT_METHOD_CREDIT,
                'returned_items' => [
                    $originalItem->getKey() => ['quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_id' => $replacement['variant_id'], 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_exchanges', [
            'original_order_id' => $order->getKey(),
            'returned_total' => '999.00',
            'replacement_total' => '500.00',
            'difference_amount' => '-499.00',
            'amount_refunded' => '0.00',
            'credit_adjustment_amount' => '499.00',
            'settlement_type' => 'credit_adjustment',
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function merchantShopFixture(): array
    {
        $userId = (int) DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'POS Merchant',
            'email' => 'pos-merchant-'.Str::random(6).'@example.test',
            'mobile' => '90000'.random_int(10000, 99999),
            'password' => Hash::make('password'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roleId = (int) (DB::table('auth_roles')->where('slug', 'merchant')->value('id')
            ?? DB::table('auth_roles')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => 'Merchant',
                'slug' => 'merchant',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        DB::table('auth_user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $merchantId = (int) DB::table('merchant_profiles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'business_name' => 'POS Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rootCategoryId = $this->category('Retail', null);
        $shopId = (int) DB::table('shops')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'root_product_category_id' => $rootCategoryId,
            'name' => 'POS Shop',
            'slug' => 'pos-shop-'.Str::random(6),
            'address_line_1' => 'Main Road',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['pos_root_category_id' => $rootCategoryId]);

        return [$userId, $shopId];
    }

    private function createCustomer(int $shopId, string $name, string $mobile, string $email): int
    {
        $customerId = $this->createGlobalCustomer($name, $mobile, $email);

        return $this->createMerchantCustomerRelation($shopId, $customerId);
    }

    private function createGlobalCustomer(string $name, string $mobile, ?string $email = null): int
    {
        return (int) DB::table('customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'mobile_country_code' => '+91',
            'mobile' => $mobile,
            'mobile_normalized' => '91'.$mobile,
            'email' => $email,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMerchantCustomerRelation(int $shopId, int $customerId, ?string $customerCode = null): int
    {
        $merchantId = (int) DB::table('shops')->where('id', $shopId)->value('merchant_id');

        return (int) DB::table('merchant_customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'customer_id' => $customerId,
            'customer_code' => $customerCode ?? 'CUS-'.Str::upper(Str::random(6)),
            'trust_status' => 'normal',
            'status' => 'active',
            'linked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function merchantIdForShop(int $shopId): int
    {
        return (int) DB::table('shops')->where('id', $shopId)->value('merchant_id');
    }

    private function setMerchantSetting(int $merchantId, string $group, string $key, string $value, string $type): void
    {
        DB::table('merchant_settings')->updateOrInsert(
            [
                'merchant_id' => $merchantId,
                'group' => $group,
                'setting_key' => $key,
            ],
            [
                'setting_value' => $value,
                'setting_type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function enablePosTax(
        int $shopId,
        int $productCategoryId,
        bool $pricesIncludeTax,
        string $totalRate = '5.0000',
        string $className = 'GST 5% POS',
        string $classCode = 'GST_5_POS',
        string $componentRate = '2.5000',
    ): int
    {
        $this->seed(LocationSeeder::class);

        $shop = DB::table('shops')->where('id', $shopId)->first();
        $country = DB::table('loc_countries')->where('iso2', 'IN')->first();
        $state = DB::table('loc_states')->where('country_id', $country->id)->first();

        DB::table('merchant_addresses')->updateOrInsert(
            ['merchant_id' => $shop->merchant_id, 'address_type' => 'business'],
            [
                'uuid' => (string) Str::uuid(),
                'address_line_1' => 'Tax Market Road',
                'country_id' => $country->id,
                'state_id' => $state->id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('shops')->where('id', $shopId)->update([
            'country_id' => $country->id,
            'state_id' => $state->id,
            'updated_at' => now(),
        ]);

        $taxClassId = (int) DB::table('tax_classes')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'country_id' => $country->id,
            'code' => $classCode,
            'name' => $className,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $taxRateId = (int) DB::table('tax_rates')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tax_class_id' => $taxClassId,
            'name' => $className,
            'total_rate' => $totalRate,
            'effective_from' => '2026-01-01',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([['CGST', 'central', 1], ['SGST', 'state', 2]] as [$code, $jurisdiction, $priority]) {
            DB::table('tax_rate_components')->insert([
                'tax_rate_id' => $taxRateId,
                'code' => $code,
                'name' => $code,
                'rate' => $componentRate,
                'jurisdiction_type' => $jurisdiction,
                'priority' => $priority,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('product_categories')->where('id', $productCategoryId)->update([
            'default_tax_class_id' => $taxClassId,
            'updated_at' => now(),
        ]);
        DB::table('merchant_tax_settings')->updateOrInsert(
            ['merchant_id' => $shop->merchant_id],
            [
                'uuid' => (string) Str::uuid(),
                'tax_enabled' => true,
                'default_tax_class_id' => $taxClassId,
                'prices_include_tax' => $pricesIncludeTax,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return $taxClassId;
    }

    private function setMerchantTaxMode(int $merchantId, int $taxClassId, bool $pricesIncludeTax): void
    {
        DB::table('merchant_tax_settings')->where('merchant_id', $merchantId)->update([
            'tax_enabled' => true,
            'default_tax_class_id' => $taxClassId,
            'prices_include_tax' => $pricesIncludeTax,
            'updated_at' => now(),
        ]);
    }

    private function checkoutGoldenOrder(User $user, int $shopId, int $variantId, int $amountPaid): Order
    {
        $checkout = $this
            ->actingAs($user)
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => $amountPaid,
                'fulfilment_type' => 'counter',
                'payment_method' => Order::PAYMENT_METHOD_CASH,
                'items' => [
                    ['product_variant_id' => $variantId, 'quantity' => 1],
                ],
            ]);

        $checkout->assertOk();

        return Order::query()->with('items')->findOrFail($checkout->json('order.id'));
    }

    private function returnReasonId(int $merchantId): int
    {
        return (int) DB::table('merchant_return_reasons')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'code' => 'golden-return-'.Str::random(6),
            'name' => 'Golden Return',
            'sort_order' => 10,
            'restock_by_default' => true,
            'requires_manager_override' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{user_id: int, shop_id: int, fixture: array<string, int>, order: Order, receipt_url: string}
     */
    private function createTaxedPosOrder(bool $pricesIncludeTax): array
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, $pricesIncludeTax ? 'Receipt Inclusive Tax Shirt' : 'Receipt Exclusive Tax Shirt', 'Default', $pricesIncludeTax ? 'REC-INC-TAX' : 'REC-EXC-TAX', $pricesIncludeTax ? 'REC-INC-TAX-BAR' : 'REC-EXC-TAX-BAR');
        $this->enablePosTax($shopId, $fixture['product_category_id'], $pricesIncludeTax);

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => $pricesIncludeTax ? 1000 : 1100,
                'fulfilment_type' => 'counter',
                'payment_method' => Order::PAYMENT_METHOD_CASH,
                'items' => [
                    ['product_variant_id' => $fixture['variant_id'], 'quantity' => 1],
                ],
            ]);

        $checkout->assertOk();

        return [
            'user_id' => $userId,
            'shop_id' => $shopId,
            'fixture' => $fixture,
            'order' => Order::query()->with('items.taxComponents')->findOrFail($checkout->json('order.id')),
            'receipt_url' => (string) $checkout->json('order.receipt_url'),
        ];
    }

    /**
     * @return array{user_id: int, shop_id: int, original: array<string, int>, replacement: array<string, int>, order: Order}
     */
    private function createExchangeSale(?bool $pricesIncludeTax, int $originalQuantity, int $replacementPrice): array
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $mode = $pricesIncludeTax === null ? 'NO-TAX' : ($pricesIncludeTax ? 'INC-TAX' : 'EXC-TAX');
        $original = $this->createPosProduct($shopId, 'Exchange '.$mode.' Original Shirt', 'Default', 'EX-'.$mode.'-OLD', 'EX-'.$mode.'-OLD-BAR');
        $replacement = $this->createPosProduct($shopId, 'Exchange '.$mode.' Replacement Shirt', 'Default', 'EX-'.$mode.'-NEW', 'EX-'.$mode.'-NEW-BAR');

        DB::table('product_variants')->where('id', $replacement['variant_id'])->update([
            'selling_price' => $replacementPrice,
            'updated_at' => now(),
        ]);

        if ($pricesIncludeTax !== null) {
            $this->enablePosTax($shopId, $original['product_category_id'], $pricesIncludeTax);
        }

        $checkout = $this
            ->actingAs(User::query()->findOrFail($userId))
            ->withSession(['active_shop_id' => $shopId])
            ->postJson(route('merchant.pos.checkout'), [
                'amount_paid' => 5000,
                'fulfilment_type' => 'counter',
                'payment_method' => Order::PAYMENT_METHOD_CASH,
                'items' => [
                    ['product_variant_id' => $original['variant_id'], 'quantity' => $originalQuantity],
                ],
            ]);

        $checkout->assertOk();

        return [
            'user_id' => $userId,
            'shop_id' => $shopId,
            'original' => $original,
            'replacement' => $replacement,
            'order' => Order::query()->with('items')->findOrFail($checkout->json('order.id')),
        ];
    }

    private function createCustomerAddress(int $customerId, string $lineOne): int
    {
        $globalCustomerId = (int) DB::table('merchant_customers')->where('id', $customerId)->value('customer_id');

        return (int) DB::table('customer_addresses')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'customer_id' => $globalCustomerId,
            'label' => 'Home',
            'recipient_name' => 'Snapshot Buyer',
            'recipient_mobile_country_code' => '+91',
            'recipient_mobile' => '9876543210',
            'recipient_mobile_normalized' => '919876543210',
            'address_line_1' => $lineOne,
            'postal_code' => '560001',
            'is_default_shipping' => true,
            'is_default_billing' => false,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{product_id: int, variant_id: int, root_category_id: int, product_category_id: int, attribute_group_id: int}
     */
    private function createPosProduct(int $shopId, string $name, string $variantName, string $sku, string $barcode): array
    {
        $shop = DB::table('shops')->where('id', $shopId)->first();
        $categoryId = $this->category('Apparel', (int) $shop->root_product_category_id);
        $attributeGroupId = $this->attributeGroup('Color');
        $productId = (int) DB::table('products')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shopId,
            'root_product_category_id' => $shop->root_product_category_id,
            'product_category_id' => $categoryId,
            'product_name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variantId = (int) DB::table('product_variants')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'product_id' => $productId,
            'shop_id' => $shopId,
            'sku' => $sku,
            'barcode' => $barcode,
            'name' => $variantName,
            'mrp' => 1299,
            'selling_price' => 999,
            'stock_quantity' => 12,
            'is_default' => true,
            'sort_order' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'product_id' => $productId,
            'variant_id' => $variantId,
            'root_category_id' => (int) $shop->root_product_category_id,
            'product_category_id' => $categoryId,
            'attribute_group_id' => $attributeGroupId,
        ];
    }

    private function createVariant(int $shopId, int $productId, string $variantName, string $sku, string $barcode): int
    {
        return (int) DB::table('product_variants')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'product_id' => $productId,
            'shop_id' => $shopId,
            'sku' => $sku,
            'barcode' => $barcode,
            'name' => $variantName,
            'mrp' => 1299,
            'selling_price' => 999,
            'stock_quantity' => 12,
            'is_default' => false,
            'sort_order' => 2,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createImageAttributeMapping(int $rootCategoryId): array
    {
        $groupId = $this->attributeGroup('Color');
        $redValueId = $this->attributeValue($groupId, 'Red');
        $blueValueId = $this->attributeValue($groupId, 'Blue');

        DB::table('product_category_attribute_groups')->insert([
            'root_product_category_id' => $rootCategoryId,
            'product_attribute_group_id' => $groupId,
            'is_required' => true,
            'is_variant' => true,
            'is_image_attribute' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$redValueId, $blueValueId];
    }

    private function attachMappedImage(int $productId, string $path, int $attributeValueId): void
    {
        $imageId = (int) DB::table('product_images')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'product_id' => $productId,
            'image_path' => $path,
            'thumbnail_path' => $path,
            'is_primary' => false,
            'sort_order' => 1,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_image_attribute_values')->insert([
            'product_image_id' => $imageId,
            'product_attribute_group_value_id' => $attributeValueId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attributeGroup(string $name): int
    {
        $existingId = DB::table('product_attribute_groups')->where('name', $name)->value('id');

        if ($existingId !== null) {
            return (int) $existingId;
        }

        return (int) DB::table('product_attribute_groups')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'code' => Str::slug($name).'-'.Str::random(5),
            'selection_type' => 'single',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attributeValue(int $groupId, string $name): int
    {
        $existingId = DB::table('product_attribute_group_values')
            ->where('product_attribute_group_id', $groupId)
            ->where('name', $name)
            ->value('id');

        if ($existingId !== null) {
            return (int) $existingId;
        }

        return (int) DB::table('product_attribute_group_values')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'product_attribute_group_id' => $groupId,
            'name' => $name,
            'code' => Str::slug($name).'-'.Str::random(5),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function category(string $name, ?int $parentId): int
    {
        $existingId = DB::table('product_categories')
            ->where('name', $name)
            ->where('parent_id', $parentId)
            ->value('id');

        if ($existingId !== null) {
            return (int) $existingId;
        }

        return (int) DB::table('product_categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
