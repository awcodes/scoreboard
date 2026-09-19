<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
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
        // Surface N+1 queries (e.g. a relation missing from `with()`) outside production.
        Model::preventLazyLoading(! $this->app->isProduction());

        Date::use(CarbonImmutable::class);

        URL::forceScheme('https');
    }
}
