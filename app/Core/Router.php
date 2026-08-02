<?php
namespace App\Core;

class Router {
    private static array $routes = [];
    private static array $groupStack = [];

    // Rotas publicas que nao precisam de autenticacao
    private static array $publicRoutes = [
        '/login',
        '/logout',
    ];

    public static function get(string $path, $handler, array $middleware = []): void {
        self::addRoute('GET', $path, $handler, $middleware);
    }

    public static function post(string $path, $handler, array $middleware = []): void {
        self::addRoute('POST', $path, $handler, $middleware);
    }

    private static function addRoute(string $method, string $path, $handler, array $middleware): void {
        self::$routes[] = [
            'method'     => $method,
            'path'       => self::currentPrefix() . $path,
            'handler'    => $handler,
            'middleware' => array_merge(self::currentGroupMiddleware(), $middleware),
        ];
    }

    /**
     * Agrupa rotas sob um prefixo e/ou uma lista de middlewares comuns.
     *
     * Exemplo:
     *   Router::group(['prefix' => '/admin', 'middleware' => [AuthMiddleware::class]], function () {
     *       Router::get('/usuarios', 'UsuariosController@index');
     *   });
     */
    public static function group(array $options, callable $callback): void {
        self::$groupStack[] = $options;
        $callback();
        array_pop(self::$groupStack);
    }

    private static function currentPrefix(): string {
        $prefix = '';
        foreach (self::$groupStack as $group) {
            $prefix .= $group['prefix'] ?? '';
        }
        return $prefix;
    }

    private static function currentGroupMiddleware(): array {
        $middleware = [];
        foreach (self::$groupStack as $group) {
            $middleware = array_merge($middleware, $group['middleware'] ?? []);
        }
        return $middleware;
    }

    /**
     * Define quais rotas podem ser acessadas sem autenticacao.
     * Chame isso nas suas rotas (ex: routes/web.php) se precisar
     * liberar caminhos adicionais.
     */
    public static function publicRoutes(array $paths): void {
        self::$publicRoutes = array_merge(self::$publicRoutes, $paths);
    }

    private static function isPublicRoute(string $uri): bool {
        foreach (self::$publicRoutes as $pub) {
            if ($uri === $pub || strpos($uri, $pub) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = strtok($_SERVER['REQUEST_URI'], '?');

        // Redireciona para login se nao autenticado e a rota nao e publica
        if (!self::isPublicRoute($uri) && !Auth::check()) {
            header('Location: /login');
            exit;
        }

        foreach (self::$routes as $route) {
            $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                array_shift($matches);

                try {
                    if (!empty($route['middleware'])) {
                        Middleware::run($route['middleware']);
                    }

                    if (is_callable($route['handler'])) {
                        call_user_func_array($route['handler'], $matches);
                        return;
                    }

                    [$controllerName, $action] = explode('@', $route['handler']);
                    $class = "App\\Controllers\\{$controllerName}";

                    if (!class_exists($class)) {
                        http_response_code(500);
                        Logger::error("Controller nao encontrado: {$class}");
                        echo "<h1>Erro 500</h1><p>Controller <code>{$class}</code> nao encontrado.</p>";
                        return;
                    }

                    $controller = new $class();
                    call_user_func_array([$controller, $action], $matches);
                } catch (\Throwable $e) {
                    self::handleError($e);
                }
                return;
            }
        }

        http_response_code(404);
        echo '<h1>404 - Pagina nao encontrada</h1>';
    }

    private static function handleError(\Throwable $e): void {
        http_response_code(500);
        Logger::error('Erro nao tratado na rota', [
            'exception' => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
        ]);

        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            echo "<h1>Erro 500</h1>";
            echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($e->getFile()) . " na linha " . $e->getLine() . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        } else {
            echo "<h1>Erro 500 - Erro Interno do Servidor</h1>";
            echo "<p>Ocorreu um erro ao processar sua requisicao. Tente novamente mais tarde.</p>";
        }
    }
}
