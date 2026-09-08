<?php
/**
 * KanbanDoo - Configurações da Aplicação
 */

// Suporte para sobrescrever configurações locais geradas pelo instalador
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
}

// Identidade do Sistema
defined('APP_NAME') || define('APP_NAME', 'KanbanDoo');
defined('APP_TAGLINE') || define('APP_TAGLINE', 'Gerenciador Ágil de Tarefas e Clientes');
defined('APP_URL') || define('APP_URL', 'http://localhost:8000');

// Fuso Horário da Aplicação (usado em prazos, cronômetros e históricos)
defined('APP_TIMEZONE') || define('APP_TIMEZONE', getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo');
date_default_timezone_set(APP_TIMEZONE);

// Driver de Banco: 'sqlite' ou 'mysql'
defined('DB_DRIVER') || define('DB_DRIVER', 'sqlite');

// Configuração SQLite
defined('DB_SQLITE_PATH') || define('DB_SQLITE_PATH', __DIR__ . '/../storage/database.sqlite');

// Configurações do Banco de Dados MySQL
defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
defined('DB_PORT') || define('DB_PORT', getenv('DB_PORT') ?: '3306');
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ?: 'kanbandoo');
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') ?: '');

// Sessão e Segurança
defined('SESSION_NAME') || define('SESSION_NAME', 'kanbandoo_session');
defined('SESSION_LIFETIME') || define('SESSION_LIFETIME', 30 * 24 * 60 * 60); // 30 dias

// Uploads
defined('UPLOAD_DIR') || define('UPLOAD_DIR', __DIR__ . '/../storage/uploads');
defined('MAX_UPLOAD_SIZE') || define('MAX_UPLOAD_SIZE', 15 * 1024 * 1024); // 15MB

// Quantos dias de tarefas concluídas permanecem visíveis na coluna "Concluído"
defined('DONE_VISIBLE_DAYS') || define('DONE_VISIBLE_DAYS', 14);

// Inicialização de Sessão
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
    session_name(SESSION_NAME);
    session_start();
}
