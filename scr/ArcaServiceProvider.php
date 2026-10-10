<?php

namespace GKCode\Arca;

use Illuminate\Support\ServiceProvider;

class ArcaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/arca.php',
            'arca'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/arca.php' => config_path('arca.php'),
        ], 'arca-config');

        $this->loadRoutesFrom(
            __DIR__ . '/../routes/Arca/web.php'
        );

        $this->loadMigrationsFrom(
            __DIR__ . '/../database/migrations'
        );
    }
}