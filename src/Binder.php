<?php

namespace RouteInjection;

use Closure;
use ReflectionClass;
use RuntimeException;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionException;
use UnexpectedValueException;
use Illuminate\Routing\{Route, Router};
use Illuminate\Http\{Request, Response};

/**
 * Class RouteInjectionBinding
 * @package Lti\Http\Middleware
 */
abstract class Binder
{
    /**
     * @var string
     */
    protected $injectionClassName = '';

    /**
     * @var string[]
     */
    private static $deferred = [];

    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$this->setParameter($request)) {
            $this->deferMiddleware($request);
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
            if ($this->routeExpectsBinding($route)) {
                $concrete = $this->concreteObject($request);

                if (!($concrete instanceof $this->injectionClassName)) {
                    throw new UnexpectedValueException('The abstraction for '.get_class($this)
                        .' must be the name of the "'.get_class($concrete)
                        .'" object, its parent, or an interface it implements. "'
                        .$this->injectionClassName.'" given.');
                }

                $route->setParameter($this->injectionClassName, $concrete);

                if (($deferred = array_search(static::class, self::$deferred)) !== false) {
                    unset(self::$deferred[$deferred]);
                }
            }

            return true;
        }

        return false;
    }

    protected function routeExpectsBinding(Route $route): bool
    {
        $typeHints = $this->getControllerParamTypes($route);

        return in_array($this->injectionClassName, $typeHints);
    }

    /**
     * @param Route $route
     * @return string[]
     */
    protected function getControllerParamTypes(Route $route): array
    {
        $params = $this->getControllerParams($route);

        return collect($params)->mapWithKeys(function (ReflectionParameter $parameter) {
            $name = $parameter->getName();
            /** @var ReflectionNamedType $type */
            $type = $parameter->getType();
            $hint = $type ? $type->getName() : null;

            return [$name => $hint];
        })->toArray();
    }

    /**
     * @param Route $route
     * @return ReflectionParameter[]
     * @throws ReflectionException
     */
    public function getControllerParams(Route $route): array
    {
        $action = $route->getAction('uses');
        $reflection = is_string($action) ? $this->reflectControllerMethod($route) : $this->reflectClosure($action);

        return $reflection->getParameters();
    }

    /**
     * @param Route $route
     * @return ReflectionMethod
     * @throws ReflectionException
     */
    protected function reflectControllerMethod(Route $route): ReflectionMethod
    {
        $controller = $route->getController();
        $methodName = $route->getActionMethod();

        return app(ReflectionClass::class, ['argument' => $controller])->getMethod($methodName);
    }

    /**
     * @param Closure $action
     * @return ReflectionFunction
     */
    protected function reflectClosure(Closure $action): ReflectionFunction
    {
        return app(ReflectionFunction::class, ['name' => $action]);
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
     * @return string
     */
    protected function getClassName(): string
    {
        return $this->injectionClassName;
    }

    /**
     * @param Request $request
     * @return object
     */
    abstract protected function concreteObject(Request $request): object;
}
