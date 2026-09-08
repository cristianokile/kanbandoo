<?php
/**
 * Autenticação e Controle de Sessão
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function current_user(): ?array
{
    static $user = null;

    if ($user !== null) {
        return $user;
    }

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, full_name, email, role, avatar_path, theme_preference FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();
    return $user && $user['role'] === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Por favor, faça login para continuar.');
        redirect(function_exists('url') ? url('/entrar') : 'index.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('danger', 'Acesso restrito apenas para administradores.');
        redirect(function_exists('url') ? url('/tarefas') : 'index.php');
    }
}

function attempt_login(string $usernameOrEmail, string $password): bool
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([
        ':u' => $usernameOrEmail,
        ':e' => $usernameOrEmail
    ]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Regenerar ID de sessão para prevenir session fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        return true;
    }

    return false;
}

function current_theme(): string
{
    if (isset($_SESSION['theme'])) {
        return $_SESSION['theme'];
    }
    $user = current_user();
    if ($user && !empty($user['theme_preference'])) {
        return $user['theme_preference'];
    }
    return 'dark'; // Dark mode padrão
}
