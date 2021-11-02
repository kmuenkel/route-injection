<?php

namespace RouteInjection;

use Closure;
use RuntimeException;
use ReflectionMethod;
use ReflectionFunction;
use ReflectionParameter;
use Illuminate\Support\Str;
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
     * @var string
     */
    protected $abstractionName = '';

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
        $method = is_callable($controller) && $method == get_class($controller) ? '__invoke' : $method;

        $reflector = $controller instanceof Closure ? app(ReflectionFunction::class, ['name' => $controller])
            : app(ReflectionMethod::class, ['class_or_method' => $controller, 'name' => $method]);

        return $reflector->getParameters();
    }

    /**
     * @param Route $route
     */
    public function setParameter(Route $route)
    {
        $parameters = static::getRouteParams($route);
        $abstraction = $this->abstractionName();

        collect($parameters)->each(function (ReflectionParameter $parameter) use ($route, $abstraction) {
            $parameterType = optional($parameter->getType())->getName();
            $parameterName = $this->getRouteParameterName($route, $parameter);
            $parameterValue = $route->parameter($parameterName);

            if ($parameterType && is_a($parameterType, $abstraction, true)) {
                $concrete = $this->concreteObject($parameterValue);
                $route->setParameter($parameterName, $concrete);
            }
        });
    }

    /**
     * @param Route $route
     * @param ReflectionParameter $parameter
     * @return string
     */
    protected function getRouteParameterName(Route $route, ReflectionParameter $parameter): string
    {
        $parameterExists = $route->hasParameter($parameter->name);
        $singularName = Str::singular($parameter->name);
        $parameterIsPlural = $parameter->name != $singularName;
        $singularExists = $parameterIsPlural && $route->hasParameter($singularName);

        return !$parameterExists && $singularExists ? $singularName : $parameter->name;
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
