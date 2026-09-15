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

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT theme_preference, work_start_time, work_end_time, work_days, board_view_mode, visible_columns_json FROM users WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);
    $prefs = $stmt->fetch() ?: [];

    $start = $prefs['work_start_time'] ?? '09:00';
    $end   = $prefs['work_end_time'] ?? '17:00';

    $startParts = explode(':', $start);
    $endParts   = explode(':', $end);
    $startMins  = ((int)($startParts[0] ?? 9)) * 60 + ((int)($startParts[1] ?? 0));
    $endMins    = ((int)($endParts[0] ?? 17)) * 60 + ((int)($endParts[1] ?? 0));
    $capacity   = max(60, $endMins - $startMins);

    json_response([
        'success' => true,
        'theme' => $prefs['theme_preference'] ?? 'dark',
        'work_start_time' => $start,
        'work_end_time' => $end,
        'work_capacity_minutes' => $capacity,
        'work_days' => !empty($prefs['work_days']) ? explode(',', $prefs['work_days']) : ['1', '2', '3', '4', '5'],
        'board_view_mode' => $prefs['board_view_mode'] ?? 'status',
        'visible_columns' => !empty($prefs['visible_columns_json']) ? json_decode($prefs['visible_columns_json'], true) : null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Método não suportado.'], 405);
}

try {
    $updates = [];
    $params = [':id' => $user['id'], ':now' => now()];

    if (isset($input['theme'])) {
        $theme = $input['theme'];
        if (!in_array($theme, ['dark', 'light'], true)) {
            json_response(['error' => 'Tema inválido.'], 400);
        }
        $updates[] = 'theme_preference = :t';
        $params[':t'] = $theme;
        $_SESSION['theme'] = $theme;
    }

    if (isset($input['work_start_time'])) {
        $start = trim((string)$input['work_start_time']);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start)) {
            $updates[] = 'work_start_time = :w_start';
            $params[':w_start'] = $start;
        }
    }

    if (isset($input['work_end_time'])) {
        $end = trim((string)$input['work_end_time']);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
            $updates[] = 'work_end_time = :w_end';
            $params[':w_end'] = $end;
        }
    }

    if (isset($input['work_days'])) {
        $days = is_array($input['work_days']) ? implode(',', array_filter(array_map('strval', $input['work_days']))) : trim((string)$input['work_days']);
        $updates[] = 'work_days = :w_days';
        $params[':w_days'] = $days;
    }

    if (isset($input['board_view_mode'])) {
        $mode = in_array($input['board_view_mode'], ['status', 'weekdays'], true) ? $input['board_view_mode'] : 'status';
        $updates[] = 'board_view_mode = :bv_mode';
        $params[':bv_mode'] = $mode;
    }

    if (isset($input['visible_columns'])) {
        $vis = is_array($input['visible_columns']) ? json_encode($input['visible_columns'], JSON_UNESCAPED_UNICODE) : (string)$input['visible_columns'];
        $updates[] = 'visible_columns_json = :vis_cols';
        $params[':vis_cols'] = $vis;
    }

    if (!empty($updates)) {
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ', updated_at = :now WHERE id = :id';
        $pdo->prepare($sql)->execute($params);
    }

    // Calcular capacidade diária resultante
    $stmt = $pdo->prepare('SELECT work_start_time, work_end_time FROM users WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);
    $row = $stmt->fetch();
    $start = $row['work_start_time'] ?? '09:00';
    $end   = $row['work_end_time'] ?? '17:00';
    $startParts = explode(':', $start);
    $endParts   = explode(':', $end);
    $startMins  = ((int)($startParts[0] ?? 9)) * 60 + ((int)($startParts[1] ?? 0));
    $endMins    = ((int)($endParts[0] ?? 17)) * 60 + ((int)($endParts[1] ?? 0));
    $capacity   = max(60, $endMins - $startMins);

    json_response([
        'success' => true,
        'work_start_time' => $start,
        'work_end_time' => $end,
        'work_capacity_minutes' => $capacity,
    ]);
} catch (Exception $e) {
    error_log('[KanbanDoo] ' . $e->getMessage());
    json_response(['error' => 'Não foi possível salvar a preferência.'], 500);
}
