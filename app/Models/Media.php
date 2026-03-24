<?php

namespace App\Models;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

class Media extends BaseMedia
{
    protected static function booted(): void
    {
        static::saving(function (Media $media): void {
            $sanitized = preg_replace('/[^\x20-\x7E]/', '', $media->name);

            $media->name = trim($sanitized) !== '' ? $sanitized : (string) Str::ulid();
        });
    }
}
