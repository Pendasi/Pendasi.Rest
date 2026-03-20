<?php
namespace Pendasi\Rest\Middleware;

use Pendasi\Rest\Security\JWT;
use Pendasi\Rest\Core\HttpException;

class AuthMiddleware implements MiddlewareInterface {

    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = JWT::getTokenFromHeader();

        if (!$token) {
            throw new HttpException(401, [
                "success" => false,
                "message" => "Unauthorized - Token missing"
            ]);
        }

        $payload = JWT::verify($token);

        if (!$payload) {
            throw new HttpException(401, [
                "success" => false,
                "message" => "Unauthorized - Invalid or expired token"
            ]);
        }

        // Stocker l'utilisateur dans la session
        $_SESSION['user'] = $payload;
    }
}
