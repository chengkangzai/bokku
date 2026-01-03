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
                if (! $state) {
                    return;
                }

                foreach ((array) $state as $file) {
                    if ($file instanceof TemporaryUploadedFile && str_starts_with($file->getMimeType(), 'image/')) {
                        $path = $file->getRealPath();
                        if ($path && file_exists($path)) {
                            try {
                                Image::load($path)->optimize()->save();
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
