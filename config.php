<?php
// Configuration de Pendasi.Rest
// À customiser selon votre environnement

$appEnv = getenv('APP_ENV') ?: 'production';
$appDebugRaw = getenv('APP_DEBUG');
$appDebug = false;
if ($appDebugRaw !== false) {
    $parsed = filter_var($appDebugRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $appDebug = $parsed ?? false;
}

return [
    'app_env' => $appEnv,
    'debug' => $appDebug,
    // Préfixe des routes "API REST" construites par Router::api().
    // Exemple: '' => /users, '/api' => /api/users
    'api_prefix' => getenv('API_PREFIX') !== false ? (string)getenv('API_PREFIX') : '',
    // Heuristique utilisée par le routeur quand la classe n'est pas fully-qualified.
    // Exemple doc: 'UserController@index' -> App\Controllers\UserController
    'controller_namespace' => 'App\\Controllers\\',
    
    // Configuration base de données
    'database' => [
        'host' => getenv('DB_HOST') ?? 'localhost',
        'port' => getenv('DB_PORT') ?? 3306,
        'name' => getenv('DB_NAME') ?? 'pendasi',
        'user' => getenv('DB_USER') ?? 'root',
        'password' => getenv('DB_PASSWORD') ?? '',
        'charset' => 'utf8mb4'
    ],

    // Configuration JWT
    'jwt_secret' => getenv('JWT_SECRET') ?? 'your-secret-key-change-in-production',
    'jwt_expiry' => getenv('JWT_EXPIRY') ?? 3600, // 1 heure

    // Configuration CORS
    'cors' => [
        'allowed_origins' => ['localhost:3000', 'localhost:8080'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-CSRF-Token']
    ],

    // Configuration uploads
    'upload' => [
        'max_size' => 10485760, // 10MB en octets
        'allowed_types' => ['image/jpeg', 'image/png', 'application/pdf'],
        'directory' => __DIR__ . '/../../uploads'
    ],

    // Configuration cache
    'cache' => [
        'driver' => 'file', // file, redis (à implémenter)
        'ttl' => 3600
    ]
];
