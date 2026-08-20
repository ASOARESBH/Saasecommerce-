<?php
namespace App\Core;

class Router
{
    private static array $routes = [];
    private static array $groupStack = [];
    private static array $publicRoutes = ['/login', '/logout', '/api/v1', '/webhooks'];

    public static function get(string $path, $handler, array $middleware = []): void { self::addRoute('GET', $path, $handler, $middleware); }
    public static function post(string $path, $handler, array $middleware = []): void { self::addRoute('POST', $path, $handler, $middleware); }
    public static function put(string $path, $handler, array $middleware = []): void { self::addRoute('PUT', $path, $handler, $middleware); }
    public static function patch(string $path, $handler, array $middleware = []): void { self::addRoute('PATCH', $path, $handler, $middleware); }
    public static function delete(string $path, $handler, array $middleware = []): void { self::addRoute('DELETE', $path, $handler, $middleware); }

    private static function addRoute(string $method, string $path, $handler, array $middleware): void
    {
        self::$routes[] = [
            'method' => $method,
            'path' => self::currentPrefix() . $path,
            'handler' => $handler,
            'middleware' => array_merge(self::currentGroupMiddleware(), $middleware),
        ];
    }

    public static function group(array $options, callable $callback): void
    {
        self::$groupStack[] = $options;
        $callback();
        array_pop(self::$groupStack);
    }

    private static function currentPrefix(): string
    {
        $prefix = '';
        foreach (self::$groupStack as $group) $prefix .= $group['prefix'] ?? '';
        return $prefix;
    }

    private static function currentGroupMiddleware(): array
    {
        $middleware = [];
        foreach (self::$groupStack as $group) $middleware = array_merge($middleware, $group['middleware'] ?? []);
        return $middleware;
    }

    public static function publicRoutes(array $paths): void { self::$publicRoutes = array_merge(self::$publicRoutes, $paths); }

    private static function isPublicRoute(string $uri): bool
    {
        foreach (self::$publicRoutes as $pub) {
            if ($uri === $pub || str_starts_with($uri, rtrim($pub, '/') . '/')) return true;
        }
        return false;
    }

    private static function isApiRequest(string $uri): bool
    {
        return str_starts_with($uri, '/api/') || str_starts_with($uri, '/webhooks');
    }

    public static function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
        $isApi = self::isApiRequest($uri);

        if ($method === 'OPTIONS' && $isApi) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-API-Secret, X-Signature, X-Timestamp, Idempotency-Key');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            http_response_code(204);
            return;
        }

        if (!$isApi && !self::isPublicRoute($uri) && !Auth::check()) {
            header('Location: /login');
            exit;
        }

        foreach (self::$routes as $route) {
            $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            if ($route['method'] !== $method || !preg_match($pattern, $uri, $matches)) continue;
            array_shift($matches);

            try {
                if (!empty($route['middleware'])) Middleware::run($route['middleware']);
                if (is_callable($route['handler'])) {
                    call_user_func_array($route['handler'], $matches);
                    return;
                }
                [$controllerName, $action] = explode('@', $route['handler']);
                $class = str_contains($controllerName, '\\')
                    ? 'App\\Controllers\\' . $controllerName
                    : 'App\\Controllers\\' . $controllerName;
                if (!class_exists($class)) {
                    Logger::error("Controller não encontrado: {$class}");
                    if ($isApi) ApiResponse::error('Rota indisponível.', 500, 'CONTROLLER_NOT_FOUND');
                    http_response_code(500);
                    echo '<h1>Erro 500</h1><p>Controller não encontrado.</p>';
                    return;
                }
                $controller = new $class();
                call_user_func_array([$controller, $action], $matches);
                return;
            } catch (\Throwable $e) {
                self::handleError($e, $isApi);
                return;
            }
        }

        if ($isApi) ApiResponse::error('Endpoint não encontrado.', 404, 'NOT_FOUND');
        http_response_code(404);
        echo '<h1>404 - Página não encontrada</h1>';
    }

    private static function handleError(\Throwable $e, bool $isApi): void
    {
        Logger::error('Erro não tratado na rota', [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        if ($isApi) {
            $status = $e->getCode() >= 400 && $e->getCode() <= 599 ? $e->getCode() : 500;
            ApiResponse::error($status === 500 ? 'Erro interno do servidor.' : $e->getMessage(), $status, 'INTERNAL_ERROR');
        }
        http_response_code(500);
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            echo '<h1>Erro 500</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>';
        } else {
            echo '<h1>Erro 500 - Erro Interno do Servidor</h1><p>Ocorreu um erro ao processar sua requisição.</p>';
        }
    }
}
