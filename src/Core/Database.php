<?php
namespace Pendasi\Rest\Core;

use PDO;

class Database {
    private static ?Database $instance = null;
    private PDO $pdo;
    private int $transactionLevel = 0;

    private function __construct($host, $db, $user, $pass) {
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    public static function getInstance($host, $db, $user, $pass): Database {
        if (!self::$instance) {
            self::$instance = new Database($host, $db, $user, $pass);
        }
        return self::$instance;
    }

    public function connection(): PDO {
        return $this->pdo;
    }

    /**
     * Démarrer une transaction
     */
    public function beginTransaction(): void {
        if ($this->transactionLevel === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT LEVEL' . ($this->transactionLevel + 1));
        }
        $this->transactionLevel++;
    }

    /**
     * Valider la transaction
     */
    public function commit(): void {
        if ($this->transactionLevel === 0) {
            throw new \Exception("No active transaction");
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec('RELEASE SAVEPOINT LEVEL' . ($this->transactionLevel + 1));
        }
    }

    /**
     * Annuler la transaction
     */
    public function rollback(): void {
        if ($this->transactionLevel === 0) {
            throw new \Exception("No active transaction");
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            $this->pdo->rollback();
        } else {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT LEVEL' . ($this->transactionLevel + 1));
        }
    }

    /**
     * Exécuter une opération dans une transaction
     */
    public function transaction(callable $callback) {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Vérifier le niveau de transaction
     */
    public function getTransactionLevel(): int {
        return $this->transactionLevel;
    }
}
