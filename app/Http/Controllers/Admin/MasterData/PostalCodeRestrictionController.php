<?php

namespace App\Http\Controllers\Admin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterData\StorePostalCodeRestrictionRequest;
use App\Http\Requests\Admin\MasterData\UpdatePostalCodeRestrictionRequest;
use App\Models\PostalCodeRestriction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PostalCodeRestrictionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'state' => trim((string) $request->query('state', '')),
            'district' => trim((string) $request->query('district', '')),
            'status' => $request->query('status'),
            'search' => trim((string) $request->query('search', '')),
        ];

        $restrictions = PostalCodeRestriction::withTrashed()
            ->global()
            ->when($filters['state'] !== '', fn ($query) => $query->whereIn('postal_code', $this->postalCodesForLocation($filters['state'], null)))
            ->when($filters['district'] !== '', fn ($query) => $query->whereIn('postal_code', $this->postalCodesForLocation($filters['state'], $filters['district'])))
            ->when($filters['status'] === 'trash', fn ($query) => $query->onlyTrashed())
            ->when(in_array($filters['status'], [PostalCodeRestriction::STATUS_ACTIVE, PostalCodeRestriction::STATUS_INACTIVE], true), fn ($query) => $query->where('status', $filters['status'])->whereNull('deleted_at'))
            ->when($filters['status'] !== 'trash', fn ($query) => $query->whereNull('deleted_at'))
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $postalCodes = $this->postalCodesForSearch($search);

                $query->where(function ($query) use ($search, $postalCodes): void {
                    $query->where('postal_code', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhereIn('postal_code', $postalCodes);
                });
            })
            ->orderBy('postal_code')
            ->orderByDesc('id')
            ->paginate((int) config('admin.pagination.per_page', 15))
            ->withQueryString();

        return view('admin.master-data.postal-code-restrictions.index', [
            'restrictions' => $restrictions,
            'filters' => $filters,
            'states' => $this->states(),
            'districts' => $this->districts($filters['state']),
            'locations' => $this->locationsFor($restrictions->pluck('postal_code')->all()),
        ]);
    }

    public function create(): View
    {
        return view('admin.master-data.postal-code-restrictions.create', [
            'restriction' => null,
            'globalWarning' => null,
        ]);
    }

    public function store(StorePostalCodeRestrictionRequest $request): RedirectResponse
    {
        $data = $request->normalizedRestrictionData();
        $this->ensureNoCurrentDuplicate($data['postal_code']);

        PostalCodeRestriction::query()->create([
            ...$data,
            'merchant_id' => null,
            'shop_id' => null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.master.postal-code-restrictions.index')
            ->with('success', 'Postal code restriction created successfully.');
    }

    public function edit(PostalCodeRestriction $postalCodeRestriction): View
    {
        abort_unless($postalCodeRestriction->merchant_id === null && $postalCodeRestriction->shop_id === null, 404);

        return view('admin.master-data.postal-code-restrictions.edit', [
            'restriction' => $postalCodeRestriction,
            'globalWarning' => null,
        ]);
    }

    public function update(UpdatePostalCodeRestrictionRequest $request, PostalCodeRestriction $postalCodeRestriction): RedirectResponse
    {
        abort_unless($postalCodeRestriction->merchant_id === null && $postalCodeRestriction->shop_id === null, 404);
        $data = $request->normalizedRestrictionData();
        $this->ensureNoCurrentDuplicate($data['postal_code'], $postalCodeRestriction);

        $postalCodeRestriction->forceFill([
            ...$data,
            'merchant_id' => null,
            'shop_id' => null,
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.master.postal-code-restrictions.edit', $postalCodeRestriction)
            ->with('success', 'Postal code restriction updated successfully.');
    }

    public function destroy(PostalCodeRestriction $postalCodeRestriction): RedirectResponse
    {
        abort_unless($postalCodeRestriction->merchant_id === null && $postalCodeRestriction->shop_id === null, 404);
        $postalCodeRestriction->delete();

        return redirect()
            ->route('admin.master.postal-code-restrictions.index')
            ->with('success', 'Postal code restriction deleted successfully.');
    }

    public function restore(PostalCodeRestriction $postalCodeRestriction): RedirectResponse
    {
        abort_unless($postalCodeRestriction->merchant_id === null && $postalCodeRestriction->shop_id === null, 404);
        $postalCodeRestriction->restore();

        return redirect()
            ->route('admin.master.postal-code-restrictions.index', ['status' => 'trash'])
            ->with('success', 'Postal code restriction restored successfully.');
    }

    public function toggleStatus(PostalCodeRestriction $postalCodeRestriction): RedirectResponse
    {
        abort_unless($postalCodeRestriction->merchant_id === null && $postalCodeRestriction->shop_id === null, 404);

        $postalCodeRestriction->forceFill([
            'status' => $postalCodeRestriction->status === PostalCodeRestriction::STATUS_ACTIVE
                ? PostalCodeRestriction::STATUS_INACTIVE
                : PostalCodeRestriction::STATUS_ACTIVE,
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('admin.master.postal-code-restrictions.index')
            ->with('success', 'Postal code restriction status updated successfully.');
    }

    private function ensureNoCurrentDuplicate(string $postalCode, ?PostalCodeRestriction $ignore = null): void
    {
        $request = request();

        if ($request->input('status') !== PostalCodeRestriction::STATUS_ACTIVE || ! $this->requestRestrictionIsCurrent($request->input('starts_at'), $request->input('ends_at'))) {
            return;
        }

        $exists = PostalCodeRestriction::query()
            ->global()
            ->forPostalCode($postalCode)
            ->currentlyApplicable()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'postal_code' => 'This postal code already has an active marketplace-wide restriction.',
            ]);
        }
    }

    private function requestRestrictionIsCurrent(mixed $startsAt, mixed $endsAt): bool
    {
        $now = now();

        if ($startsAt && \Illuminate\Support\Carbon::parse($startsAt)->greaterThan($now)) {
            return false;
        }

        if ($endsAt && \Illuminate\Support\Carbon::parse($endsAt)->lessThan($now)) {
            return false;
        }

        return true;
    }

    private function postalCodesForSearch(string $search): array
    {
        return DB::table('postal_codes')
            ->whereNull('deleted_at')
            ->where(fn ($query) => $query->where('office_name', 'like', "%{$search}%")
                ->orWhere('district', 'like', "%{$search}%")
                ->orWhere('state', 'like', "%{$search}%"))
            ->distinct()
            ->limit(500)
            ->pluck('postal_code')
            ->all();
    }

    private function postalCodesForLocation(?string $state, ?string $district): array
    {
        return DB::table('postal_codes')
            ->whereNull('deleted_at')
            ->when($state, fn ($query) => $query->where('state', $state))
            ->when($district, fn ($query) => $query->where('district', $district))
            ->distinct()
            ->pluck('postal_code')
            ->all();
    }

    private function locationsFor(array $postalCodes): array
    {
        if ($postalCodes === []) {
            return [];
        }

        return DB::table('postal_codes')
            ->selectRaw('postal_code, MIN(office_name) as office_name, MIN(district) as district, MIN(state) as state')
            ->whereNull('deleted_at')
            ->whereIn('postal_code', array_unique($postalCodes))
            ->groupBy('postal_code')
            ->get()
            ->keyBy('postal_code')
            ->all();
    }

    private function states()
    {
        return DB::table('postal_codes')->whereNull('deleted_at')->whereNotNull('state')->distinct()->orderBy('state')->pluck('state');
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
