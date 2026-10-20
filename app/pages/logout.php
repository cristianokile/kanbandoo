<?php
/**
 * KanbanDoo - Logout
 */
require_once dirname(__DIR__) . '/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    try {
        $pdo = get_pdo();
        $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = :id")->execute([':id' => $_SESSION['user_id']]);
    } catch (Throwable $e) {}
}

if (isset($_COOKIE['kd_remember'])) {
    setcookie('kd_remember', '', time() - 42000, '/');
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();
header('Location: ' . url('/entrar'));
exit;
