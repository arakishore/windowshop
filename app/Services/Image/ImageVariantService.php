<?php

namespace App\Services\Image;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use RuntimeException;

class ImageVariantService
{
    /**
     * @return array<string, string>
     */
    public function store(UploadedFile $file, string $profile, string $directory): array
    {
        $config = config("images.{$profile}");

        if (! is_array($config)) {
            throw new RuntimeException("Image profile [{$profile}] is not configured.");
        }

        $manager = $this->manager();
        $source = $manager->read($file->getRealPath());
        $quality = (int) ($config['quality'] ?? config('images.default_quality', 82));
        $paths = [];

        Storage::disk('public')->makeDirectory($directory);

        foreach ($config['variants'] as $name => [$width, $height]) {
            $path = "{$directory}/{$name}.webp";
            $absolutePath = Storage::disk('public')->path($path);

            (clone $source)
                ->cover((int) $width, (int) $height)
                ->toWebp(quality: $quality)
                ->save($absolutePath);

            $paths[$name] = $path;
        }

        return $paths;
    }

    private function manager(): ImageManager
    {
        return match (config('images.driver', 'gd')) {
            'gd' => ImageManager::gd(autoOrientation: true, strip: true),
            'imagick' => ImageManager::imagick(autoOrientation: true, strip: true),
            default => throw new RuntimeException('Unsupported image driver configured.'),
        };
    }
}
