<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register the CheckUserActiveStatus middleware for API routes
        $middleware->alias([
            'check.user.active' => \App\Http\Middleware\CheckUserActiveStatus::class,
            'check.boutique.active' => \App\Http\Middleware\EnsureBoutiqueIsActive::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'detect.nature' => \App\Http\Middleware\DetectShopNature::class,
        ]);
        
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\DetectShopNature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });
    })->create();

// Mode Standalone (desktop) : stockage inscriptible dans AppData plutôt que dans Program Files
if ($storagePath = env('LARAVEL_STORAGE_PATH')) {
    $app->useStoragePath($storagePath);
}

// Windows : Laravel ne reconnaît comme absolus que les chemins commençant par "/" ou "\".
// Les chemins "C:\..." des APP_*_CACHE seraient sinon préfixés par le basePath.
$hasWindowsCachePath = false;
foreach (['APP_PACKAGES_CACHE', 'APP_SERVICES_CACHE', 'APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE'] as $cacheEnv) {
    $value = env($cacheEnv);
    if (is_string($value) && preg_match('#^[A-Za-z]:[/\x5C]#', $value)) {
        $app->addAbsoluteCachePathPrefix(substr($value, 0, 3));
        $hasWindowsCachePath = true;
    }
}

// PackageManifest est instancié dans le constructeur : on le recrée avec le bon chemin.
if ($hasWindowsCachePath) {
    $app->instance(
        \Illuminate\Foundation\PackageManifest::class,
        new \Illuminate\Foundation\PackageManifest(
            new \Illuminate\Filesystem\Filesystem, $app->basePath(), $app->getCachedPackagesPath()
        )
    );
}

return $app;
