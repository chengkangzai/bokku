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
                // Skip WebP conversion in test environment
                if (! $state || app()->runningUnitTests()) {
                    return;
                }

                $newState = [];
                $stateChanged = false;

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

                        // Build new WebP filename (replace extension)
                        $oldFilename = $file->getFilename();
                        $newFilename = preg_replace('/\.[^.]+$/', '.webp', $oldFilename);
                        $newRelativePath = FileUploadConfiguration::path($newFilename, false);

                        // Download, convert, upload
                        $tempLocalPath = storage_path('app/convert_'.uniqid().'.tmp');
                        $tempWebpPath = storage_path('app/convert_'.uniqid().'.webp');

                        file_put_contents($tempLocalPath, $storage->get($oldRelativePath));
                        Image::load($tempLocalPath)->format('webp')->quality(80)->save($tempWebpPath);

                        $originalSize = filesize($tempLocalPath);
                        $newSize = filesize($tempWebpPath);

                        // Upload new WebP file
                        $storage->put($newRelativePath, file_get_contents($tempWebpPath));

                        // Delete old file
                        $storage->delete($oldRelativePath);

                        // Cleanup local files
                        unlink($tempLocalPath);
                        unlink($tempWebpPath);

                        // Create new TemporaryUploadedFile for the WebP
                        $newFile = TemporaryUploadedFile::createFromLivewire($newFilename);
                        $newState[$key] = $newFile;
                        $stateChanged = true;

                        Log::info("Converted to WebP: {$originalSize} -> {$newSize} bytes");
                    } catch (\Throwable $e) {
                        Log::warning("Failed to convert to WebP: {$e->getMessage()}");
                        $newState[$key] = $file;
                    }
                }

                // Update the field state with new WebP files if changed
                if ($stateChanged) {
                    $set($component->getStatePath(), $newState);
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
