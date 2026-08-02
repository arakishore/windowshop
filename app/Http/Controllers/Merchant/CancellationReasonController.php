<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\MerchantCancellationReason;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CancellationReasonController extends Controller
{
    public function __construct(private readonly MerchantShopContextService $shopContextService)
    {
    }

    public function index(Request $request): View
    {
        $merchant = $this->activeMerchant($request);
        $filters = $this->filters($request);

        $reasons = MerchantCancellationReason::query()
            ->forMerchant((int) $merchant->getKey())
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(
                in_array($filters['status'], MerchantCancellationReason::statuses(), true),
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->ordered()
            ->get();

        return view('merchant.cancellation-reasons.index', [
            'merchant' => $merchant,
            'reasons' => $reasons,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        $this->activeMerchant($request);

        return view('merchant.cancellation-reasons.create', [
            'reason' => new MerchantCancellationReason([
                'sort_order' => 99,
                'customer_selectable' => false,
                'merchant_selectable' => true,
                'requires_comment' => false,
                'status' => MerchantCancellationReason::STATUS_ACTIVE,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $merchant = $this->activeMerchant($request);
        $data = $this->validated($request, (int) $merchant->getKey());

        MerchantCancellationReason::query()->create([
            ...$data,
            'merchant_id' => $merchant->getKey(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('merchant.cancellation-reasons.index')
            ->with('success', 'Cancellation reason created successfully.');
    }

    public function edit(Request $request, MerchantCancellationReason $cancellationReason): View
    {
        $merchant = $this->activeMerchant($request);
        $this->authorizeMerchantReason($merchant->getKey(), $cancellationReason);

        return view('merchant.cancellation-reasons.edit', [
            'reason' => $cancellationReason,
        ]);
    }

    public function update(Request $request, MerchantCancellationReason $cancellationReason): RedirectResponse
    {
        $merchant = $this->activeMerchant($request);
        $this->authorizeMerchantReason($merchant->getKey(), $cancellationReason);
        abort_if($cancellationReason->trashed(), 404);

        $data = $this->validated($request, (int) $merchant->getKey(), $cancellationReason);

        $cancellationReason->forceFill([
            ...$data,
            'code' => $cancellationReason->code,
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('merchant.cancellation-reasons.edit', $cancellationReason)
            ->with('success', 'Cancellation reason updated successfully.');
    }

    public function destroy(Request $request, MerchantCancellationReason $cancellationReason): RedirectResponse
    {
        $merchant = $this->activeMerchant($request);
        $this->authorizeMerchantReason($merchant->getKey(), $cancellationReason);
        abort_if($cancellationReason->trashed(), 404);

        $cancellationReason->forceFill([
            'updated_by' => Auth::id(),
        ])->save();
        $cancellationReason->delete();

        return redirect()
            ->route('merchant.cancellation-reasons.index')
            ->with('success', 'Cancellation reason deleted successfully.');
    }

    public function trash(Request $request): View
    {
        $merchant = $this->activeMerchant($request);
        $filters = $this->filters($request);

        $reasons = MerchantCancellationReason::onlyTrashed()
            ->forMerchant((int) $merchant->getKey())
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->ordered()
            ->get();

        return view('merchant.cancellation-reasons.trash', [
            'reasons' => $reasons,
            'filters' => $filters,
        ]);
    }

    public function restore(Request $request, string $cancellationReason): RedirectResponse
    {
        $merchant = $this->activeMerchant($request);

        $reason = MerchantCancellationReason::onlyTrashed()
            ->forMerchant((int) $merchant->getKey())
            ->where('uuid', $cancellationReason)
            ->firstOrFail();

        $reason->restore();
        $reason->forceFill([
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('merchant.cancellation-reasons.edit', $reason)
            ->with('success', 'Cancellation reason restored successfully.');
    }

    /**
     * @return array{search: string, status: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', ''),
        ];
    }

    /**
     * @return array{code: string, name: string, description: ?string, internal_notes: ?string, sort_order: int, customer_selectable: bool, merchant_selectable: bool, requires_comment: bool, status: string}
     */
    private function validated(Request $request, int $merchantId, ?MerchantCancellationReason $reason = null): array
    {
        $codeRules = $reason
            ? ['nullable']
            : [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/',
                Rule::unique('merchant_cancellation_reasons', 'code')
                    ->where(fn ($query) => $query->where('merchant_id', $merchantId)),
            ];

        $data = $request->validate([
            'code' => $codeRules,
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'internal_notes' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'customer_selectable' => ['nullable', 'boolean'],
            'merchant_selectable' => ['nullable', 'boolean'],
            'requires_comment' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(MerchantCancellationReason::statuses())],
        ]);

        $code = $reason?->code ?? trim((string) $data['code']);
        $customerSelectable = (bool) ($data['customer_selectable'] ?? false);
        $merchantSelectable = (bool) ($data['merchant_selectable'] ?? false);
        $requiresComment = (bool) ($data['requires_comment'] ?? false);

        if (! $customerSelectable && ! $merchantSelectable) {
            throw ValidationException::withMessages([
                'merchant_selectable' => 'At least one selectable audience is required.',
            ]);
        }

        if ($code === MerchantCancellationReason::CODE_OTHER && ! $requiresComment) {
            throw ValidationException::withMessages([
                'requires_comment' => 'The Other cancellation reason must require a comment.',
            ]);
        }

        return [
            'code' => $code,
            'name' => trim((string) $data['name']),
            'description' => $this->nullable($data['description'] ?? null),
            'internal_notes' => $this->nullable($data['internal_notes'] ?? null),
            'sort_order' => (int) $data['sort_order'],
            'customer_selectable' => $customerSelectable,
            'merchant_selectable' => $merchantSelectable,
            'requires_comment' => $requiresComment,
            'status' => $data['status'],
        ];
    }

    private function activeMerchant(Request $request)
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant !== null, 403);

        return $merchant;
    }

    private function authorizeMerchantReason(int $merchantId, MerchantCancellationReason $reason): void
    {
        abort_unless((int) $reason->merchant_id === $merchantId, 404);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
