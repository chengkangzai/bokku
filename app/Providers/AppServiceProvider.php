<?php

namespace App\Providers;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\Image\Image;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        SpatieMediaLibraryFileUpload::configureUsing(function (SpatieMediaLibraryFileUpload $upload) {
            $upload->afterStateUpdated(function ($state) {
                // Skip in test environment
                if (! $state || app()->runningUnitTests()) {
                    return;
                }

                foreach ((array) $state as $file) {
                    if (! $file instanceof TemporaryUploadedFile) {
                        continue;
                    }

                    $mimeType = $file->getMimeType();
                    if (! $mimeType || ! str_starts_with($mimeType, 'image/') || $mimeType === 'image/webp') {
                        continue;
                    }

                    try {
                        $storage = FileUploadConfiguration::storage();
                        $relativePath = FileUploadConfiguration::path($file->getFilename(), false);
                        $fullPath = $storage->path($relativePath);

                        $originalSize = filesize($fullPath);

                        // Convert to WebP in place (keep same path, replace content)
                        Image::load($fullPath)->format('webp')->quality(80)->save($fullPath);
                        $newSize = filesize($fullPath);

                        Log::info("Converted to WebP: {$originalSize} -> {$newSize} bytes");
                    } catch (\Throwable $e) {
                        Log::warning("Failed to convert to WebP: {$e->getMessage()}");
                    }
                }
            });
        });

        Section::configureUsing(function (Section $section) {
            $section->compact();
        });

        TextColumn::configureUsing(function (TextColumn $column) {
            if ($column->isBadge()) {
                $column->fontFamily(FontFamily::Sans);
            }
        });

        // Use custom Client model that skips authorization
        Passport::useClientModel(\App\Models\PassportClient::class);

        Passport::authorizationView(function ($parameters) {
            return view('mcp.authorize', $parameters);
        });
    }
}
