<?php
namespace Pendasi\Rest\Database;

use PDO;

class MigrationRunner {
    private PDO $pdo;
    private string $migrationsPath;

    public function __construct(PDO $pdo, string $migrationsPath = '') {
        $this->pdo = $pdo;
        $this->migrationsPath = $migrationsPath ?: __DIR__ . '/../../database/migrations';
    }

    /**
     * Exécuter toutes les migrations en attente
     */
    public function migrate(): array {
        Migration::createMigrationsTable($this->pdo);

        $executed = Migration::getMigrations($this->pdo);
        $executedNames = array_column($executed, 'name');

        $batch = Migration::getLastBatch($this->pdo);
        $results = [];

        foreach ($this->getMigrationFiles() as $file) {
            $name = basename($file, '.php');

            if (in_array($name, $executedNames)) {
                continue;
            }

            try {
                $this->runMigration($file, 'up');
                Migration::recordMigration($this->pdo, $name, $batch);
                $results[] = "Migrated: $name";
            } catch (\Exception $e) {
                $results[] = "Failed: $name - " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Rollback la dernière batch de migrations
     */
    public function rollback(): array {
        Migration::createMigrationsTable($this->pdo);

        $executed = Migration::getMigrations($this->pdo);

        if (empty($executed)) {
            return ["No migrations to rollback"];
        }

        $lastBatch = max(array_column($executed, 'batch'));
        $toRollback = array_filter($executed, fn($m) => $m['batch'] === $lastBatch);

        $results = [];

        foreach (array_reverse($toRollback) as $migration) {
            try {
                $file = $this->migrationsPath . '/' . $migration['name'] . '.php';
                $this->runMigration($file, 'down');
                Migration::deleteMigration($this->pdo, $migration['name']);
                $results[] = "Rolled back: " . $migration['name'];
            } catch (\Exception $e) {
                $results[] = " Failed: " . $migration['name'];
            }
        }

        return $results;
    }

    /**
     * Obtenir toutes les migrations en attente
     */
    public function getPending(): array {
        Migration::createMigrationsTable($this->pdo);

        $executed = Migration::getMigrations($this->pdo);
        $executedNames = array_column($executed, 'name');

        $pending = [];
        foreach ($this->getMigrationFiles() as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $executedNames)) {
                $pending[] = $name;
            }
        }

        return $pending;
    }

    /**
     * Obtenir l'historique des migrations
     */
    public function getHistory(): array {
        Migration::createMigrationsTable($this->pdo);
        return Migration::getMigrations($this->pdo);
    }

    /**
     * Exécuter une migration
     */
    private function runMigration(string $file, string $method): void {
        if (!file_exists($file)) {
            throw new \Exception("Migration file not found: $file");
        }

        require_once $file;

        $className = $this->getClassNameFromFile($file);

        if (!class_exists($className)) {
            throw new \Exception("Migration class not found: $className");
        }

        $migration = new $className($this->pdo);
        $migration->$method();
    }

    /**
     * Extraire le nom de la classe d'un fichier
     */
    private function getClassNameFromFile(string $file): string {
        $filename = basename($file, '.php');
        // Convertir snake_case en PascalCase
        $parts = explode('_', $filename);
        return 'Pendasi\\Rest\\Database\\' . implode('', array_map('ucfirst', $parts));
    }

    /**
     * Obtenir tous les fichiers de migrations
     */
    private function getMigrationFiles(): array {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = scandir($this->migrationsPath);
        $migrations = [];

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && str_ends_with($file, '.php')) {
                $migrations[] = $this->migrationsPath . '/' . $file;
            }
        }

        sort($migrations);
        return $migrations;
    }
}
