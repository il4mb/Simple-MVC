<?php

namespace Il4mb\Routing;

use Closure;
use Exception;
use Il4mb\Routing\Http\Code;
use Il4mb\Routing\Http\Request;
use Il4mb\Routing\Http\Response;
use InvalidArgumentException;
use ReflectionClass;
use Il4mb\Routing\Map\Route;
use Il4mb\Routing\Middlewares\MiddlewareExecutor;
use Throwable;

class Router implements Interceptor
{
    private readonly string $routeOffset;
    private array $routes = [];
    private array $interceptors = [];
    private readonly array $options;

    public function __construct(array $interceptors = [], array $options = [])
    {
        $this->initOption($options);
        $this->interceptors = [$this, ...$interceptors];
    }

    private function initOption(array $options = []): void
    {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? null;

        if (!isset($options['pathOffset'])) {
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? false;
            if ($scriptName) {
                $this->routeOffset = dirname(trim($scriptName));
            } else {
                $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                $file = null;
                foreach ($traces as $trace) {
                    if (isset($trace['file']) && strtolower(basename($trace['file'])) === 'index.php') {
                        $file = $trace['file'];
                        break;
                    }
                }

                if (is_string($root) && is_string($file)) {
                    $root = preg_replace('/\\\\|\\//im', '/', $root);
                    $path = preg_replace('/\\\\|\\//im', '/', dirname($file));
                    $offset = str_replace($root, "", $path);
                    $this->routeOffset = $offset;
                } else {
                    $this->routeOffset = "";
                }
            }
        } else {
            $this->routeOffset = $options['pathOffset'];
        }

        $this->controlHtaccess($root);
        $this->options = [
            "throwOnDuplicatePath" => true,
            "autoDetectFolderOffset" => true,
            ...$options
        ];
    }

    private function controlHtaccess($root)
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? false;
        if (!$scriptName) return;

