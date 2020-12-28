<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            \CyrildeWit\EloquentViewable\Contracts\View::class,
            \App\Models\View::class
        );

        $this->app->bind(
            \CyrildeWit\EloquentViewable\Contracts\Views::class,
            \App\Services\Views\Views::class
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
