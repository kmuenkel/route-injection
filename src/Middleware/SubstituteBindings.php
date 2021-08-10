<?php

namespace RouteInjection\Middleware;

use Closure;
use RouteInjection\Binder;
use InvalidArgumentException;
use Illuminate\Routing\Middleware\SubstituteBindings as BaseSubstituteBindings;

/**
 * Class SubstituteBindings
 * @package RouteInjection\Middleware
 */
class SubstituteBindings extends BaseSubstituteBindings
{
    /**
     * @inheritDoc
     */
    public function handle($request, Closure $next)
    {
        foreach (config('route-injection.binders', []) as $binder) {
            if (!is_subclass_of($binder, Binder::class)) {
                throw new InvalidArgumentException('Config must be a class name inheriting from '.Binder::class.'. "'
                    .$binder.'" given.');
            }

            $binder = is_string($binder) ? app($binder) : $binder;
            $binder->setParameter($request->route());
        }

        return parent::handle($request, $next);
    }
}