        $htaccessFile = rtrim($root, "\/") . "/" . trim($this->routeOffset, "\/") . "/.htaccess";
        if (!file_exists($htaccessFile)) {
            $htaccess =
                <<<EOS
            # THIS FILE ARE GENERATE BY <IL4MB/ROUTING> 
            # YOU CAN MODIFY ANY THING BUT MAKE SURE EACH REQUEST ARE POINT TO INDEX.PHP
            RewriteEngine on
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^(.*)$ "/$scriptName" [NC,L,QSA]
            EOS;
            file_put_contents($htaccessFile, $htaccess);
        } else {
            $htaccess = file_get_contents($htaccessFile);
            preg_match("/RewriteRule\s+(.*)\s+\[/im", $htaccess, $matches);
            if (isset($matches[1])) {
                $should = "^(.*)$ \"$scriptName\"";
                if ($matches[1] !== $should) {
                    $htaccess = str_replace($matches[1], $should, $htaccess);
                    file_put_contents($htaccessFile, $htaccess);
                    header("Refresh: 0");
                }
            }
        }
    }

    public function removeInterceptor(Interceptor $interceptor): void
    {
        foreach ($this->interceptors as $key => $existingInterceptor) {
            if ($existingInterceptor === $interceptor) {
                unset($this->interceptors[$key]);
                $this->interceptors = array_values($this->interceptors);
                return;
            }
        }
    }

    public function addInterceptor(Interceptor $interceptor): void
    {
        $this->interceptors[] = $interceptor;
    }

    public function addRoute(mixed $obj): void
    {
        if ($obj instanceof Route) {
            $this->addRouteInternal($obj);
        } else {
            $this->addRoutesFromController($obj);
        }
    }

    public function addRouteBy(string $basepath, mixed $obj): void
    {
        if ($obj instanceof Route) {
            $this->addRouteInternal($obj, $basepath);
        } else {
            $this->addRoutesFromController($obj, $basepath);
        }
    }

    private function addRouteInternal(Route $route, string $basepath = ''): void
    {
        $basepath   = trim($basepath, "\/");
        $offsetpath = trim($this->routeOffset, "\/");
        $path = (!empty($offsetpath) ? "/$offsetpath" : "")
            . (!empty($basepath) ? "/$basepath" : "") . "/"
            .  trim($route->path, "\/");

        if ($this->options["throwOnDuplicatePath"]) {
            $duplicates = array_filter(
                $this->routes,
                fn($existingRoute) => $existingRoute->path === $path
                    && $existingRoute->method === $route->method
            );
            if (count($duplicates) > 0) {
                throw new InvalidArgumentException("Cannot add path \"$route->path\", same path already added in collection.");
            }
        }

        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->onAddRoute($route)) break;
        }

        $this->routes[] = $route->clone([
            "path" => $path,
            "callback" => $route->callback
        ]);
    }

    private function addRoutesFromController(mixed $controller, string $basepath = ''): void
    {
        $reflector = new ReflectionClass($controller);
        foreach ($reflector->getMethods() as $method) {
            foreach ($method->getAttributes() as $defAttr) {
                if ($defAttr->getName() == Route::class) {
                    $routeInstance = $defAttr->newInstance();
                    $reflector = new ReflectionClass($routeInstance);
                    if ($reflector->hasProperty('callback')) {
                        $property = $reflector->getProperty('callback');
                        $property->setAccessible(true);
                        $property->setValue($routeInstance, Callback::create($method->getName(), $controller));
                        $property->setAccessible(false);
                    }
                    $this->addRouteInternal($routeInstance, $basepath);
                }
            }
        }
    }

    /**
     * Dispath registered route by request
     */
    public function dispatch(Request $request): Response
    {
        $response = new Response();
        try {

            $originalRoutes = $this->routes;
            $routes = $this->routes;
            usort($routes, fn($a, $b) => strcmp($b->path, $a->path));

            $request->set("__route_options", [
                "pathOffset" => $this->routeOffset,
                ...$this->options
            ]);
            $uri = $request->uri;
            $nonBrancesRoutes = array_filter($routes, fn(Route $route) => empty($route->parameters));
            $brancesRoutes = array_filter($routes, fn(Route $route) => count($route->parameters) > 0);

            $mathedRoutes = array_values(
                array_filter(
                    $nonBrancesRoutes,
                    fn(Route $route) => $request->method === $route->method
                        && $uri->matchRoute($route)
                )
            );
            if (count($mathedRoutes) < 1) {
                $mathedRoutes = array_values(
                    array_filter(
                        $brancesRoutes,
                        fn(Route $route) => $request->method === $route->method
                            && $uri->matchRoute($route)
                    )
                );
            }
            if (empty($mathedRoutes)) throw new Exception("Route not found.", 404);

            /**
             * Reset route orders
             * @var array<string> $matchesRoutePath
             */
            $matchesRoutePath = array_map(
                fn(Route $route) => $route->path,
                $mathedRoutes
            );

            $mathedRoutes = array_values(
                array_filter(
                    $originalRoutes,
                    fn(Route $route) => in_array($route->path, $matchesRoutePath)
                )
            );
            $request->set("__routes", $mathedRoutes);
            foreach ($this->interceptors as $interceptor) {
                if ($interceptor->onDispatch($request, $response)) break;
            }
        } catch (Throwable $t) {

            foreach ($this->interceptors as $interceptor) {
                if ($interceptor->onFailed($t, $request, $response)) break;
            }
        }
        return $response;
    }

    public function onAddRoute(Route &$route): bool
    {
        return false;
    }

    public function onBeforeInvoke(Route &$route): bool
    {
        return false;
    }

    public function onInvoke(Route &$route): bool
    {
        return false;
    }

    public function onDispatch(Request &$request, Response &$response): bool
    {
        try {
            /**
             * @var array<Route> $routes
             */
            $routes = $request->get("__routes");
            if ($routes) {
                $this->executeRoutes($routes, 0, $request, $response);
            }
        } catch (Throwable $t) {
            foreach ($this->interceptors as $interceptor) {
                if ($interceptor->onFailed($t, $request, $response)) break;
            }
        }
        return false;
    }

    private function executeRoutes(array $routes, int $index, Request $request, Response $response): void
    {
        if (!isset($routes[$index])) // Route not found
            throw new Exception("Route not found.", 404);

        $route = $routes[$index];
        $next = function () use ($routes, $index, $request, $response) {
            $this->executeRoutes($routes, $index + 1, $request, $response);
        };
        $this->invokeRoute($route, $request, $response, $next);
    }

    private function invokeRoute(Route $route, Request $request, Response $response, Closure $next): void
    {
        MiddlewareExecutor::execute($route, $request);
        $params = $route->parameters;
        foreach ($this->interceptors as $interceptor)
            if ($interceptor->onBeforeInvoke($route)) break;
        $content = $route->callback->__invoke(...[...$params, $request, $response, $next]);
        if ($content !== null)
            $response->setContent($content);

        foreach ($this->interceptors as $interceptor) {
            if ($interceptor->onInvoke($route)) break;
        }
    }


    function onFailed(Throwable $t, Request &$request, Response &$response): bool
    {
        $response->setCode(Code::fromCode($t->getCode()) ?? 500);
        if (empty($response->getContent())) {
            $response->setContent("Error: {$t->getCode()}, {$t->getMessage()}");
        }
        return false;
    }

    public function __debugInfo()
    {
        return [
            "routes" => $this->routes
        ];
    }
}
