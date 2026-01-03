<?php

namespace App\Observers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class MediaObserver
{
    public function created(Media $media): void
    {
        if (! str_starts_with($media->mime_type, 'image/')) {
            return;
        }

        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        $tempPath = storage_path('app/temp_'.$media->uuid.'.'.$media->extension);

        try {
            file_put_contents($tempPath, $disk->get($path));

            $originalSize = filesize($tempPath);

            Image::load($tempPath)
                ->optimize()
                ->save();

            $optimizedSize = filesize($tempPath);

            if ($optimizedSize < $originalSize) {
                $disk->put($path, file_get_contents($tempPath));

                $media->size = $optimizedSize;
                $media->saveQuietly();

                Log::debug("Optimized image {$media->file_name}: {$originalSize} -> {$optimizedSize} bytes");
            }
        } catch (Throwable $e) {
            Log::warning("Failed to optimize image {$media->file_name}: {$e->getMessage()}");
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
