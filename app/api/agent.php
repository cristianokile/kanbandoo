<?php
/**
 * KanbanDoo - API REST para Agentes de IA & MCP
 *
 * Autenticação via Bearer Token (Authorization: Bearer kd_...) ou X-API-Key: kd_...
 * Também aceita sessão logada (para gerar/regenerar token no Perfil).
 *
 * Cobre: Contexto (whoami, stages, clients, team), Tarefas (listar, detalhar, criar,
 * editar, mover, concluir, comentar, subtarefas, excluir) e Notas & Grupos (listar,
 * detalhar, criar, editar/anexar, excluir).
 */
require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'];

// ------------------------------------------------------------------
// 1. Extrair e validar Token de API (ou sessão logada)
// ------------------------------------------------------------------
function extract_api_token(): string
{
    $authHeader = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($authHeader === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $authHeader = (string)$v;
                break;
            }
            if (strtolower($k) === 'x-api-key' && $authHeader === '') {
                return trim((string)$v);
            }
        }
    }

    if (stripos($authHeader, 'Bearer ') === 0) {
        return trim(substr($authHeader, 7));
    }

    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        return trim((string)$_SERVER['HTTP_X_API_KEY']);
    }

    return '';
}

function authenticate_agent_user(PDO $pdo): ?array
{
    $token = extract_api_token();
    if ($token !== '') {
        try {
            $stmt = $pdo->prepare('SELECT id, username, full_name, email, role, api_token FROM users WHERE api_token = :tok LIMIT 1');
            $stmt->execute([':tok' => $token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['api_token']) && hash_equals($row['api_token'], $token)) {
                unset($row['api_token']);
                return $row;
            }
        } catch (Throwable $e) {
            return null;
        }
        return null;
    }

    // Fallback: sessão autenticada do navegador
    if (is_logged_in()) {
        return current_user();
    }

    return null;
}

$user = authenticate_agent_user($pdo);
if (!$user) {
    json_response([
        'ok' => false,
        'error' => 'Não autorizado. Envie um token válido via header Authorization: Bearer <token> ou X-API-Key: <token>.',
    ], 401);
}

// Ler payload JSON ou query string
$rawBody = file_get_contents('php://input');
$input = [];
if ($rawBody !== '' && $rawBody !== false) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
$params = array_merge($_GET, $_POST, $input);
$action = trim((string)($params['action'] ?? ($method === 'GET' ? 'whoami' : '')));
$now = now();

