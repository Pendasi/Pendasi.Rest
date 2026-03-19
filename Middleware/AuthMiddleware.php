<?php
namespace Pendasi\Rest\Middleware;

use Pendasi\Rest\Security\JWT;

class AuthMiddleware implements MiddlewareInterface {

    public function handle(): void {
        $token = JWT::getTokenFromHeader();

        if (!$token) {
            http_response_code(401);
            exit(json_encode([
                "success" => false,
                "message" => "Unauthorized - Token missing"
            ]));
        }

        $payload = JWT::verify($token);

        if (!$payload) {
            http_response_code(401);
            exit(json_encode([
                "success" => false,
                "message" => "Unauthorized - Invalid or expired token"
            ]));
        }

        // Stocker l'utilisateur dans la session
        $_SESSION['user'] = $payload;
    }
}
