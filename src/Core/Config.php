<?php
namespace Pendasi\Rest\Core;

class Config {
    private static array $config = [];

    public static function load(string $path) {
        if (file_exists($path)) {
            self::$config = require $path;
        } else {
            throw new \Exception("Config file not found: $path");
        }
    }

    public static function get(string $key, $default = null) {
        return self::$config[$key] ?? $default;
    }

    public static function set(string $key, $value) {
        self::$config[$key] = $value;
    }

    public static function getDb() {
        return self::$config['database'] ?? null;
    }

    public static function getJwtSecret() {
        return self::$config['jwt_secret'] ?? null;
    }

    public static function getJwtExpiry() {
        return self::$config['jwt_expiry'] ?? 3600; // 1 heure par défaut
    }
}
