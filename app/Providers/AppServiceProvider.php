<?php

namespace App\Providers;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
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
            $upload->afterStateUpdated(function ($state, Set $set, $component) {
                if (! $state || app()->runningUnitTests()) {
                    return;
                }

                $newState = [];
                $hasChanges = false;

                foreach ((array) $state as $key => $file) {
                    if (! $file instanceof TemporaryUploadedFile) {
                        $newState[$key] = $file;

                        continue;
                    }

                    $mimeType = $file->getMimeType();
                    if (! $mimeType || ! str_starts_with($mimeType, 'image/') || $mimeType === 'image/webp') {
                        $newState[$key] = $file;

                        continue;
                    }

                    try {
                        $storage = FileUploadConfiguration::storage();
                        $oldRelativePath = FileUploadConfiguration::path($file->getFilename(), false);
                        $oldFullPath = $storage->path($oldRelativePath);

                        $newFilename = preg_replace('/\.[^.]+$/', '.webp', $file->getFilename());
                        $newRelativePath = FileUploadConfiguration::path($newFilename, false);
                        $newFullPath = $storage->path($newRelativePath);

                        $originalSize = filesize($oldFullPath);

                        Image::load($oldFullPath)->format('webp')->quality(80)->save($newFullPath);
                        $newSize = filesize($newFullPath);

                        $storage->delete($oldRelativePath);

                        $newFile = TemporaryUploadedFile::createFromLivewire($newFilename);
                        $newState[$key] = $newFile;
                        $hasChanges = true;

                        Log::info("Converted to WebP: {$originalSize} -> {$newSize} bytes");
                    } catch (\Throwable $e) {
                        Log::warning("Failed to convert to WebP: {$e->getMessage()}");
                        $newState[$key] = $file;
                    }
                }

                if ($hasChanges) {
                    $set($component->getStatePath(false), $newState);
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
