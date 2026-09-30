<?php

namespace App\Providers;

use App\Models\PassportClient;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\FontFamily;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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

        // Money inputs show thousands separators while typing ("4,300.00"); the commas are stripped from the state.
        TextInput::macro('money', function (): TextInput {
            /** @var TextInput $this */
            return $this->mask(RawJs::make('$money($input)'))->stripCharacters(',')->numeric();
        });

        TextColumn::configureUsing(function (TextColumn $column) {
            if ($column->isBadge()) {
                $column->fontFamily(FontFamily::Sans);
            }
        });

        // Use custom Client model that skips authorization
        Passport::useClientModel(PassportClient::class);

        Passport::authorizationView(function ($parameters) {
            return view('mcp.authorize', $parameters);
        });
    }
}
