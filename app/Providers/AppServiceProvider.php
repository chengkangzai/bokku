<?php

namespace App\Providers;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
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
                if (! $state) {
                    return;
                }

                foreach ((array) $state as $file) {
                    if (! $file instanceof TemporaryUploadedFile) {
                        continue;
                    }

                    $mimeType = $file->getMimeType();
                    if (! $mimeType || ! str_starts_with($mimeType, 'image/')) {
                        continue;
                    }

                    try {
                        $storage = FileUploadConfiguration::storage();
                        $relativePath = FileUploadConfiguration::path($file->getFilename(), false);
                        $tempLocalPath = storage_path('app/optimize_'.uniqid().'.'.$file->getClientOriginalExtension());

                        // Download from storage to local
                        file_put_contents($tempLocalPath, $storage->get($relativePath));
                        $originalSize = filesize($tempLocalPath);

                        // Optimize locally
                        Image::load($tempLocalPath)->optimize()->save();
                        $newSize = filesize($tempLocalPath);

                        // Re-upload to storage if optimized
                        if ($newSize < $originalSize) {
                            $storage->put($relativePath, file_get_contents($tempLocalPath));
                            \Log::info("Optimized image: {$originalSize} -> {$newSize} bytes");
                        }

                        unlink($tempLocalPath);
                    } catch (\Throwable $e) {
                        \Log::warning("Failed to optimize image: {$e->getMessage()}");
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
