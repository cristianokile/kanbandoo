<?php
/**
 * Script temporário para rodar migração de notas em produção (MySQL & SQLite)
 */
require_once __DIR__ . '/app/bootstrap.php';

try {
    $pdo = get_pdo();
    $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';

    if ($driver === 'sqlite') {
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS note_groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            client_id INTEGER NULL,
            color TEXT DEFAULT '#6366f1',
            created_by INTEGER NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        ");

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            group_id INTEGER NULL,
            client_id INTEGER NULL,
            created_by INTEGER NOT NULL,
            is_pinned INTEGER DEFAULT 0,
            color TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL
        );
        ");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notes_client ON notes(client_id);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notes_group ON notes(group_id);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notes_author ON notes(created_by);");
    } else {
        // MySQL / MariaDB
        $pdo->exec("
        CREATE TABLE IF NOT EXISTS note_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            client_id INT NULL,
            color VARCHAR(50) DEFAULT '#6366f1',
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_note_groups_client (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $pdo->exec("
        CREATE TABLE IF NOT EXISTS notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content MEDIUMTEXT NOT NULL,
            group_id INT NULL,
            client_id INT NULL,
            created_by INT NOT NULL,
            is_pinned TINYINT DEFAULT 0,
            color VARCHAR(50) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME DEFAULT NULL,
            KEY idx_notes_client (client_id),
            KEY idx_notes_group (group_id),
            KEY idx_notes_author (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    $count = $pdo->query("SELECT COUNT(*) FROM notes")->fetchColumn();

    // Se estiver vazio, inserir grupo inicial de demonstração
    if ($count == 0) {
        $firstClient = $pdo->query("SELECT id FROM clients ORDER BY id ASC LIMIT 1")->fetchColumn();
        $firstUser = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();

        if ($firstUser) {
            $stmtG = $pdo->prepare("INSERT INTO note_groups (name, client_id, color, created_by) VALUES (:n, :c, :col, :u)");
            $stmtG->execute([':n' => 'Diretrizes & Alinhamento', ':c' => $firstClient ?: null, ':col' => '#6366f1', ':u' => $firstUser]);
            $gid = $pdo->lastInsertId();

            $stmtN = $pdo->prepare("INSERT INTO notes (title, content, group_id, client_id, created_by, is_pinned) VALUES (:t, :ct, :g, :cl, :u, :p)");
            $stmtN->execute([
                ':t' => 'Boas-vindas e alinhamento inicial',
                ':ct' => "Foco principal do projeto:\n- Aumentar geração de leads qualificados via Google Ads e Meta Ads.\n- Relatórios mensais com foco em CPA e ROAS.",
                ':g' => $gid,
                ':cl' => $firstClient ?: null,
                ':u' => $firstUser,
                ':p' => 1
            ]);
        }
    }

    echo json_encode([
        'ok' => true,
        'driver' => $driver,
        'message' => 'PROD_MIGRATION_NOTES_SUCCESS',
        'current_notes_count' => (int)$pdo->query("SELECT COUNT(*) FROM notes")->fetchColumn()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
