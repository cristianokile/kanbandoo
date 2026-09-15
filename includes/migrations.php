<?php
/**
 * KanbanDoo - Migrações Incrementais de Schema
 *
 * Aplica alterações de estrutura de forma idempotente, sem apagar dados.
 * É chamado uma vez por requisição a partir de includes/db.php (custo: 1 SELECT).
 */

function run_migrations(PDO $pdo): void
{
    $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';

    // Tabela de controle
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        name VARCHAR(190) NOT NULL PRIMARY KEY,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $applied = $pdo->query("SELECT name FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $applied = array_flip($applied);

    $migrations = kanbandoo_migrations($driver);

    foreach ($migrations as $name => $statements) {
        if (isset($applied[$name])) {
            continue;
        }

        foreach ($statements as $sql) {
            try {
                $pdo->exec($sql);
            } catch (PDOException $e) {
                // Coluna/índice já existente em bases criadas antes do controle de migração.
                // Segue adiante e apenas registra a migração como aplicada.
            }
        }

        $stmt = $pdo->prepare("INSERT INTO schema_migrations (name, applied_at) VALUES (:n, :d)");
        $stmt->execute([':n' => $name, ':d' => date('Y-m-d H:i:s')]);
    }
}

function kanbandoo_migrations(string $driver): array
{
    $datetime = $driver === 'sqlite' ? 'DATETIME' : 'DATETIME NULL';
    $int      = $driver === 'sqlite' ? 'INTEGER' : 'INT';

    return [
        // Exclusão reversível: permite o "Desfazer" após excluir uma tarefa.
        '001_tasks_deleted_at' => [
            "ALTER TABLE tasks ADD COLUMN deleted_at $datetime DEFAULT NULL",
        ],

        // Limite de WIP por coluna do quadro.
        '002_stages_wip_limit' => [
            "ALTER TABLE task_stages ADD COLUMN wip_limit $int DEFAULT NULL",
        ],

        // Índices de leitura do quadro (filtros, buscas e contagens).
        '003_indexes' => [
            "CREATE INDEX IF NOT EXISTS idx_tasks_stage ON tasks (stage_id)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks (status)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_client ON tasks (client_id)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_due ON tasks (due_date)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_completed ON tasks (completed_at)",
            "CREATE INDEX IF NOT EXISTS idx_tasks_deleted ON tasks (deleted_at)",
            "CREATE INDEX IF NOT EXISTS idx_assignees_user ON task_assignees (user_id)",
            "CREATE INDEX IF NOT EXISTS idx_checklists_task ON task_checklists (task_id)",
            "CREATE INDEX IF NOT EXISTS idx_comments_task ON task_comments (task_id)",
            "CREATE INDEX IF NOT EXISTS idx_summaries_task ON task_summaries (task_id)",
        ],

        // Tarefas concluídas há muito tempo saem do quadro sem serem apagadas.
        '004_tasks_archived_at' => [
            "ALTER TABLE tasks ADD COLUMN archived_at $datetime DEFAULT NULL",
            "CREATE INDEX IF NOT EXISTS idx_tasks_archived ON tasks (archived_at)",
        ],

        // Modelos de tarefas pré-configurados
        '005_task_templates' => [
            $driver === 'sqlite'
                ? "CREATE TABLE IF NOT EXISTS task_templates (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    estimated_minutes INTEGER DEFAULT NULL,
                    tags TEXT DEFAULT '[]',
                    description TEXT DEFAULT NULL,
                    checklist TEXT DEFAULT '[]',
                    created_by INTEGER DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )"
                : "CREATE TABLE IF NOT EXISTS task_templates (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    estimated_minutes INT DEFAULT NULL,
                    tags TEXT DEFAULT NULL,
                    description TEXT DEFAULT NULL,
                    checklist TEXT DEFAULT NULL,
                    created_by INT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
        ],

        // Tarefas recorrentes (diário, semanal, quinzenal, mensal, anual, personalizada)
        '006_task_recurrence' => [
            "ALTER TABLE tasks ADD COLUMN is_recurring $int DEFAULT 0",
            "ALTER TABLE tasks ADD COLUMN recurrence_type VARCHAR(50) DEFAULT NULL",
            "ALTER TABLE tasks ADD COLUMN recurrence_config TEXT DEFAULT NULL",
            "ALTER TABLE tasks ADD COLUMN parent_task_id $int DEFAULT NULL",
        ],

        // Horário de expediente, capacidade e preferências de visão do quadro
        '007_work_hours_and_board_settings' => [
            "ALTER TABLE tasks ADD COLUMN estimated_minutes $int DEFAULT NULL",
            "ALTER TABLE users ADD COLUMN work_start_time VARCHAR(10) DEFAULT '09:00'",
            "ALTER TABLE users ADD COLUMN work_end_time VARCHAR(10) DEFAULT '17:00'",
            "ALTER TABLE users ADD COLUMN work_days VARCHAR(50) DEFAULT '1,2,3,4,5'",
            "ALTER TABLE users ADD COLUMN board_view_mode VARCHAR(20) DEFAULT 'status'",
            "ALTER TABLE users ADD COLUMN visible_columns_json TEXT DEFAULT NULL",
        ],
    ];
}
