<?php

namespace App\Observers;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver as BaseMediaObserver;

class MediaObserver extends BaseMediaObserver
{
    public function creating(Media $media): void
    {
        $sanitized = preg_replace('/[^\x20-\x7E]/', '', $media->name);

        $media->name = trim($sanitized) !== '' ? $sanitized : (string) Str::ulid();

        parent::creating($media);
    }
}
