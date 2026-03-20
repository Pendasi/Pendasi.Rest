<?php
namespace Pendasi\Rest\Database;

use PDO;

class Migration {
    protected PDO $pdo;
    protected string $connection;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Créer la table de metadata des migrations
     */
    public static function createMigrationsTable(PDO $pdo) {
        $sql = "
            CREATE TABLE IF NOT EXISTS migrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $pdo->exec($sql);
    }

    /**
     * Obtenir toutes les migrations exécutées
     */
    public static function getMigrations(PDO $pdo): array {
        $stmt = $pdo->query("SELECT name, batch FROM migrations ORDER BY batch ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enregistrer une migration comme exécutée
     */
    public static function recordMigration(PDO $pdo, string $name, int $batch) {
        $stmt = $pdo->prepare("INSERT INTO migrations (name, batch) VALUES (?, ?)");
        $stmt->execute([$name, $batch]);
    }

    /**
     * Supprimer un enregistrement de migration
     */
    public static function deleteMigration(PDO $pdo, string $name) {
        $stmt = $pdo->prepare("DELETE FROM migrations WHERE name = ?");
        $stmt->execute([$name]);
    }

    /**
     * Obtenir le batch actuel
     */
    public static function getLastBatch(PDO $pdo): int {
        $stmt = $pdo->query("SELECT MAX(batch) as batch FROM migrations");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['batch'] ? $result['batch'] + 1 : 1;
    }

    // Stub methods pour les classes enfant
    public function up() {
        throw new \Exception("Method up() must be implemented");
    }

    public function down() {
        throw new \Exception("Method down() must be implemented");
    }

    /**
     * Helpers pour créer des tables
     */
    protected function createTable(string $tableName, callable $callback): void {
        $builder = new SchemaBuilder($this->pdo);
        $builder->create($tableName, $callback);
    }

    protected function dropTable(string $tableName): void {
        $sql = "DROP TABLE IF EXISTS $tableName";
        $this->pdo->exec($sql);
    }

    protected function dropTableIfExists(string $tableName): void {
        $this->dropTable($tableName);
    }
}
