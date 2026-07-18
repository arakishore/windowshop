<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderTotal;
use App\Models\Product;
use App\Models\User;
use App\Services\Product\ProductVariantManagementService;
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
            ->assertJsonPath('customers.0.id', $customerId)
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

    public function test_pos_delivery_checkout_requires_address_and_stores_snapshots(): void
    {
        [$userId, $shopId] = $this->merchantShopFixture();
        $fixture = $this->createPosProduct($shopId, 'Delivery Shirt', 'White / M', 'DEL-M-WHT', 'DELBAR001');
        $customerId = $this->createCustomer($shopId, 'Snapshot Buyer', '9876543210', 'snapshot@example.test');
        $addressId = $this->createCustomerAddress($customerId, 'Snapshot Line One');

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
            'shipping_address_id' => $addressId,
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
        $merchantId = (int) DB::table('shops')->where('id', $shopId)->value('merchant_id');

        return (int) DB::table('merchant_customers')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'customer_code' => 'CUS-'.Str::upper(Str::random(6)),
            'name' => $name,
            'mobile_country_code' => '+91',
            'mobile' => $mobile,
            'mobile_normalized' => '91'.$mobile,
            'email' => $email,
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

    private function createCustomerAddress(int $customerId, string $lineOne): int
    {
        return (int) DB::table('merchant_customer_addresses')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_customer_id' => $customerId,
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
     * @return array{product_id: int, variant_id: int, root_category_id: int, attribute_group_id: int}
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
