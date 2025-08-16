<?php

namespace App\Providers;

use Filament\Schemas\Components\Section;
use Illuminate\Support\ServiceProvider;

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
        Section::configureUsing(function (Section $section) {
            $section->compact();
        });
    }
}
