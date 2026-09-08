<?php
/**
 * KanbanDoo - Preferências do Usuário
 *
 * Guarda no perfil o que hoje só existia no navegador (tema, visão do quadro),
 * para que a preferência acompanhe a pessoa em qualquer máquina.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['error' => 'Não autorizado.'], 401);
}

$pdo = get_pdo();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método não suportado.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$theme = $input['theme'] ?? null;

if (!in_array($theme, ['dark', 'light'], true)) {
    json_response(['error' => 'Tema inválido.'], 400);
}

try {
    $pdo->prepare('UPDATE users SET theme_preference = :t, updated_at = :now WHERE id = :id')
        ->execute([':t' => $theme, ':now' => now(), ':id' => $user['id']]);
    $_SESSION['theme'] = $theme;

    json_response(['success' => true, 'theme' => $theme]);
} catch (Exception $e) {
    error_log('[KanbanDoo] ' . $e->getMessage());
    json_response(['error' => 'Não foi possível salvar a preferência.'], 500);
}
