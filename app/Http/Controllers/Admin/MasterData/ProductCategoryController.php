<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StoreProductCategoryRequest;
use App\Http\Requests\Admin\MasterData\UpdateProductCategoryRequest;
use App\Models\ProductCategory;
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

class ProductCategoryController extends Controller
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

        $categories = ProductCategory::query()
            ->with('parent')
            ->withCount(['products', 'descriptionTemplates'])
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
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($filters['search'] === '' && empty($filters['parent_id']) && empty($filters['status'])) {
            $categories = $this->flattenCategoryTree($categories);
        }

        $categoryPaths = $this->categoryPaths();

        return view('admin.master-data.product-categories.index', [
            'categories' => $categories,
            'categoryPaths' => $categoryPaths,
            'filters' => $filters,
            'parentCategories' => $parentCategories,
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.product-categories.create', [
            'category' => null,
            'parentCategories' => $this->parentCategories(),
        ]);
    }

    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;

        $category = DB::transaction(function () use ($data, $actorId, $parentId): ProductCategory {
            $category = ProductCategory::create([
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
            ->route('admin.master.product-categories.index')
            ->with('success', 'Product category created successfully.');
    }

    public function edit(ProductCategory $productCategory): View
    {
        return view('admin.master-data.product-categories.edit', [
            'category' => $productCategory,
            'parentCategories' => $this->parentCategories($productCategory),
        ]);
    }

    public function show(ProductCategory $productCategory): View
    {
        $productCategory->load(['parent', 'children' => fn ($query) => $query->withCount('products')]);

        return view('admin.master-data.product-categories.show', [
            'category' => $productCategory,
            'categoryPaths' => $this->categoryPaths(),
        ]);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        $data = $request->validated();
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;

        $imagePath = $productCategory->image_path;

        if ($request->boolean('remove_image')) {
            $this->deleteImageDirectory($imagePath);
            $imagePath = null;
        }

        if ($request->hasFile('image')) {
            $this->deleteImageDirectory($imagePath);
            $imagePath = $this->storeImage($request, $productCategory);
        }

        $productCategory->forceFill([
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => $this->slugForCategory($productCategory, $data['name']),
            'description' => $this->nullable($data['description'] ?? null),
            'image_path' => $imagePath,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.master.product-categories.edit', $productCategory)
            ->with('success', 'Product category updated successfully.');
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        if ($productCategory->children()->exists()) {
            return redirect()
                ->route('admin.master.product-categories.index')
                ->with('error', 'This category cannot be deleted because it has child categories. Move or delete the child categories first.');
        }

        if ($productCategory->products()->withTrashed()->exists()) {
            return redirect()
                ->route('admin.master.product-categories.index')
                ->with('error', 'This category cannot be deleted because it is assigned to one or more products. Set it to Inactive instead.');
        }

        if ($productCategory->shops()->withTrashed()->exists()) {
            return redirect()
                ->route('admin.master.product-categories.index')
                ->with('error', 'This category cannot be deleted because it is assigned to one or more shops as a shop type. Set it to Inactive instead.');
        }

        if ($productCategory->descriptionTemplates()->exists()) {
            return redirect()
                ->route('admin.master.product-categories.index')
                ->with('error', 'This category cannot be deleted because description templates are assigned to it.');
        }

        DB::transaction(function () use ($productCategory): void {
            $productCategory->forceFill([
                'deleted_by' => Auth::id(),
            ])->save();

            $productCategory->delete();
        });

        return redirect()
            ->route('admin.master.product-categories.index')
            ->with('success', 'Product category deleted successfully.');
    }

    private function storeImage(Request $request, ProductCategory $category): string
    {
        try {
            $paths = $this->imageVariantService->store(
                $request->file('image'),
                'product_category',
                "product-categories/{$category->getKey()}/image",
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

    private function slugForCategory(ProductCategory $category, ?string $name = null): string
    {
        return (Str::slug($name ?? $category->name) ?: 'category').'-'.$category->getKey();
    }

    private function parentCategories(?ProductCategory $category = null): Collection
    {
        $excludedIds = $category?->exists ? $this->descendantIds($category)->push($category->getKey())->all() : [];
        $categories = ProductCategory::query()
            ->when($excludedIds !== [], fn ($query) => $query->whereNotIn('id', $excludedIds))
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $paths = $this->buildCategoryPaths($categories);

        return $categories
            ->map(fn (ProductCategory $item) => $item->setAttribute('full_path_label', $paths[$item->getKey()] ?? $item->name))
            ->filter(fn (ProductCategory $item) => substr_count((string) $item->full_path_label, ' > ') < StoreProductCategoryRequest::MAX_DEPTH - 1)
            ->sortBy('full_path_label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function categoryPaths(): array
    {
        return $this->buildCategoryPaths(ProductCategory::query()->select(['id', 'parent_id', 'name'])->get());
    }

    private function flattenCategoryTree(Collection $categories, ?int $parentId = null, int $depth = 0): Collection
    {
        $result = collect();

        $children = $categories
            ->filter(fn (ProductCategory $category) => $category->parent_id === $parentId)
            ->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ]);

        foreach ($children as $category) {
            $category->setAttribute('depth', $depth);
            $result->push($category);

            $result = $result->concat(
                $this->flattenCategoryTree($categories, $category->getKey(), $depth + 1),
            );
        }

        return $result;
    }

    private function buildCategoryPaths(Collection $categories): array
    {
        $byId = $categories->keyBy('id');

        return $categories
            ->mapWithKeys(fn (ProductCategory $category) => [
                $category->getKey() => $this->categoryPathFromCollection($category, $byId),
            ])
            ->all();
    }

    private function categoryPathFromCollection(ProductCategory $category, Collection $byId): string
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

    private function descendantIds(ProductCategory $category): Collection
    {
        $allCategories = ProductCategory::query()->select(['id', 'parent_id'])->get()->groupBy('parent_id');
        $descendants = collect();
        $queue = collect($allCategories->get($category->getKey(), []));

        while ($queue->isNotEmpty()) {
            /** @var ProductCategory $child */
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
