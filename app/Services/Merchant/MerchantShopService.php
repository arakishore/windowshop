<?php

namespace App\Services\Merchant;

use App\Models\MerchantProfile;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Services\Image\ImageVariantService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MerchantShopService
{
    public function __construct(
        private readonly MerchantService $merchantService,
        private readonly ImageVariantService $imageVariantService,
    ) {
    }

    public function shopsForMerchant(MerchantProfile $merchant): LengthAwarePaginator
    {
        return $merchant->shops()
            ->with(['rootProductCategory', 'audiences', 'city'])
            ->orderByDesc('created_at')
            ->paginate((int) config('admin.pagination.per_page', 15));
    }

    public function canBeActive(Shop $shop, MerchantProfile $merchant): bool
    {
        return (int) $shop->merchant_id === (int) $merchant->getKey()
            && $merchant->status === 'active'
            && $merchant->verification_status !== 'suspended'
            && $shop->status === 'active'
            && $shop->deleted_at === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function formData(?Shop $shop = null): array
    {
        $defaultLocation = $this->merchantService->defaultBusinessLocation();
        $countryId = (int) old('country_id', $shop?->country_id ?? $defaultLocation['country_id']);
        $stateId = (int) old('state_id', $shop?->state_id ?? $defaultLocation['state_id']);

        return [
            'shopTypes' => ProductCategory::query()
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'countries' => $this->merchantService->activeCountries(),
            'states' => $countryId ? $this->merchantService->activeStates($countryId) : collect(),
            'cities' => $countryId && $stateId ? $this->merchantService->citiesForState($countryId, $stateId) : collect(),
            'defaultLocation' => $defaultLocation,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createShop(MerchantProfile $merchant, array $data, Request $request, ?int $actorId): Shop
    {
        return DB::transaction(function () use ($merchant, $data, $request, $actorId): Shop {
            $shop = Shop::query()->create([
                'merchant_id' => $merchant->getKey(),
                'root_product_category_id' => $data['root_product_category_id'],
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'short_description' => $this->nullable($data['short_description'] ?? null),
                'description' => $this->nullable($data['description'] ?? null),
                'email' => $this->nullableLower($data['email'] ?? null),
                'mobile' => $this->nullable($data['mobile'] ?? null),
                'whatsapp_number' => $this->nullable($data['whatsapp_number'] ?? null),
                'website_url' => $this->nullable($data['website_url'] ?? null),
                'address_line_1' => $data['address_line_1'],
                'address_line_2' => $this->nullable($data['address_line_2'] ?? null),
                'landmark' => $this->nullable($data['landmark'] ?? null),
                'country_id' => $data['country_id'] ?? null,
                'state_id' => $data['state_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'pincode' => $this->nullable($data['pincode'] ?? null),
                'latitude' => $this->nullable($data['latitude'] ?? null),
                'longitude' => $this->nullable($data['longitude'] ?? null),
                'status' => $data['status'],
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);

            $paths = [];

            if ($request->hasFile('logo')) {
                $paths['logo_path'] = $this->storeImage($request, 'logo', 'shop_logo', "shops/{$shop->getKey()}/logo");
            }

            if ($request->hasFile('banner')) {
                $paths['banner_path'] = $this->storeImage($request, 'banner', 'shop_banner', "shops/{$shop->getKey()}/banner");
            }

            if ($paths !== []) {
                $shop->forceFill($paths)->save();
            }

            return $shop;
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateShop(Shop $shop, MerchantProfile $merchant, array $data, Request $request, ?int $actorId): Shop
    {
        return DB::transaction(function () use ($shop, $merchant, $data, $request, $actorId): Shop {
            $logoPath = $shop->logo_path;
            $bannerPath = $shop->banner_path;

            $shop->forceFill([
                'merchant_id' => $merchant->getKey(),
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name'], $shop),
                'short_description' => $this->nullable($data['short_description'] ?? null),
                'description' => $this->nullable($data['description'] ?? null),
                'email' => $this->nullableLower($data['email'] ?? null),
                'mobile' => $this->nullable($data['mobile'] ?? null),
                'whatsapp_number' => $this->nullable($data['whatsapp_number'] ?? null),
                'website_url' => $this->nullable($data['website_url'] ?? null),
                'address_line_1' => $data['address_line_1'],
                'address_line_2' => $this->nullable($data['address_line_2'] ?? null),
                'landmark' => $this->nullable($data['landmark'] ?? null),
                'country_id' => $data['country_id'] ?? null,
                'state_id' => $data['state_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
                'pincode' => $this->nullable($data['pincode'] ?? null),
                'latitude' => $this->nullable($data['latitude'] ?? null),
                'longitude' => $this->nullable($data['longitude'] ?? null),
                'status' => $data['status'] ?? $shop->status,
                'updated_by' => $actorId,
            ])->save();

            if ($request->boolean('remove_logo')) {
                $this->deleteImageDirectory($logoPath);
                $logoPath = null;
            }

            if ($request->boolean('remove_banner')) {
                $this->deleteImageDirectory($bannerPath);
                $bannerPath = null;
            }

            if ($request->hasFile('logo')) {
                $this->deleteImageDirectory($logoPath);
                $logoPath = $this->storeImage($request, 'logo', 'shop_logo', "shops/{$shop->getKey()}/logo");
            }

            if ($request->hasFile('banner')) {
                $this->deleteImageDirectory($bannerPath);
                $bannerPath = $this->storeImage($request, 'banner', 'shop_banner', "shops/{$shop->getKey()}/banner");
            }

            if ($logoPath !== $shop->logo_path || $bannerPath !== $shop->banner_path) {
                $shop->forceFill([
                    'logo_path' => $logoPath,
                    'banner_path' => $bannerPath,
                ])->save();
            }

            return $shop;
        });
    }

    private function uniqueSlug(string $name, ?Shop $shop = null): string
    {
        $base = Str::slug($name) ?: Str::uuid()->toString();
        $slug = $base;
        $suffix = 2;

        while (Shop::query()
            ->where('slug', $slug)
            ->when($shop?->exists, fn ($query) => $query->whereKeyNot($shop->getKey()))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function storeImage(Request $request, string $field, string $profile, string $directory): string
    {
        try {
            $paths = $this->imageVariantService->store($request->file($field), $profile, $directory);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                $field => $exception->getMessage(),
            ]);
        }

        return $paths['web'] ?? array_values($paths)[0];
    }

    private function deleteImageDirectory(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('public')->deleteDirectory(dirname($path));
    }

    private function nullableLower(mixed $value): ?string
    {
        $value = $this->nullable($value);

        return $value === null ? null : strtolower($value);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
