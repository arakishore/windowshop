<?php

namespace App\Services\Marketplace;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;

class MarketplaceLogoService
{
    public const SETTING_KEY = 'marketplace.logo';

    public const DEFAULT_LOGO_PATH = 'assets/admin/images/logov2.png';

    public const MANAGED_DIRECTORY = 'marketplace/logo';

    public function path(): string
    {
        $path = SystemSetting::query()
            ->where('key', self::SETTING_KEY)
            ->where('status', SystemSetting::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->value('value');

        return is_string($path) && $path !== '' ? $path : self::DEFAULT_LOGO_PATH;
    }

    public function url(): string
    {
        $path = $this->path();

        if ($this->isManagedPath($path) && Storage::disk('public')->exists($path)) {
            return asset('storage/'.$path);
        }

        return asset(self::DEFAULT_LOGO_PATH);
    }

    public function isManagedPath(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        return str_starts_with($path, self::MANAGED_DIRECTORY.'/')
            && ! str_contains($path, '..')
            && ! str_starts_with($path, '/')
            && ! str_contains($path, '\\');
    }

    public function deleteManaged(?string $path): void
    {
        if ($this->isManagedPath($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
