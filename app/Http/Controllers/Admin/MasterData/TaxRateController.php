<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StoreTaxRateRequest;
use App\Http\Requests\Admin\MasterData\UpdateTaxRateRequest;
use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Services\Tax\TaxRateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TaxRateController extends Controller
{
    public function __construct(private readonly TaxRateService $taxRates)
    {
    }

    public function create(TaxClass $taxClass): View
    {
        return view('admin.master-data.tax-rates.create', [
            'taxClass' => $taxClass->load('country'),
            'taxRate' => null,
        ]);
    }

    public function store(StoreTaxRateRequest $request, TaxClass $taxClass): RedirectResponse
    {
        $this->taxRates->create($taxClass, $request->validated());

        return redirect()
            ->route('admin.master.tax-classes.show', $taxClass)
            ->with('success', 'Tax rate created successfully.');
    }

    public function edit(TaxClass $taxClass, TaxRate $taxRate): View
    {
        abort_unless((int) $taxRate->tax_class_id === (int) $taxClass->getKey(), 404);

        return view('admin.master-data.tax-rates.edit', [
            'taxClass' => $taxClass->load('country'),
            'taxRate' => $taxRate->load(['components' => fn ($query) => $query->withTrashed()]),
        ]);
    }

    public function update(UpdateTaxRateRequest $request, TaxClass $taxClass, TaxRate $taxRate): RedirectResponse
    {
        abort_unless((int) $taxRate->tax_class_id === (int) $taxClass->getKey(), 404);
        $this->taxRates->update($taxRate, $request->validated());

        return redirect()
            ->route('admin.master.tax-classes.rates.edit', [$taxClass, $taxRate])
            ->with('success', 'Tax rate updated successfully.');
    }

    public function destroy(TaxClass $taxClass, TaxRate $taxRate): RedirectResponse
    {
        abort_unless((int) $taxRate->tax_class_id === (int) $taxClass->getKey(), 404);
        $this->taxRates->delete($taxRate);

        return redirect()
            ->route('admin.master.tax-classes.show', $taxClass)
            ->with('success', 'Tax rate deleted successfully.');
    }

    public function restore(TaxClass $taxClass, TaxRate $taxRate): RedirectResponse
    {
        abort_unless((int) $taxRate->tax_class_id === (int) $taxClass->getKey(), 404);
        $this->taxRates->restore($taxRate);

        return redirect()
            ->route('admin.master.tax-classes.show', $taxClass)
            ->with('success', 'Tax rate restored successfully.');
    }
}
