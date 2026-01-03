<?php

namespace App\Providers;

use App\Observers\MediaObserver;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
        Media::observe(MediaObserver::class);

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
