<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $parents = DB::table('product_categories as parent_categories')
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(TRIM(name)) <> ?', ['other'])
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('product_categories as child_categories')
                    ->whereColumn('child_categories.parent_id', 'parent_categories.id')
                    ->whereNull('child_categories.deleted_at')
                    ->whereRaw('LOWER(TRIM(child_categories.name)) <> ?', ['other']);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id']);

        foreach ($parents as $parent) {
            $existingId = DB::table('product_categories')
                ->where('parent_id', $parent->id)
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(TRIM(name)) = ?', ['other'])
                ->value('id');

            if ($existingId) {
                continue;
            }

            $id = DB::table('product_categories')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'parent_id' => $parent->id,
                'name' => 'Other',
                'slug' => 'pending-'.Str::uuid()->toString(),
                'sort_order' => 99,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('product_categories')
                ->where('id', $id)
                ->update(['slug' => 'other-'.$id]);
        }
    }

    public function down(): void
    {
        DB::table('product_categories')
            ->whereNotNull('parent_id')
            ->whereRaw('LOWER(TRIM(name)) = ?', ['other'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('products')
                    ->whereColumn('products.product_category_id', 'product_categories.id');
            })
            ->delete();
    }
};
