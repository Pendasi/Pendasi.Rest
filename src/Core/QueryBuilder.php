<?php
namespace Pendasi\Rest\Core;

class QueryBuilder {
    private $pdo;
    private string $table;
    private array $wheres = [];
    private array $joins = [];
    private array $params = [];
    private string $order = "";
    private string $limit = "";

    private array $allowedTables;
    private array $allowedColumns;

    public function __construct($pdo, string $table, array $allowedTables = [], array $allowedColumns = []) {
        if ($allowedTables && !in_array($table, $allowedTables)) {
            throw new \Exception("Table non autorisée: $table");
        }

        $this->pdo = $pdo;
        $this->table = $table;
        $this->allowedTables = $allowedTables;
        $this->allowedColumns = $allowedColumns;
    }

    private function validateColumn(string $column): void {
        // Réduit le risque d'injection via noms de colonnes
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
            throw new \Exception("Colonne invalide: $column");
        }

        // Autoriser "table.col" tout en validant la colonne finale
        $col = $column;
        if (str_contains($column, '.')) {
            $col = substr($column, strrpos($column, '.') + 1);
        }

        if ($this->allowedColumns && !in_array($col, $this->allowedColumns, true)) {
            throw new \Exception("Colonne non autorisée: $column");
        }
    }

    public function join(string $table, string $condition): self {
        if ($this->allowedTables && !in_array($table, $this->allowedTables)) {
            throw new \Exception("Table non autorisée pour join: $table");
        }
        $this->joins[] = "JOIN $table ON $condition";
        return $this;
    }

    public function leftJoin(string $table, string $condition): self {
        if ($this->allowedTables && !in_array($table, $this->allowedTables)) {
            throw new \Exception("Table non autorisée pour leftJoin: $table");
        }
        $this->joins[] = "LEFT JOIN $table ON $condition";
        return $this;
    }

    public function rightJoin(string $table, string $condition): self {
        if ($this->allowedTables && !in_array($table, $this->allowedTables)) {
            throw new \Exception("Table non autorisée pour rightJoin: $table");
        }
        $this->joins[] = "RIGHT JOIN $table ON $condition";
        return $this;
    }

    public function where(string $column, $value): self {
        return $this->whereOp($column, '=', $value);
    }

    /**
     * where avec opérateur (API de query côté framework)
     */
    public function whereOp(string $column, string $operator, $value): self {
        $this->validateColumn($column);

        $op = strtoupper(trim($operator));

        $sqlOperator = match ($op) {
            '=' => '=',
            '==' => '=',
            '!=' => '!=',
            '<>' => '!=',
            '>' => '>',
            '<' => '<',
            '>=' => '>=',
            '<=' => '<=',
            'LIKE' => 'LIKE',
            default => null,
        };

        if ($sqlOperator === null) {
            throw new \Exception("Opérateur non autorisé: $operator");
        }

        $this->wheres[] = "$column $sqlOperator ?";
        $this->params[] = $value;
        return $this;
    }

    public function whereNull(string $column): self {
        $this->validateColumn($column);
        $this->wheres[] = "$column IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): self {
        $this->validateColumn($column);
        $this->wheres[] = "$column IS NOT NULL";
        return $this;
    }

    public function orderBy(string $column, string $direction = "ASC"): self {
        $this->validateColumn($column);
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->order = "ORDER BY $column $direction";
        return $this;
    }

    public function limit(int $limit, int $offset = 0): self {
        if ($limit < 0 || $offset < 0) throw new \Exception("Limit ou offset invalid");
        $this->limit = "LIMIT $offset, $limit";
        return $this;
    }

    public function get(): array {
        $sql = "SELECT * FROM {$this->table} ";
        if ($this->joins) $sql .= implode(" ", $this->joins) . " ";
        if ($this->wheres) $sql .= "WHERE " . implode(" AND ", $this->wheres) . " ";
        if ($this->order) $sql .= $this->order . " ";
        if ($this->limit) $sql .= $this->limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->params);
        return $stmt->fetchAll();
    }

    public function first(): ?array {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }
}