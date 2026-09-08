<?php
/**
 * API REST de Tarefas do KanbanDoo
 */
require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['error' => 'Não autorizado.'], 401);
}

$pdo = get_pdo();
$user = current_user();
$method = $_SERVER['REQUEST_METHOD'];

// -------------------------------------------------------------
// Helpers internos
// -------------------------------------------------------------

/** Estágio marcado como conclusão do fluxo. */
function done_stage(PDO $pdo): ?array
{
    static $stage = null;
    if ($stage === null) {
        $stage = $pdo->query("SELECT id, name, slug FROM task_stages WHERE slug IN ('done', 'concluido') ORDER BY sort_order DESC LIMIT 1")->fetch() ?: false;
    }
    return $stage ?: null;
}

function done_stage_id(PDO $pdo): ?int
{
    $stage = done_stage($pdo);
    return $stage ? (int)$stage['id'] : null;
}

function touch_task(PDO $pdo, int $taskId): void
{
    $pdo->prepare("UPDATE tasks SET updated_at = :now WHERE id = :id")
        ->execute([':now' => now(), ':id' => $taskId]);
}

/**
 * Reescreve o sort_order de uma coluna inteira, em passos de 10.
 * Sem isso, dois cards podem terminar com a mesma posição e o quadro
 * "embaralha" ao recarregar.
 */
function normalize_stage_order(PDO $pdo, int $stageId, array $orderedIds = []): void
{
    $ids = array_values(array_unique(array_map('intval', $orderedIds)));

    $existing = $pdo->prepare("
        SELECT id, is_bomb, in_focus FROM tasks
        WHERE stage_id = :stage AND deleted_at IS NULL AND archived_at IS NULL
        ORDER BY is_bomb DESC, in_focus DESC, sort_order ASC, id DESC
    ");
    $existing->execute([':stage' => $stageId]);
    $rows = $existing->fetchAll() ?: [];
    $current = array_map(fn($r) => (int)$r['id'], $rows);

    $rank = [];
    foreach ($rows as $r) {
        // Bomba na frente, depois foco, depois o resto
        $rank[(int)$r['id']] = ((int)$r['is_bomb'] === 1 ? 0 : ((int)$r['in_focus'] === 1 ? 1 : 2));
    }

    // Mantém a ordem enviada pelo cliente e acrescenta o que ficou de fora.
    $final = array_values(array_filter($ids, fn($id) => in_array($id, $current, true)));
    foreach ($current as $id) {
        if (!in_array($id, $final, true)) {
            $final[] = $id;
        }
    }

    // A posição gravada respeita a prioridade de bomba/foco, para o dado
    // ficar coerente com o que a listagem mostra.
    $position = array_flip($final);
    usort($final, fn($a, $b) => [$rank[$a], $position[$a]] <=> [$rank[$b], $position[$b]]);

    $upd = $pdo->prepare("UPDATE tasks SET sort_order = :pos WHERE id = :id");
    foreach ($final as $index => $id) {
        $upd->execute([':pos' => ($index + 1) * 10, ':id' => $id]);
    }
}

/** Segundos efetivos considerando um cronômetro em execução. */
function effective_seconds(array $task): int
{
    $total = (int)($task['total_seconds'] ?? 0);
    if ((int)($task['is_timer_running'] ?? 0) === 1 && !empty($task['timer_started_at'])) {
        $startedAt = strtotime($task['timer_started_at']);
        if ($startedAt > 0) {
            $total += max(0, time() - $startedAt);
        }
    }
    return $total;
}

/** Todas as etiquetas em uso, para sugestões e para o filtro. */
function all_tags(PDO $pdo): array
{
    $rows = $pdo->query("SELECT tags FROM tasks WHERE deleted_at IS NULL AND tags IS NOT NULL AND tags != '[]'")
        ->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $counts = [];
    foreach ($rows as $raw) {
        foreach (parse_tags($raw) as $tag) {
            $key = mb_strtolower($tag, 'UTF-8');
            if (!isset($counts[$key])) {
                $counts[$key] = ['name' => $tag, 'color' => tag_color_index($tag), 'count' => 0];
            }
            $counts[$key]['count']++;
        }
    }

    usort($counts, fn($a, $b) => $b['count'] <=> $a['count'] ?: strcasecmp($a['name'], $b['name']));
    return array_values($counts);
}

/** Contadores do topo do quadro, recalculados a cada carregamento. */
function board_metrics(PDO $pdo): array
{
    $today = date('Y-m-d');
    $base = "FROM tasks WHERE deleted_at IS NULL AND archived_at IS NULL AND completed_at IS NULL";

    $total = (int)$pdo->query("SELECT COUNT(*) $base")->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) $base AND due_date = :d");
    $stmt->execute([':d' => $today]);
    $todayDue = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) $base AND due_date < :d");
    $stmt->execute([':d' => $today]);
    $overdue = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE deleted_at IS NULL AND completed_at >= :start");
    $stmt->execute([':start' => $today . ' 00:00:00']);
    $doneToday = (int)$stmt->fetchColumn();

    return [
        'total_open' => $total,
        'due_today' => $todayDue,
        'overdue' => $overdue,
        'done_today' => $doneToday,
    ];
}

