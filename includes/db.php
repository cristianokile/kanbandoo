<?php
/**
 * Conexão com o Banco de Dados via PDO (SQLite & MySQL)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/migrations.php';

function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            if ($driver === 'sqlite') {
                $dbPath = DB_SQLITE_PATH;
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $pdo = new PDO('sqlite:' . $dbPath, null, null, $options);
                $pdo->exec('PRAGMA foreign_keys = ON;');
            } else {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            }
        } catch (PDOException $e) {
            if (php_sapi_name() === 'cli') {
                throw $e;
            }
            if (!str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'install.php')) {
                header('Location: ' . APP_URL . '/install/install.php');
                exit;
            }
            throw $e;
        }

        // Mantém o schema em dia sem apagar dados (idempotente).
        if (!str_contains($_SERVER['SCRIPT_NAME'] ?? '', 'install.php')) {
            try {
                run_migrations($pdo);
            } catch (PDOException $e) {
                // Base ainda não instalada: o instalador cuidará do schema completo.
            }
        }
    }

    return $pdo;
}
