<?php

namespace Pendasi\Rest\Rest;

class Router {

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

    /**
     * Shortcut RESTful:
     * - resource: déduit du nom du contrôleur (UserController -> users)
     * - base: /api/{resource}
     *
     * Exemple: Router::api('UserController');
     */
    public static function api(string $controller, array $middlewares = []): void {
        $resource = self::inferResourceFromController($controller);
        $apiPrefix = (string)\Pendasi\Rest\Core\Config::get('api_prefix', '');
        $apiPrefix = trim($apiPrefix);
        if ($apiPrefix === '') {
            $base = '/' . $resource;
        } else {
            $base = rtrim($apiPrefix, '/') . '/' . $resource;
        }

        self::get($base, $controller . '@index', $middlewares);
        self::post($base, $controller . '@store', $middlewares);
        self::get($base . '/{id}', $controller . '@show', $middlewares);
        self::put($base . '/{id}', $controller . '@update', $middlewares);
        self::delete($base . '/{id}', $controller . '@delete', $middlewares);
    }

    public static function dispatch(): void {
        self::$sorted = false;
        self::sortRoutesIfNeeded();

        $path = self::getRequestPath();
        // Normalisation: nos regex commencent par "/"
        $path = $path === '' ? '/' : '/' . $path;
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $body = self::parseRequestBody();
        $query = \Pendasi\Rest\Core\RequestQuery::fromRequest();

        $allowedMethods = [];

        foreach (self::$routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            // On calcule les méthodes autorisées (pour 405)
            if ($route['method'] !== $method) {
                $allowedMethods[$route['method']] = true;
                continue;
            }

            // Match OK + méthode OK
            $paramsValues = [];
            foreach ($route['paramNames'] as $name) {
                $value = $matches[$name] ?? null;
                if ($name === 'id' && $value !== null) {
                    $value = (int)$value;
                }
                $paramsValues[] = $value;
            }

            foreach ($route['middlewares'] as $mw) {
                if (!is_string($mw) || !class_exists($mw)) {
                    throw new \RuntimeException('Middleware invalide: ' . (is_string($mw) ? $mw : gettype($mw)));
                }
                (new $mw)->handle();
            }

            [$controllerClass, $action] = self::parseControllerSpec($route['controller']);
            $controllerClass = self::resolveControllerClass($controllerClass);

            if (!class_exists($controllerClass)) {
                throw new \RuntimeException("Controller class introuvable: {$controllerClass}");
            }

            $controllerInstance = new $controllerClass;

            if (!$action) {
                $action = self::inferAction($method, count($paramsValues));
            }

            if (!method_exists($controllerInstance, $action)) {
                throw new \RuntimeException("Action introuvable: {$controllerClass}::{$action}()");
            }

            $args = self::buildControllerArgs($method, $paramsValues, $body);

            // Optionnel: si l'action attend 1 paramètre de plus, on lui fournit la query.
            $refMethod = new \ReflectionMethod($controllerInstance, $action);
            $paramCount = $refMethod->getNumberOfParameters();
            if ($paramCount === count($args) + 1) {
                $param = $refMethod->getParameters()[count($args)];
                $type = $param->getType();
                $expectsArray = $type && $type->isBuiltin() && $type->getName() === 'array';
                $expectsRequestQuery = $type && !$type->isBuiltin() && $type->getName() === \Pendasi\Rest\Core\RequestQuery::class;

                $args[] = $expectsRequestQuery ? $query : ($expectsArray ? $query->toArray() : $query->toArray());
            }

            $result = $controllerInstance->{$action}(...$args);

            // Si le controller a déjà envoyé une réponse, on ne fait rien.
            if (headers_sent()) {
                return;
            }

            if (is_array($result)) {
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
            $allow = implode(', ', array_keys($allowedMethods));
            header('Allow: ' . $allow);
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

    private static function addRoute(string $method, string $uri, string $controller, array $middlewares): void {
        $compiled = self::compilePath($uri);
        self::$routes[] = [
            'method' => strtoupper($method),
            'path' => $uri,
            'controller' => $controller,
            'middlewares' => $middlewares,
            'regex' => $compiled['regex'],
            'paramNames' => $compiled['paramNames'],
            'score' => $compiled['score'],
            'order' => self::$order++,
        ];
    }

    private static function sortRoutesIfNeeded(): void {
        if (self::$sorted) {
            return;
        }

        usort(self::$routes, function (array $a, array $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            return $a['order'] <=> $b['order'];
        });

        self::$sorted = true;
    }

    /**
     * @return array{regex:string,paramNames:string[],score:int}
     */
    private static function compilePath(string $uri): array {
        $trimmed = trim($uri);
        $trimmed = trim($trimmed, '/');

        if ($trimmed === '') {
            return [
                'regex' => '#^/?$#',
                'paramNames' => [],
                'score' => 0
            ];
        }

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

                // Convention: {id} -> numérique
                if (strtolower($name) === 'id') {
                    $regexParts[] = '(?P<' . $name . '>[0-9]+)';
                } else {
                    $regexParts[] = '(?P<' . $name . '>[^/]+)';
                }
            } else {
                $literalChars += strlen($seg);
                $regexParts[] = preg_quote($seg, '#');
            }
        }

        $regex = '#^/' . implode('/', $regexParts) . '/?$#';

        // Score: plus c'est spécifique (plus de littéraux, moins de paramètres), mieux c'est.
        $score = ($literalChars * 10) - ($paramCount * 100);

        return [
            'regex' => $regex,
            'paramNames' => $paramNames,
            'score' => $score
        ];
    }

    private static function getRequestPath(): string {
        $fromQuery = $_GET['url'] ?? null;
        if (is_string($fromQuery) && $fromQuery !== '') {
            return trim($fromQuery, '/');
        }

        $path = $_SERVER['PATH_INFO'] ?? null;
        if (is_string($path) && $path !== '') {
            return trim($path, '/');
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $parsed = parse_url($requestUri, PHP_URL_PATH) ?: '/';

        $parsed = (string)$parsed;

        // Retire le préfixe lié au dossier d'entrée (ex: /Pendasi.Rest/public)
        // pour que les routes puissent être définies à partir de /api/...
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = '';
        if (is_string($scriptName) && $scriptName !== '') {
            $basePath = trim((string)dirname($scriptName), '/');
        }

        $trimmedParsed = trim($parsed, '/');
        if ($basePath !== '' && (string)trim((string)$basePath, '/') !== '') {
            $prefix = trim($basePath, '/');
            if (str_starts_with($trimmedParsed, $prefix . '/')) {
                $trimmedParsed = substr($trimmedParsed, strlen($prefix . '/'));
            } elseif ($trimmedParsed === $prefix) {
                $trimmedParsed = '';
            }
        }

        return $trimmedParsed;
    }

    private static function parseRequestBody(): array {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Pour POST form-data / x-www-form-urlencoded, PHP remplit souvent $_POST.
        if ($method === 'POST' && !empty($_POST)) {
            $rawParsed = self::parseRawBody();
            return array_merge($_POST, $rawParsed);
        }

        return self::parseRawBody();
    }

    private static function parseRawBody(): array {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        $contentType = strtolower(trim($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json') || str_contains($contentType, '+json')) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        // x-www-form-urlencoded (et fallback)
        $data = [];
        parse_str($raw, $data);
        return is_array($data) ? $data : [];
    }

    /**
     * @return array{0:string,1:?string}
     */
    private static function parseControllerSpec(string $controllerSpec): array {
        $parts = explode('@', $controllerSpec, 2);
        if (count($parts) === 2) {
            return [$parts[0], $parts[1]];
        }
        return [$controllerSpec, null];
    }

    private static function resolveControllerClass(string $controllerClass): string {
        // Si déjà pleinement qualifié, on laisse.
        if (str_contains($controllerClass, '\\')) {
            return $controllerClass;
        }

        if (class_exists($controllerClass)) {
            return $controllerClass;
        }

        // Heuristique (alignée avec la doc): App\\Controllers\\
        $prefix = \Pendasi\Rest\Core\Config::get('controller_namespace');
        if (!is_string($prefix) || $prefix === '') {
            $prefix = 'App\\Controllers\\';
        }

        return $prefix . $controllerClass;
    }

    private static function inferAction(string $method, int $hasParams): string {
        if ($method === 'GET') {
            return $hasParams > 0 ? 'show' : 'index';
        }
        if ($method === 'POST') {
            return 'store';
        }
        if ($method === 'PUT' || $method === 'PATCH') {
            return 'update';
        }
        if ($method === 'DELETE') {
            return 'delete';
        }

        return 'index';
    }

    /**
     * @param string[] $paramsValues
     */
    private static function buildControllerArgs(string $method, array $paramsValues, array $body): array {
        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
            return array_merge($paramsValues, [$body]);
        }

        return $paramsValues;
    }

    private static function inferResourceFromController(string $controllerSpec): string {
        // On enlève '@method' si jamais l'utilisateur passe 'UserController@x'
        $controllerClass = explode('@', $controllerSpec, 2)[0];
        $short = $controllerClass;
        if (str_contains($short, '\\')) {
            $short = substr($short, strrpos($short, '\\') + 1);
        }

        $short = preg_replace('/Controller$/i', '', $short) ?: $short;
        $short = strtolower($short);

        // Pluralisation naïve: ajoute 's' si pas déjà.
        if (!str_ends_with($short, 's')) {
            $short .= 's';
        }

        return $short;
    }
}
