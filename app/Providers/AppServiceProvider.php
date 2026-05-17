<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\LanguageService::class, fn () => new \App\Services\LanguageService());
        $this->app->singleton(\App\Services\ConnectionService::class, fn () => new \App\Services\ConnectionService());
    }

    /**
     * Bootstrap any application services.
     */

     public function boot()
     {
        
     }
}