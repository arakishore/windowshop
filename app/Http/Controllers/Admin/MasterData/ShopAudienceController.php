<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StoreShopAudienceRequest;
use App\Http\Requests\Admin\MasterData\UpdateShopAudienceRequest;
use App\Models\ShopAudience;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopAudienceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'name' => trim((string) $request->query('name', '')),
            'slug' => trim((string) $request->query('slug', '')),
            'status' => $request->query('status'),
        ];

        $audiences = ShopAudience::query()
            ->withCount('shops')
            ->when($filters['name'] !== '', fn ($query) => $query->where('name', 'like', "%{$filters['name']}%"))
            ->when($filters['slug'] !== '', fn ($query) => $query->where('slug', 'like', "%{$filters['slug']}%"))
            ->when(in_array($filters['status'], ['active', 'inactive'], true), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.master-data.shop-audiences.index', [
            'audiences' => $audiences,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.shop-audiences.create', [
            'audience' => null,
        ]);
    }

    public function store(StoreShopAudienceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();

        ShopAudience::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
            'description' => $this->nullable($data['description'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return redirect()
            ->route('admin.master.shop-audiences.index')
            ->with('success', 'Shop audience created successfully.');
    }

    public function edit(ShopAudience $shopAudience): View
    {
        return view('admin.master-data.shop-audiences.edit', [
            'audience' => $shopAudience,
        ]);
    }

    public function update(UpdateShopAudienceRequest $request, ShopAudience $shopAudience): RedirectResponse
    {
        $data = $request->validated();
        $oldName = $shopAudience->getOriginal('name');
        $oldSlug = $shopAudience->getOriginal('slug');
        $slug = $oldSlug;

        if ($data['name'] !== $oldName && $this->isGeneratedFromName($oldSlug, $oldName)) {
            $slug = $this->uniqueSlug($data['name'], $shopAudience);
        }

        $shopAudience->forceFill([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $this->nullable($data['description'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.master.shop-audiences.edit', $shopAudience)
            ->with('success', 'Shop audience updated successfully.');
    }

    public function destroy(ShopAudience $shopAudience): RedirectResponse
    {
        if ($shopAudience->shops()->withTrashed()->exists()) {
            return redirect()
                ->route('admin.master.shop-audiences.index')
                ->with('error', 'This audience cannot be deleted because it is assigned to one or more shops. Set it to Inactive instead.');
        }

        DB::transaction(function () use ($shopAudience): void {
            $shopAudience->forceFill([
                'deleted_by' => Auth::id(),
            ])->save();

            $shopAudience->delete();
        });

        return redirect()
            ->route('admin.master.shop-audiences.index')
            ->with('success', 'Shop audience deleted successfully.');
    }

    private function uniqueSlug(string $value, ?ShopAudience $audience = null): string
    {
        $base = Str::slug($value) ?: Str::uuid()->toString();
        $slug = $base;
        $suffix = 2;

        while (ShopAudience::query()
            ->where('slug', $slug)
            ->when($audience?->exists, fn ($query) => $query->whereKeyNot($audience->getKey()))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function isGeneratedFromName(?string $slug, ?string $name): bool
    {
        if (! $slug || ! $name) {
            return false;
        }

        $base = Str::slug($name);

        return $slug === $base || (bool) preg_match('/^'.preg_quote($base, '/').'-[0-9]+$/', $slug);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
