<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Models\CatalogueMasterRequest;
use App\Models\ProductAttributeGroup;
use App\Models\ProductCategory;
use App\Models\ProductCategoryAttributeGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogueMasterRequestController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'status' => (string) $request->query('status', ''),
            'type' => (string) $request->query('type', ''),
            'search' => trim((string) $request->query('search', '')),
        ];

        $requests = CatalogueMasterRequest::query()
            ->with(['merchant', 'shop', 'rootCategory', 'parentCategory', 'requestedBy', 'reviewedBy'])
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['type'] !== '', fn ($query) => $query->where('request_type', $filters['type']))
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('suggested_name', 'like', "%{$search}%")
                        ->orWhere('example_product_name', 'like', "%{$search}%")
                        ->orWhereHas('merchant', fn ($query) => $query->where('business_name', 'like', "%{$search}%"))
                        ->orWhereHas('shop', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.master-data.catalogue-master-requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'statuses' => $this->statuses(),
            'types' => $this->types(),
        ]);
    }

    public function update(Request $request, CatalogueMasterRequest $catalogueMasterRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($catalogueMasterRequest, $data): void {
            if ($data['status'] === CatalogueMasterRequest::STATUS_APPROVED) {
                $this->applyApprovedRequest($catalogueMasterRequest);
            }

            $catalogueMasterRequest->forceFill([
                'status' => $data['status'],
                'admin_note' => $data['admin_note'] ?? null,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ])->save();
        });

        return redirect()
            ->route('admin.master.catalogue-requests.index')
            ->with('success', 'Catalogue request updated successfully.');
    }

    private function statuses(): array
    {
        return [
            CatalogueMasterRequest::STATUS_PENDING => 'Pending',
            CatalogueMasterRequest::STATUS_APPROVED => 'Approved',
            CatalogueMasterRequest::STATUS_REJECTED => 'Rejected',
            CatalogueMasterRequest::STATUS_NEEDS_INFO => 'Needs info',
        ];
    }

    private function types(): array
    {
        return [
            CatalogueMasterRequest::TYPE_CATEGORY => 'Category',
            CatalogueMasterRequest::TYPE_ATTRIBUTE => 'Attribute',
        ];
    }

    private function applyApprovedRequest(CatalogueMasterRequest $request): void
    {
        if ($request->request_type === CatalogueMasterRequest::TYPE_CATEGORY) {
            $this->approveCategoryRequest($request);
            return;
        }

        if ($request->request_type === CatalogueMasterRequest::TYPE_ATTRIBUTE) {
            $this->approveAttributeRequest($request);
        }
    }

    private function approveCategoryRequest(CatalogueMasterRequest $request): void
    {
        $parentId = $request->parent_product_category_id ?: $request->root_product_category_id;
        $existing = ProductCategory::query()
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($request->suggested_name))])
            ->first();

        if ($existing) {
            $existing->forceFill([
                'status' => 'active',
                'updated_by' => Auth::id(),
            ])->save();
            return;
        }

        $category = ProductCategory::query()->create([
            'parent_id' => $parentId,
            'name' => $request->suggested_name,
            'slug' => 'pending-'.Str::uuid()->toString(),
            'description' => $this->nullable($request->description),
            'sort_order' => $this->nextCategorySortOrder($parentId),
            'status' => 'active',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $category->updateQuietly([
            'slug' => (Str::slug($category->name) ?: 'category').'-'.$category->getKey(),
        ]);
    }

    private function approveAttributeRequest(CatalogueMasterRequest $request): void
    {
        $code = Str::slug($request->suggested_name, '_') ?: 'attribute';
        $group = ProductAttributeGroup::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($request->suggested_name))])
            ->orWhere('code', $code)
            ->first();

        if (! $group) {
            $group = ProductAttributeGroup::query()->create([
                'name' => $request->suggested_name,
                'code' => $this->uniqueAttributeCode($code),
                'description' => $this->nullable($request->description),
                'selection_type' => 'single',
                'status' => 'active',
                'sort_order' => $this->nextAttributeSortOrder(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        } else {
            $group->forceFill([
                'status' => 'active',
                'updated_by' => Auth::id(),
            ])->save();
        }

        ProductCategoryAttributeGroup::query()->firstOrCreate([
            'root_product_category_id' => $request->root_product_category_id,
            'product_attribute_group_id' => $group->getKey(),
        ], [
            'is_required' => false,
            'is_variant' => false,
            'is_image_attribute' => false,
            'sort_order' => $this->nextAttributeMappingSortOrder((int) $request->root_product_category_id),
        ]);
    }

    private function nextCategorySortOrder(int $parentId): int
    {
        $max = (int) ProductCategory::query()
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(TRIM(name)) <> ?', ['other'])
            ->max('sort_order');

        return min($max + 1, 98);
    }

    private function nextAttributeSortOrder(): int
    {
        return ((int) ProductAttributeGroup::query()->max('sort_order')) + 1;
    }

    private function nextAttributeMappingSortOrder(int $rootCategoryId): int
    {
        return ((int) ProductCategoryAttributeGroup::query()
            ->where('root_product_category_id', $rootCategoryId)
            ->max('sort_order')) + 1;
    }

    private function uniqueAttributeCode(string $base): string
    {
        $code = $base;
        $counter = 2;

        while (ProductAttributeGroup::query()->where('code', $code)->exists()) {
            $code = $base.'_'.$counter;
            $counter++;
        }

        return $code;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
