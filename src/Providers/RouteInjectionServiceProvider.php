<?php

namespace RouteInjection\Providers;

use RouteInjection\Binder;
use InvalidArgumentException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * Class RouteInjectionServiceProvider
 * @package RouteInjection\Providers
 */
class RouteInjectionServiceProvider extends ServiceProvider
{
    const CONFIG_NAME = 'route-injection';

    /**
     * @void
     */
    public function register()
    {
        $configPath = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'route-injection.php');
        $this->publishes([$configPath => config_path(self::CONFIG_NAME.'.php')]);
        $this->mergeConfigFrom($configPath, self::CONFIG_NAME);
    }

    /**
     * @void
     * @throws BindingResolutionException
     */
    public function boot()
    {
        $kernel = $this->app->make(Kernel::class);

        array_walk(config(self::CONFIG_NAME.'.binders', []), function (string $binder) use ($kernel) {
            if (!is_subclass_of($binder, Binder::class)) {
                throw new InvalidArgumentException('Config must be a class name inheriting from '.Binder::class.'. "'
                    .get_class($binder).'" given.');
            }

            $kernel->pushMiddleware($binder);
        });
    }
}
