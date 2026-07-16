<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCreationService
{
    public function __construct(
        private readonly OrderNumberService $orderNumberService,
        private readonly OrderTotalsService $orderTotalsService,
        private readonly OrderStatusService $orderStatusService,
    ) {
    }

    /**
     * @param array{
     *     shop_id: int,
     *     customer_id?: int|null,
     *     created_source?: string,
     *     fulfilment_type?: string,
     *     order_status?: string,
     *     payment_method?: string,
     *     payment_status?: string,
     *     amount_paid?: float|string|int,
     *     customer_name?: string|null,
     *     customer_mobile?: string|null,
     *     remarks?: string|null,
     *     items: array<int, array{product_variant_id: int, quantity: int}>,
     *     totals?: array<int, array<string, mixed>>
     * } $data
     */
    public function create(array $data, User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor): Order {
            $shop = Shop::query()->findOrFail((int) $data['shop_id']);
            $items = $this->buildItems($shop, $data['items'] ?? []);
            $orderStatus = (string) ($data['order_status'] ?? Order::STATUS_COMPLETED);
            $paymentStatus = (string) ($data['payment_status'] ?? Order::PAYMENT_PAID);

            $order = Order::query()->create([
                'order_number' => $this->orderNumberService->generate(),
                'merchant_id' => $shop->merchant_id,
                'shop_id' => $shop->getKey(),
                'customer_id' => $data['customer_id'] ?? null,
                'created_source' => $data['created_source'] ?? Order::SOURCE_POS,
                'fulfilment_type' => $data['fulfilment_type'] ?? Order::FULFILMENT_COUNTER,
                'order_status' => $orderStatus,
                'payment_method' => $data['payment_method'] ?? Order::PAYMENT_METHOD_CASH,
                'payment_status' => $paymentStatus,
                'currency_code' => $data['currency_code'] ?? 'INR',
                'customer_name' => $data['customer_name'] ?? null,
                'customer_mobile' => $data['customer_mobile'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
                'completed_at' => $orderStatus === Order::STATUS_COMPLETED ? now() : null,
                'cancelled_at' => $orderStatus === Order::STATUS_CANCELLED ? now() : null,
            ]);

            foreach ($items as $item) {
                $order->items()->create($item->getAttributes());
            }

            $createdItems = $order->items()->get();
            $calculated = $this->orderTotalsService->calculate(
                $createdItems,
                $data['totals'] ?? [],
                $data['amount_paid'] ?? 0,
            );

            if ($paymentStatus === Order::PAYMENT_PAID && (float) $calculated['summary']['amount_paid'] < (float) $calculated['summary']['grand_total']) {
                throw ValidationException::withMessages([
                    'amount_paid' => 'The amount paid must be at least the grand total for a paid cash sale.',
                ]);
            }

            $this->orderTotalsService->save($order, $calculated['summary'], $calculated['rows']);
            $this->orderStatusService->recordInitial($order, $orderStatus, $actor, 'POS cash sale completed');

            return $order->load(['items', 'totals', 'statusHistories']);
        });
    }

    /**
     * @param array<int, array{product_variant_id: int, quantity: int}> $rows
     * @return array<int, OrderItem>
     */
    private function buildItems(Shop $shop, array $rows): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one order item is required.',
            ]);
        }

        $items = [];

        foreach ($rows as $index => $row) {
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Quantity must be at least 1.',
                ]);
            }

            $variant = ProductVariant::query()
                ->with('product.primaryImage')
                ->whereKey((int) $row['product_variant_id'])
                ->where('shop_id', $shop->getKey())
                ->firstOrFail();
            $unitPrice = $this->money($variant->selling_price);
            $lineSubtotal = $this->money((float) $unitPrice * $quantity);

            $items[] = new OrderItem([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->getKey(),
                'product_name' => $variant->product?->product_name ?? 'Product',
                'product_image' => $variant->product?->primaryImage?->image_path,
                'variant_name' => $variant->name,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'quantity' => $quantity,
                'unit_mrp' => $this->money($variant->mrp),
                'unit_price' => $unitPrice,
                'unit_discount' => '0.00',
                'line_subtotal' => $lineSubtotal,
                'line_discount' => '0.00',
                'line_tax' => '0.00',
                'line_total' => $lineSubtotal,
                'metadata' => null,
            ]);
        }

        return $items;
    }

    private function money(float|string|int $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
}
