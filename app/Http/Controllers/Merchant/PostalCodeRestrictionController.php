<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\StorePostalCodeRestrictionRequest;
use App\Http\Requests\Merchant\UpdatePostalCodeRestrictionRequest;
use App\Models\MerchantProfile;
use App\Models\PostalCodeRestriction;
use App\Models\Shop;
use App\Services\Merchant\MerchantShopContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PostalCodeRestrictionController extends Controller
{
    public function __construct(private readonly MerchantShopContextService $shopContextService)
    {
    }

    public function index(Request $request): View
    {
        [$merchant, $shop] = $this->context($request);
        $filters = [
            'status' => $request->query('status'),
            'search' => trim((string) $request->query('search', '')),
        ];

        $restrictions = PostalCodeRestriction::withTrashed()
            ->forShop((int) $merchant->getKey(), (int) $shop->getKey())
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

        return view('merchant.postal-code-restrictions.index', [
            'merchant' => $merchant,
            'shop' => $shop,
            'restrictions' => $restrictions,
            'filters' => $filters,
            'locations' => $this->locationsFor($restrictions->pluck('postal_code')->all()),
        ]);
    }

    public function create(Request $request): View
    {
        [$merchant, $shop] = $this->context($request);

        return view('merchant.postal-code-restrictions.create', [
            'merchant' => $merchant,
            'shop' => $shop,
            'restriction' => null,
            'globalWarning' => null,
        ]);
    }

    public function store(StorePostalCodeRestrictionRequest $request): RedirectResponse
    {
        [$merchant, $shop] = $this->context($request);
        $data = $request->normalizedRestrictionData();
        $this->ensureNoCurrentDuplicate($data['postal_code'], (int) $merchant->getKey(), (int) $shop->getKey());

        PostalCodeRestriction::query()->create([
            ...$data,
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('merchant.postal-code-restrictions.index')
            ->with('success', 'Postal code restriction created successfully.')
            ->with('info', $this->globalRestrictionMessage($data['postal_code']));
    }

    public function edit(Request $request, PostalCodeRestriction $postalCodeRestriction): View
    {
        [$merchant, $shop] = $this->context($request);
        $this->authorizeRestriction($postalCodeRestriction, $merchant, $shop);

        return view('merchant.postal-code-restrictions.edit', [
            'merchant' => $merchant,
            'shop' => $shop,
            'restriction' => $postalCodeRestriction,
            'globalWarning' => $this->globalRestrictionMessage($postalCodeRestriction->postal_code),
        ]);
    }

    public function update(UpdatePostalCodeRestrictionRequest $request, PostalCodeRestriction $postalCodeRestriction): RedirectResponse
    {
        [$merchant, $shop] = $this->context($request);
        $this->authorizeRestriction($postalCodeRestriction, $merchant, $shop);
        $data = $request->normalizedRestrictionData();
        $this->ensureNoCurrentDuplicate($data['postal_code'], (int) $merchant->getKey(), (int) $shop->getKey(), $postalCodeRestriction);

        $postalCodeRestriction->forceFill([
            ...$data,
            'merchant_id' => $merchant->getKey(),
            'shop_id' => $shop->getKey(),
            'updated_by' => Auth::id(),
        ])->save();

        return redirect()
            ->route('merchant.postal-code-restrictions.edit', $postalCodeRestriction)
            ->with('success', 'Postal code restriction updated successfully.')
            ->with('info', $this->globalRestrictionMessage($data['postal_code']));
    }

    public function destroy(Request $request, PostalCodeRestriction $postalCodeRestriction): RedirectResponse
    {
        [$merchant, $shop] = $this->context($request);
        $this->authorizeRestriction($postalCodeRestriction, $merchant, $shop);
        $postalCodeRestriction->delete();

        return redirect()
            ->route('merchant.postal-code-restrictions.index')
            ->with('success', 'Postal code restriction deleted successfully.');
    }

    public function restore(Request $request, PostalCodeRestriction $postalCodeRestriction): RedirectResponse
    {
        [$merchant, $shop] = $this->context($request);
        $this->authorizeRestriction($postalCodeRestriction, $merchant, $shop);
        $postalCodeRestriction->restore();

        return redirect()
            ->route('merchant.postal-code-restrictions.index', ['status' => 'trash'])
            ->with('success', 'Postal code restriction restored successfully.');
    }

    private function ensureNoCurrentDuplicate(string $postalCode, int $merchantId, int $shopId, ?PostalCodeRestriction $ignore = null): void
    {
        $request = request();

        if ($request->input('status') !== PostalCodeRestriction::STATUS_ACTIVE || ! $this->requestRestrictionIsCurrent($request->input('starts_at'), $request->input('ends_at'))) {
            return;
        }

        $exists = PostalCodeRestriction::query()
            ->forPostalCode($postalCode)
            ->forShop($merchantId, $shopId)
            ->currentlyApplicable()
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'postal_code' => 'This shop already has an active restriction for this postal code.',
            ]);
        }
    }

    private function requestRestrictionIsCurrent(mixed $startsAt, mixed $endsAt): bool
    {
        $now = now();
        $businessTime = app(\App\Services\DateTime\BusinessTimeService::class);
        $startsAt = $businessTime->toUtcFromLocalInput($startsAt);
        $endsAt = $businessTime->toUtcFromLocalInput($endsAt);

        if ($startsAt && $startsAt->greaterThan($now)) {
            return false;
        }

        if ($endsAt && $endsAt->lessThan($now)) {
            return false;
        }

        return true;
    }

    private function authorizeRestriction(PostalCodeRestriction $restriction, MerchantProfile $merchant, Shop $shop): void
    {
        abort_unless((int) $restriction->merchant_id === (int) $merchant->getKey(), 404);
        abort_unless((int) $restriction->shop_id === (int) $shop->getKey(), 404);
    }

    /**
     * @return array{0: MerchantProfile, 1: Shop}
     */
    private function context(Request $request): array
    {
        $merchant = $this->shopContextService->activeMerchantForUser($request->user());
        abort_unless($merchant instanceof MerchantProfile, 403);

        $shop = $this->shopContextService->resolveActiveShop(
            $this->shopContextService->activeShops($merchant),
            $request->session()->get('active_shop_id'),
        );
        abort_unless($shop instanceof Shop, 403);

        return [$merchant, $shop];
    }

    private function globalRestrictionMessage(string $postalCode): ?string
    {
        $exists = PostalCodeRestriction::query()
            ->forPostalCode($postalCode)
            ->global()
            ->currentlyApplicable()
            ->exists();

        return $exists ? 'This postal code is already temporarily restricted marketplace-wide.' : null;
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
}
