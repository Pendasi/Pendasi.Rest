<?php
namespace Pendasi\Rest\Middleware;

use Pendasi\Rest\Security\Security;

class CsrfMiddleware implements MiddlewareInterface {

    public function handle(): void {
        // Démarrer session si nécessaire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $method = $_SERVER['REQUEST_METHOD'];

        // CSRF check uniquement pour les modifications
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = Security::getCsrfTokenFromRequest();
            
            if (!$token || !Security::verifyCsrfToken($token)) {
                http_response_code(419);
                exit(json_encode([
                    "success" => false,
                    "message" => "CSRF token validation failed"
                ]));
            }
        }
    }
}