// ------------------------------------------------------------------
// Helpers internos de resolução (Cliente / Coluna / Grupo)
// ------------------------------------------------------------------
function resolve_client_id(PDO $pdo, array $params): ?int
{
    if (!empty($params['client_id']) && is_numeric($params['client_id'])) {
        return (int)$params['client_id'];
    }
    $clientName = trim((string)($params['client_name'] ?? ''));
    if ($clientName !== '') {
        $st = $pdo->prepare('SELECT id FROM clients WHERE LOWER(company_name) = LOWER(:exact) LIMIT 1');
        $st->execute([':exact' => $clientName]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;

        $st = $pdo->prepare('SELECT id FROM clients WHERE company_name LIKE :like ORDER BY company_name ASC LIMIT 1');
        $st->execute([':like' => '%' . $clientName . '%']);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;
    }
    return null;
}

function resolve_stage(PDO $pdo, array $params): ?array
{
    if (!empty($params['stage_id']) && is_numeric($params['stage_id'])) {
        $st = $pdo->prepare('SELECT id, name, slug, color FROM task_stages WHERE id = :id LIMIT 1');
        $st->execute([':id' => (int)$params['stage_id']]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    $slug = trim((string)($params['stage_slug'] ?? $params['stage_name'] ?? ''));
    if ($slug !== '') {
        $st = $pdo->prepare('SELECT id, name, slug, color FROM task_stages WHERE LOWER(slug) = LOWER(:s) OR LOWER(name) LIKE LOWER(:n) LIMIT 1');
        $st->execute([':s' => $slug, ':n' => '%' . $slug . '%']);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    return null;
}

function resolve_note_group_id(PDO $pdo, array $params): ?int
{
    if (!empty($params['group_id']) && is_numeric($params['group_id'])) {
        return (int)$params['group_id'];
    }
    $groupName = trim((string)($params['group_name'] ?? ''));
    if ($groupName !== '') {
        $st = $pdo->prepare('SELECT id FROM note_groups WHERE LOWER(name) = LOWER(:exact) LIMIT 1');
        $st->execute([':exact' => $groupName]);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;

        $st = $pdo->prepare('SELECT id FROM note_groups WHERE name LIKE :like ORDER BY name ASC LIMIT 1');
        $st->execute([':like' => '%' . $groupName . '%']);
        $id = $st->fetchColumn();
        if ($id) return (int)$id;
    }
    return null;
}

function format_task_row(array $t, array $assignees = [], array $checklistSummary = ['total' => 0, 'completed' => 0]): array
{
    $tags = [];
    if (!empty($t['tags'])) {
        $decoded = json_decode((string)$t['tags'], true);
        if (is_array($decoded)) {
            $tags = $decoded;
        } else {
            $tags = array_values(array_filter(array_map('trim', explode(',', (string)$t['tags']))));
        }
    }

    return [
        'id' => (int)$t['id'],
        'title' => $t['title'],
        'description' => $t['description'] ?? '',
        'priority' => $t['priority'] ?? 'medium',
        'due_date' => $t['due_date'] ?: null,
        'due_time' => !empty($t['due_time']) ? substr((string)$t['due_time'], 0, 5) : null,
        'estimated_minutes' => $t['estimated_minutes'] !== null ? (int)$t['estimated_minutes'] : null,
        'stage_id' => (int)$t['stage_id'],
        'stage_name' => $t['stage_name'] ?? null,
        'stage_slug' => $t['stage_slug'] ?? null,
        'client_id' => $t['client_id'] !== null ? (int)$t['client_id'] : null,
        'client_name' => $t['client_company'] ?? null,
        'is_completed' => !empty($t['completed_at']),
        'completed_at' => $t['completed_at'] ?: null,
        'is_private' => !empty($t['is_private']),
        'is_calendar_event' => !empty($t['is_calendar_event']),
        'in_focus' => !empty($t['in_focus']),
        'is_bomb' => !empty($t['is_bomb']),
        'tags' => $tags,
        'assignees' => $assignees,
        'checklist_summary' => $checklistSummary,
        'created_at' => $t['created_at'] ?? null,
        'updated_at' => $t['updated_at'] ?? null,
    ];
}

try {
    // ==============================================================
    // REGENERAR TOKEN (somente usuário logado na web)
    // ==============================================================
    if ($action === 'regenerate_api_token') {
        if (!is_logged_in()) {
            json_response(['ok' => false, 'error' => 'Apenas via sessão web autenticada.'], 403);
        }
        $newToken = 'kd_' . bin2hex(random_bytes(32));
        $st = $pdo->prepare('UPDATE users SET api_token = :tok, updated_at = :now WHERE id = :id');
        $st->execute([':tok' => $newToken, ':now' => $now, ':id' => $user['id']]);
        json_response(['ok' => true, 'api_token' => $newToken]);
    }

    // ==============================================================
    // 1. CONTEXTO E REFERÊNCIAS
    // ==============================================================
    if ($action === 'whoami') {
        $today = date('Y-m-d');
        $openCount = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE deleted_at IS NULL AND archived_at IS NULL AND completed_at IS NULL")->fetchColumn();
        $stToday = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE deleted_at IS NULL AND archived_at IS NULL AND completed_at IS NULL AND due_date = :d");
        $stToday->execute([':d' => $today]);
        $todayCount = (int)$stToday->fetchColumn();

        $stOverdue = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE deleted_at IS NULL AND archived_at IS NULL AND completed_at IS NULL AND due_date IS NOT NULL AND due_date < :d");
        $stOverdue->execute([':d' => $today]);
        $overdueCount = (int)$stOverdue->fetchColumn();

        $notesCount = (int)$pdo->query("SELECT COUNT(*) FROM notes WHERE deleted_at IS NULL")->fetchColumn();

        json_response([
            'ok' => true,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
            'summary' => [
                'today' => $today,
                'open_tasks' => $openCount,
                'due_today' => $todayCount,
                'overdue' => $overdueCount,
                'total_notes' => $notesCount,
            ],
        ]);
    }

    if ($action === 'list_stages') {
        $stages = $pdo->query("SELECT id, name, slug, color, sort_order FROM task_stages ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        json_response(['ok' => true, 'stages' => $stages]);
    }

    if ($action === 'list_clients') {
        $search = trim((string)($params['search'] ?? ''));
        $sql = "SELECT id, company_name, category, contact_name, email, phone, status, summary, links_json FROM clients WHERE status = 'active'";
        $bind = [];
        if ($search !== '') {
            $sql .= " AND company_name LIKE :s";
            $bind[':s'] = '%' . $search . '%';
        }
        $sql .= " ORDER BY company_name ASC";
        $st = $pdo->prepare($sql);
        $st->execute($bind);
        $clients = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($clients as &$c) {
            $c['id'] = (int)$c['id'];
            $c['links'] = !empty($c['links_json']) ? (json_decode($c['links_json'], true) ?: []) : [];
            unset($c['links_json']);
        }
        json_response(['ok' => true, 'clients' => $clients]);
    }

    if ($action === 'list_team') {
        $team = $pdo->query("SELECT id, full_name, username, email, role FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($team as &$m) {
            $m['id'] = (int)$m['id'];
        }
        json_response(['ok' => true, 'team' => $team]);
    }

    // ==============================================================
    // 2. TAREFAS (LISTAR, DETALHAR, CRIAR, EDITAR, MOVER, CONCLUIR)
    // ==============================================================
    if ($action === 'list_tasks') {
        $includeCompleted = filter_var($params['include_completed'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $onlyMine = filter_var($params['only_mine'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $assigneeId = !empty($params['assignee_id']) ? (int)$params['assignee_id'] : ($onlyMine ? (int)$user['id'] : 0);
        $clientId = resolve_client_id($pdo, $params);
        $stage = resolve_stage($pdo, $params);
        $priority = trim((string)($params['priority'] ?? ''));
        $dueFilter = trim((string)($params['due_filter'] ?? 'all'));
        $dueDate = trim((string)($params['due_date'] ?? ''));
        $dueFrom = trim((string)($params['due_from'] ?? ''));
        $dueTo = trim((string)($params['due_to'] ?? ''));
        $search = trim((string)($params['search'] ?? ''));
        $limit = min(200, max(1, (int)($params['limit'] ?? 50)));

        $sql = "
            SELECT t.*, s.name AS stage_name, s.slug AS stage_slug, c.company_name AS client_company
            FROM tasks t
            LEFT JOIN task_stages s ON s.id = t.stage_id
            LEFT JOIN clients c ON c.id = t.client_id
            WHERE t.deleted_at IS NULL
              AND t.archived_at IS NULL
        ";
        $bind = [];

        if (!$includeCompleted) {
            $sql .= " AND t.completed_at IS NULL";
        }
        if ($clientId !== null) {
            $sql .= " AND t.client_id = :cid";
            $bind[':cid'] = $clientId;
        }
        if ($stage !== null) {
            $sql .= " AND t.stage_id = :sid";
            $bind[':sid'] = (int)$stage['id'];
        }
        if ($assigneeId > 0) {
            $sql .= " AND EXISTS (SELECT 1 FROM task_assignees ta WHERE ta.task_id = t.id AND ta.user_id = :aid)";
            $bind[':aid'] = $assigneeId;
        }
        if ($priority !== '' && in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $sql .= " AND t.priority = :prio";
            $bind[':prio'] = $priority;
        }

        $today = date('Y-m-d');
        if ($dueDate !== '') {
            $sql .= " AND t.due_date = :dd";
            $bind[':dd'] = $dueDate;
        } elseif ($dueFilter === 'today') {
            $sql .= " AND t.due_date = :dd_today";
            $bind[':dd_today'] = $today;
        } elseif ($dueFilter === 'overdue') {
            $sql .= " AND t.due_date IS NOT NULL AND t.due_date < :dd_over";
            $bind[':dd_over'] = $today;
        } elseif ($dueFilter === 'week') {
            $weekEnd = date('Y-m-d', strtotime('+7 days'));
            $sql .= " AND t.due_date IS NOT NULL AND t.due_date >= :dd_ws AND t.due_date <= :dd_we";
            $bind[':dd_ws'] = $today;
            $bind[':dd_we'] = $weekEnd;
        } elseif ($dueFilter === 'upcoming') {
            $sql .= " AND t.due_date IS NOT NULL AND t.due_date >= :dd_up";
            $bind[':dd_up'] = $today;
        }

        if ($dueFrom !== '') {
            $sql .= " AND t.due_date >= :dfrom";
            $bind[':dfrom'] = $dueFrom;
        }
        if ($dueTo !== '') {
            $sql .= " AND t.due_date <= :dto";
            $bind[':dto'] = $dueTo;
        }
        if ($search !== '') {
            $sql .= " AND (t.title LIKE :q1 OR t.description LIKE :q2 OR c.company_name LIKE :q3)";
            $like = '%' . $search . '%';
            $bind[':q1'] = $like;
            $bind[':q2'] = $like;
            $bind[':q3'] = $like;
        }

        $sql .= " ORDER BY (t.due_date IS NULL) ASC, t.due_date ASC, (t.due_time IS NULL) ASC, t.due_time ASC, t.id DESC LIMIT {$limit}";

        $st = $pdo->prepare($sql);
        $st->execute($bind);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // Carregar responsáveis e resumo de checklist em lote
        $taskIds = array_map(static fn($r) => (int)$r['id'], $rows);
        $assigneesByTask = [];
        $checkByTask = [];
        if (!empty($taskIds)) {
            $in = implode(',', $taskIds);
            $aSt = $pdo->query("SELECT ta.task_id, u.id, u.full_name FROM task_assignees ta JOIN users u ON u.id = ta.user_id WHERE ta.task_id IN ($in)");
            while ($a = $aSt->fetch(PDO::FETCH_ASSOC)) {
                $assigneesByTask[(int)$a['task_id']][] = ['id' => (int)$a['id'], 'full_name' => $a['full_name']];
            }
            $cSt = $pdo->query("SELECT task_id, COUNT(*) AS total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) AS completed FROM task_checklists WHERE task_id IN ($in) GROUP BY task_id");
            while ($c = $cSt->fetch(PDO::FETCH_ASSOC)) {
                $checkByTask[(int)$c['task_id']] = ['total' => (int)$c['total'], 'completed' => (int)$c['completed']];
            }
        }

        $tasks = [];
        foreach ($rows as $r) {
            $tid = (int)$r['id'];
            $tasks[] = format_task_row($r, $assigneesByTask[$tid] ?? [], $checkByTask[$tid] ?? ['total' => 0, 'completed' => 0]);
        }

        json_response(['ok' => true, 'count' => count($tasks), 'tasks' => $tasks]);
    }

    if ($action === 'get_task') {
        $taskId = (int)($params['task_id'] ?? $params['id'] ?? 0);
        if ($taskId <= 0) {
            json_response(['ok' => false, 'error' => 'task_id é obrigatório.'], 422);
        }

        $st = $pdo->prepare("
            SELECT t.*, s.name AS stage_name, s.slug AS stage_slug, c.company_name AS client_company
            FROM tasks t
            LEFT JOIN task_stages s ON s.id = t.stage_id
            LEFT JOIN clients c ON c.id = t.client_id
            WHERE t.id = :id AND t.deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([':id' => $taskId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            json_response(['ok' => false, 'error' => 'Tarefa não encontrada.'], 404);
        }

        $aSt = $pdo->prepare("SELECT u.id, u.full_name FROM task_assignees ta JOIN users u ON u.id = ta.user_id WHERE ta.task_id = :id");
        $aSt->execute([':id' => $taskId]);
        $assignees = array_map(static fn($r) => ['id' => (int)$r['id'], 'full_name' => $r['full_name']], $aSt->fetchAll(PDO::FETCH_ASSOC));

        $cSt = $pdo->prepare("SELECT id, title, is_completed, sort_order FROM task_checklists WHERE task_id = :id ORDER BY sort_order ASC, id ASC");
        $cSt->execute([':id' => $taskId]);
        $subtasks = array_map(static fn($r) => [
            'id' => (int)$r['id'],
            'title' => $r['title'],
            'is_completed' => (bool)$r['is_completed'],
        ], $cSt->fetchAll(PDO::FETCH_ASSOC));

        $mSt = $pdo->prepare("
            SELECT cm.id, cm.comment_text, cm.created_at, u.full_name AS author_name
            FROM task_comments cm
            LEFT JOIN users u ON u.id = cm.user_id
            WHERE cm.task_id = :id
            ORDER BY cm.created_at ASC
        ");
        $mSt->execute([':id' => $taskId]);
        $comments = $mSt->fetchAll(PDO::FETCH_ASSOC);

        $completedSub = count(array_filter($subtasks, static fn($s) => $s['is_completed']));
        $task = format_task_row($row, $assignees, ['total' => count($subtasks), 'completed' => $completedSub]);
        $task['subtasks'] = $subtasks;
        $task['comments'] = $comments;

        // Opcionalmente incluir notas recentes do cliente vinculado
        $clientNotes = [];
        if (!empty($row['client_id']) && filter_var($params['include_client_notes'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            $nSt = $pdo->prepare("
                SELECT n.id, n.title, n.content, n.is_pinned, g.name AS group_name, n.updated_at
                FROM notes n
                LEFT JOIN note_groups g ON g.id = n.group_id
                WHERE n.deleted_at IS NULL AND (n.client_id = :cid1 OR g.client_id = :cid2)
                ORDER BY n.is_pinned DESC, n.updated_at DESC
                LIMIT 10
            ");
            $nSt->execute([':cid1' => (int)$row['client_id'], ':cid2' => (int)$row['client_id']]);
            $clientNotes = $nSt->fetchAll(PDO::FETCH_ASSOC);
        }
        $task['client_notes'] = $clientNotes;

        json_response(['ok' => true, 'task' => $task]);
    }

    if ($action === 'create_task') {
        $title = trim((string)($params['title'] ?? ''));
        if ($title === '') {
            json_response(['ok' => false, 'error' => 'O campo title é obrigatório.'], 422);
        }

        $description = trim((string)($params['description'] ?? ''));
        $clientId = resolve_client_id($pdo, $params);
        $stage = resolve_stage($pdo, $params);
        if (!$stage) {
            $stage = $pdo->query("SELECT id, name, slug FROM task_stages ORDER BY sort_order ASC, id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        $stageId = (int)($stage['id'] ?? 1);
        $status = $stage['slug'] ?? 'todo';

        $priority = trim((string)($params['priority'] ?? 'medium'));
        if (!in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            $priority = 'medium';
        }

        $dueDate = !empty($params['due_date']) ? trim((string)$params['due_date']) : null;
        $dueTime = !empty($params['due_time']) ? trim((string)$params['due_time']) : null;
        $estimatedMinutes = isset($params['estimated_minutes']) && $params['estimated_minutes'] !== '' ? (int)$params['estimated_minutes'] : null;
        $isPrivate = !empty($params['is_private']) ? 1 : 0;
        $isCalendarEvent = isset($params['is_calendar_event']) ? (!empty($params['is_calendar_event']) ? 1 : 0) : ($dueTime !== null ? 1 : 0);

        $tagsRaw = $params['tags'] ?? [];
        if (is_string($tagsRaw)) {
            $tagsArr = array_values(array_filter(array_map('trim', explode(',', $tagsRaw))));
        } elseif (is_array($tagsRaw)) {
            $tagsArr = array_values(array_filter(array_map('trim', $tagsRaw)));
        } else {
            $tagsArr = [];
        }
        $tagsJson = !empty($tagsArr) ? json_encode($tagsArr, JSON_UNESCAPED_UNICODE) : null;

        $ins = $pdo->prepare("
            INSERT INTO tasks (
                client_id, stage_id, created_by, title, description, priority,
                due_date, due_time, estimated_minutes, status, tags,
                source, is_calendar_event, show_in_kanban, is_private,
                record_type, created_at, updated_at
            ) VALUES (
                :cid, :sid, :uid, :title, :desc, :prio,
                :dd, :dt, :est, :status, :tags,
                'agent', :iscal, 1, :ispriv,
                :rtype, :now1, :now2
            )
        ");
        $ins->execute([
            ':cid' => $clientId,
            ':sid' => $stageId,
            ':uid' => (int)$user['id'],
            ':title' => $title,
            ':desc' => $description,
            ':prio' => $priority,
            ':dd' => $dueDate,
            ':dt' => $dueTime,
            ':est' => $estimatedMinutes,
            ':status' => $status,
            ':tags' => $tagsJson,
            ':iscal' => $isCalendarEvent,
            ':ispriv' => $isPrivate,
            ':rtype' => $isCalendarEvent ? 'event' : 'task',
            ':now1' => $now,
            ':now2' => $now,
        ]);
        $taskId = (int)$pdo->lastInsertId();

        // Responsáveis (padrão: usuário autenticado)
        $assigneeIds = $params['assignee_ids'] ?? [(int)$user['id']];
        if (!is_array($assigneeIds) || empty($assigneeIds)) {
            $assigneeIds = [(int)$user['id']];
        }
        $aIns = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id, assigned_at) VALUES (:tid, :uid, :now)");
        foreach (array_unique(array_map('intval', $assigneeIds)) as $aid) {
            if ($aid > 0) {
                $aIns->execute([':tid' => $taskId, ':uid' => $aid, ':now' => $now]);
            }
        }

        // Subtarefas / Checklist opcionais
        $subtasks = $params['subtasks'] ?? $params['checklist'] ?? [];
        if (is_array($subtasks) && !empty($subtasks)) {
            $cIns = $pdo->prepare("INSERT INTO task_checklists (task_id, title, is_completed, sort_order, created_at) VALUES (:tid, :title, 0, :ord, :now)");
            $ord = 0;
            foreach ($subtasks as $stItem) {
                $stTitle = is_array($stItem) ? trim((string)($stItem['title'] ?? '')) : trim((string)$stItem);
                if ($stTitle !== '') {
                    $cIns->execute([':tid' => $taskId, ':title' => $stTitle, ':ord' => $ord++, ':now' => $now]);
                }
            }
        }

        json_response([
            'ok' => true,
            'message' => 'Tarefa criada com sucesso.',
            'task_id' => $taskId,
            'url' => url('/tarefas/' . $taskId),
        ], 201);
    }

    if ($action === 'update_task') {
        $taskId = (int)($params['task_id'] ?? $params['id'] ?? 0);
        if ($taskId <= 0) {
            json_response(['ok' => false, 'error' => 'task_id é obrigatório.'], 422);
        }

        $sets = [];
        $bind = [':id' => $taskId, ':now' => $now];

        if (array_key_exists('title', $params) && trim((string)$params['title']) !== '') {
            $sets[] = 'title = :title';
            $bind[':title'] = trim((string)$params['title']);
        }
        if (array_key_exists('description', $params)) {
            $sets[] = 'description = :desc';
            $bind[':desc'] = (string)$params['description'];
        }
        if (array_key_exists('priority', $params) && in_array($params['priority'], ['low', 'medium', 'high', 'urgent'], true)) {
            $sets[] = 'priority = :prio';
            $bind[':prio'] = $params['priority'];
        }
        if (array_key_exists('due_date', $params)) {
            $sets[] = 'due_date = :dd';
            $bind[':dd'] = $params['due_date'] !== '' && $params['due_date'] !== null ? trim((string)$params['due_date']) : null;
        }
        if (array_key_exists('due_time', $params)) {
            $sets[] = 'due_time = :dt';
            $bind[':dt'] = $params['due_time'] !== '' && $params['due_time'] !== null ? trim((string)$params['due_time']) : null;
        }
        if (array_key_exists('estimated_minutes', $params)) {
            $sets[] = 'estimated_minutes = :est';
            $bind[':est'] = $params['estimated_minutes'] !== '' && $params['estimated_minutes'] !== null ? (int)$params['estimated_minutes'] : null;
        }
        if (array_key_exists('client_id', $params) || array_key_exists('client_name', $params)) {
            $sets[] = 'client_id = :cid';
            $bind[':cid'] = resolve_client_id($pdo, $params);
        }
        if (array_key_exists('stage_id', $params) || array_key_exists('stage_slug', $params) || array_key_exists('stage_name', $params)) {
            $stage = resolve_stage($pdo, $params);
            if ($stage) {
                $sets[] = 'stage_id = :sid';
                $sets[] = 'status = :status';
                $bind[':sid'] = (int)$stage['id'];
                $bind[':status'] = $stage['slug'];
            }
        }
        if (array_key_exists('tags', $params)) {
            $tagsArr = is_array($params['tags']) ? $params['tags'] : array_values(array_filter(array_map('trim', explode(',', (string)$params['tags']))));
            $sets[] = 'tags = :tags';
            $bind[':tags'] = !empty($tagsArr) ? json_encode($tagsArr, JSON_UNESCAPED_UNICODE) : null;
        }
        if (array_key_exists('is_private', $params)) {
            $sets[] = 'is_private = :ispriv';
            $bind[':ispriv'] = !empty($params['is_private']) ? 1 : 0;
        }

        if (empty($sets)) {
            json_response(['ok' => false, 'error' => 'Nenhum campo para atualizar foi informado.'], 422);
        }

        $sets[] = 'updated_at = :now';
        $sql = 'UPDATE tasks SET ' . implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL';
        $st = $pdo->prepare($sql);
        $st->execute($bind);

        json_response(['ok' => true, 'message' => 'Tarefa atualizada com sucesso.', 'task_id' => $taskId]);
    }

    if ($action === 'move_task') {
        $taskId = (int)($params['task_id'] ?? $params['id'] ?? 0);
        $stage = resolve_stage($pdo, $params);
        if ($taskId <= 0 || !$stage) {
            json_response(['ok' => false, 'error' => 'Informe task_id e stage_id ou stage_slug válido.'], 422);
        }

        $isDone = ($stage['slug'] === 'done');
        $st = $pdo->prepare("UPDATE tasks SET stage_id = :sid, status = :slug, completed_at = :comp, updated_at = :now WHERE id = :id AND deleted_at IS NULL");
        $st->execute([
            ':sid' => (int)$stage['id'],
            ':slug' => $stage['slug'],
            ':comp' => $isDone ? $now : null,
            ':now' => $now,
            ':id' => $taskId,
        ]);

        json_response([
            'ok' => true,
            'message' => "Tarefa #{$taskId} movida para {$stage['name']}.",
            'task_id' => $taskId,
            'stage' => $stage,
        ]);
    }

    if ($action === 'complete_task') {
        $taskId = (int)($params['task_id'] ?? $params['id'] ?? 0);
        if ($taskId <= 0) {
            json_response(['ok' => false, 'error' => 'task_id é obrigatório.'], 422);
        }

        $doneStage = $pdo->query("SELECT id, name, slug FROM task_stages WHERE slug = 'done' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $doneStageId = $doneStage ? (int)$doneStage['id'] : null;

        if ($doneStageId) {
            $st = $pdo->prepare("UPDATE tasks SET stage_id = :sid, status = 'done', completed_at = :comp, is_timer_running = 0, timer_started_at = NULL, updated_at = :now WHERE id = :id AND deleted_at IS NULL");
            $st->execute([':sid' => $doneStageId, ':comp' => $now, ':now' => $now, ':id' => $taskId]);
        } else {
            $st = $pdo->prepare("UPDATE tasks SET status = 'done', completed_at = :comp, is_timer_running = 0, timer_started_at = NULL, updated_at = :now WHERE id = :id AND deleted_at IS NULL");
            $st->execute([':comp' => $now, ':now' => $now, ':id' => $taskId]);
        }

        $comment = trim((string)($params['comment'] ?? ''));
        if ($comment !== '') {
            $cSt = $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment_text, created_at) VALUES (:tid, :uid, :txt, :now)");
            $cSt->execute([':tid' => $taskId, ':uid' => (int)$user['id'], ':txt' => $comment, ':now' => $now]);
        }

        json_response(['ok' => true, 'message' => "Tarefa #{$taskId} marcada como concluída.", 'task_id' => $taskId]);
    }

    if ($action === 'add_comment') {
        $taskId = (int)($params['task_id'] ?? $params['id'] ?? 0);
        $content = trim((string)($params['content'] ?? $params['comment'] ?? ''));
        if ($taskId <= 0 || $content === '') {
            json_response(['ok' => false, 'error' => 'task_id e content são obrigatórios.'], 422);
        }

        $st = $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment_text, created_at) VALUES (:tid, :uid, :txt, :now)");
        $st->execute([':tid' => $taskId, ':uid' => (int)$user['id'], ':txt' => $content, ':now' => $now]);

        json_response(['ok' => true, 'message' => 'Comentário adicionado.', 'comment_id' => (int)$pdo->lastInsertId()], 201);
    }

    if ($action === 'add_subtask') {
        $taskId = (int)($params['task_id'] ?? 0);
        $title = trim((string)($params['title'] ?? ''));
        if ($taskId <= 0 || $title === '') {
            json_response(['ok' => false, 'error' => 'task_id e title são obrigatórios.'], 422);
        }
        $ordSt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM task_checklists WHERE task_id = :tid");
        $ordSt->execute([':tid' => $taskId]);
        $nextOrd = (int)$ordSt->fetchColumn();

        $st = $pdo->prepare("INSERT INTO task_checklists (task_id, title, is_completed, sort_order, created_at) VALUES (:tid, :title, 0, :ord, :now)");
        $st->execute([':tid' => $taskId, ':title' => $title, ':ord' => $nextOrd, ':now' => $now]);

        json_response(['ok' => true, 'subtask_id' => (int)$pdo->lastInsertId()], 201);
    }

    if ($action === 'toggle_subtask') {
        $subtaskId = (int)($params['subtask_id'] ?? 0);
        if ($subtaskId <= 0) {
            json_response(['ok' => false, 'error' => 'subtask_id é obrigatório.'], 422);
        }
        $isDone = filter_var($params['is_completed'] ?? $params['is_done'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $st = $pdo->prepare("UPDATE task_checklists SET is_completed = :d WHERE id = :id");
        $st->execute([':d' => $isDone, ':id' => $subtaskId]);
        json_response(['ok' => true, 'subtask_id' => $subtaskId, 'is_completed' => (bool)$isDone]);
    }

    if ($action === 'delete_task') {
        $taskId = (int)($params['task_id'] ?? $params['id'] ?? 0);
        if ($taskId <= 0) {
            json_response(['ok' => false, 'error' => 'task_id é obrigatório.'], 422);
        }
        $st = $pdo->prepare("UPDATE tasks SET deleted_at = :now, updated_at = :now2 WHERE id = :id AND deleted_at IS NULL");
        $st->execute([':now' => $now, ':now2' => $now, ':id' => $taskId]);
        json_response(['ok' => true, 'message' => "Tarefa #{$taskId} movida para a lixeira."]);
    }

    // ==============================================================
    // 3. NOTAS & CONHECIMENTO (GRUPOS E NOTAS)
    // ==============================================================
    if ($action === 'list_note_groups') {
        $groups = $pdo->query("
            SELECT g.id, g.name, g.color, g.client_id, c.company_name AS client_name,
                   (SELECT COUNT(*) FROM notes n WHERE n.group_id = g.id AND n.deleted_at IS NULL) AS notes_count
            FROM note_groups g
            LEFT JOIN clients c ON c.id = g.client_id
            ORDER BY g.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($groups as &$g) {
            $g['id'] = (int)$g['id'];
            $g['client_id'] = $g['client_id'] !== null ? (int)$g['client_id'] : null;
            $g['notes_count'] = (int)$g['notes_count'];
        }
        json_response(['ok' => true, 'groups' => $groups]);
    }

    if ($action === 'create_note_group') {
        $name = trim((string)($params['name'] ?? ''));
        if ($name === '') {
            json_response(['ok' => false, 'error' => 'name é obrigatório.'], 422);
        }
        $clientId = resolve_client_id($pdo, $params);
        $color = trim((string)($params['color'] ?? '#6366f1'));
        $st = $pdo->prepare("INSERT INTO note_groups (name, client_id, color, created_by, created_at, updated_at) VALUES (:n, :cid, :col, :uid, :now1, :now2)");
        $st->execute([':n' => $name, ':cid' => $clientId, ':col' => $color, ':uid' => (int)$user['id'], ':now1' => $now, ':now2' => $now]);
        json_response(['ok' => true, 'group_id' => (int)$pdo->lastInsertId(), 'name' => $name], 201);
    }

    if ($action === 'list_notes') {
        $clientId = resolve_client_id($pdo, $params);
        $groupId = resolve_note_group_id($pdo, $params);
        $search = trim((string)($params['search'] ?? $params['q'] ?? ''));
        $period = trim((string)($params['period'] ?? 'all'));
        $pinnedOnly = filter_var($params['pinned_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $limit = min(100, max(1, (int)($params['limit'] ?? 30)));

        $sql = "
            SELECT n.id, n.title, n.content, n.group_id, n.client_id, n.is_pinned, n.color,
                   n.created_at, n.updated_at,
                   g.name AS group_name,
                   COALESCE(c_direct.company_name, c_group.company_name) AS client_name,
                   COALESCE(n.client_id, g.client_id) AS resolved_client_id,
                   u.full_name AS author_name
            FROM notes n
            LEFT JOIN note_groups g ON g.id = n.group_id
            LEFT JOIN clients c_direct ON c_direct.id = n.client_id
            LEFT JOIN clients c_group ON c_group.id = g.client_id
            LEFT JOIN users u ON u.id = n.created_by
            WHERE n.deleted_at IS NULL
        ";
        $bind = [];

        if ($clientId !== null) {
            $sql .= " AND (n.client_id = :cid1 OR (n.client_id IS NULL AND g.client_id = :cid2))";
            $bind[':cid1'] = $clientId;
            $bind[':cid2'] = $clientId;
        }
        if ($groupId !== null) {
            $sql .= " AND n.group_id = :gid";
            $bind[':gid'] = $groupId;
        }
        if ($pinnedOnly) {
            $sql .= " AND n.is_pinned = 1";
        }
        if ($search !== '') {
            $sql .= " AND (n.title LIKE :s1 OR n.content LIKE :s2)";
            $like = '%' . $search . '%';
            $bind[':s1'] = $like;
            $bind[':s2'] = $like;
        }
        if ($period === 'today') {
            $sql .= " AND n.created_at >= :pt";
            $bind[':pt'] = date('Y-m-d 00:00:00');
        } elseif ($period === 'week') {
            $sql .= " AND n.created_at >= :pw";
            $bind[':pw'] = date('Y-m-d 00:00:00', strtotime('-7 days'));
        } elseif ($period === 'month') {
            $sql .= " AND n.created_at >= :pm";
            $bind[':pm'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
        }

        $sql .= " ORDER BY n.is_pinned DESC, n.updated_at DESC LIMIT {$limit}";

        $st = $pdo->prepare($sql);
        $st->execute($bind);
        $notes = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($notes as &$n) {
            $n['id'] = (int)$n['id'];
            $n['group_id'] = $n['group_id'] !== null ? (int)$n['group_id'] : null;
            $n['client_id'] = $n['resolved_client_id'] !== null ? (int)$n['resolved_client_id'] : null;
            $n['is_pinned'] = (bool)$n['is_pinned'];
            unset($n['resolved_client_id']);
        }

        json_response(['ok' => true, 'count' => count($notes), 'notes' => $notes]);
    }

    if ($action === 'get_note') {
        $noteId = (int)($params['note_id'] ?? $params['id'] ?? 0);
        if ($noteId <= 0) {
            json_response(['ok' => false, 'error' => 'note_id é obrigatório.'], 422);
        }
        $st = $pdo->prepare("
            SELECT n.id, n.title, n.content, n.group_id, n.client_id, n.is_pinned, n.color,
                   n.created_at, n.updated_at,
                   g.name AS group_name,
                   COALESCE(c_direct.company_name, c_group.company_name) AS client_name,
                   u.full_name AS author_name
            FROM notes n
            LEFT JOIN note_groups g ON g.id = n.group_id
            LEFT JOIN clients c_direct ON c_direct.id = n.client_id
            LEFT JOIN clients c_group ON c_group.id = g.client_id
            LEFT JOIN users u ON u.id = n.created_by
            WHERE n.id = :id AND n.deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([':id' => $noteId]);
        $note = $st->fetch(PDO::FETCH_ASSOC);
        if (!$note) {
            json_response(['ok' => false, 'error' => 'Nota não encontrada.'], 404);
        }
        $note['id'] = (int)$note['id'];
        $note['is_pinned'] = (bool)$note['is_pinned'];
        json_response(['ok' => true, 'note' => $note]);
    }

    if ($action === 'create_note') {
        $title = trim((string)($params['title'] ?? ''));
        if ($title === '') {
            json_response(['ok' => false, 'error' => 'O campo title é obrigatório.'], 422);
        }
        $content = trim((string)($params['content'] ?? ''));
        $groupId = resolve_note_group_id($pdo, $params);
        $clientId = resolve_client_id($pdo, $params);
        $isPinned = !empty($params['is_pinned']) ? 1 : 0;
        $color = trim((string)($params['color'] ?? '#6366f1'));

        if ($clientId === null && $groupId !== null) {
            $gSt = $pdo->prepare("SELECT client_id FROM note_groups WHERE id = :gid");
            $gSt->execute([':gid' => $groupId]);
            $inh = $gSt->fetchColumn();
            if ($inh) $clientId = (int)$inh;
        }

        $st = $pdo->prepare("
            INSERT INTO notes (title, content, group_id, client_id, created_by, is_pinned, color, created_at, updated_at)
            VALUES (:t, :c, :gid, :cid, :uid, :pin, :col, :now1, :now2)
        ");
        $st->execute([
            ':t' => $title,
            ':c' => $content,
            ':gid' => $groupId,
            ':cid' => $clientId,
            ':uid' => (int)$user['id'],
            ':pin' => $isPinned,
            ':col' => $color,
            ':now1' => $now,
            ':now2' => $now,
        ]);

        json_response([
            'ok' => true,
            'message' => 'Nota criada com sucesso.',
            'note_id' => (int)$pdo->lastInsertId(),
        ], 201);
    }

    if ($action === 'update_note') {
        $noteId = (int)($params['note_id'] ?? $params['id'] ?? 0);
        if ($noteId <= 0) {
            json_response(['ok' => false, 'error' => 'note_id é obrigatório.'], 422);
        }

        $sets = [];
        $bind = [':id' => $noteId, ':now' => $now];

        if (array_key_exists('title', $params) && trim((string)$params['title']) !== '') {
            $sets[] = 'title = :t';
            $bind[':t'] = trim((string)$params['title']);
        }
        if (array_key_exists('append_content', $params) && trim((string)$params['append_content']) !== '') {
            $curSt = $pdo->prepare("SELECT content FROM notes WHERE id = :id AND deleted_at IS NULL");
            $curSt->execute([':id' => $noteId]);
            $curContent = (string)$curSt->fetchColumn();
            $sets[] = 'content = :c';
            $bind[':c'] = trim($curContent . "\n\n" . trim((string)$params['append_content']));
        } elseif (array_key_exists('content', $params)) {
            $sets[] = 'content = :c';
            $bind[':c'] = (string)$params['content'];
        }
        if (array_key_exists('client_id', $params) || array_key_exists('client_name', $params)) {
            $sets[] = 'client_id = :cid';
            $bind[':cid'] = resolve_client_id($pdo, $params);
        }
        if (array_key_exists('group_id', $params) || array_key_exists('group_name', $params)) {
            $sets[] = 'group_id = :gid';
            $bind[':gid'] = resolve_note_group_id($pdo, $params);
        }
        if (array_key_exists('is_pinned', $params)) {
            $sets[] = 'is_pinned = :pin';
            $bind[':pin'] = !empty($params['is_pinned']) ? 1 : 0;
        }

        if (empty($sets)) {
            json_response(['ok' => false, 'error' => 'Nenhum campo para atualizar foi informado.'], 422);
        }

        $sets[] = 'updated_at = :now';
        $st = $pdo->prepare('UPDATE notes SET ' . implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL');
        $st->execute($bind);

        json_response(['ok' => true, 'message' => 'Nota atualizada com sucesso.', 'note_id' => $noteId]);
    }

    if ($action === 'delete_note') {
        $noteId = (int)($params['note_id'] ?? $params['id'] ?? 0);
        if ($noteId <= 0) {
            json_response(['ok' => false, 'error' => 'note_id é obrigatório.'], 422);
        }
        $st = $pdo->prepare("UPDATE notes SET deleted_at = :now, updated_at = :now2 WHERE id = :id AND deleted_at IS NULL");
        $st->execute([':now' => $now, ':now2' => $now, ':id' => $noteId]);
        json_response(['ok' => true, 'message' => "Nota #{$noteId} excluída."]);
    }

    json_response(['ok' => false, 'error' => "Ação não reconhecida: '{$action}'."], 400);
} catch (Throwable $e) {
    error_log('[KanbanDoo Agent API] ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'Erro interno na API do agente: ' . $e->getMessage()], 500);
}
