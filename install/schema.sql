-- KanbanDoo Database Schema
-- Gerenciador ágil de tarefas e clientes

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Clientes / Empresas
CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_name VARCHAR(150) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clients_status (status),
    INDEX idx_clients_company (company_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Usuários / Membros da Equipe
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
    avatar_path VARCHAR(255) DEFAULT NULL,
    theme_preference ENUM('light', 'dark') NOT NULL DEFAULT 'dark',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Colunas / Estágios do Kanban
CREATE TABLE IF NOT EXISTS task_stages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(20) NOT NULL DEFAULT '#6366f1',
    sort_order INT NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tarefas
CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED DEFAULT NULL,
    stage_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    due_date DATE DEFAULT NULL,
    estimated_minutes INT UNSIGNED DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('open', 'completed', 'archived') NOT NULL DEFAULT 'open',
    is_timer_running TINYINT(1) NOT NULL DEFAULT 0,
    timer_started_at DATETIME DEFAULT NULL,
    total_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    in_focus TINYINT(1) NOT NULL DEFAULT 0,
    is_bomb TINYINT(1) NOT NULL DEFAULT 0,
    tags JSON DEFAULT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'manual',
    group_name VARCHAR(191) DEFAULT NULL,
    mentioned_by VARCHAR(191) DEFAULT NULL,
    whatsapp_url TEXT DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tasks_client (client_id),
    INDEX idx_tasks_stage (stage_id),
    INDEX idx_tasks_priority (priority),
    INDEX idx_tasks_status (status),
    INDEX idx_tasks_due_date (due_date),
    CONSTRAINT fk_tasks_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    CONSTRAINT fk_tasks_stage FOREIGN KEY (stage_id) REFERENCES task_stages(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tasks_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Responsáveis atribuídos à tarefa
CREATE TABLE IF NOT EXISTS task_assignees (
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    CONSTRAINT fk_assignees_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignees_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Checklists / Subtarefas
CREATE TABLE IF NOT EXISTS task_checklists (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_checklists_task (task_id),
    CONSTRAINT fk_checklists_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Comentários da Tarefa
CREATE TABLE IF NOT EXISTS task_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    comment_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_comments_task (task_id),
    CONSTRAINT fk_comments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Anexos da Tarefa
CREATE TABLE IF NOT EXISTS task_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED DEFAULT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attachments_task (task_id),
    CONSTRAINT fk_attachments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Histórico de Atividades da Tarefa
CREATE TABLE IF NOT EXISTS task_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_history_task (task_id),
    CONSTRAINT fk_history_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Resumos e Diário de Bordo da Tarefa
CREATE TABLE IF NOT EXISTS task_summaries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    summary_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_summaries_task (task_id),
    CONSTRAINT fk_summaries_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    CONSTRAINT fk_summaries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de Estágios do Kanban
INSERT INTO task_stages (id, name, slug, color, sort_order, is_system) VALUES
(1, 'Menções', 'mentions', '#ec4899', 1, 1),
(2, 'A Fazer', 'todo', '#64748b', 2, 1),
(3, 'Em Andamento', 'in_progress', '#3b82f6', 3, 1),
(4, 'Revisão / Aguardando', 'review', '#f59e0b', 4, 1),
(5, 'Concluído', 'done', '#10b981', 5, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Usuário Administrador Padrão (admin / admin123)
INSERT INTO users (id, username, password_hash, full_name, email, role) VALUES
(1, 'admin', '$2y$10$L23nO5KTlHJMvAckU4iRpuj1EbCqd0CurUgU1kzSUk0otYmSFSw4G', 'Administrador', 'admin@kanbando.local', 'admin')
ON DUPLICATE KEY UPDATE username = VALUES(username);

SET FOREIGN_KEY_CHECKS = 1;
