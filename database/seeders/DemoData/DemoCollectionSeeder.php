<?php

namespace Database\Seeders\DemoData;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoCollectionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::transaction(function () use ($now): void {
            $shops = DB::table('shops')
                ->select('id', 'merchant_id', 'name')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get();

            if ($shops->isEmpty()) {
                throw new RuntimeException('Demo shops must exist before seeding demo collections.');
            }

            $actorId = DB::table('users')->where('email', 'admin@windowshop.test')->value('id')
                ?? DB::table('users')->where('status', 'active')->orderBy('id')->value('id');

            foreach ($shops as $shop) {
                foreach ($this->collections() as $index => $collection) {
                    $collectionId = $this->upsertCollection($shop, $collection, $index, $actorId, $now);
                    $this->syncProducts((int) $shop->id, $collectionId, $index, $now);
                }
            }
        });
    }

    /**
     * @return array<int, array{name: string, description: string}>
     */
    private function collections(): array
    {
        return [
            ['name' => 'Diwali Sale', 'description' => 'Demo festive collection for offer planning.'],
            ['name' => '50% OFF', 'description' => 'Demo markdown collection for merchant workflows.'],
            ['name' => 'Buy 1 Get 1', 'description' => 'Demo collection for future BOGO offer setup.'],
            ['name' => 'Clearance Sale', 'description' => 'Demo end-of-season product grouping.'],
        ];
    }

    private function upsertCollection(object $shop, array $collection, int $index, mixed $actorId, mixed $now): int
    {
        $slug = Str::slug((string) $collection['name']) ?: 'collection';
        $existingId = DB::table('collections')
            ->where('shop_id', $shop->id)
            ->where('slug', $slug)
            ->value('id');

        $payload = [
            'merchant_id' => $shop->merchant_id,
            'shop_id' => $shop->id,
            'name' => $collection['name'],
            'slug' => $slug,
            'description' => $collection['description'],
            'status' => 'active',
            'sort_order' => $index + 1,
            'updated_by' => $actorId,
            'deleted_by' => null,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        if ($existingId !== null) {
            DB::table('collections')->where('id', $existingId)->update($payload);

            return (int) $existingId;
        }

        return (int) DB::table('collections')->insertGetId([
            ...$payload,
            'uuid' => (string) Str::uuid(),
            'created_by' => $actorId,
            'created_at' => $now,
        ]);
    }

    private function syncProducts(int $shopId, int $collectionId, int $offset, mixed $now): void
    {
        $productIds = DB::table('products')
            ->where('shop_id', $shopId)
            ->whereNull('deleted_at')
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('product_name')
            ->skip($offset * 3)
            ->limit(6)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($productIds as $sortOrder => $productId) {
            DB::table('collection_products')->updateOrInsert(
                [
                    'collection_id' => $collectionId,
                    'product_id' => $productId,
                ],
                [
                    'sort_order' => $sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }
}
