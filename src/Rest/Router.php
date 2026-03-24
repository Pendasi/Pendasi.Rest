<?php

namespace Pendasi\Rest\Rest;

use Pendasi\Rest\Http\Request;

class Router
{
    private static array $routes = [];
    private static int $order = 0;
    private static bool $sorted = false;

    public static function get(string $uri, string $controller, array $middlewares = []): void {
        self::addRoute('GET', $uri, $controller, $middlewares);
    }

    public static function post(string $uri, string $controller, array $middlewares = []): void {
        self::addRoute('POST', $uri, $controller, $middlewares);
    }

    public static function put(string $uri, string $controller, array $middlewares = []): void {
        self::addRoute('PUT', $uri, $controller, $middlewares);
    }

    public static function patch(string $uri, string $controller, array $middlewares = []): void {
        self::addRoute('PATCH', $uri, $controller, $middlewares);
    }

    public static function delete(string $uri, string $controller, array $middlewares = []): void {
        self::addRoute('DELETE', $uri, $controller, $middlewares);
    }

    public static function dispatch(): void
    {
        self::sortRoutesIfNeeded();

        $request = new Request();
        $path = '/' . trim(self::getRequestPath(), '/');
        $method = strtoupper($request->method());
        $allowedMethods = [];

        foreach (self::$routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) continue;

            // On calcule les méthodes autorisées (pour 405)
            if ($route['method'] !== $method) {
                $allowedMethods[$route['method']] = true;
                continue;
            }

            // Middleware
            foreach ($route['middlewares'] as $mw) {
                if (!class_exists($mw) || !method_exists($mw, 'handle')) {
                    throw new \RuntimeException("Middleware invalide: $mw");
                }
                (new $mw)->handle();
            }

            [$controllerClass, $action] = self::parseControllerSpec($route['controller']);
            $controllerClass = self::resolveControllerClass($controllerClass);

            if (!class_exists($controllerClass)) {
                throw new \RuntimeException("Controller introuvable: $controllerClass");
            }

            $controllerInstance = new $controllerClass;
            if (!$action) $action = self::inferAction($method, count($route['paramNames']));

            $args = [];
            foreach ($route['paramNames'] as $name) {
                $value = $matches[$name] ?? null;
                if ($name === 'id') $value = (int)$value;
                $args[] = $value;
            }

            // Injection automatique Request / body
            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $args[] = $request->all();
            } else {
                $args[] = $request;
            }

            $result = $controllerInstance->{$action}(...$args);

            if (headers_sent()) return;

            if (is_array($result) || is_object($result)) {
                header('Content-Type: application/json');
                echo json_encode($result);
                return;
            }

            if (is_string($result)) {
                echo $result;
                return;
            }

            // Si rien n'a été renvoyé, on ne force pas une réponse.
            return;
        }

        if (!empty($allowedMethods)) {
            header('Allow: ' . implode(', ', array_keys($allowedMethods)));
            throw new \Pendasi\Rest\Core\HttpException(405, [
                "success" => false,
                "message" => "Method not allowed",
                "allowed" => array_keys($allowedMethods)
            ]);
        }

        throw new \Pendasi\Rest\Core\HttpException(404, [
            "success" => false,
            "message" => "Route not found"
        ]);
    }

    private static function addRoute(string $method, string $uri, string $controller, array $middlewares): void
    {
        $compiled = self::compilePath($uri);
        self::$routes[] = [
            'method' => strtoupper($method),
            'path' => $uri,
            'controller' => $controller,
            'middlewares' => $middlewares,
            'regex' => $compiled['regex'],
            'paramNames' => $compiled['paramNames'],
            'score' => $compiled['score'],
            'order' => self::$order++
        ];
    }

    private static function sortRoutesIfNeeded(): void
    {
        if (self::$sorted) return;
        usort(self::$routes, fn($a, $b) => $b['score'] <=> $a['score'] ?: $a['order'] <=> $b['order']);
        self::$sorted = true;
    }

    private static function getRequestPath(): string
    {
        $fromQuery = $_GET['url'] ?? null;
        if ($fromQuery) return trim($fromQuery, '/');

        $path = $_SERVER['PATH_INFO'] ?? $_SERVER['REQUEST_URI'] ?? '/';
        $parsed = parse_url($path, PHP_URL_PATH) ?: '/';
        $basePath = trim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');

        if ($basePath && str_starts_with(trim($parsed, '/'), $basePath)) {
            $parsed = substr(trim($parsed, '/'), strlen($basePath . '/'));
        }

        return $parsed;
    }

    private static function parseControllerSpec(string $controllerSpec): array
    {
        $parts = explode('@', $controllerSpec, 2);
        return [ $parts[0], $parts[1] ?? null ];
    }

    private static function resolveControllerClass(string $controllerClass): string
    {
        // Le framework n’impose aucun namespace d’application
        return $controllerClass;
    }

    private static function inferAction(string $method, int $hasParams): string
    {
        return match ($method) {
            'GET' => $hasParams ? 'show' : 'index',
            'POST' => 'store',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'index'
        };
    }

    private static function compilePath(string $uri): array
    {
        $trimmed = trim($uri, '/');
        if ($trimmed === '') return ['regex' => '#^/?$#', 'paramNames' => [], 'score' => 0];

        $segments = explode('/', $trimmed);
        $paramNames = [];
        $regexParts = [];
        $literalChars = 0;
        $paramCount = 0;

        foreach ($segments as $seg) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $seg, $m)) {
                $name = $m[1];
                $paramNames[] = $name;
                $paramCount++;
                $regexParts[] = strtolower($name) === 'id' ? "(?P<$name>[0-9]+)" : "(?P<$name>[^/]+)";
            } else {
                $literalChars += strlen($seg);
                $regexParts[] = preg_quote($seg, '#');
            }
        }

        $regex = '#^/' . implode('/', $regexParts) . '/?$#';

        // Score: plus c'est spécifique (plus de littéraux, moins de paramètres), mieux c'est.
        $score = ($literalChars * 10) - ($paramCount * 100);

        return ['regex' => $regex, 'paramNames' => $paramNames, 'score' => $score];
    }
}

