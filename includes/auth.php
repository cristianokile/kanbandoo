<?php
/**
 * Autenticação e Controle de Sessão
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/totp.php';

function current_user(): ?array
{
    static $user = null;

    if ($user !== null) {
        return $user;
    }

    $pdo = get_pdo();

    // 1. Verificar sessão ativa
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT id, username, full_name, email, role, avatar_path, theme_preference, two_factor_enabled FROM users WHERE id = :id');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            return $user;
        }

        unset($_SESSION['user_id']);
    }

    // 2. Verificar cookie "Manter conectado" (Remember Me)
    if (!empty($_COOKIE['kd_remember'])) {
        $cookieVal = $_COOKIE['kd_remember'];
        $parts = explode(':', $cookieVal, 2);

        if (count($parts) === 2) {
            $userId = (int)$parts[0];
            $token = $parts[1];

            if ($userId > 0 && $token !== '') {
                $stmt = $pdo->prepare('SELECT id, username, full_name, email, role, avatar_path, theme_preference, remember_token, two_factor_enabled FROM users WHERE id = :id');
                $stmt->execute([':id' => $userId]);
                $candidate = $stmt->fetch();

                if ($candidate && !empty($candidate['remember_token'])) {
                    $tokenHash = hash('sha256', $token);
                    if (hash_equals($candidate['remember_token'], $tokenHash)) {
                        // Sessão válida recuperada via cookie persistente
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int)$candidate['id'];
                        $user = $candidate;
                        return $user;
                    }
                }
            }
        }

        // Se o token for inválido, limpa o cookie
        setcookie('kd_remember', '', time() - 3600, '/');
    }

    return null;
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

/**
 * Conclui a autenticação do usuário, definindo sessão e opcionalmente o cookie persistente.
 */
function complete_login(array $user, bool $remember = false): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];

    if ($remember) {
        $pdo = get_pdo();
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $stmt = $pdo->prepare('UPDATE users SET remember_token = :token WHERE id = :id');
        $stmt->execute([':token' => $tokenHash, ':id' => $user['id']]);

        // Cookie válido por 30 dias
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        setcookie('kd_remember', $user['id'] . ':' . $rawToken, time() + (86400 * 30), '/', '', $isHttps, true);
    }
}

/**
 * Tenta autenticar credenciais.
 * Retorna:
 * - ['status' => '2fa_required', 'user_id' => int] se o usuário possuir 2FA ativo
 * - ['status' => 'success'] se logado com sucesso
 * - ['status' => 'failed'] se credenciais incorretas
 */
function attempt_login(string $usernameOrEmail, string $password, bool $remember = false): array
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, password_hash, full_name, two_factor_enabled, two_factor_secret FROM users WHERE username = :u OR email = :e LIMIT 1');
    $stmt->execute([
        ':u' => $usernameOrEmail,
        ':e' => $usernameOrEmail
    ]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Se possui verificação em 2 passos ativa e segredo configurado
        if (!empty($user['two_factor_enabled']) && !empty($user['two_factor_secret'])) {
            return [
                'status' => '2fa_required',
                'user_id' => (int)$user['id'],
                'user' => $user,
            ];
        }

        complete_login($user, $remember);
        return ['status' => 'success', 'user' => $user];
    }

    return ['status' => 'failed'];
}

/**
 * Valida o código 2FA e conclui o login.
 */
function verify_2fa_login(int $userId, string $code, bool $remember = false): bool
{
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, full_name, two_factor_enabled, two_factor_secret FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user || empty($user['two_factor_secret'])) {
        return false;
    }

    if (TOTP::verifyCode($user['two_factor_secret'], $code)) {
        complete_login($user, $remember);
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
