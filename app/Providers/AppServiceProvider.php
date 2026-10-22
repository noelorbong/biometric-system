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
        // Also wraps the migrate command spawned by NativePHP itself.
        $this->app->extend(\Illuminate\Database\Console\Migrations\MigrateCommand::class,
            fn ($command, $app) => new \App\Console\DesktopMigrateCommand($app['migrator'], $app['events']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            if (! config('nativephp-internal.running')) {
                return;
            }

            // NativePHP only copies storage on the first launch. Repair missing
            // directories after upgrades once its AppData path is configured.
            foreach (['framework/sessions', 'framework/cache/data', 'framework/views', 'framework/testing', 'logs', 'app/public/uploads', 'app/private'] as $directory) {
                \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path($directory));
            }
        });

        \App\Models\User::observe(\App\Observers\UserObserver::class);
    }
}
