<?php

namespace App\Providers;

use Filament\Support\Assets\Js;
use Filament\Support\Assets\Css;
use Illuminate\Support\Facades\URL;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentAsset;

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
        Model::unguard();
        FilamentAsset::register([
            Css::make('custom-mobile', asset('css/custom.css')),
            Js::make('custom-mobile', asset('js/custom.js')),
        ]);
        if (env('APP_ENV') === 'production') {
            URL::forceScheme('https');
        }
    }
}
