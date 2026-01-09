<?php

namespace OctoSqueeze\Laravel;

use Illuminate\Support\ServiceProvider;
use OctoSqueeze\Client\OctoSqueeze as OctoSqueezeClient;

class OctoSqueezeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/octosqueeze.php',
            'octosqueeze'
        );

        $this->app->singleton(OctoSqueezeManager::class, function ($app) {
            return new OctoSqueezeManager($app);
        });

        $this->app->alias(OctoSqueezeManager::class, 'octosqueeze');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/octosqueeze.php' => config_path('octosqueeze.php'),
            ], 'octosqueeze-config');

            $this->commands([
                Console\Commands\CompressCommand::class,
                Console\Commands\UsageCommand::class,
            ]);
        }
    }
}
