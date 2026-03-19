<?php
namespace Pendasi\Rest\Core;

class ApiResponse {
    public static function success(array $data = [], string $message = "ok") : array {
        http_response_code(200);
        return [
            "success" => true,
            "message" => $message,
            "data" => $data
        ];
    }

    public static function error(string $message = "Error", int $code = 400) : array {
        http_response_code($code);
        return [
            "success" => false,
            "message" => $message
        ];
    }
}