try {
    // -------------------------------------------------------------
    // GET: Listagem ou Detalhes de Tarefa
    // -------------------------------------------------------------
    if ($method === 'GET') {
        $taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($taskId > 0) {
            $stmt = $pdo->prepare("
                SELECT t.*,
                       c.company_name AS client_company, c.contact_name AS client_contact,
                       c.email AS client_email, c.phone AS client_phone, c.notes AS client_notes,
                       c.links_json AS client_links_json, c.summary AS client_summary,
                       s.name AS stage_name, s.color AS stage_color, s.slug AS stage_slug,
                       u.full_name AS creator_name
                FROM tasks t
                LEFT JOIN clients c ON t.client_id = c.id
                LEFT JOIN task_stages s ON t.stage_id = s.id
                LEFT JOIN users u ON t.created_by = u.id
                WHERE t.id = :id
            ");
            $stmt->execute([':id' => $taskId]);
            $task = $stmt->fetch();

            if (!$task) {
                json_response(['error' => 'Tarefa não encontrada.'], 404);
            }

            $task['effective_seconds'] = effective_seconds($task);
            $task['is_running'] = (int)($task['is_timer_running'] ?? 0) === 1;
            $task['priority_info'] = priority_info($task['priority']);
            $task['due_badge'] = format_due_date_badge($task['due_date']);
            $task['client_color'] = client_color_index($task['client_id'] ? (int)$task['client_id'] : null);
            $task['tags'] = array_map(
                fn($tag) => ['name' => $tag, 'color' => tag_color_index($tag)],
                parse_tags($task['tags'])
            );

            $assigneesStmt = $pdo->prepare("
                SELECT u.id, u.full_name, u.email, u.avatar_path
                FROM task_assignees ta
                JOIN users u ON ta.user_id = u.id
                WHERE ta.task_id = :id
                ORDER BY u.full_name ASC
            ");
            $assigneesStmt->execute([':id' => $taskId]);
            $task['assignees'] = $assigneesStmt->fetchAll();

            $checklistStmt = $pdo->prepare("
                SELECT id, title, is_completed, sort_order
                FROM task_checklists
                WHERE task_id = :id
                ORDER BY sort_order ASC, id ASC
            ");
            $checklistStmt->execute([':id' => $taskId]);
            $task['checklists'] = $checklistStmt->fetchAll();

            $commentsStmt = $pdo->prepare("
                SELECT tc.id, tc.comment_text, tc.created_at, u.full_name AS user_name, u.avatar_path
                FROM task_comments tc
                JOIN users u ON tc.user_id = u.id
                WHERE tc.task_id = :id
                ORDER BY tc.created_at ASC
            ");
            $commentsStmt->execute([':id' => $taskId]);
            $task['comments'] = $commentsStmt->fetchAll();

            $summariesStmt = $pdo->prepare("
                SELECT ts.id, ts.summary_text, ts.created_at, u.full_name AS user_name, u.avatar_path
                FROM task_summaries ts
                JOIN users u ON ts.user_id = u.id
                WHERE ts.task_id = :id
                ORDER BY ts.created_at DESC
            ");
            $summariesStmt->execute([':id' => $taskId]);
            $task['summaries'] = $summariesStmt->fetchAll();

            if (!empty($task['client_id'])) {
                $clientTasksStmt = $pdo->prepare("
                    SELECT t.id, t.title, t.completed_at, t.due_date, s.name AS stage_name, s.slug AS stage_slug
                    FROM tasks t
                    LEFT JOIN task_stages s ON t.stage_id = s.id
                    WHERE t.client_id = :cid AND t.id != :tid AND t.deleted_at IS NULL
                    ORDER BY t.id DESC
                    LIMIT 10
                ");
                $clientTasksStmt->execute([':cid' => $task['client_id'], ':tid' => $taskId]);
                $task['client_recent_tasks'] = $clientTasksStmt->fetchAll();
            } else {
                $task['client_recent_tasks'] = [];
            }

            json_response(['success' => true, 'task' => $task]);
        }

        // ---------------------------------------------------------
        // Listagem do quadro
        // ---------------------------------------------------------
        $clientId   = !empty($_GET['client_id']) ? (int)$_GET['client_id'] : null;
        $priority   = !empty($_GET['priority']) ? $_GET['priority'] : null;
        $assigneeId = !empty($_GET['assignee_id']) ? (int)$_GET['assignee_id'] : null;
        $search     = !empty($_GET['q']) ? trim($_GET['q']) : null;
        $tagFilter  = !empty($_GET['tag']) ? trim($_GET['tag']) : null;
        $onlyMine   = !empty($_GET['mine']);
        $showArchived = !empty($_GET['archived']);

        if ($onlyMine) {
            $assigneeId = (int)$user['id'];
        }

        $query = "
            SELECT t.id, t.client_id, t.stage_id, t.title, t.description, t.priority, t.due_date, t.sort_order,
                   t.is_timer_running, t.timer_started_at, t.total_seconds, t.in_focus, t.is_bomb, t.tags,
                   t.source, t.group_name, t.mentioned_by, t.whatsapp_url, t.completed_at, t.created_at,
                   c.company_name AS client_company,
                   s.slug AS stage_slug,
                   (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id) AS checklist_total,
                   (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id AND is_completed = 1) AS checklist_done,
                   (SELECT COUNT(*) FROM task_comments WHERE task_id = t.id) AS comments_count
            FROM tasks t
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN task_stages s ON t.stage_id = s.id
            WHERE t.deleted_at IS NULL
        ";
        $params = [];

        if (!$showArchived) {
            $query .= " AND t.archived_at IS NULL";
        }

        // Tarefas concluídas há mais de X dias saem do quadro (sem serem apagadas).
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . DONE_VISIBLE_DAYS . ' days'));
        if (!$showArchived) {
            $query .= " AND (t.completed_at IS NULL OR t.completed_at >= :cutoff)";
            $params[':cutoff'] = $cutoff;
        }

        if ($clientId) {
            $query .= " AND t.client_id = :client_id";
            $params[':client_id'] = $clientId;
        }
        if ($priority) {
            $query .= " AND t.priority = :priority";
            $params[':priority'] = $priority;
        }
        if ($assigneeId) {
            $query .= " AND EXISTS (SELECT 1 FROM task_assignees WHERE task_id = t.id AND user_id = :assignee_id)";
            $params[':assignee_id'] = $assigneeId;
        }
        if ($search) {
            $query .= " AND (t.title LIKE :search OR t.description LIKE :search OR c.company_name LIKE :search OR t.tags LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        if ($tagFilter) {
            // As etiquetas ficam num JSON; a comparação exata é refeita em PHP logo abaixo.
            $query .= " AND t.tags LIKE :tag";
            $params[':tag'] = '%' . $tagFilter . '%';
        }

        // Tarefa bomba sempre no topo da coluna, depois a lista de foco
        $query .= " ORDER BY t.is_bomb DESC, t.in_focus DESC, t.sort_order ASC, t.id DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        if (!empty($tasks)) {
            $taskIds = array_column($tasks, 'id');
            $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
            $assigneesStmt = $pdo->prepare("
                SELECT ta.task_id, u.id AS user_id, u.full_name, u.avatar_path
                FROM task_assignees ta
                JOIN users u ON ta.user_id = u.id
                WHERE ta.task_id IN ($placeholders)
                ORDER BY u.full_name ASC
            ");
            $assigneesStmt->execute($taskIds);

            $assigneesGrouped = [];
            foreach ($assigneesStmt->fetchAll() as $row) {
                $assigneesGrouped[$row['task_id']][] = [
                    'id' => (int)$row['user_id'],
                    'name' => $row['full_name'],
                    'avatar_path' => $row['avatar_path'] ?? null,
                    'initial' => mb_strtoupper(mb_substr($row['full_name'], 0, 1, 'UTF-8')),
                ];
            }

            foreach ($tasks as &$t) {
                $t['assignees'] = $assigneesGrouped[$t['id']] ?? [];
                $t['due_badge'] = format_due_date_badge($t['due_date']);
                $t['priority_info'] = priority_info($t['priority']);
                $t['effective_seconds'] = effective_seconds($t);
                $t['client_color'] = client_color_index($t['client_id'] ? (int)$t['client_id'] : null);
                $t['is_mine'] = (bool)array_filter($t['assignees'], fn($a) => $a['id'] === (int)$user['id']);

                $t['tags'] = array_map(
                    fn($tag) => ['name' => $tag, 'color' => tag_color_index($tag)],
                    parse_tags($t['tags'])
                );
            }
            unset($t);

            // O LIKE acima é só um pré-filtro: aqui a etiqueta precisa bater por inteiro.
            if ($tagFilter) {
                $needle = mb_strtolower($tagFilter, 'UTF-8');
                $tasks = array_values(array_filter($tasks, function ($t) use ($needle) {
                    foreach ($t['tags'] as $tag) {
                        if (mb_strtolower($tag['name'], 'UTF-8') === $needle) {
                            return true;
                        }
                    }
                    return false;
                }));
            }
        }

        $stages = $pdo->query("
            SELECT id, name, slug, color, sort_order, wip_limit
            FROM task_stages ORDER BY sort_order ASC
        ")->fetchAll();

        $doneId = done_stage_id($pdo);
        foreach ($stages as &$s) {
            $s['id'] = (int)$s['id'];
            $s['wip_limit'] = $s['wip_limit'] !== null ? (int)$s['wip_limit'] : null;
            $s['is_done'] = $doneId !== null && (int)$s['id'] === $doneId;
        }
        unset($s);

        // Quantas tarefas concluídas estão fora da janela visível
        $hiddenStmt = $pdo->prepare("
            SELECT COUNT(*) FROM tasks
            WHERE deleted_at IS NULL AND completed_at IS NOT NULL
              AND (completed_at < :cutoff OR archived_at IS NOT NULL)
        ");
        $hiddenStmt->execute([':cutoff' => $cutoff]);

        json_response([
            'success' => true,
            'stages' => $stages,
            'tasks' => $tasks,
            'all_tags' => all_tags($pdo),
            'metrics' => board_metrics($pdo),
            'done_hidden_count' => (int)$hiddenStmt->fetchColumn(),
            'done_visible_days' => DONE_VISIBLE_DAYS,
            'current_user_id' => (int)$user['id'],
            'server_time' => now(),
        ]);
    }

    // -------------------------------------------------------------
    // POST: Ações
    // -------------------------------------------------------------
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = $input['action'] ?? 'save';

        // 1. Alternar Timer (Play / Pause) — só um cronômetro roda por vez
        if ($action === 'toggle_timer') {
            $taskId = (int)($input['task_id'] ?? 0);
            if ($taskId <= 0) {
                json_response(['error' => 'Tarefa inválida.'], 400);
            }

            $stmt = $pdo->prepare("SELECT is_timer_running, timer_started_at, total_seconds FROM tasks WHERE id = :id");
            $stmt->execute([':id' => $taskId]);
            $task = $stmt->fetch();

            if (!$task) {
                json_response(['error' => 'Tarefa não encontrada.'], 404);
            }

            $isRunning = (int)($task['is_timer_running'] ?? 0) === 1;
            $totalSecs = (int)($task['total_seconds'] ?? 0);

            if ($isRunning) {
                $startedAt = !empty($task['timer_started_at']) ? strtotime($task['timer_started_at']) : time();
                $newTotal = $totalSecs + max(0, time() - $startedAt);

                $pdo->prepare("UPDATE tasks SET is_timer_running = 0, timer_started_at = NULL, total_seconds = :sec, updated_at = :now WHERE id = :id")
                    ->execute([':sec' => $newTotal, ':now' => now(), ':id' => $taskId]);

                json_response(['success' => true, 'is_running' => false, 'total_seconds' => $newTotal]);
            }

            $pdo->beginTransaction();

            // Pausa qualquer outro cronômetro em execução para não somar tempo duplicado.
            $running = $pdo->query("SELECT id, timer_started_at, total_seconds FROM tasks WHERE is_timer_running = 1")->fetchAll();
            $paused = [];
            foreach ($running as $r) {
                $startedAt = !empty($r['timer_started_at']) ? strtotime($r['timer_started_at']) : time();
                $acc = (int)$r['total_seconds'] + max(0, time() - $startedAt);
                $pdo->prepare("UPDATE tasks SET is_timer_running = 0, timer_started_at = NULL, total_seconds = :s WHERE id = :id")
                    ->execute([':s' => $acc, ':id' => (int)$r['id']]);
                $paused[] = (int)$r['id'];
            }

            $nowStr = now();
            $pdo->prepare("UPDATE tasks SET is_timer_running = 1, timer_started_at = :now, updated_at = :now2 WHERE id = :id")
                ->execute([':now' => $nowStr, ':now2' => $nowStr, ':id' => $taskId]);

            $pdo->commit();

            json_response([
                'success' => true,
                'is_running' => true,
                'total_seconds' => $totalSecs,
                'timer_started_at' => $nowStr,
                'paused_tasks' => $paused,
            ]);
        }

        // 2. Alternar Lista de Foco
        if ($action === 'toggle_focus') {
            $taskId = (int)($input['task_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT in_focus FROM tasks WHERE id = :id");
            $stmt->execute([':id' => $taskId]);
            $newVal = (int)$stmt->fetchColumn() === 1 ? 0 : 1;

            $pdo->prepare("UPDATE tasks SET in_focus = :val, updated_at = :now WHERE id = :id")
                ->execute([':val' => $newVal, ':now' => now(), ':id' => $taskId]);

            json_response(['success' => true, 'in_focus' => $newVal]);
        }

        // 3. Alternar Tarefa Bomba
        if ($action === 'toggle_bomb') {
            $taskId = (int)($input['task_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT is_bomb FROM tasks WHERE id = :id");
            $stmt->execute([':id' => $taskId]);
            $newVal = (int)$stmt->fetchColumn() === 1 ? 0 : 1;

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE tasks SET is_bomb = :val, updated_at = :now WHERE id = :id")
                ->execute([':val' => $newVal, ':now' => now(), ':id' => $taskId]);

            // Sobe (ou devolve) o card na coluna imediatamente
            $stageStmt = $pdo->prepare("SELECT stage_id FROM tasks WHERE id = :id");
            $stageStmt->execute([':id' => $taskId]);
            normalize_stage_order($pdo, (int)$stageStmt->fetchColumn());
            $pdo->commit();

            json_response(['success' => true, 'is_bomb' => $newVal]);
        }

        // 4. Concluir / Reabrir (com dados suficientes para o "Desfazer")
        if ($action === 'complete_task' || $action === 'uncomplete_task') {
            $taskId = (int)($input['task_id'] ?? 0);
            if ($taskId <= 0) {
                json_response(['error' => 'Tarefa inválida.'], 400);
            }

            $stmt = $pdo->prepare("SELECT stage_id, sort_order, completed_at FROM tasks WHERE id = :id");
            $stmt->execute([':id' => $taskId]);
            $prev = $stmt->fetch();
            if (!$prev) {
                json_response(['error' => 'Tarefa não encontrada.'], 404);
            }

            if ($action === 'complete_task') {
                $doneId = done_stage_id($pdo);
                if (!$doneId) {
                    json_response(['error' => 'Nenhuma coluna de conclusão configurada.'], 400);
                }

                $pdo->beginTransaction();
                $pdo->prepare("UPDATE tasks SET stage_id = :stg, completed_at = :now, is_timer_running = 0, updated_at = :now2 WHERE id = :id")
                    ->execute([':stg' => $doneId, ':now' => now(), ':now2' => now(), ':id' => $taskId]);
                normalize_stage_order($pdo, $doneId);
                normalize_stage_order($pdo, (int)$prev['stage_id']);
                $pdo->commit();

                json_response([
                    'success' => true,
                    'undo' => ['action' => 'restore_stage', 'task_id' => $taskId, 'stage_id' => (int)$prev['stage_id']],
                ]);
            }

            $pdo->prepare("UPDATE tasks SET completed_at = NULL, archived_at = NULL, updated_at = :now WHERE id = :id")
                ->execute([':now' => now(), ':id' => $taskId]);
            json_response(['success' => true]);
        }

        // 5. Devolver tarefa para uma coluna anterior (usado pelo Desfazer)
        if ($action === 'restore_stage') {
            $taskId = (int)($input['task_id'] ?? 0);
            $stageId = (int)($input['stage_id'] ?? 0);
            if ($taskId <= 0 || $stageId <= 0) {
                json_response(['error' => 'Dados inválidos.'], 400);
            }

            $doneId = done_stage_id($pdo);
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE tasks SET stage_id = :stg, completed_at = NULL, archived_at = NULL, updated_at = :now WHERE id = :id")
                ->execute([':stg' => $stageId, ':now' => now(), ':id' => $taskId]);
            normalize_stage_order($pdo, $stageId);
            if ($doneId) {
                normalize_stage_order($pdo, $doneId);
            }
            $pdo->commit();

            json_response(['success' => true]);
        }

        // 6. Duplicar Tarefa (cópia completa)
        if ($action === 'duplicate_task') {
            $taskId = (int)($input['task_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
            $stmt->execute([':id' => $taskId]);
            $orig = $stmt->fetch();

            if (!$orig) {
                json_response(['error' => 'Tarefa original não encontrada.'], 404);
            }

            $pdo->beginTransaction();

            $ins = $pdo->prepare("
                INSERT INTO tasks (client_id, stage_id, created_by, title, description, priority, due_date,
                                   estimated_minutes, tags, source, group_name, mentioned_by, whatsapp_url,
                                   in_focus, is_bomb, status, sort_order, created_at, updated_at)
                VALUES (:client_id, :stage_id, :created_by, :title, :description, :priority, :due_date,
                        :estimated_minutes, :tags, :source, :group_name, :mentioned_by, :whatsapp_url,
                        :in_focus, :is_bomb, 'open', :sort_order, :now, :now2)
            ");
            $ins->execute([
                ':client_id' => $orig['client_id'],
                ':stage_id' => $orig['stage_id'],
                ':created_by' => $user['id'],
                ':title' => $orig['title'] . ' (Cópia)',
                ':description' => $orig['description'],
                ':priority' => $orig['priority'],
                ':due_date' => $orig['due_date'],
                ':estimated_minutes' => $orig['estimated_minutes'] ?? null,
                ':tags' => $orig['tags'] ?? '[]',
                ':source' => $orig['source'] ?? 'manual',
                ':group_name' => $orig['group_name'] ?? null,
                ':mentioned_by' => $orig['mentioned_by'] ?? null,
                ':whatsapp_url' => $orig['whatsapp_url'] ?? null,
                ':in_focus' => (int)($orig['in_focus'] ?? 0),
                ':is_bomb' => (int)($orig['is_bomb'] ?? 0),
                ':sort_order' => (int)($orig['sort_order'] ?? 0) + 1,
                ':now' => now(),
                ':now2' => now(),
            ]);
            $newTaskId = (int)$pdo->lastInsertId();

            $chkOrig = $pdo->prepare("SELECT title, sort_order FROM task_checklists WHERE task_id = :id ORDER BY sort_order ASC, id ASC");
            $chkOrig->execute([':id' => $taskId]);
            $insChk = $pdo->prepare("INSERT INTO task_checklists (task_id, title, is_completed, sort_order) VALUES (:tid, :t, 0, :s)");
            foreach ($chkOrig->fetchAll() as $chk) {
                $insChk->execute([':tid' => $newTaskId, ':t' => $chk['title'], ':s' => $chk['sort_order']]);
            }

            $assOrig = $pdo->prepare("SELECT user_id FROM task_assignees WHERE task_id = :id");
            $assOrig->execute([':id' => $taskId]);
            $insAss = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id) VALUES (:tid, :uid)");
            foreach ($assOrig->fetchAll() as $ass) {
                $insAss->execute([':tid' => $newTaskId, ':uid' => $ass['user_id']]);
            }

            normalize_stage_order($pdo, (int)$orig['stage_id']);
            $pdo->commit();

            json_response([
                'success' => true,
                'new_task_id' => $newTaskId,
                'undo' => ['action' => 'delete', 'task_id' => $newTaskId],
            ]);
        }

        // 7. Mover Card (drag & drop e atalhos de teclado)
        if ($action === 'move') {
            $taskId  = (int)($input['task_id'] ?? 0);
            $stageId = (int)($input['stage_id'] ?? 0);
            $orderedIds = isset($input['ordered_ids']) && is_array($input['ordered_ids']) ? $input['ordered_ids'] : [];

            if ($taskId <= 0 || $stageId <= 0) {
                json_response(['error' => 'Dados inválidos para movimentação.'], 400);
            }

            $stmt = $pdo->prepare("SELECT stage_id, completed_at, is_bomb, title FROM tasks WHERE id = :id");
            $stmt->execute([':id' => $taskId]);
            $prev = $stmt->fetch();
            if (!$prev) {
                json_response(['error' => 'Tarefa não encontrada.'], 404);
            }

            $fromStage = (int)$prev['stage_id'];

            // Tarefa bomba não muda de coluna: ela precisa ser resolvida onde está.
            // Reordenar dentro da mesma coluna continua permitido.
            if ((int)$prev['is_bomb'] === 1 && $stageId !== $fromStage) {
                json_response([
                    'error' => 'Tarefa bomba não pode mudar de coluna. Resolva a tarefa ou desmarque a bomba antes de movê-la.',
                    'code' => 'bomb_locked',
                ], 409);
            }
            $doneId = done_stage_id($pdo);

            $pdo->beginTransaction();

            // Entrar na coluna de conclusão fecha a tarefa; sair reabre.
            if ($doneId !== null && $stageId === $doneId && empty($prev['completed_at'])) {
                $pdo->prepare("UPDATE tasks SET stage_id = :stg, completed_at = :now, is_timer_running = 0, updated_at = :now2 WHERE id = :id")
                    ->execute([':stg' => $stageId, ':now' => now(), ':now2' => now(), ':id' => $taskId]);
            } elseif ($doneId !== null && $stageId !== $doneId && !empty($prev['completed_at'])) {
                $pdo->prepare("UPDATE tasks SET stage_id = :stg, completed_at = NULL, archived_at = NULL, updated_at = :now WHERE id = :id")
                    ->execute([':stg' => $stageId, ':now' => now(), ':id' => $taskId]);
            } else {
                $pdo->prepare("UPDATE tasks SET stage_id = :stg, updated_at = :now WHERE id = :id")
                    ->execute([':stg' => $stageId, ':now' => now(), ':id' => $taskId]);
            }

            normalize_stage_order($pdo, $stageId, $orderedIds);
            if ($fromStage !== $stageId) {
                normalize_stage_order($pdo, $fromStage);
            }

            $pdo->commit();

            json_response([
                'success' => true,
                'undo' => ['action' => 'restore_stage', 'task_id' => $taskId, 'stage_id' => $fromStage],
                'from_stage_id' => $fromStage,
            ]);
        }

        // 8. Salvar / Atualizar Tarefa
        if ($action === 'save') {
            $taskId = !empty($input['id']) ? (int)$input['id'] : 0;
            $title = trim((string)($input['title'] ?? ''));
            $clientId = !empty($input['client_id']) ? (int)$input['client_id'] : null;
            $stageId = !empty($input['stage_id']) ? (int)$input['stage_id'] : 1;
            $priority = in_array($input['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $input['priority'] : 'medium';
            $dueDate = !empty($input['due_date']) ? $input['due_date'] : null;
            $description = trim((string)($input['description'] ?? ''));
            $assignees = isset($input['assignees']) && is_array($input['assignees']) ? $input['assignees'] : [];
            $tags = normalize_tags(is_array($input['tags'] ?? null) ? $input['tags'] : parse_tags($input['tags'] ?? []));
            $tagsJson = json_encode($tags, JSON_UNESCAPED_UNICODE);

            if ($title === '') {
                json_response(['error' => 'Informe um título para a tarefa.', 'field' => 'title'], 400);
            }
            if (mb_strlen($title) > 200) {
                json_response(['error' => 'O título deve ter no máximo 200 caracteres.', 'field' => 'title'], 400);
            }
            if ($dueDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                json_response(['error' => 'Data de entrega inválida.', 'field' => 'due_date'], 400);
            }

            $source = trim((string)($input['source'] ?? 'manual'));
            $groupName = !empty($input['group_name']) ? trim((string)$input['group_name']) : null;
            $mentionedBy = !empty($input['mentioned_by']) ? trim((string)$input['mentioned_by']) : null;
            $whatsappUrl = !empty($input['whatsapp_url']) ? trim((string)$input['whatsapp_url']) : null;

            // Só aceita links de esquema seguro para não gerar um href quebrado no card.
            if ($whatsappUrl !== null && !preg_match('#^https?://#i', $whatsappUrl)) {
                $whatsappUrl = null;
            }

            $pdo->beginTransaction();
            $isNew = $taskId <= 0;

            if (!$isNew) {
                $stmt = $pdo->prepare("
                    UPDATE tasks
                    SET title = :title, client_id = :client_id, stage_id = :stage_id,
                        priority = :priority, due_date = :due_date, description = :description,
                        source = :source, group_name = :group_name, mentioned_by = :mentioned_by,
                        whatsapp_url = :whatsapp_url, tags = :tags, updated_at = :now
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':title' => $title,
                    ':client_id' => $clientId,
                    ':stage_id' => $stageId,
                    ':priority' => $priority,
                    ':due_date' => $dueDate,
                    ':description' => $description,
                    ':source' => $source,
                    ':group_name' => $groupName,
                    ':mentioned_by' => $mentionedBy,
                    ':whatsapp_url' => $whatsappUrl,
                    ':tags' => $tagsJson,
                    ':now' => now(),
                    ':id' => $taskId,
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO tasks (title, client_id, stage_id, priority, due_date, description, created_by,
                                       status, source, group_name, mentioned_by, whatsapp_url, tags, created_at, updated_at)
                    VALUES (:title, :client_id, :stage_id, :priority, :due_date, :description, :created_by,
                            'open', :source, :group_name, :mentioned_by, :whatsapp_url, :tags, :now, :now2)
                ");
                $stmt->execute([
                    ':title' => $title,
                    ':client_id' => $clientId,
                    ':stage_id' => $stageId,
                    ':priority' => $priority,
                    ':due_date' => $dueDate,
                    ':description' => $description,
                    ':created_by' => $user['id'],
                    ':source' => $source,
                    ':group_name' => $groupName,
                    ':mentioned_by' => $mentionedBy,
                    ':whatsapp_url' => $whatsappUrl,
                    ':tags' => $tagsJson,
                    ':now' => now(),
                    ':now2' => now(),
                ]);
                $taskId = (int)$pdo->lastInsertId();
            }

            $pdo->prepare("DELETE FROM task_assignees WHERE task_id = :task_id")->execute([':task_id' => $taskId]);
            if (!empty($assignees)) {
                $assignStmt = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id) VALUES (:task_id, :user_id)");
                foreach ($assignees as $userId) {
                    $assignStmt->execute([':task_id' => $taskId, ':user_id' => (int)$userId]);
                }
            }

            normalize_stage_order($pdo, $stageId);
            $pdo->commit();

            json_response(['success' => true, 'task_id' => $taskId, 'created' => $isNew]);
        }

        // Os atalhos do cliente são gravados apenas pelo cadastro de clientes.
        // A API de tarefas não altera esse dado, para um atalho importante não
        // ser removido sem querer durante a operação do dia a dia.

        // 10. Excluir (reversível) e Restaurar
        if ($action === 'delete') {
            $taskId = (int)($input['task_id'] ?? 0);
            if ($taskId <= 0) {
                json_response(['error' => 'Tarefa inválida.'], 400);
            }

            $stmt = $pdo->prepare("SELECT title, stage_id FROM tasks WHERE id = :id");
            $stmt->execute([':id' => $taskId]);
            $task = $stmt->fetch();
            if (!$task) {
                json_response(['error' => 'Tarefa não encontrada.'], 404);
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE tasks SET deleted_at = :now, is_timer_running = 0, updated_at = :now2 WHERE id = :id")
                ->execute([':now' => now(), ':now2' => now(), ':id' => $taskId]);
            normalize_stage_order($pdo, (int)$task['stage_id']);
            $pdo->commit();

            json_response([
                'success' => true,
                'title' => $task['title'],
                'undo' => ['action' => 'undelete', 'task_id' => $taskId],
            ]);
        }

        if ($action === 'undelete') {
            $taskId = (int)($input['task_id'] ?? 0);
            $pdo->prepare("UPDATE tasks SET deleted_at = NULL, updated_at = :now WHERE id = :id")
                ->execute([':now' => now(), ':id' => $taskId]);
            json_response(['success' => true]);
        }

        // 11. Checklist
        if ($action === 'toggle_checklist') {
            $itemId = (int)($input['item_id'] ?? 0);
            $isCompleted = !empty($input['is_completed']) ? 1 : 0;

            $stmt = $pdo->prepare("UPDATE task_checklists SET is_completed = :is_completed WHERE id = :id");
            $stmt->execute([':is_completed' => $isCompleted, ':id' => $itemId]);

            $taskStmt = $pdo->prepare("SELECT task_id FROM task_checklists WHERE id = :id");
            $taskStmt->execute([':id' => $itemId]);
            $tid = (int)$taskStmt->fetchColumn();
            if ($tid) {
                touch_task($pdo, $tid);
            }

            json_response(['success' => true, 'task_id' => $tid]);
        }

        if ($action === 'add_checklist') {
            $taskId = (int)($input['task_id'] ?? 0);
            $title = trim((string)($input['title'] ?? ''));

            if ($taskId <= 0 || $title === '') {
                json_response(['error' => 'Escreva o texto da subtarefa.'], 400);
            }

            $orderStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM task_checklists WHERE task_id = :id");
            $orderStmt->execute([':id' => $taskId]);
            $sortOrder = (int)$orderStmt->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO task_checklists (task_id, title, is_completed, sort_order) VALUES (:task_id, :title, 0, :s)");
            $stmt->execute([':task_id' => $taskId, ':title' => $title, ':s' => $sortOrder]);
            touch_task($pdo, $taskId);

            json_response([
                'success' => true,
                'item' => ['id' => (int)$pdo->lastInsertId(), 'title' => $title, 'is_completed' => 0, 'sort_order' => $sortOrder],
            ]);
        }

        if ($action === 'delete_checklist') {
            $itemId = (int)($input['item_id'] ?? 0);
            $taskStmt = $pdo->prepare("SELECT task_id FROM task_checklists WHERE id = :id");
            $taskStmt->execute([':id' => $itemId]);
            $tid = (int)$taskStmt->fetchColumn();

            $pdo->prepare("DELETE FROM task_checklists WHERE id = :id")->execute([':id' => $itemId]);
            if ($tid) {
                touch_task($pdo, $tid);
            }

            json_response(['success' => true]);
        }

        // 12. Comentários
        if ($action === 'add_comment') {
            $taskId = (int)($input['task_id'] ?? 0);
            $commentText = trim((string)($input['comment_text'] ?? ''));

            if ($taskId <= 0 || $commentText === '') {
                json_response(['error' => 'Escreva algo antes de enviar.'], 400);
            }

            $createdAt = now();
            $stmt = $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment_text, created_at) VALUES (:task_id, :user_id, :text, :created)");
            $stmt->execute([':task_id' => $taskId, ':user_id' => $user['id'], ':text' => $commentText, ':created' => $createdAt]);
            touch_task($pdo, $taskId);

            json_response([
                'success' => true,
                'comment' => [
                    'id' => (int)$pdo->lastInsertId(),
                    'user_name' => $user['full_name'],
                    'comment_text' => $commentText,
                    'created_at' => $createdAt,
                ],
            ]);
        }

        // 13. Resumos / Diário de Bordo
        if ($action === 'add_summary') {
            $taskId = (int)($input['task_id'] ?? 0);
            $summaryText = trim((string)($input['summary_text'] ?? ''));

            if ($taskId <= 0 || $summaryText === '') {
                json_response(['error' => 'Escreva o resumo antes de salvar.'], 400);
            }

            $createdAt = now();
            $stmt = $pdo->prepare("INSERT INTO task_summaries (task_id, user_id, summary_text, created_at) VALUES (:task_id, :user_id, :text, :created)");
            $stmt->execute([':task_id' => $taskId, ':user_id' => $user['id'], ':text' => $summaryText, ':created' => $createdAt]);
            touch_task($pdo, $taskId);

            json_response([
                'success' => true,
                'summary' => [
                    'id' => (int)$pdo->lastInsertId(),
                    'user_name' => $user['full_name'],
                    'summary_text' => $summaryText,
                    'created_at' => $createdAt,
                ],
            ]);
        }

        // 14. Limite de WIP da coluna
        if ($action === 'set_wip_limit') {
            $stageId = (int)($input['stage_id'] ?? 0);
            $limit = $input['wip_limit'] ?? null;
            $limit = ($limit === null || $limit === '' || (int)$limit <= 0) ? null : (int)$limit;

            if ($stageId <= 0) {
                json_response(['error' => 'Coluna inválida.'], 400);
            }

            $pdo->prepare("UPDATE task_stages SET wip_limit = :lim WHERE id = :id")
                ->execute([':lim' => $limit, ':id' => $stageId]);

            json_response(['success' => true, 'wip_limit' => $limit]);
        }

        // 15. Arquivar concluídas antigas (tira do quadro, mantém no banco)
        if ($action === 'archive_done') {
            $doneId = done_stage_id($pdo);
            if (!$doneId) {
                json_response(['error' => 'Nenhuma coluna de conclusão configurada.'], 400);
            }

            $stmt = $pdo->prepare("
                UPDATE tasks SET archived_at = :now, updated_at = :now2
                WHERE stage_id = :stg AND deleted_at IS NULL AND archived_at IS NULL AND completed_at IS NOT NULL
            ");
            $stmt->execute([':now' => now(), ':now2' => now(), ':stg' => $doneId]);

            json_response(['success' => true, 'archived' => $stmt->rowCount()]);
        }

        json_response(['error' => 'Ação desconhecida.'], 400);
    }

    json_response(['error' => 'Método não suportado.'], 405);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[KanbanDoo] ' . $e->getMessage());
    json_response(['error' => 'Não foi possível concluir a ação. Tente novamente.'], 500);
}
