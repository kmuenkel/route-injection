<?php

namespace RouteInjection;

use Closure;
use RuntimeException;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionParameter;
use Illuminate\Routing\{Route, Router};
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Validator;
use Lti\Models\Repositories\Contracts\ResourceLinkRepository;

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
     * @var string
     */
    protected $abstractionName = '';

    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->rules($request)) {
            if ($route = $request->route()) {
                $this->setParameter($route);

                if (($deferred = array_search(static::class, self::$deferred)) !== false) {
                    unset(self::$deferred[$deferred]);
                }
            } else {
                $this->deferMiddleware($request);
            }
        }

        return $next($request);
    }

    /**
     * @param Route $route
     * @return ReflectionParameter[]
     */
    public static function getRouteParams(Route $route = null): array
    {
        $route = $route ?: request()->route();
        $action = $route->getAction('uses');
        $controller = is_callable($action) ? $action : $route->getController();
        $method = $route->getActionMethod();
        $reflector = $controller instanceof Closure ? app(ReflectionFunction::class, ['name' => $controller])
            : app(ReflectionMethod::class, ['class_or_method' => $controller, 'name' => $method]);

        return $reflector->getParameters();
    }

    /**
     * @param Route $route
     */
    protected function setParameter(Route $route)
    {
        $parameters = static::getRouteParams($route);
        $abstraction = $this->abstractionName();

        collect($parameters)->each(function (ReflectionParameter $parameter) use ($route, $abstraction) {
            $parameterType = optional($parameter->getType())->getName();
            $parameterValue = $route->parameter($parameter->name);

            if ($parameterType && is_a($parameterType, $abstraction, true)) {
                $concrete = $this->concreteObject($parameterValue);
                $route->setParameter($parameter->name, $concrete);
            }
        });
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
     * @return string
     */
    protected function abstractionName(): string
    {
        return $this->abstractionName;
    }

    /**
     * @param mixed $parameter
     * @return object
     */
    abstract protected function concreteObject($parameter): object;
}
