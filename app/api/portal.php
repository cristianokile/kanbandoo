<?php
/**
 * KanbanDoo - API do Portal do Cliente
 *
 * Acesso autenticado via portal_token do cliente.
 */
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$input = json_decode($raw, true) ?: $_POST;

$token = trim((string)($input['token'] ?? $_GET['token'] ?? ''));
if ($token === '') {
    json_response(['error' => 'Token de acesso inválido.'], 403);
}

$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT id, company_name, contact_name, email, phone, status FROM clients WHERE portal_token = :t AND status = "active" LIMIT 1');
$stmt->execute([':t' => $token]);
$client = $stmt->fetch();

if (!$client) {
    json_response(['error' => 'Portal de cliente não encontrado ou inativo.'], 404);
}

$action = $input['action'] ?? 'get_tasks';

if ($action === 'new_request') {
    $title = trim((string)($input['title'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $dueDate = !empty($input['due_date']) ? trim((string)$input['due_date']) : null;

    if ($title === '') {
        json_response(['error' => 'Por favor, informe o título da solicitação.', 'field' => 'title'], 400);
    }
    if (mb_strlen($title) > 200) {
        json_response(['error' => 'O título deve ter no máximo 200 caracteres.', 'field' => 'title'], 400);
    }
    if ($description === '') {
        json_response(['error' => 'Por favor, detalhe a sua solicitação.', 'field' => 'description'], 400);
    }
    if ($dueDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        $dueDate = null;
    }

    // Estágio "Menções e Solicitações" (slug mentions)
    $stageId = (int)$pdo->query("SELECT id FROM task_stages WHERE slug = 'mentions' LIMIT 1")->fetchColumn();
    if (!$stageId) {
        $stageId = 5; // fallback ID padrão
    }

    $tagsJson = json_encode(['Solicitação'], JSON_UNESCAPED_UNICODE);
    $nowStr = now();

    $pdo->beginTransaction();
    $ins = $pdo->prepare("
        INSERT INTO tasks (client_id, stage_id, created_by, title, description, priority, due_date,
                           estimated_minutes, tags, source, in_focus, is_bomb, status, sort_order,
                           is_recurring, show_in_kanban, created_at, updated_at)
        VALUES (:client_id, :stage_id, NULL, :title, :description, 'medium', :due_date,
                NULL, :tags, 'client_portal', 0, 0, 'open', 0,
                0, 1, :now, :now2)
    ");
    $ins->execute([
        ':client_id' => $client['id'],
        ':stage_id' => $stageId,
        ':title' => $title,
        ':description' => $description,
        ':due_date' => $dueDate,
        ':tags' => $tagsJson,
        ':now' => $nowStr,
        ':now2' => $nowStr,
    ]);
    $taskId = (int)$pdo->lastInsertId();

    if (function_exists('log_task_activity')) {
        log_task_activity($pdo, $taskId, null, 'task_created', 'Solicitação aberta pelo cliente via portal');
    }

    $pdo->commit();

    json_response([
        'success' => true,
        'task_id' => $taskId,
        'message' => 'Sua solicitação foi enviada com sucesso e já está no quadro da nossa equipe!'
    ]);
}

// Retorna tarefas agrupadas
$tasksStmt = $pdo->prepare("
    SELECT t.id, t.title, t.description, t.priority, t.due_date, t.due_time, t.completed_at, t.created_at,
           s.slug AS stage_slug, s.name AS stage_name
    FROM tasks t
    LEFT JOIN task_stages s ON t.stage_id = s.id
    WHERE t.client_id = :cid
      AND t.deleted_at IS NULL
      AND (t.is_calendar_event = 0 OR t.show_in_kanban = 1)
    ORDER BY t.sort_order ASC, t.created_at DESC
");
$tasksStmt->execute([':cid' => $client['id']]);
$allTasks = $tasksStmt->fetchAll();

json_response([
    'client' => [
        'id' => $client['id'],
        'company_name' => $client['company_name'],
        'contact_name' => $client['contact_name'],
    ],
    'tasks' => $allTasks,
]);
