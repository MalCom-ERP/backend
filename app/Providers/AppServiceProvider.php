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
        if (config('database.default') === 'sqlite') {
            try {
                \Illuminate\Support\Facades\DB::statement('PRAGMA journal_mode = WAL;');
                \Illuminate\Support\Facades\DB::statement('PRAGMA synchronous = NORMAL;');
                \Illuminate\Support\Facades\DB::statement('PRAGMA cache_size = -16000;');
                \Illuminate\Support\Facades\DB::statement('PRAGMA temp_store = MEMORY;');
            } catch (\Throwable $e) {
                // Ignorer si la base SQLite n'est pas encore initialisée
            }
        }
    }
}
