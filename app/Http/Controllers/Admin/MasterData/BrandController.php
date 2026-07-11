<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StoreBrandRequest;
use App\Http\Requests\Admin\MasterData\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\Image\ImageVariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class BrandController extends Controller
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

        $brands = Brand::query()
            ->when($filters['name'] !== '', fn ($query) => $query->where('name', 'like', "%{$filters['name']}%"))
            ->when($filters['slug'] !== '', fn ($query) => $query->where('slug', 'like', "%{$filters['slug']}%"))
            ->when(in_array($filters['status'], ['active', 'inactive'], true), fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.master-data.brands.index', [
            'brands' => $brands,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.brands.create', [
            'brand' => null,
        ]);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();

        $brand = Brand::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $this->nullable($data['description'] ?? null),
            'logo_path' => null,
            'website_url' => $this->nullable($data['website_url'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        if ($request->hasFile('logo')) {
            $brand->forceFill([
                'logo_path' => $this->replaceLogo($request, $brand, null),
            ])->save();
        }

        return redirect()
            ->route('admin.master.brands.index')
            ->with('success', 'Brand created successfully.');
    }

    public function edit(Brand $brand): View
    {
        return view('admin.master-data.brands.edit', [
            'brand' => $brand,
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $data = $request->validated();
        $oldName = $brand->getOriginal('name');
        $oldSlug = $brand->getOriginal('slug');
        $slug = $oldSlug;
        $logoPath = $brand->logo_path;

        if ($data['name'] !== $oldName && $this->isGeneratedFromName($oldSlug, $oldName)) {
            $slug = $this->uniqueSlug($data['name'], $brand);
        }

        if ($request->boolean('remove_logo')) {
            $this->deleteLogoDirectory($logoPath);
            $logoPath = null;
        }

        if ($request->hasFile('logo')) {
            $logoPath = $this->replaceLogo($request, $brand, $logoPath);
        }

        $brand->forceFill([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $this->nullable($data['description'] ?? null),
            'logo_path' => $logoPath,
            'website_url' => $this->nullable($data['website_url'] ?? null),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => $data['status'],
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.master.brands.edit', $brand)
            ->with('success', 'Brand updated successfully.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($this->hasAssignedProducts($brand)) {
            return redirect()
                ->route('admin.master.brands.index')
                ->with('error', 'This brand cannot be deleted because it is assigned to one or more products. Set it to Inactive instead.');
        }

        DB::transaction(function () use ($brand): void {
            $brand->forceFill([
                'deleted_by' => Auth::id(),
            ])->save();

            $brand->delete();
        });

        return redirect()
            ->route('admin.master.brands.index')
            ->with('success', 'Brand deleted successfully.');
    }

    private function hasAssignedProducts(Brand $brand): bool
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'brand_id')) {
            return false;
        }

        return DB::table('products')
            ->where('brand_id', $brand->getKey())
            ->exists();
    }

    private function replaceLogo(Request $request, Brand $brand, ?string $oldPath): string
    {
        $finalDirectory = "brands/{$brand->uuid}/logo";
        $pendingDirectory = "brands/{$brand->uuid}/logo-pending-".Str::uuid();

        try {
            $paths = $this->imageVariantService->store($request->file('logo'), 'brand_logo', $pendingDirectory);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'logo' => $exception->getMessage(),
            ]);
        }

        if ($oldPath) {
            $this->deleteLogoDirectory($oldPath);
        } else {
            Storage::disk('public')->deleteDirectory($finalDirectory);
        }

        Storage::disk('public')->makeDirectory($finalDirectory);

        foreach (Storage::disk('public')->files($pendingDirectory) as $file) {
            Storage::disk('public')->move($file, $finalDirectory.'/'.basename($file));
        }

        Storage::disk('public')->deleteDirectory($pendingDirectory);

        $webFile = basename($paths['web'] ?? array_values($paths)[0]);

        return "{$finalDirectory}/{$webFile}";
    }

    private function deleteLogoDirectory(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->deleteDirectory(dirname($path));
    }

    private function uniqueSlug(string $value, ?Brand $brand = null): string
    {
        $base = Str::slug($value) ?: Str::uuid()->toString();
        $slug = $base;
        $suffix = 2;

        while (Brand::query()
            ->where('slug', $slug)
            ->when($brand?->exists, fn ($query) => $query->whereKeyNot($brand->getKey()))
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
