<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StorePaymentStatusRequest;
use App\Http\Requests\Admin\MasterData\UpdatePaymentStatusRequest;
use App\Models\PaymentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentStatusController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => $request->query('status', ''),
            'category' => $request->query('category', ''),
            'type' => $request->query('type', ''),
        ];

        $paymentStatuses = PaymentStatus::withTrashed()
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('category_description', 'like', "%{$search}%");
                });
            })
            ->when(in_array($filters['category'], PaymentStatus::categories(), true), fn ($query) => $query->where('category', $filters['category']))
            ->when($filters['type'] === 'system', fn ($query) => $query->system())
            ->when($filters['type'] === 'custom', fn ($query) => $query->custom())
            ->when($filters['status'] === 'trash', fn ($query) => $query->onlyTrashed())
            ->when(in_array($filters['status'], [PaymentStatus::STATUS_ACTIVE, PaymentStatus::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $filters['status'])->whereNull('deleted_at'))
            ->when($filters['status'] !== 'trash', fn ($query) => $query->whereNull('deleted_at'))
            ->ordered()
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.master-data.payment-statuses.index', [
            'paymentStatuses' => $paymentStatuses,
            'filters' => $filters,
            'categories' => PaymentStatus::categories(),
            'categoryDescriptions' => PaymentStatus::categoryDescriptions(),
            'badgeTypes' => PaymentStatus::badgeTypes(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.payment-statuses.create', [
            'paymentStatus' => null,
            'categories' => PaymentStatus::categories(),
            'categoryDescriptions' => PaymentStatus::categoryDescriptions(),
            'badgeTypes' => PaymentStatus::badgeTypes(),
        ]);
    }

    public function store(StorePaymentStatusRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();

        PaymentStatus::query()->create([
            'code' => $this->uniqueCode($data['name']),
            'name' => trim($data['name']),
            'category' => $data['category'],
            'description' => $this->nullable($data['description'] ?? null),
            'category_description' => $this->nullable($data['category_description'] ?? null) ?? PaymentStatus::categoryDescriptions()[$data['category']] ?? null,
            'badge_type' => $data['badge_type'],
            'sort_order' => (int) $data['sort_order'],
            'is_system' => false,
            'is_terminal' => (bool) ($data['is_terminal'] ?? false),
            'merchant_visible' => (bool) ($data['merchant_visible'] ?? false),
            'status' => $data['status'],
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return redirect()
            ->route('admin.master.payment-statuses.index')
            ->with('success', 'Payment status created successfully.');
    }

    public function edit(PaymentStatus $paymentStatus): View
    {
        return view('admin.master-data.payment-statuses.edit', [
            'paymentStatus' => $paymentStatus,
            'categories' => PaymentStatus::categories(),
            'categoryDescriptions' => PaymentStatus::categoryDescriptions(),
            'badgeTypes' => PaymentStatus::badgeTypes(),
        ]);
    }

    public function update(UpdatePaymentStatusRequest $request, PaymentStatus $paymentStatus): RedirectResponse
    {
        abort_if($paymentStatus->trashed(), 404);

        $data = $request->validated();
        $updates = [
            'name' => trim($data['name']),
            'description' => $this->nullable($data['description'] ?? null),
            'category_description' => $this->nullable($data['category_description'] ?? null) ?? PaymentStatus::categoryDescriptions()[$paymentStatus->category] ?? null,
            'badge_type' => $data['badge_type'],
            'sort_order' => (int) $data['sort_order'],
            'merchant_visible' => (bool) ($data['merchant_visible'] ?? false),
            'updated_by' => Auth::id(),
        ];

        if (! $paymentStatus->is_system) {
            $updates['category'] = $data['category'];
            $updates['category_description'] = $this->nullable($data['category_description'] ?? null) ?? PaymentStatus::categoryDescriptions()[$data['category']] ?? null;
            $updates['is_terminal'] = (bool) ($data['is_terminal'] ?? false);
            $updates['status'] = $data['status'];
        }

        $paymentStatus->forceFill($updates)->save();

        return redirect()
            ->route('admin.master.payment-statuses.edit', $paymentStatus)
            ->with('success', 'Payment status updated successfully.');
    }

    public function destroy(PaymentStatus $paymentStatus): RedirectResponse
    {
        abort_if($paymentStatus->trashed(), 404);

        if ($paymentStatus->is_system) {
            throw ValidationException::withMessages([
                'payment_status' => 'System payment statuses cannot be deleted.',
            ]);
        }

        if ($this->isStatusCodeUsed($paymentStatus->code)) {
            throw ValidationException::withMessages([
                'payment_status' => 'This payment status cannot be deleted because it is already used by orders.',
            ]);
        }

        DB::transaction(function () use ($paymentStatus): void {
            $paymentStatus->forceFill([
                'deleted_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ])->save();
            $paymentStatus->delete();
        });

        return redirect()
            ->route('admin.master.payment-statuses.index')
            ->with('success', 'Payment status deleted successfully.');
    }

    public function restore(PaymentStatus $paymentStatus): RedirectResponse
    {
        abort_unless($paymentStatus->trashed(), 404);
        abort_if($paymentStatus->is_system, 404);

        DB::transaction(function () use ($paymentStatus): void {
            $paymentStatus->restore();
            $paymentStatus->forceFill([
                'deleted_by' => null,
                'status' => PaymentStatus::STATUS_INACTIVE,
                'updated_by' => Auth::id(),
            ])->save();
        });

        return redirect()
            ->route('admin.master.payment-statuses.index', ['status' => 'trash'])
            ->with('success', 'Payment status restored successfully.');
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::snake(Str::lower(Str::slug($name, '_'))) ?: 'custom';
        $code = $base;
        $suffix = 2;

        while (PaymentStatus::withTrashed()->where('code', $code)->exists()) {
            $code = "{$base}_{$suffix}";
            $suffix++;
        }

        return $code;
    }

    private function isStatusCodeUsed(string $code): bool
    {
        return DB::table('orders')->where('payment_status', $code)->exists();
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
