<?php

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $middlewareStack = [];

    public function get(string $path, array $action, array $middleware = []): void
    {
        $this->add('GET', $path, $action, $middleware);
    }

    public function post(string $path, array $action, array $middleware = []): void
    {
        $this->add('POST', $path, $action, $middleware);
    }

    public function put(string $path, array $action, array $middleware = []): void
    {
        $this->add('PUT', $path, $action, $middleware);
    }

    public function delete(string $path, array $action, array $middleware = []): void
    {
        $this->add('DELETE', $path, $action, $middleware);
    }

    public function group(array $middleware, callable $callback): void
    {
        $this->middlewareStack = array_merge($this->middlewareStack, $middleware);
        $callback($this);
        $this->middlewareStack = array_slice($this->middlewareStack, 0, count($this->middlewareStack) - count($middleware));
    }

    private function add(string $method, string $path, array $action, array $middleware): void
    {
        $path = rtrim($path, '/') ?: '/';
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $this->toPattern($path),
            'action' => $action,
            'middleware' => array_merge($this->middlewareStack, $middleware),
        ];
    }

    private function toPattern(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);

        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        $path = $this->basePathStrip($request->path());

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                foreach ($route['middleware'] as $middleware) {
                    $result = $this->runMiddleware($middleware);
                    if ($result === false) {
                        return;
                    }
                }

                [$controllerClass, $methodName] = $route['action'];
                $controller = new $controllerClass();
                $controller->$methodName($request, $params);

                return;
            }
        }

        $this->notFound();
    }

    private function basePathStrip(string $path): string
    {
        $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        return $path === '' ? '/' : $path;
    }

    private function runMiddleware(string $middleware): mixed
    {
        [$name, $arg] = array_pad(explode(':', $middleware, 2), 2, null);

        $map = [
            'auth' => \App\Middleware\AuthMiddleware::class,
            'guest' => \App\Middleware\GuestMiddleware::class,
            'permission' => \App\Middleware\PermissionMiddleware::class,
        ];

        if (!isset($map[$name])) {
            return true;
        }

        $instance = new $map[$name]();

        return $instance->handle($arg);
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', [], 'layouts/guest');
    }
}
