<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StoreTaxClassRequest;
use App\Http\Requests\Admin\MasterData\UpdateTaxClassRequest;
use App\Models\LocCountry;
use App\Models\TaxClass;
use App\Services\Tax\TaxClassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxClassController extends Controller
{
    public function __construct(private readonly TaxClassService $taxClasses)
    {
    }

    public function index(Request $request): View
    {
        $filters = [
            'country_id' => $request->query('country_id'),
            'status' => $request->query('status'),
            'search' => trim((string) $request->query('search', '')),
        ];

        $taxClasses = TaxClass::withTrashed()
            ->with('country')
            ->withCount('rates')
            ->when(is_numeric($filters['country_id']), fn ($query) => $query->where('country_id', (int) $filters['country_id']))
            ->when($filters['status'] === 'trash', fn ($query) => $query->onlyTrashed())
            ->when(in_array($filters['status'], ['active', 'inactive'], true), fn ($query) => $query->where('status', $filters['status'])->whereNull('deleted_at'))
            ->when(! in_array($filters['status'], ['trash'], true), fn ($query) => $query->whereNull('deleted_at'))
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('country_id')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.master-data.tax-classes.index', [
            'taxClasses' => $taxClasses,
            'countries' => $this->countries(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.tax-classes.create', [
            'taxClass' => null,
            'countries' => $this->countries(),
        ]);
    }

    public function store(StoreTaxClassRequest $request): RedirectResponse
    {
        $this->taxClasses->create($request->validated());

        return redirect()
            ->route('admin.master.tax-classes.index')
            ->with('success', 'Tax class created successfully.');
    }

    public function show(TaxClass $taxClass): View
    {
        $taxClass->load('country');

        $rates = $taxClass->rates()
            ->withTrashed()
            ->withCount('components')
            ->orderByDesc('effective_from')
            ->orderByDesc('priority')
            ->paginate((int) config('admin.pagination.per_page', 15));

        return view('admin.master-data.tax-classes.show', [
            'taxClass' => $taxClass,
            'rates' => $rates,
        ]);
    }

    public function edit(TaxClass $taxClass): View
    {
        return view('admin.master-data.tax-classes.edit', [
            'taxClass' => $taxClass->load('country'),
            'countries' => $this->countries($taxClass),
        ]);
    }

    public function update(UpdateTaxClassRequest $request, TaxClass $taxClass): RedirectResponse
    {
        $this->taxClasses->update($taxClass, $request->validated());

        return redirect()
            ->route('admin.master.tax-classes.edit', $taxClass)
            ->with('success', 'Tax class updated successfully.');
    }

    public function destroy(TaxClass $taxClass): RedirectResponse
    {
        $this->taxClasses->delete($taxClass);

        return redirect()
            ->route('admin.master.tax-classes.index')
            ->with('success', 'Tax class deleted successfully.');
    }

    public function restore(TaxClass $taxClass): RedirectResponse
    {
        $this->taxClasses->restore($taxClass);

        return redirect()
            ->route('admin.master.tax-classes.index', ['status' => 'trash'])
            ->with('success', 'Tax class restored successfully.');
    }

    private function countries(?TaxClass $taxClass = null)
    {
        return LocCountry::query()
            ->where(function ($query) use ($taxClass): void {
                $query->where('status', true);

                if ($taxClass?->country_id) {
                    $query->orWhere('id', $taxClass->country_id);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
