<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\PreviewProductDescriptionTemplateRequest;
use App\Http\Requests\Admin\MasterData\StoreProductDescriptionTemplateRequest;
use App\Http\Requests\Admin\MasterData\UpdateProductDescriptionTemplateRequest;
use App\Models\ProductDescriptionTemplate;
use App\Models\ProductCategory;
use App\Services\Product\ProductDescriptionTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductDescriptionTemplateController extends Controller
{
    public function __construct(
        private readonly ProductDescriptionTemplateService $templateService,
    ) {
    }

    public function index(): View
    {
        $templates = ProductDescriptionTemplate::query()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.master-data.description-templates.index', [
            'templates' => $templates,
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.description-templates.create', [
            'template' => null,
            'categories' => $this->categories(),
            'placeholders' => ProductDescriptionTemplateService::PLACEHOLDERS,
        ]);
    }

    public function store(StoreProductDescriptionTemplateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();

        ProductDescriptionTemplate::create([
            'product_category_id' => $data['product_category_id'],
            'name' => $data['name'],
            'short_description_template' => $data['short_description_template'],
            'description_template' => $data['description_template'],
            'meta_title_template' => $this->nullable($data['meta_title_template'] ?? null),
            'meta_description_template' => $this->nullable($data['meta_description_template'] ?? null),
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return redirect()
            ->route('admin.master.description-templates.index')
            ->with('success', 'Description template created successfully.');
    }

    public function edit(ProductDescriptionTemplate $descriptionTemplate): View
    {
        return view('admin.master-data.description-templates.edit', [
            'template' => $descriptionTemplate,
            'categories' => $this->categories(),
            'placeholders' => ProductDescriptionTemplateService::PLACEHOLDERS,
        ]);
    }

    public function update(
        UpdateProductDescriptionTemplateRequest $request,
        ProductDescriptionTemplate $descriptionTemplate,
    ): RedirectResponse {
        $data = $request->validated();

        $descriptionTemplate->forceFill([
            'product_category_id' => $data['product_category_id'],
            'name' => $data['name'],
            'short_description_template' => $data['short_description_template'],
            'description_template' => $data['description_template'],
            'meta_title_template' => $this->nullable($data['meta_title_template'] ?? null),
            'meta_description_template' => $this->nullable($data['meta_description_template'] ?? null),
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.master.description-templates.edit', $descriptionTemplate)
            ->with('success', 'Description template updated successfully.');
    }

    public function destroy(ProductDescriptionTemplate $descriptionTemplate): RedirectResponse
    {
        $descriptionTemplate->delete();

        return redirect()
            ->route('admin.master.description-templates.index')
            ->with('success', 'Description template deleted successfully.');
    }

    public function preview(ProductDescriptionTemplate $descriptionTemplate): View
    {
        $descriptionTemplate->load('category');

        $sampleValues = $this->templateService->sampleValues($descriptionTemplate->category);

        return view('admin.master-data.description-templates.preview', [
            'template' => $descriptionTemplate,
            'placeholders' => ProductDescriptionTemplateService::PLACEHOLDERS,
            'values' => $sampleValues,
            'preview' => $this->templateService->generateFromTemplate($descriptionTemplate, $sampleValues),
        ]);
    }

    public function generatePreview(
        PreviewProductDescriptionTemplateRequest $request,
        ProductDescriptionTemplate $descriptionTemplate,
    ): View {
        $descriptionTemplate->load('category');

        $values = [
            ...$this->templateService->sampleValues($descriptionTemplate->category),
            ...$request->validated(),
        ];

        return view('admin.master-data.description-templates.preview', [
            'template' => $descriptionTemplate,
            'placeholders' => ProductDescriptionTemplateService::PLACEHOLDERS,
            'values' => $values,
            'preview' => $this->templateService->generateFromTemplate($descriptionTemplate, $values),
        ]);
    }

    private function categories(): Collection
    {
        $categories = ProductCategory::query()
            ->whereIn('status', ['active', 'inactive'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $paths = $this->buildCategoryPaths($categories);

        return $categories
            ->map(fn (ProductCategory $category) => $category->setAttribute('full_path_label', $paths[$category->getKey()] ?? $category->name))
            ->sortBy('full_path_label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
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

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
