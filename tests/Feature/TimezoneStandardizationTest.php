<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Order;
use App\Models\OrderComment;
use App\Services\DateTime\BusinessTimeService;
use App\Services\DateTime\DateDisplayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class TimezoneStandardizationTest extends TestCase
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

    public function test_order_comment_stores_utc_and_displays_business_timezone(): void
    {
        $this->regionalTimezone('Asia/Kolkata');
        $order = $this->orderFixture();
        $comment = $order->comments()->create([
            'author_type' => OrderComment::AUTHOR_MERCHANT,
            'comment' => 'UTC storage check',
            'visibility' => OrderComment::VISIBILITY_MERCHANT_ONLY,
        ]);
        DB::table('order_comments')->where('id', $comment->getKey())->update([
            'created_at' => '2026-08-25 13:00:00',
            'updated_at' => '2026-08-25 13:00:00',
        ]);
        $comment->refresh();

        $raw = DB::table('order_comments')->where('id', $comment->getKey())->value('created_at');

        $this->assertSame('2026-08-25 13:00:00', Carbon::parse($raw)->format('Y-m-d H:i:s'));
        $this->assertSame('25-08-2026 06:30 PM', app(DateDisplayService::class)->dateTime($comment->created_at));
    }

    public function test_business_day_boundaries_are_converted_to_utc(): void
    {
        $this->regionalTimezone('Asia/Kolkata');
        [$start, $end] = app(BusinessTimeService::class)->dateRangeToUtc('2026-08-26', '2026-08-26');

        $this->assertSame('2026-08-25 18:30:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-26 18:30:00', $end->format('Y-m-d H:i:s'));
    }

    public function test_business_day_query_range_counts_after_midnight_india_not_utc_date(): void
    {
        $this->regionalTimezone('Asia/Kolkata');
        [$merchantId, $shopId] = $this->merchantShopFixture();
        $businessDayOrder = $this->orderFixture($merchantId, $shopId, [
            'order_number' => 'ORD-BUSINESS-DAY',
        ]);
        DB::table('orders')->where('id', $businessDayOrder->getKey())->update([
            'created_at' => '2026-08-25 19:00:00',
            'updated_at' => '2026-08-25 19:00:00',
        ]);
        $previousDayOrder = $this->orderFixture($merchantId, $shopId, [
            'order_number' => 'ORD-PREVIOUS-DAY',
        ]);
        DB::table('orders')->where('id', $previousDayOrder->getKey())->update([
            'created_at' => '2026-08-25 18:29:59',
            'updated_at' => '2026-08-25 18:29:59',
        ]);

        [$start, $end] = app(BusinessTimeService::class)->dateRangeToUtc('2026-08-26', '2026-08-26');
        $count = Order::query()
            ->where('shop_id', $shopId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_datetime_local_round_trip_uses_business_timezone_and_stores_utc(): void
    {
        $this->regionalTimezone('Asia/Kolkata');
        $businessTime = app(BusinessTimeService::class);

        $this->assertSame('2026-08-25T18:30', $businessTime->toLocalDateTimeInput('2026-08-25 13:00:00'));
        $this->assertSame('2026-08-25 13:00:00', $businessTime->toUtcFromLocalInput('2026-08-25T18:30')->format('Y-m-d H:i:s'));
    }

    public function test_mysql_connection_timezone_is_configured_for_utc(): void
    {
        $this->assertSame('+00:00', config('database.connections.mysql.timezone'));

        if (DB::getDriverName() === 'mysql') {
            $clock = DB::selectOne('select @@session.time_zone as session_tz, now() as db_now, utc_timestamp() as db_utc');

            $this->assertSame('+00:00', $clock->session_tz);
            $this->assertEqualsWithDelta(
                Carbon::parse($clock->db_utc, 'UTC')->timestamp,
                Carbon::parse($clock->db_now, 'UTC')->timestamp,
                2,
            );
        }
    }

    private function regionalTimezone(string $timezone): void
    {
        AdminSetting::query()->updateOrCreate(
            ['group' => 'regional', 'setting_key' => 'timezone'],
            ['setting_value' => $timezone, 'setting_type' => AdminSetting::TYPE_STRING],
        );
        AdminSetting::query()->updateOrCreate(
            ['group' => 'regional', 'setting_key' => 'date_format'],
            ['setting_value' => 'd-m-Y', 'setting_type' => AdminSetting::TYPE_STRING],
        );
        AdminSetting::query()->updateOrCreate(
            ['group' => 'regional', 'setting_key' => 'time_format'],
            ['setting_value' => 'h:i A', 'setting_type' => AdminSetting::TYPE_STRING],
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function merchantShopFixture(): array
    {
        $userId = DB::table('users')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Timezone Merchant',
            'email' => 'timezone-'.Str::random(6).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $merchantId = DB::table('merchant_profiles')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'business_name' => 'Timezone Merchant',
            'verification_status' => 'approved',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('product_categories')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Timezone Root',
            'slug' => 'timezone-root-'.Str::random(6),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shopId = DB::table('shops')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'merchant_id' => $merchantId,
            'root_product_category_id' => $categoryId,
            'name' => 'Timezone Shop',
            'slug' => 'timezone-shop-'.Str::random(6),
            'address_line_1' => 'Clock Street',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$merchantId, $shopId];
    }

    private function orderFixture(?int $merchantId = null, ?int $shopId = null, array $overrides = []): Order
    {
        if ($merchantId === null || $shopId === null) {
            [$merchantId, $shopId] = $this->merchantShopFixture();
        }

        return Order::query()->create(array_merge([
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'merchant_id' => $merchantId,
            'shop_id' => $shopId,
            'created_source' => Order::SOURCE_POS,
            'fulfilment_type' => Order::FULFILMENT_COUNTER,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_method' => Order::PAYMENT_METHOD_CASH,
            'payment_status' => Order::PAYMENT_PAID,
            'currency_code' => 'INR',
            'subtotal' => 100,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'grand_total' => 100,
            'amount_paid' => 100,
        ], $overrides));
    }
}
