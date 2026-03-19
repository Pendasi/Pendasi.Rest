<?php

namespace Pendasi\Rest\Rest;

class Router {

    private static array $routes = [];
    private static array $middlewares = [];

    public static function get($uri, $controller, $middlewares = []) {
        self::$routes[] = ['GET', $uri, $controller, $middlewares];
    }

    public static function post($uri, $controller, $middlewares = []) {
        self::$routes[] = ['POST', $uri, $controller, $middlewares];
    }

    public static function put($uri, $controller, $middlewares = []) {
        self::$routes[] = ['PUT', $uri, $controller, $middlewares];
    }

    public static function delete($uri, $controller, $middlewares = []) {
        self::$routes[] = ['DELETE', $uri, $controller, $middlewares];
    }

    public static function api(string $controller) {
        self::$routes[] = ['API', '*', $controller, []];
    }

    public static function dispatch() {

        $url = trim($_GET['url'] ?? '', '/');
        $method = $_SERVER['REQUEST_METHOD'];

        foreach (self::$routes as [$m, $uri, $controller, $middlewares]) {

            if ($m === 'API') {
                return self::handleApi($controller);
            }

            $pattern = preg_replace('#\{id\}#', '([0-9]+)', $uri);
            $pattern = "#^$pattern$#";

            if ($method === $m && preg_match($pattern, $url, $matches)) {

                array_shift($matches);

                // Exécuter les middlewares
                foreach ($middlewares as $mw) {
                    (new $mw)->handle();
                }

                $c = new $controller;

                return $matches
                    ? $c->show(...$matches)
                    : $c->index();
            }
        }

        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Route not found"
        ]);
    }

    private static function handleApi($controller) {
        $method = $_SERVER['REQUEST_METHOD'];
        $id = $_GET['id'] ?? null;
        $c = new $controller;

        switch($method) {
            case "GET":
                $id ? $c->show($id) : $c->index();
                break;
            case "POST":
                $c->store($_POST);
                break;
            case "PUT":
                parse_str(file_get_contents("php://input"), $data);
                $c->update($id, $data);
                break;
            case "DELETE":
                $c->delete($id);
                break;
        }
    }
}
