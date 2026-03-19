<?php
// Configuration de Pendasi.Rest
// À customiser selon votre environnement

return [
    'app_env' => getenv('APP_ENV') ?? 'production',
    'debug' => getenv('APP_DEBUG') ?? false,
    
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
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization']
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
