<?php
namespace Pendasi\Rest\Http;

class Request
{
    private array $data = [];
    private array $files = [];
    private string $method;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->parse();
    }

    private function parse(): void
    {
        $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');

        // 🔹 JSON
        if (str_contains($contentType, 'application/json') || str_contains($contentType, '+json')) {
            $raw = file_get_contents('php://input');
            $this->data = is_string($raw) && $raw !== '' ? json_decode($raw, true) ?? [] : [];
        }
        // 🔹 Form classique
        elseif (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $this->data = $_POST;
        }
        // 🔹 FormData / upload
        elseif (str_contains($contentType, 'multipart/form-data')) {
            $this->data = $_POST;
            $this->files = $_FILES;
        }
        // 🔹 Fallback GET/PUT/DELETE etc.
        else {
            // On tente de parser l'input brut (PUT, PATCH, DELETE)
            $raw = file_get_contents('php://input');
            if ($raw && is_string($raw)) {
                parse_str($raw, $parsed);
                $this->data = $parsed;
            } else {
                $this->data = $_REQUEST;
            }
        }
    }

    /** Renvoie toutes les données (body ou query selon le cas) */
    public function all(): array
    {
        return $this->data;
    }

    /** Renvoie une valeur spécifique ou $default si non existante */
    public function input(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /** Récupère un fichier uploadé */
    public function file(string $key)
    {
        return $this->files[$key] ?? null;
    }

    /** Renvoie la méthode HTTP */
    public function method(): string
    {
        return $this->method;
    }

    /** Vérifie si la requête est de type AJAX (optionnel) */
    public function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /** Fusionne automatiquement le body et les query params si nécessaire */
    public function merged(): array
    {
        return array_merge($_GET, $this->data);
    }
}