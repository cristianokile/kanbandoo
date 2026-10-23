<?php
/**
 * KanbanDoo - API do Painel da Coordenadoria
 */
require_once dirname(__DIR__) . '/bootstrap.php';


header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?: $_POST;

$action = $input['action'] ?? $_GET['action'] ?? 'get_data';
$pdo = get_pdo();

// Ação de regenerar token pelo usuário autenticado (no perfil ou no painel)
if ($action === 'regenerate_token') {
    if (!is_logged_in()) {
        json_response(['error' => 'Não autorizado.'], 401);
    }
    $curUser = current_user();
    $newToken = bin2hex(random_bytes(16));
    $upd = $pdo->prepare('UPDATE users SET public_token = :t WHERE id = :id');
    $upd->execute([':t' => $newToken, ':id' => $curUser['id']]);

    json_response([
        'success' => true,
        'token' => $newToken,
        'url' => url('/coordenadoria/' . $newToken),
        'message' => 'Novo link da coordenadoria gerado com sucesso! O link antigo foi invalidado.'
    ]);
}

// Obter dados da coordenadoria via token
$token = trim((string)($input['token'] ?? $_GET['token'] ?? ''));
if ($token === '') {
    json_response(['error' => 'Token não informado.'], 400);
}

$stmt = $pdo->prepare('SELECT id, full_name, email, avatar_path FROM users WHERE public_token = :t LIMIT 1');
$stmt->execute([':t' => $token]);
$coordUser = $stmt->fetch();

if (!$coordUser) {
    json_response(['error' => 'Link inválido ou expirado.'], 404);
}

// Buscar tarefas (ignora particulares: is_private = 1)
$tasksStmt = $pdo->prepare("
    SELECT DISTINCT t.id, t.title, t.description, t.priority, t.due_date, t.original_due_date, t.due_time,
           t.completed_at, t.created_at, t.is_timer_running, t.timer_started_at, t.total_seconds,
           t.tags, t.estimated_minutes, t.is_bomb,
           c.company_name AS client_company,
           s.slug AS stage_slug, s.name AS stage_name, s.color AS stage_color,
           (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id) AS checklist_total,
           (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id AND is_completed = 1) AS checklist_done
    FROM tasks t
    LEFT JOIN clients c ON t.client_id = c.id
    LEFT JOIN task_stages s ON t.stage_id = s.id
    LEFT JOIN task_assignees ta ON ta.task_id = t.id
    WHERE (ta.user_id = :uid OR (t.created_by = :uid AND NOT EXISTS(SELECT 1 FROM task_assignees WHERE task_id = t.id)))
      AND t.deleted_at IS NULL
      AND t.archived_at IS NULL
      AND (t.is_private IS NULL OR t.is_private = 0)
      AND (t.is_calendar_event = 0 OR t.show_in_kanban = 1)
    ORDER BY t.is_bomb DESC, t.sort_order ASC, t.id DESC
");
$tasksStmt->execute([':uid' => $coordUser['id']]);
$tasks = $tasksStmt->fetchAll();

foreach ($tasks as &$t) {
    $t['due_badge'] = format_due_date_badge($t['due_date'], $t['original_due_date'] ?? null);
}

json_response([
    'success' => true,
    'user' => $coordUser,
    'tasks' => $tasks,
]);
