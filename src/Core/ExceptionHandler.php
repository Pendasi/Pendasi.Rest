<?php
namespace Pendasi\Rest\Core;

class ExceptionHandler {
    
    public static function register() {
        set_exception_handler([self::class, 'handle']);
        set_error_handler([self::class, 'handleError']);
    }

    public static function handle(\Throwable $exception) {
        header('Content-Type: application/json', true);
        
        $statusCode = $exception->getCode() > 0 && $exception->getCode() < 600 
            ? $exception->getCode() 
            : 500;
        
        http_response_code($statusCode);

        $response = [
            'success' => false,
            'message' => $exception->getMessage() ?? 'Internal Server Error',
            'error' => get_class($exception)
        ];

        // En développement: inclure la trace
        if (getenv('APP_ENV') === 'development') {
            $response['trace'] = $exception->getTraceAsString();
            error_log("[" . date('Y-m-d H:i:s') . "] " . $exception->getMessage() . "\n" . $exception->getTraceAsString());
        } else {
            error_log("[" . date('Y-m-d H:i:s') . "] " . $exception->getMessage());
        }

        echo json_encode($response);
        exit;
    }

    public static function handleError($errno, $errstr, $errfile, $errline) {
        throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
    }
}
