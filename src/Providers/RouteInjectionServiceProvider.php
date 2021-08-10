<?php

namespace RouteInjection\Providers;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use RouteInjection\Middleware\SubstituteBindings;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Routing\Middleware\SubstituteBindings as BaseSubstituteBindings;

/**
 * Class RouteInjectionServiceProvider
 * @package RouteInjection\Providers
 */
class RouteInjectionServiceProvider extends ServiceProvider
{
    /**
     * @void
     */
    public function register()
    {
        $configPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'route-injection.php');
        $this->publishes([$configPath => config_path('route-injection.php')]);
        $this->mergeConfigFrom($configPath, 'route-injection');
    }

    /**
     * @void
     * @throws BindingResolutionException
     */
    public function boot()
    {
        $this->app->bind(BaseSubstituteBindings::class, SubstituteBindings::class);
    }
}
