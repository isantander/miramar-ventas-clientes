<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ProductoService;
use App\Services\VentaService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\ClienteService::class);

        $this->app->singleton(ProductoService::class, function ($app) {
            return new ProductoService();
        });

        $this->app->bind(VentaService::class, function ($app) {
            return new VentaService($app->make(ProductoService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
