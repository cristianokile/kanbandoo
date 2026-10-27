<?php
/**
 * API Pública de Consulta da Agenda / Calendário do KanbanDoo
 *
 * Somente leitura. Exige token público válido de um usuário ativo.
 * Filtra OBRIGATORIAMENTE tarefas particulares (is_private = 0) e tarefas excluídas.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    json_response(['error' => 'Token não informado.'], 400);
}

$pdo = get_pdo();

// Validar usuário pelo public_token
$stmt = $pdo->prepare('SELECT id, full_name, email FROM users WHERE public_token = :t LIMIT 1');
$stmt->execute([':t' => $token]);
$user = $stmt->fetch();

if (!$user) {
    json_response(['error' => 'Agenda não encontrada ou link inválido.'], 404);
}

$userId = (int)$user['id'];
$start = trim((string)($_GET['start'] ?? date('Y-m-01')));
$end = trim((string)($_GET['end'] ?? date('Y-m-t')));

try {
    // Buscar tarefas agendadas para o intervalo que pertencem ou estão atribuídas ao usuário
    // NUNCA incluir tarefas particulares (is_private = 1)
    $sql = "
        SELECT 
            t.id, t.title, t.due_date, t.due_time, t.priority, t.stage_id, t.is_bomb, t.in_focus,
            t.completed_at, t.estimated_minutes, t.is_calendar_event,
            c.company_name AS client_company, c.category AS client_category,
            s.name AS stage_name, s.color AS stage_color
        FROM tasks t
        LEFT JOIN clients c ON t.client_id = c.id
        LEFT JOIN task_stages s ON t.stage_id = s.id
        WHERE t.deleted_at IS NULL
          AND t.is_private = 0
          AND t.due_date IS NOT NULL
          AND t.due_date >= :start AND t.due_date <= :end
          AND (
              t.created_by = :uid1 
              OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = :uid2)
          )
        ORDER BY t.due_date ASC, t.due_time ASC, t.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start' => $start,
        ':end'   => $end,
        ':uid1'  => $userId,
        ':uid2'  => $userId,
    ]);
    $tasks = $stmt->fetchAll();

    $events = [];
    foreach ($tasks as $t) {
        $events[] = [
            'id' => (int)$t['id'],
            'title' => $t['title'],
            'due_date' => $t['due_date'],
            'due_time' => $t['due_time'] ? substr($t['due_time'], 0, 5) : null,
            'priority' => $t['priority'],
            'stage_name' => $t['stage_name'],
            'stage_color' => $t['stage_color'],
            'is_bomb' => (int)$t['is_bomb'],
            'in_focus' => (int)$t['in_focus'],
            'is_completed' => !empty($t['completed_at']),
            'client_company' => $t['client_company'],
            'client_category' => $t['client_category'] ?? 'C',
            'is_calendar_event' => (int)$t['is_calendar_event'],
            'estimated_minutes' => $t['estimated_minutes'] ? (int)$t['estimated_minutes'] : null,
        ];
    }

    json_response([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
        ],
        'events' => $events,
    ]);

} catch (Throwable $e) {
    json_response(['error' => 'Erro ao carregar agenda: ' . $e->getMessage()], 500);
}
