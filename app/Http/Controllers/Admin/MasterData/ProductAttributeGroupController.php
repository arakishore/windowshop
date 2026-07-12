<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StoreProductAttributeGroupRequest;
use App\Http\Requests\Admin\MasterData\UpdateProductAttributeGroupRequest;
use App\Models\ProductAttributeGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductAttributeGroupController extends Controller
{
    public function index(): View
    {
        $groups = ProductAttributeGroup::query()
            ->withCount('values')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.master-data.product-attributes.index', [
            'groups' => $groups,
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.product-attributes.create', [
            'group' => null,
        ]);
    }

    public function store(StoreProductAttributeGroupRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();

        ProductAttributeGroup::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $this->nullable($data['description'] ?? null),
            'selection_type' => $data['selection_type'],
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return redirect()
            ->route('admin.master.product-attributes.index')
            ->with('success', 'Product attribute group created successfully.');
    }

    public function edit(ProductAttributeGroup $productAttribute): View
    {
        return view('admin.master-data.product-attributes.edit', [
            'group' => $productAttribute,
        ]);
    }

    public function update(UpdateProductAttributeGroupRequest $request, ProductAttributeGroup $productAttribute): RedirectResponse
    {
        $data = $request->validated();

        $productAttribute->forceFill([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $this->nullable($data['description'] ?? null),
            'selection_type' => $data['selection_type'],
            'status' => $data['status'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.master.product-attributes.edit', $productAttribute)
            ->with('success', 'Product attribute group updated successfully.');
    }

    public function destroy(ProductAttributeGroup $productAttribute): RedirectResponse
    {
        $productAttribute->delete();

        return redirect()
            ->route('admin.master.product-attributes.index')
            ->with('success', 'Product attribute group deleted successfully.');
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
