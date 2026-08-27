<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Models\CustomerCancellationReason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerCancellationReasonController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => $request->query('status'),
        ];

        $reasons = CustomerCancellationReason::query()
            ->when($filters['search'] !== '', fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('code', 'like', '%'.$filters['search'].'%')))
            ->when(in_array($filters['status'], CustomerCancellationReason::statuses(), true), fn ($query) => $query->where('status', $filters['status']))
            ->ordered()
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.master-data.customer-cancellation-reasons.index', compact('reasons', 'filters'));
    }

    public function create(): View
    {
        return view('admin.master-data.customer-cancellation-reasons.create', [
            'reason' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CustomerCancellationReason::query()->create($this->validated($request));

        return redirect()
            ->route('admin.master.customer-cancellation-reasons.index')
            ->with('success', 'Customer cancellation reason created successfully.');
    }

    public function edit(CustomerCancellationReason $customerCancellationReason): View
    {
        return view('admin.master-data.customer-cancellation-reasons.edit', [
            'reason' => $customerCancellationReason,
        ]);
    }

    public function update(Request $request, CustomerCancellationReason $customerCancellationReason): RedirectResponse
    {
        $customerCancellationReason->forceFill($this->validated($request, $customerCancellationReason))->save();

        return redirect()
            ->route('admin.master.customer-cancellation-reasons.edit', $customerCancellationReason)
            ->with('success', 'Customer cancellation reason updated successfully.');
    }

    public function destroy(CustomerCancellationReason $customerCancellationReason): RedirectResponse
    {
        $customerCancellationReason->delete();

        return redirect()
            ->route('admin.master.customer-cancellation-reasons.index')
            ->with('success', 'Customer cancellation reason deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CustomerCancellationReason $reason = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('customer_cancellation_reasons', 'code')->ignore($reason?->getKey()),
            ],
            'name' => ['required', 'string', 'max:150'],
            'requires_comment' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(CustomerCancellationReason::statuses())],
        ]);

        $data['requires_comment'] = $request->boolean('requires_comment');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
