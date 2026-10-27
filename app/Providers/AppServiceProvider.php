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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') || request()->header('X-Forwarded-Proto') === 'https') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Auto-initialize SQLite file and seed data if missing or empty
        if (config('database.default') === 'sqlite') {
            $sqlitePath = config('database.connections.sqlite.database');
            if (!empty($sqlitePath) && $sqlitePath !== ':memory:') {
                $needsMigrate = false;
                if (!file_exists($sqlitePath)) {
                    $dir = dirname($sqlitePath);
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0775, true);
                    }
                    @touch($sqlitePath);
                    @chmod($sqlitePath, 0666);
                    $needsMigrate = true;
                }
                try {
                    if ($needsMigrate) {
                        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
                    }
                    if (\App\Models\Product::count() === 0) {
                        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[AppServiceProvider] Auto-migrate error: ' . $e->getMessage());
                }
            }
        }
    }
}
