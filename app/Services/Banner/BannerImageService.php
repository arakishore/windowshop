<?php

namespace App\Services\Banner;

use App\Models\Banner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BannerImageService
{
    public function store(UploadedFile $file, Banner|string $owner, string $slot): string
    {
        $directory = $owner instanceof Banner
            ? 'banners/'.$owner->getKey().'-'.$owner->uuid
            : 'banners/pending/'.$owner;

        return $file->storeAs($directory, $slot.'-'.uniqid('', true).'.'.$file->extension(), 'public');
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    public function deleteOwnedCustom(?string $path, Banner $banner): void
    {
        if (! $path) {
            return;
        }

        $prefix = 'banners/'.$banner->getKey().'-'.$banner->uuid.'/';

        if (str_starts_with($path, $prefix) || str_starts_with($path, 'banners/pending/')) {
            $this->delete($path);
        }
    }
}
