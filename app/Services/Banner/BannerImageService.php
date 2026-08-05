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
}
