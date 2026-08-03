<?php

namespace Tests\Feature;

use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class ProductQuantityFoundationTest extends TestCase
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

    public function test_quantity_columns_are_decimal_capable(): void
    {
        $columns = [
            'product_variants' => ['stock_quantity', 'low_stock_threshold'],
            'order_items' => ['quantity'],
            'order_refund_items' => ['quantity'],
            'order_exchange_return_items' => ['quantity'],
        ];

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), "{$table}.{$column} should exist.");
                $this->assertDecimalLike($this->columnType($table, $column), "{$table}.{$column}");
            }
        }
    }

    public function test_product_variant_selling_rule_columns_and_model_casts_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('product_variants', [
            'allow_decimal_quantity',
            'quantity_increment',
            'minimum_order_quantity',
            'maximum_order_quantity',
            'purchase_quantity_multiple',
            'allow_backorder',
            'is_sellable',
        ]));

        $variant = new ProductVariant([
            'allow_decimal_quantity' => 1,
            'quantity_increment' => '0.500',
            'minimum_order_quantity' => '1.250',
            'maximum_order_quantity' => null,
            'purchase_quantity_multiple' => '0.250',
            'allow_backorder' => 0,
            'is_sellable' => 1,
        ]);

        $this->assertTrue($variant->allow_decimal_quantity);
        $this->assertSame('0.500', $variant->quantity_increment);
        $this->assertSame('1.250', $variant->minimum_order_quantity);
        $this->assertNull($variant->maximum_order_quantity);
        $this->assertSame('0.250', $variant->purchase_quantity_multiple);
        $this->assertFalse($variant->allow_backorder);
        $this->assertTrue($variant->is_sellable);
    }

    private function columnType(string $table, string $column): string
    {
        if (DB::getDriverName() === 'sqlite') {
            $columns = DB::select("PRAGMA table_info({$table})");

            foreach ($columns as $definition) {
                if ($definition->name === $column) {
                    return strtolower((string) $definition->type);
                }
            }

            return '';
        }

        $definition = DB::selectOne("SHOW COLUMNS FROM {$table} WHERE Field = ?", [$column]);

        return strtolower((string) ($definition->Type ?? ''));
    }

    private function assertDecimalLike(string $type, string $column): void
    {
        $this->assertTrue(
            str_contains($type, 'decimal') || str_contains($type, 'numeric'),
            "{$column} should be decimal-like, found [{$type}].",
        );
    }
}
