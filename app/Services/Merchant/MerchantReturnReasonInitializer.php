<?php

namespace App\Services\Merchant;

use App\Models\ReturnReason;
use Illuminate\Support\Str;

class MerchantReturnReasonInitializer
{
    /**
     * @return array<int, array{code: string, name: string, sort_order: int, restock_by_default: bool, requires_manager_override: bool, status: string}>
     */
    public function defaults(): array
    {
        return [
            ['code' => 'customer_changed_mind', 'name' => 'Customer changed mind', 'sort_order' => 1, 'restock_by_default' => true, 'requires_manager_override' => false, 'status' => ReturnReason::STATUS_ACTIVE],
            ['code' => 'wrong_item', 'name' => 'Wrong item sold', 'sort_order' => 2, 'restock_by_default' => true, 'requires_manager_override' => false, 'status' => ReturnReason::STATUS_ACTIVE],
            ['code' => 'wrong_size', 'name' => 'Wrong size / variant', 'sort_order' => 3, 'restock_by_default' => true, 'requires_manager_override' => false, 'status' => ReturnReason::STATUS_ACTIVE],
            ['code' => 'defective', 'name' => 'Defective / not working', 'sort_order' => 4, 'restock_by_default' => false, 'requires_manager_override' => false, 'status' => ReturnReason::STATUS_ACTIVE],
            ['code' => 'damaged_in_transit', 'name' => 'Damaged in transit', 'sort_order' => 5, 'restock_by_default' => false, 'requires_manager_override' => false, 'status' => ReturnReason::STATUS_ACTIVE],
            ['code' => 'expired', 'name' => 'Expired product', 'sort_order' => 6, 'restock_by_default' => false, 'requires_manager_override' => false, 'status' => ReturnReason::STATUS_ACTIVE],
            ['code' => 'price_dispute', 'name' => 'Pricing dispute', 'sort_order' => 7, 'restock_by_default' => true, 'requires_manager_override' => false, 'status' => ReturnReason::STATUS_ACTIVE],
            ['code' => 'duplicate_charge', 'name' => 'Duplicate billing', 'sort_order' => 8, 'restock_by_default' => true, 'requires_manager_override' => false, 'status' => ReturnReason::STATUS_ACTIVE],
            ['code' => 'other', 'name' => 'Other', 'sort_order' => 99, 'restock_by_default' => true, 'requires_manager_override' => false, 'status' => ReturnReason::STATUS_ACTIVE],
        ];
    }

    public function initialize(int $merchantId, ?int $actorId = null): void
    {
        foreach ($this->defaults() as $row) {
            ReturnReason::query()->firstOrCreate(
                [
                    'merchant_id' => $merchantId,
                    'code' => $row['code'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $row['name'],
                    'sort_order' => $row['sort_order'],
                    'restock_by_default' => $row['restock_by_default'],
                    'requires_manager_override' => $row['requires_manager_override'],
                    'status' => $row['status'],
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ],
            );
        }
    }

    /**
     * @param iterable<int> $merchantIds
     */
    public function initializeMany(iterable $merchantIds): void
    {
        foreach ($merchantIds as $merchantId) {
            $this->initialize((int) $merchantId);
        }
    }

    public function uniqueCode(int $merchantId, string $name, ?ReturnReason $ignore = null): string
    {
        $base = Str::slug($name, '_') ?: 'reason';
        $code = $base;
        $suffix = 2;

        while (ReturnReason::query()
            ->where('merchant_id', $merchantId)
            ->where('code', $code)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }
}
