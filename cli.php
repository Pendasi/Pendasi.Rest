#!/usr/bin/env php
<?php
/**
 * Pendasi.Rest CLI
 * Utilitaire en ligne de commande pour gérer les migrations
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Pendasi\Rest\Core\Config;
use Pendasi\Rest\Core\Database;
use Pendasi\Rest\Database\MigrationRunner;

// Charger la configuration
Config::load(__DIR__ . '/../config.php');

// Obtenir les arguments
$command = $argv[1] ?? 'help';

// Initialiser la BD+++-
$dbConfig = Config::getDb();
$db = Database::getInstance(
    $dbConfig['host'],
    $dbConfig['name'],
    $dbConfig['user'],
    $dbConfig['password']
);

$runner = new MigrationRunner($db->connection(), __DIR__ . '/../database/migrations');

// Traiter les commandes
switch ($command) {
    case 'migrate':
        echo "Running migrations...\n";
        $results = $runner->migrate();
        foreach ($results as $result) {
            echo $result . "\n";
        }
        break;

    case 'rollback':
        echo "Rolling back migrations...\n";
        $results = $runner->rollback();
        foreach ($results as $result) {
            echo $result . "\n";
        }
        break;

    case 'migrate:status':
        echo "=== Migration Status ===\n";
        $history = $runner->getHistory();
        if (empty($history)) {
            echo "No migrations executed yet.\n";
        } else {
            foreach ($history as $migration) {
                echo "✓ " . $migration['name'] . " (Batch: " . $migration['batch'] . ")\n";
            }
        }

        echo "\nPending migrations:\n";
        $pending = $runner->getPending();
        if (empty($pending)) {
            echo "No pending migrations.\n";
        } else {
            foreach ($pending as $name) {
                echo "⋯ $name\n";
            }
        }
        break;

    case 'make:migration':
        if (empty($argv[2])) {
            echo "Usage: cli make:migration <name>\n";
            exit(1);
        }

        $name = strtolower(str_replace(' ', '_', $argv[2]));
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_$name.php";
        $filepath = __DIR__ . "/../database/migrations/$filename";
        $className = 'Pendasi\\Rest\\Database\\' . implode('', array_map('ucfirst', explode('_', $timestamp . '_' . $name)));

        $stub = <<<PHP
                <?php
                namespace Pendasi\Rest\Database;

                class {$className} extends Migration {
                    
                    public function up() {
                        // TODO: Implémenter la migration
                    }

                    public function down() {
                        // TODO: Implémenter le rollback
                    }
                }

                PHP;

        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        file_put_contents($filepath, $stub);
        echo "✓ Migration created: $filename\n";
        break;

    default:
        echo "Pendasi.Rest CLI\n\n";
        echo "Available commands:\n";
        echo "  migrate              Run all pending migrations\n";
        echo "  rollback             Rollback the last batch of migrations\n";
        echo "  migrate:status       Show migration status\n";
        echo "  make:migration <name> Create a new migration file\n";
        echo "\n";
        break;
}
?>
