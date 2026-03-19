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

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Content-Type: application/json');

// Charger la configuration
Config::load(__DIR__ . '/../config.php');

// Si c'est une requête OPTIONS, retourner 200
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Dispatcher les routes
Router::dispatch();
