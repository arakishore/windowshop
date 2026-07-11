<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StoreShopCategoryRequest;
use App\Http\Requests\Admin\MasterData\UpdateShopCategoryRequest;
use App\Models\ShopCategory;
use App\Services\Image\ImageVariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class ShopCategoryController extends Controller
{
    public function __construct(
        private readonly ImageVariantService $imageVariantService,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = [
            'name' => trim((string) $request->query('name', '')),
            'slug' => trim((string) $request->query('slug', '')),
            'status' => $request->query('status'),
        ];

        $categories = ShopCategory::query()
            ->withCount('shops')
            ->when($filters['name'] !== '', fn ($query) => $query->where('name', 'like', "%{$filters['name']}%"))
            ->when($filters['slug'] !== '', fn ($query) => $query->where('slug', 'like', "%{$filters['slug']}%"))
            ->when(in_array($filters['status'], ['active', 'inactive'], true), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.master-data.shop-categories.index', [
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.shop-categories.create', [
            'category' => null,
        ]);
    }

    public function store(StoreShopCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();

        $category = ShopCategory::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['slug'] ?? $data['name']),
            'description' => $this->nullable($data['description'] ?? null),
            'image_path' => null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        if ($request->hasFile('image')) {
            $category->forceFill([
                'image_path' => $this->storeImage($request, $category),
            ])->save();
        }

        return redirect()
            ->route('admin.master.shop-categories.index')
            ->with('success', 'Shop category created successfully.');
    }

    public function edit(ShopCategory $shopCategory): View
    {
        return view('admin.master-data.shop-categories.edit', [
            'category' => $shopCategory,
        ]);
    }

    public function update(UpdateShopCategoryRequest $request, ShopCategory $shopCategory): RedirectResponse
    {
        $data = $request->validated();
        $oldName = $shopCategory->getOriginal('name');
        $oldSlug = $shopCategory->getOriginal('slug');
        $slug = $oldSlug;

        if ($data['name'] !== $oldName && $this->isGeneratedFromName($oldSlug, $oldName)) {
            $slug = $this->uniqueSlug($data['name'], $shopCategory);
        }

        $imagePath = $shopCategory->image_path;

        if ($request->boolean('remove_image')) {
            $this->deleteImageDirectory($imagePath);
            $imagePath = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteImageDirectory($imagePath);
            $imagePath = $this->storeImage($request, $shopCategory);
        }

        $shopCategory->forceFill([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $this->nullable($data['description'] ?? null),
            'image_path' => $imagePath,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.master.shop-categories.edit', $shopCategory)
            ->with('success', 'Shop category updated successfully.');
    }

    public function destroy(ShopCategory $shopCategory): RedirectResponse
    {
        if ($shopCategory->shops()->withTrashed()->exists()) {
            return redirect()
                ->route('admin.master.shop-categories.index')
                ->with('error', 'This category cannot be deleted because it is assigned to one or more shops. Set it to Inactive instead.');
        }

        DB::transaction(function () use ($shopCategory): void {
            $shopCategory->forceFill([
                'deleted_by' => Auth::id(),
            ])->save();

            $shopCategory->delete();
        });

        return redirect()
            ->route('admin.master.shop-categories.index')
            ->with('success', 'Shop category deleted successfully.');
    }

    private function storeImage(Request $request, ShopCategory $category): string
    {
        try {
            $paths = $this->imageVariantService->store(
                $request->file('image'),
                'shop_category',
                "shop-categories/{$category->getKey()}/image",
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'image' => $exception->getMessage(),
            ]);
        }

        return $paths['web'] ?? array_values($paths)[0];
    }

    private function deleteImageDirectory(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->deleteDirectory(dirname($path));
    }

    private function uniqueSlug(string $value, ?ShopCategory $category = null): string
    {
        $base = Str::slug($value) ?: Str::uuid()->toString();
        $slug = $base;
        $suffix = 2;

        while (ShopCategory::query()
            ->where('slug', $slug)
            ->when($category?->exists, fn ($query) => $query->whereKeyNot($category->getKey()))
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
