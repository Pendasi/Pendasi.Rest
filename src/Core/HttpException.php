<?php
declare(strict_types=1);

namespace Pendasi\Rest\Core;

/**
 * Exception "HTTP" interne au framework.
 * Permet de centraliser l'envoi de JSON (sans exit()) côté ExceptionHandler.
 */
class HttpException extends \Exception {
    private array $payload;

    public function __construct(int $statusCode, array $payload) {
        $message = $payload['message'] ?? 'HTTP Error';
        parent::__construct($message, $statusCode);
        $this->payload = $payload;
    }

    public function getStatusCode(): int {
        $code = $this->getCode();
        return is_int($code) && $code > 0 ? $code : 500;
    }

    public function getPayload(): array {
        return $this->payload;
    }
}

