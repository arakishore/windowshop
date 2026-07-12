<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StoreShopCategoryRequest;
use App\Http\Requests\Admin\MasterData\UpdateShopCategoryRequest;
use App\Models\ShopCategory;
use App\Services\Image\ImageVariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'search' => trim((string) $request->query('search', '')),
            'parent_id' => $request->query('parent_id'),
            'status' => $request->query('status'),
        ];

        $parentCategories = $this->parentCategories();

        $categories = ShopCategory::query()
            ->with('parent')
            ->withCount('shops')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('parent', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['parent_id'] === 'root', fn ($query) => $query->whereNull('parent_id'))
            ->when(is_numeric($filters['parent_id']), fn ($query) => $query->where('parent_id', (int) $filters['parent_id']))
            ->when(in_array($filters['status'], ['active', 'inactive'], true), fn ($query) => $query->where('status', $filters['status']))
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN id ELSE parent_id END')
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categoryPaths = $this->categoryPaths();

        return view('admin.master-data.shop-categories.index', [
            'categories' => $categories,
            'categoryPaths' => $categoryPaths,
            'filters' => $filters,
            'parentCategories' => $parentCategories,
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.shop-categories.create', [
            'category' => null,
            'parentCategories' => $this->parentCategories(),
        ]);
    }

    public function store(StoreShopCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;

        $category = DB::transaction(function () use ($data, $actorId, $parentId): ShopCategory {
            $category = ShopCategory::create([
                'parent_id' => $parentId,
                'name' => $data['name'],
                'slug' => 'pending-'.Str::uuid()->toString(),
                'description' => $this->nullable($data['description'] ?? null),
                'image_path' => null,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'status' => $data['status'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $category->updateQuietly([
                'slug' => $this->slugForCategory($category),
            ]);

            return $category;
        });

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
            'parentCategories' => $this->parentCategories($shopCategory),
        ]);
    }

    public function show(ShopCategory $shopCategory): View
    {
        $shopCategory->load(['parent', 'children' => fn ($query) => $query->withCount('shops')]);

        return view('admin.master-data.shop-categories.show', [
            'category' => $shopCategory,
            'categoryPaths' => $this->categoryPaths(),
        ]);
    }

    public function update(UpdateShopCategoryRequest $request, ShopCategory $shopCategory): RedirectResponse
    {
        $data = $request->validated();
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;

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
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => ((Str::slug($data['name']) ?: 'category').'-'.$shopCategory->getKey()),
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
        if ($shopCategory->children()->exists()) {
            return redirect()
                ->route('admin.master.shop-categories.index')
                ->with('error', 'This category cannot be deleted because it has child categories. Move or delete the child categories first.');
        }

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

    private function slugForCategory(ShopCategory $category): string
    {
        return (Str::slug($category->name) ?: 'category').'-'.$category->getKey();
    }

    private function parentCategories(?ShopCategory $category = null): \Illuminate\Support\Collection
    {
        $excludedIds = $category?->exists ? $this->descendantIds($category)->push($category->getKey())->all() : [];
        $categories = ShopCategory::query()
            ->when($excludedIds !== [], fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $paths = $this->buildCategoryPaths($categories);

        return $categories
            ->map(fn (ShopCategory $item) => $item->setAttribute('full_path_label', $paths[$item->getKey()] ?? $item->name))
            ->sortBy('full_path_label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function categoryPaths(): array
    {
        return $this->buildCategoryPaths(ShopCategory::query()->select(['id', 'parent_id', 'name'])->get());
    }

    private function buildCategoryPaths(Collection $categories): array
    {
        $byId = $categories->keyBy('id');

        return $categories
            ->mapWithKeys(fn (ShopCategory $category) => [
                $category->getKey() => $this->categoryPathFromCollection($category, $byId),
            ])
            ->all();
    }

    private function categoryPathFromCollection(ShopCategory $category, Collection $byId): string
    {
        $names = [];
        $visited = [];
        $current = $category;

        while ($current && ! in_array($current->getKey(), $visited, true)) {
            $visited[] = $current->getKey();
            array_unshift($names, $current->name);
            $current = $current->parent_id ? $byId->get($current->parent_id) : null;
        }

        return implode(' > ', $names);
    }

    private function descendantIds(ShopCategory $category): Collection
    {
        $allCategories = ShopCategory::query()->select(['id', 'parent_id'])->get()->groupBy('parent_id');
        $descendants = collect();
        $queue = collect($allCategories->get($category->getKey(), []));

        while ($queue->isNotEmpty()) {
            /** @var ShopCategory $child */
            $child = $queue->shift();
            $descendants->push($child->getKey());
            $queue = $queue->merge($allCategories->get($child->getKey(), []));
        }

        return $descendants;
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
