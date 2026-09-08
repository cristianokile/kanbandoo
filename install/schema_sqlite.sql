-- KanbanDoo Database Schema (SQLite)
-- Gerenciador ágil de tarefas e clientes

-- 1. Clientes / Empresas
CREATE TABLE IF NOT EXISTS clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name TEXT NOT NULL,
    contact_name TEXT DEFAULT NULL,
    email TEXT DEFAULT NULL,
    phone TEXT DEFAULT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    summary TEXT DEFAULT NULL,
    links_json TEXT DEFAULT '[]',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Usuários / Membros da Equipe
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    role TEXT NOT NULL DEFAULT 'member',
    avatar_path TEXT DEFAULT NULL,
    theme_preference TEXT NOT NULL DEFAULT 'dark',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3. Colunas / Estágios do Kanban
CREATE TABLE IF NOT EXISTS task_stages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    color TEXT NOT NULL DEFAULT '#6366f1',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_system INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 4. Tarefas
CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER DEFAULT NULL,
    stage_id INTEGER NOT NULL,
    created_by INTEGER DEFAULT NULL,
    title TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    priority TEXT NOT NULL DEFAULT 'medium',
    due_date DATE DEFAULT NULL,
    estimated_minutes INTEGER DEFAULT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'open',
    is_timer_running INTEGER NOT NULL DEFAULT 0,
    timer_started_at DATETIME DEFAULT NULL,
    total_seconds INTEGER NOT NULL DEFAULT 0,
    in_focus INTEGER NOT NULL DEFAULT 0,
    is_bomb INTEGER NOT NULL DEFAULT 0,
    tags TEXT DEFAULT '[]',
    source TEXT NOT NULL DEFAULT 'manual',
    group_name TEXT DEFAULT NULL,
    mentioned_by TEXT DEFAULT NULL,
    whatsapp_url TEXT DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (stage_id) REFERENCES task_stages(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 5. Responsáveis atribuídos à tarefa
CREATE TABLE IF NOT EXISTS task_assignees (
    task_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Checklists / Subtarefas
CREATE TABLE IF NOT EXISTS task_checklists (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    is_completed INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
);

-- 7. Comentários da Tarefa
CREATE TABLE IF NOT EXISTS task_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    comment_text TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 8. Anexos da Tarefa
CREATE TABLE IF NOT EXISTS task_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    uploaded_by INTEGER DEFAULT NULL,
    file_path TEXT NOT NULL,
    original_name TEXT NOT NULL,
    mime_type TEXT NOT NULL,
    file_size INTEGER NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 9. Histórico de Atividades da Tarefa
CREATE TABLE IF NOT EXISTS task_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    user_id INTEGER DEFAULT NULL,
    action TEXT NOT NULL,
    details TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 10. Resumos e Diário de Bordo da Tarefa
CREATE TABLE IF NOT EXISTS task_summaries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    summary_text TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed inicial de Estágios do Kanban
INSERT OR IGNORE INTO task_stages (id, name, slug, color, sort_order, is_system) VALUES
(1, 'Menções', 'mentions', '#ec4899', 1, 1),
(2, 'A Fazer', 'todo', '#64748b', 2, 1),
(3, 'Em Andamento', 'in_progress', '#3b82f6', 3, 1),
(4, 'Revisão / Aguardando', 'review', '#f59e0b', 4, 1),
(5, 'Concluído', 'done', '#10b981', 5, 1);
