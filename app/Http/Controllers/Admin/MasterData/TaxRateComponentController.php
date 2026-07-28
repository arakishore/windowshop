<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StoreTaxRateComponentRequest;
use App\Http\Requests\Admin\MasterData\UpdateTaxRateComponentRequest;
use App\Models\TaxRate;
use App\Models\TaxRateComponent;
use App\Services\Tax\TaxRateComponentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaxRateComponentController extends Controller
{
    public function __construct(private readonly TaxRateComponentService $components)
    {
    }

    public function create(TaxRate $taxRate): View
    {
        return view('admin.master-data.tax-rate-components.create', [
            'taxRate' => $taxRate->load('taxClass.country'),
            'taxRateComponent' => null,
            'jurisdictionTypes' => $this->jurisdictionTypes(),
        ]);
    }

    public function store(StoreTaxRateComponentRequest $request, TaxRate $taxRate): RedirectResponse
    {
        $this->components->create($taxRate, $request->validated());

        return redirect()
            ->route('admin.master.tax-classes.rates.edit', [$taxRate->taxClass, $taxRate])
            ->with('success', 'Tax component created successfully.');
    }

    public function edit(TaxRate $taxRate, TaxRateComponent $component): View
    {
        abort_unless((int) $component->tax_rate_id === (int) $taxRate->getKey(), 404);

        return view('admin.master-data.tax-rate-components.edit', [
            'taxRate' => $taxRate->load('taxClass.country'),
            'taxRateComponent' => $component,
            'jurisdictionTypes' => $this->jurisdictionTypes(),
        ]);
    }

    public function update(UpdateTaxRateComponentRequest $request, TaxRate $taxRate, TaxRateComponent $component): RedirectResponse
    {
        abort_unless((int) $component->tax_rate_id === (int) $taxRate->getKey(), 404);
        $this->components->update($component, $request->validated());

        return redirect()
            ->route('admin.master.tax-classes.rates.edit', [$taxRate->taxClass, $taxRate])
            ->with('success', 'Tax component updated successfully.');
    }

    public function destroy(TaxRate $taxRate, TaxRateComponent $component): RedirectResponse
    {
        abort_unless((int) $component->tax_rate_id === (int) $taxRate->getKey(), 404);
        $this->components->delete($component);

        return redirect()
            ->route('admin.master.tax-classes.rates.edit', [$taxRate->taxClass, $taxRate])
            ->with('success', 'Tax component deleted successfully.');
    }

    public function restore(TaxRate $taxRate, TaxRateComponent $component): RedirectResponse
    {
        abort_unless((int) $component->tax_rate_id === (int) $taxRate->getKey(), 404);
        $this->components->restore($component);

        return redirect()
            ->route('admin.master.tax-classes.rates.edit', [$taxRate->taxClass, $taxRate])
            ->with('success', 'Tax component restored successfully.');
    }

    private function jurisdictionTypes(): array
    {
        return [
            TaxRateComponent::JURISDICTION_CENTRAL => 'Central',
            TaxRateComponent::JURISDICTION_STATE => 'State',
            TaxRateComponent::JURISDICTION_INTEGRATED => 'Integrated',
            TaxRateComponent::JURISDICTION_CESS => 'Cess',
            TaxRateComponent::JURISDICTION_LOCAL => 'Local',
        ];
    }
}
