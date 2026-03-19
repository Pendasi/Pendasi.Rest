<?php
namespace Pendasi\Rest\Security;

use Pendasi\Rest\Core\Config;

class Security {
    
    /**
     * Sanitize données pour prévenir XSS
     */
    public static function sanitize(array $data): array {
        return array_map(function($value) {
            if (is_string($value)) {
                return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            }
            return $value;
        }, $data);
    }

    /**
     * Sanitize pour SQL (utiliser les prepared statements!)
     * Cette fonction est FALLBACK ONLY - toujours utiliser les prepared statements
     */
    public static function escapeSql(string $value): string {
        // ATTENTION: Ceci n'est qu'une sécurité supplémentaire
        // TOUJOURS utiliser les prepared statements!
        return addslashes(trim($value));
    }

    /**
     * Valider une URL
     */
    public static function validateUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Valider un email
     */
    public static function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Générer un token CSRF
     */
    public static function generateCsrfToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Vérifier un token CSRF
     */
    public static function verifyCsrfToken(string $token): bool {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Obtenr le token CSRF du request
     */
    public static function getCsrfTokenFromRequest(): ?string {
        // Vérifier dans les headers
        $headers = getallheaders();
        if (isset($headers['X-CSRF-Token'])) {
            return $headers['X-CSRF-Token'];
        }

        // Vérifier dans POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_csrf_token'])) {
            return $_POST['_csrf_token'];
        }

        return null;
    }
}
