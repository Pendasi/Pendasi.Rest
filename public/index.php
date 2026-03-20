<?php
declare(strict_types=1);

// Charger l'autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Charger la configuration
require_once __DIR__ . '/../config.php';

// Importer les classes core
use Pendasi\Rest\Core\Config;
use Pendasi\Rest\Core\ExceptionHandler;
use Pendasi\Rest\Rest\Router;

// Configurer la gestion des exceptions globale
ExceptionHandler::register();

// Démarrer session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Charger la configuration
Config::load(__DIR__ . '/../config.php');

// Headers CORS (configurable)
$cors = Config::get('cors', []);
$allowedOrigins = $cors['allowed_origins'] ?? ['*'];
$allowedMethods = $cors['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'];
$allowedHeaders = $cors['allowed_headers'] ?? ['Content-Type', 'Authorization'];
if (!in_array('X-CSRF-Token', $allowedHeaders, true)) {
    $allowedHeaders[] = 'X-CSRF-Token';
}
if (!in_array('OPTIONS', $allowedMethods, true)) {
    $allowedMethods[] = 'OPTIONS';
}
if (!in_array('PATCH', $allowedMethods, true)) {
    $allowedMethods[] = 'PATCH';
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowOriginValue = '*';
if (!in_array('*', $allowedOrigins, true)) {
    $allowOriginValue = in_array($origin, $allowedOrigins, true) ? $origin : '';
}

if ($allowOriginValue !== '') {
    header('Access-Control-Allow-Origin: ' . $allowOriginValue);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));

// Si c'est une requête OPTIONS, retourner 200
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Dispatcher les routes
Router::dispatch();
