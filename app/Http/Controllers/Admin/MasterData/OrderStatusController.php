<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StoreOrderStatusRequest;
use App\Http\Requests\Admin\MasterData\UpdateOrderStatusRequest;
use App\Models\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderStatusController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => $request->query('status', ''),
            'category' => $request->query('category', ''),
            'type' => $request->query('type', ''),
        ];

        $orderStatuses = OrderStatus::withTrashed()
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('customer_label', 'like', "%{$search}%")
                        ->orWhere('admin_description', 'like', "%{$search}%")
                        ->orWhere('customer_description', 'like', "%{$search}%")
                        ->orWhere('internal_notes', 'like', "%{$search}%");
                });
            })
            ->when(in_array($filters['category'], OrderStatus::categories(), true), fn ($query) => $query->where('category', $filters['category']))
            ->when($filters['type'] === 'system', fn ($query) => $query->system())
            ->when($filters['type'] === 'custom', fn ($query) => $query->custom())
            ->when($filters['status'] === 'trash', fn ($query) => $query->onlyTrashed())
            ->when(in_array($filters['status'], [OrderStatus::STATUS_ACTIVE, OrderStatus::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $filters['status'])->whereNull('deleted_at'))
            ->when($filters['status'] !== 'trash', fn ($query) => $query->whereNull('deleted_at'))
            ->ordered()
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.master-data.order-statuses.index', [
            'orderStatuses' => $orderStatuses,
            'filters' => $filters,
            'categories' => OrderStatus::categories(),
            'badgeTypes' => OrderStatus::badgeTypes(),
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.order-statuses.create', [
            'orderStatus' => null,
            'categories' => OrderStatus::categories(),
            'badgeTypes' => OrderStatus::badgeTypes(),
        ]);
    }

    public function store(StoreOrderStatusRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actorId = Auth::id();

        OrderStatus::query()->create([
            'code' => $this->uniqueCode($data['name']),
            'name' => trim($data['name']),
            'customer_label' => $this->nullable($data['customer_label'] ?? null),
            'admin_description' => $this->nullable($data['admin_description'] ?? null),
            'customer_description' => $this->nullable($data['customer_description'] ?? null),
            'internal_notes' => $this->nullable($data['internal_notes'] ?? null),
            'category' => $data['category'],
            'badge_type' => $data['badge_type'],
            'sort_order' => (int) $data['sort_order'],
            'is_system' => false,
            'is_terminal' => (bool) ($data['is_terminal'] ?? false),
            'customer_visible' => (bool) ($data['customer_visible'] ?? false),
            'merchant_visible' => (bool) ($data['merchant_visible'] ?? false),
            'status' => $data['status'],
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return redirect()
            ->route('admin.master.order-statuses.index')
            ->with('success', 'Order status created successfully.');
    }

    public function edit(OrderStatus $orderStatus): View
    {
        return view('admin.master-data.order-statuses.edit', [
            'orderStatus' => $orderStatus,
            'categories' => OrderStatus::categories(),
            'badgeTypes' => OrderStatus::badgeTypes(),
        ]);
    }

    public function update(UpdateOrderStatusRequest $request, OrderStatus $orderStatus): RedirectResponse
    {
        abort_if($orderStatus->trashed(), 404);

        $data = $request->validated();
        $updates = [
            'name' => trim($data['name']),
            'customer_label' => $this->nullable($data['customer_label'] ?? null),
            'admin_description' => $this->nullable($data['admin_description'] ?? null),
            'customer_description' => $this->nullable($data['customer_description'] ?? null),
            'internal_notes' => $this->nullable($data['internal_notes'] ?? null),
            'badge_type' => $data['badge_type'],
            'sort_order' => (int) $data['sort_order'],
            'customer_visible' => (bool) ($data['customer_visible'] ?? false),
            'merchant_visible' => (bool) ($data['merchant_visible'] ?? false),
            'updated_by' => Auth::id(),
        ];

        if (! $orderStatus->is_system) {
            $updates['category'] = $data['category'];
            $updates['is_terminal'] = (bool) ($data['is_terminal'] ?? false);
            $updates['status'] = $data['status'];
        }

        $orderStatus->forceFill($updates)->save();

        return redirect()
            ->route('admin.master.order-statuses.edit', $orderStatus)
            ->with('success', 'Order status updated successfully.');
    }

    public function destroy(OrderStatus $orderStatus): RedirectResponse
    {
        abort_if($orderStatus->trashed(), 404);

        if ($orderStatus->is_system) {
            throw ValidationException::withMessages([
                'order_status' => 'System order statuses cannot be deleted.',
            ]);
        }

        if ($this->isStatusCodeUsed($orderStatus->code)) {
            throw ValidationException::withMessages([
                'order_status' => 'This order status cannot be deleted because it is already used by orders or order status history.',
            ]);
        }

        DB::transaction(function () use ($orderStatus): void {
            $orderStatus->forceFill([
                'deleted_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ])->save();
            $orderStatus->delete();
        });

        return redirect()
            ->route('admin.master.order-statuses.index')
            ->with('success', 'Order status deleted successfully.');
    }

    public function restore(OrderStatus $orderStatus): RedirectResponse
    {
        abort_unless($orderStatus->trashed(), 404);
        abort_if($orderStatus->is_system, 404);

        DB::transaction(function () use ($orderStatus): void {
            $orderStatus->restore();
            $orderStatus->forceFill([
                'deleted_by' => null,
                'status' => OrderStatus::STATUS_INACTIVE,
                'updated_by' => Auth::id(),
            ])->save();
        });

        return redirect()
            ->route('admin.master.order-statuses.index', ['status' => 'trash'])
            ->with('success', 'Order status restored successfully.');
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::snake(Str::lower(Str::slug($name, '_'))) ?: 'custom';
        $code = $base;
        $suffix = 2;

        while (OrderStatus::withTrashed()->where('code', $code)->exists()) {
            $code = "{$base}_{$suffix}";
            $suffix++;
        }

        return $code;
    }

    private function isStatusCodeUsed(string $code): bool
    {
        return DB::table('orders')->where('order_status', $code)->exists()
            || DB::table('order_status_histories')->where('from_status', $code)->orWhere('to_status', $code)->exists();
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
