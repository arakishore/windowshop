<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StoreProductAttributeValueRequest;
use App\Http\Requests\Admin\MasterData\UpdateProductAttributeValueRequest;
use App\Models\ProductAttributeGroup;
use App\Models\ProductAttributeValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductAttributeValueController extends Controller
{
    public function index(ProductAttributeGroup $productAttribute): View
    {
        $values = $productAttribute->values()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.master-data.product-attribute-values.index', [
            'group' => $productAttribute,
            'values' => $values,
        ]);
    }

    public function create(ProductAttributeGroup $productAttribute): View
    {
        return view('admin.master-data.product-attribute-values.create', [
            'group' => $productAttribute,
            'value' => null,
        ]);
    }

    public function store(StoreProductAttributeValueRequest $request, ProductAttributeGroup $productAttribute): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();

        $productAttribute->values()->create([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $this->nullable($data['description'] ?? null),
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return redirect()
            ->route('admin.master.product-attributes.values.index', $productAttribute)
            ->with('success', 'Product attribute value created successfully.');
    }

    public function edit(ProductAttributeGroup $productAttribute, ProductAttributeValue $productAttributeValue): View
    {
        $this->authorizeValue($productAttribute, $productAttributeValue);

        return view('admin.master-data.product-attribute-values.edit', [
            'group' => $productAttribute,
            'value' => $productAttributeValue,
        ]);
    }

    public function update(
        UpdateProductAttributeValueRequest $request,
        ProductAttributeGroup $productAttribute,
        ProductAttributeValue $productAttributeValue,
    ): RedirectResponse {
        $this->authorizeValue($productAttribute, $productAttributeValue);

        $data = $request->validated();

        $productAttributeValue->forceFill([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $this->nullable($data['description'] ?? null),
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.master.product-attributes.values.edit', [$productAttribute, $productAttributeValue])
            ->with('success', 'Product attribute value updated successfully.');
    }

    public function destroy(ProductAttributeGroup $productAttribute, ProductAttributeValue $productAttributeValue): RedirectResponse
    {
        $this->authorizeValue($productAttribute, $productAttributeValue);

        $productAttributeValue->delete();

        return redirect()
            ->route('admin.master.product-attributes.values.index', $productAttribute)
            ->with('success', 'Product attribute value deleted successfully.');
    }

    private function authorizeValue(ProductAttributeGroup $group, ProductAttributeValue $value): void
    {
        abort_unless((int) $value->product_attribute_group_id === (int) $group->getKey(), 404);
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
