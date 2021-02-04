<?php

namespace AAnyszek\LaravelDevHelpers;

use AAnyszek\LaravelDevHelpers\Commands\Structure;

class PackageServiceProvider extends \Illuminate\Support\ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Structure::class
            ]);
        }
    }
}
