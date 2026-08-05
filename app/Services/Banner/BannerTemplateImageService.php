<?php

namespace App\Services\Banner;

use App\Models\BannerTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BannerTemplateImageService
{
    public function store(UploadedFile $file, BannerTemplate|string $owner, string $slot): string
    {
        $directory = $owner instanceof BannerTemplate
            ? 'banner-templates/'.$owner->uuid
            : 'banner-templates/'.$owner;

        return $file->storeAs($directory, $slot.'-'.uniqid('', true).'.'.$file->extension(), 'public');
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
