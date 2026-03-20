<?php
namespace Pendasi\Rest\Core;

class Model {
    protected $pdo;
    protected string $table;
    protected array $allowedColumns;
    protected array $attributes = [];
    protected bool $useSoftDeletes = false;
    protected bool $useTimestamps = true;
    protected string $deletedAtColumn = 'deleted_at';

    public function __construct(Database $db, string $table, array $allowedColumns = []) {
        $this->pdo = $db->connection();
        $this->table = $table;
        $this->allowedColumns = $allowedColumns;
    }

    public function query(): QueryBuilder {
        return new QueryBuilder($this->pdo, $this->table, [], $this->allowedColumns);
    }

    protected function filterColumns(array $data): array {
        if (!$this->allowedColumns) return $data;
        return array_filter($data, fn($k) => in_array($k, $this->allowedColumns), ARRAY_FILTER_USE_KEY);
    }

    public function all(): array {
        $query = $this->query();
        
        if ($this->useSoftDeletes) {
            $query = $query->whereNull($this->deletedAtColumn);
        }

        return $query->get();
    }

    public function find($id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?" . 
            ($this->useSoftDeletes ? " AND {$this->deletedAtColumn} IS NULL" : ""));
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): string {
        $data = $this->filterColumns($data);

        // Ajouter les timestamps
        if ($this->useTimestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $cols = implode(",", array_keys($data));
        $vals = ":" . implode(",:", array_keys($data));
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} ($cols) VALUES($vals)");
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    public function update($id, array $data): bool {
        $data = $this->filterColumns($data);

        // Ajouter updated_at
        if ($this->useTimestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $cols = [];
        foreach ($data as $k => $v) {
            $cols[] = "$k = :$k";
        }
        $sql = "UPDATE {$this->table} SET " . implode(", ", $cols) . " WHERE id = :id";
        $data['id'] = $id;
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete($id): bool {
        if ($this->useSoftDeletes) {
            return $this->update($id, [$this->deletedAtColumn => date('Y-m-d H:i:s')]);
        }

        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Forcer la suppression (bypass soft deletes)
     */
    public function forceDelete($id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Restaurer un soft deleted
     */
    public function restore($id): bool {
        if (!$this->useSoftDeletes) {
            throw new \Exception("Soft deletes not enabled for this model");
        }

        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET {$this->deletedAtColumn} = NULL WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Obtenir les soft deleted uniquement
     */
    public function onlyTrashed(): QueryBuilder {
        if (!$this->useSoftDeletes) {
            throw new \Exception("Soft deletes not enabled for this model");
        }

        return $this->query()->whereNotNull($this->deletedAtColumn);
    }

    /**
     * Inclure les soft deleted
     */
    public function withTrashed(): QueryBuilder {
        return $this->query();
    }

    public function hasMany(string $table, string $foreignKey, string $localKey = 'id'): array {
        if (!isset($this->attributes[$localKey])) {
            throw new \Exception("Clé locale '$localKey' non trouvée dans l'instance");
        }
        
        $value = $this->attributes[$localKey];
        
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE $foreignKey = ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    public function belongsTo(string $table, string $foreignKey, string $ownerKey = 'id'): ?array {
        if (!isset($this->attributes[$foreignKey])) {
            throw new \Exception("Clé étrangère '$foreignKey' non trouvée dans l'instance");
        }
        
        $value = $this->attributes[$foreignKey];
        
        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE $ownerKey = ?");
        $stmt->execute([$value]);
        return $stmt->fetch() ?: null;
    }

    public function setAttribute(string $key, $value) {
        $this->attributes[$key] = $value;
    }

    public function __get(string $name) {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, $value) {
        $this->attributes[$name] = $value;
    }
}