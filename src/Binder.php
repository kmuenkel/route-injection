<?php

namespace RouteInjection;

use Closure;
use RuntimeException;
use UnexpectedValueException;
use Illuminate\Routing\Router;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Validator;

/**
 * Class RouteInjectionBinding
 * @package Lti\Http\Middleware
 */
abstract class Binder
{
    /**
     * @var string[]
     */
    private static $deferred = [];

    /**
     * @var string
     */
    protected $rules = [];

    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->rules($request)) {
            if (!$this->setParameter($request)) {
                $this->deferMiddleware($request);
            }
        }

        return $next($request);
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function setParameter(Request $request): bool
    {
        if ($route = $request->route()) {
            $concrete = $this->concreteObject($request);
            $name = $this->abstractionName($concrete);

            if (!($concrete instanceof $name)) {
                throw new UnexpectedValueException('The abstraction for '.get_class($this).' must be the name of the "'
                    .get_class($concrete).'" object, its parent, or an interface it implements. "'.$name.'" given.');
            }

            $route->setParameter($name, $concrete);

            if (($deferred = array_search(static::class, self::$deferred)) !== false) {
                unset(self::$deferred[$deferred]);
            }

            return true;
        }

        return false;
    }

    /**
     * If this is being called as a global middleware, the route wont have been set yet.
     * In that case, identify what it would be, and apply this to it, so that it can fire later.
     * The parameters cannot simply be set now, because they would be overridden by Route::bind() later.
     * @param Request $request
     */
    protected function deferMiddleware(Request $request)
    {
        if (in_array(static::class, self::$deferred)) {
            throw new RuntimeException('Class '.static::class.' has already been deferred.');
        }

        /** @var Router $router singleton */
        $router = app('router');
        $routes = $router->getRoutes();
        $route = $routes->match($request);
        $route->middleware(static::class);
        self::$deferred[] = static::class;
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function rules(Request $request): bool
    {
        return !Validator::make($request->all(), $this->rules)->fails();
    }

    /**
     * @param object $concrete
     * @return string
     */
    protected function abstractionName(object $concrete): string
    {
        return get_class($concrete);
    }

    /**
     * @param Request $request
     * @return object
     */
    abstract protected function concreteObject(Request $request): object;
}
