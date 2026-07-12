<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\PreviewProductDescriptionTemplateRequest;
use App\Http\Requests\Admin\MasterData\StoreProductDescriptionTemplateRequest;
use App\Http\Requests\Admin\MasterData\UpdateProductDescriptionTemplateRequest;
use App\Models\ProductDescriptionTemplate;
use App\Models\ShopCategory;
use App\Services\Product\ProductDescriptionTemplateService;
use Illuminate\Http\RedirectResponse;
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
            'shop_category_id' => $data['shop_category_id'],
            'name' => $data['name'],
            'short_description_template' => $data['short_description_template'],
            'description_template' => $data['description_template'],
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
            'shop_category_id' => $data['shop_category_id'],
            'name' => $data['name'],
            'short_description_template' => $data['short_description_template'],
            'description_template' => $data['description_template'],
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

    private function categories(): \Illuminate\Support\Collection
    {
        return ShopCategory::query()
            ->whereIn('status', ['active', 'inactive'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
