<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\UpdateProductCategoryAttributeGroupsRequest;
use App\Models\ProductAttributeGroup;
use App\Models\ProductCategory;
use App\Services\Product\ProductCategoryAttributeMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductCategoryAttributeGroupController extends Controller
{
    public function __construct(
        private readonly ProductCategoryAttributeMappingService $mappingService,
    ) {
    }

    public function edit(ProductCategory $productCategory): View
    {
        $rootCategory = $this->rootCategory($productCategory);
        $rootCategory->load(['parent', 'attributeGroupMappings.group']);

        return view('admin.master-data.product-categories.attribute-groups', [
            'category' => $rootCategory,
            'selectedCategory' => $productCategory,
            'attributeGroups' => ProductAttributeGroup::query()
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'existingMappings' => $rootCategory->attributeGroupMappings
                ->keyBy('product_attribute_group_id'),
        ]);
    }

    public function update(
        UpdateProductCategoryAttributeGroupsRequest $request,
        ProductCategory $productCategory,
    ): RedirectResponse {
        $rootCategory = $this->rootCategory($productCategory);

        $this->mappingService->sync($rootCategory, $request->validated('mappings', []));

        return redirect()
            ->route('admin.master.product-categories.attribute-groups.edit', $rootCategory)
            ->with('success', 'Category attribute mappings updated successfully.');
    }

    private function rootCategory(ProductCategory $category): ProductCategory
    {
        if ($category->isRoot()) {
            return $category;
        }

        return ProductCategory::query()->findOrFail($category->rootCategoryId());
    }
}
