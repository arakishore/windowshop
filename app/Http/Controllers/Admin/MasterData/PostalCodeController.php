<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StorePostalCodeRequest;
use App\Http\Requests\Admin\MasterData\UpdatePostalCodeRequest;
use App\Models\PostalCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PostalCodeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'postal_code' => trim((string) $request->query('postal_code', '')),
            'state' => trim((string) $request->query('state', '')),
            'district' => trim((string) $request->query('district', '')),
            'shipping_enabled' => $request->query('shipping_enabled'),
            'status' => $request->query('status'),
            'search' => trim((string) $request->query('search', '')),
        ];

        $postalCodes = PostalCode::withTrashed()
            ->when($filters['postal_code'] !== '', fn ($query) => $query->where('postal_code', 'like', "{$filters['postal_code']}%"))
            ->when($filters['state'] !== '', fn ($query) => $query->where('state', $filters['state']))
            ->when($filters['district'] !== '', fn ($query) => $query->where('district', $filters['district']))
            ->when(in_array($filters['shipping_enabled'], ['0', '1'], true), fn ($query) => $query->where('shipping_enabled', (bool) (int) $filters['shipping_enabled']))
            ->when($filters['status'] === 'trash', fn ($query) => $query->onlyTrashed())
            ->when(in_array($filters['status'], [PostalCode::STATUS_ACTIVE, PostalCode::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $filters['status'])->whereNull('deleted_at'))
            ->when($filters['status'] !== 'trash', fn ($query) => $query->whereNull('deleted_at'))
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('postal_code', 'like', "%{$search}%")
                        ->orWhere('office_name', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhere('state', 'like', "%{$search}%")
                        ->orWhere('division_name', 'like', "%{$search}%")
                        ->orWhere('region_name', 'like', "%{$search}%")
                        ->orWhere('circle_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('state')
            ->orderBy('district')
            ->orderBy('postal_code')
            ->orderBy('office_name')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.master-data.postal-codes.index', [
            'postalCodes' => $postalCodes,
            'filters' => $filters,
            'states' => $this->states(),
            'districts' => $this->districts($filters['state']),
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.postal-codes.create', [
            'postalCode' => null,
        ]);
    }

    public function store(StorePostalCodeRequest $request): RedirectResponse
    {
        $data = $this->withSourceKey($request->normalized());
        $this->ensureUniqueSourceKey($data['source_key']);

        PostalCode::query()->create($data);

        return redirect()
            ->route('admin.master.postal-codes.index')
            ->with('success', 'Postal code created successfully.');
    }

    public function show(PostalCode $postalCode): View
    {
        return view('admin.master-data.postal-codes.show', [
            'postalCode' => $postalCode,
        ]);
    }

    public function edit(PostalCode $postalCode): View
    {
        return view('admin.master-data.postal-codes.edit', [
            'postalCode' => $postalCode,
        ]);
    }

    public function update(UpdatePostalCodeRequest $request, PostalCode $postalCode): RedirectResponse
    {
        $data = $this->withSourceKey($request->normalized());
        $this->ensureUniqueSourceKey($data['source_key'], $postalCode);

        $postalCode->forceFill($data)->save();

        return redirect()
            ->route('admin.master.postal-codes.edit', $postalCode)
            ->with('success', 'Postal code updated successfully.');
    }

    public function destroy(PostalCode $postalCode): RedirectResponse
    {
        $postalCode->delete();

        return redirect()
            ->route('admin.master.postal-codes.index')
            ->with('success', 'Postal code deleted successfully.');
    }

    public function restore(PostalCode $postalCode): RedirectResponse
    {
        $postalCode->restore();

        return redirect()
            ->route('admin.master.postal-codes.index', ['status' => 'trash'])
            ->with('success', 'Postal code restored successfully.');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withSourceKey(array $data): array
    {
        $data['source_key'] = sha1(implode('|', [
            strtolower((string) ($data['postal_code'] ?? '')),
            strtolower((string) ($data['office_name'] ?? '')),
            strtolower((string) ($data['office_type'] ?? '')),
            strtolower((string) ($data['district'] ?? '')),
            strtolower((string) ($data['state'] ?? '')),
        ]));

        return $data;
    }

    private function ensureUniqueSourceKey(string $sourceKey, ?PostalCode $postalCode = null): void
    {
        $exists = PostalCode::withTrashed()
            ->where('source_key', $sourceKey)
            ->when($postalCode, fn ($query) => $query->whereKeyNot($postalCode->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'postal_code' => 'This postal code location record already exists.',
            ]);
        }
    }

    private function states()
    {
        return DB::table('postal_codes')
            ->whereNull('deleted_at')
            ->whereNotNull('state')
            ->distinct()
            ->orderBy('state')
            ->pluck('state');
    }

    private function districts(?string $state = null)
    {
        return DB::table('postal_codes')
            ->whereNull('deleted_at')
            ->whereNotNull('district')
            ->when($state, fn ($query) => $query->where('state', $state))
            ->distinct()
            ->orderBy('district')
            ->pluck('district');
    }
}
