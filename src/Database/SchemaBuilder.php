<?php
namespace Pendasi\Rest\Database;

use PDO;

class SchemaBuilder {
    private PDO $pdo;
    private string $tableName;
    private array $columns = [];
    private array $constraints = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Créer une table
     */
    public function create(string $tableName, callable $callback): void {
        $this->tableName = $tableName;
        $this->columns = [];
        $this->constraints = [];

        // Ajouter la colonne ID automatiquement
        $this->id();

        // Exécuter le callback
        $callback($this);

        // Exécuter la création
        $this->executeCreate();
    }

    /**
     * Exécuter la création de table
     */
    private function executeCreate(): void {
        $columnDefinitions = [];
        foreach ($this->columns as $column) {
            $columnDefinitions[] = $column;
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (\n";
        $sql .= implode(",\n", $columnDefinitions);
        
        if ($this->constraints) {
            $sql .= ",\n" . implode(",\n", $this->constraints);
        }

        $sql .= "\n)";

        $this->pdo->exec($sql);
    }

    /**
     * Colonne ID auto-incrémentée
     */
    public function id(): self {
        $this->columns[] = "id INT PRIMARY KEY AUTO_INCREMENT";
        return $this;
    }

    /**
     * Colonne STRING
     */
    public function string(string $name, int $length = 255): self {
        $nullable = $this->buildNullableModifier($name);
        $this->columns[] = "$name VARCHAR($length) {$nullable}";
        return $this;
    }

    /**
     * Colonne TEXT
     */
    public function text(string $name): self {
        $nullable = $this->buildNullableModifier($name);
        $this->columns[] = "$name TEXT {$nullable}";
        return $this;
    }

    /**
     * Colonne INTEGER
     */
    public function integer(string $name): self {
        $nullable = $this->buildNullableModifier($name);
        $this->columns[] = "$name INT {$nullable}";
        return $this;
    }

    /**
     * Colonne BOOLEAN
     */
    public function boolean(string $name, bool $default = false): self {
        $defaultValue = $default ? '1' : '0';
        $this->columns[] = "$name BOOLEAN DEFAULT $defaultValue";
        return $this;
    }

    /**
     * Colonne DECIMAL
     */
    public function decimal(string $name, int $precision = 8, int $scale = 2): self {
        $nullable = $this->buildNullableModifier($name);
        $this->columns[] = "$name DECIMAL($precision, $scale) {$nullable}";
        return $this;
    }

    /**
     * Colonne TIMESTAMP
     */
    public function timestamp(string $name): self {
        $this->columns[] = "$name TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        return $this;
    }

    /**
     * Colonne DATE
     */
    public function date(string $name): self {
        $nullable = $this->buildNullableModifier($name);
        $this->columns[] = "$name DATE {$nullable}";
        return $this;
    }

    /**
     * Colonne DATETIME
     */
    public function dateTime(string $name): self {
        $nullable = $this->buildNullableModifier($name);
        $this->columns[] = "$name DATETIME {$nullable}";
        return $this;
    }

    /**
     * Clé étrangère
     */
    public function foreignKey(string $column, string $table, string $reference = 'id'): self {
        $this->constraints[] = "FOREIGN KEY ($column) REFERENCES $table($reference) ON DELETE CASCADE";
        return $this;
    }

    /**
     * Index unique
     */
    public function unique(string $column): self {
        $this->constraints[] = "UNIQUE ($column)";
        return $this;
    }

    /**
     * Index
     */
    public function index(string $column): self {
        $this->constraints[] = "INDEX ($column)";
        return $this;
    }

    /**
     * Timestamps (created_at, updated_at)
     */
    public function timestamps(): self {
        $this->columns[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        return $this;
    }

    /**
     * Helper pour modifier la nullable
     */
    private function buildNullableModifier(string $name): string {
        // Pour l'instant, retourner NOT NULL par défaut
        // Cela peut être amélioré avec une API fluent
        return "NOT NULL";
    }
}
