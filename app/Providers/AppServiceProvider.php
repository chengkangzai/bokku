<?php

namespace App\Providers;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
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
                \Log::debug('SpatieMediaLibraryFileUpload afterStateUpdated triggered', ['state_type' => gettype($state)]);

                if (! $state) {
                    return;
                }

                foreach ((array) $state as $file) {
                    \Log::debug('Processing file', ['class' => get_class($file), 'mime' => $file instanceof TemporaryUploadedFile ? $file->getMimeType() : 'N/A']);

                    if ($file instanceof TemporaryUploadedFile && str_starts_with($file->getMimeType(), 'image/')) {
                        $path = $file->getRealPath();
                        \Log::debug('Attempting optimization', ['path' => $path, 'exists' => file_exists($path)]);

                        if ($path && file_exists($path)) {
                            try {
                                $originalSize = filesize($path);
                                Image::load($path)->optimize()->save();
                                $newSize = filesize($path);
                                \Log::info("Optimized image: {$originalSize} -> {$newSize} bytes");
                            } catch (\Throwable $e) {
                                \Log::warning("Failed to optimize image: {$e->getMessage()}");
                            }
                        }
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
