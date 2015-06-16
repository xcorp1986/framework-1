<?php
namespace Spark\Handler;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Spark\Exception\HttpMethodNotAllowed;
use Spark\Exception\HttpNotFound;
use Spark\Router;

class RouteHandler
{
    /**
     * @var Router
     */
    protected $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function __invoke(Request $request, Response $response, callable $next)
    {
        list($route, $args) = $this->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        $request = $request->withAttribute('spark/adr:route', $route);

        foreach ($args as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        return $next($request, $response);
    }

    /**
     * @param  string $method
     * @param  string $path
     * @return array [$route, $arguments]
     * @throws HttpNotFound
     * @throws HttpMethodNotAllowed
     */
    public function dispatch($method, $path)
    {
        $routeInfo = $this->getDispatcher()->dispatch($method, $path);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new HttpNotFound;
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                throw (new HttpMethodNotAllowed)
                    ->setAllowedMethods($routeInfo[1]);
                break;
            case Dispatcher::FOUND:
                list($_, $route, $arguments) = $routeInfo;
                break;
        }

        //$route = $this->getResolvedRoute($route);

        return [$route, $arguments];
    }

    /**
     * @return Dispatcher
     */
    protected function getDispatcher()
    {
        return \FastRoute\simpleDispatcher(function (RouteCollector $collector) {
            foreach ($this->router->getRoutes() as $name => $route) {
                list($method, $path) = explode(' ', $name, 2);
                $collector->addRoute($method, $path, $route);
            }
        });
    }
}