<?php
/**
 * KanbanDoo - API de Integração e Eventos do Calendário
 *
 * Fornece eventos e tarefas com prazo para a visão de calendário e processa importação/sincronização
 * com feeds iCal do Google Calendar e Microsoft Outlook / 365.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['error' => 'Não autorizado.'], 401);
}

$pdo = get_pdo();
$user = current_user();
$method = $_SERVER['REQUEST_METHOD'];

// Helper para parse simples e robusto de feeds iCal / .ics
function parse_ical_feed(string $rawIcs): array
{
    // Normalizar quebras de linha
    $rawIcs = str_replace(["\r\n", "\r"], "\n", $rawIcs);
    // Unfold de linhas dobradas no padrão RFC 5545 (espaço ou tab no início da linha seguinte)
    $rawIcs = preg_replace("/\n[ \t]/", '', $rawIcs);

    $events = [];
    $lines = explode("\n", $rawIcs);
    $inEvent = false;
    $current = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === 'BEGIN:VEVENT') {
            $inEvent = true;
            $current = [];
            continue;
        }
        if ($line === 'END:VEVENT') {
            if ($inEvent && !empty($current['title'])) {
                $events[] = $current;
            }
            $inEvent = false;
            $current = [];
            continue;
        }

        if (!$inEvent) continue;

        // Separar chave e valor
        if (strpos($line, ':') !== false) {
            $pos = strpos($line, ':');
            $keyFull = substr($line, 0, $pos);
            $val = substr($line, $pos + 1);

            $keyParts = explode(';', $keyFull);
            $prop = strtoupper(trim($keyParts[0]));

            // Desescapar caracteres de iCal
            $val = str_replace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $val);

            switch ($prop) {
                case 'SUMMARY':
                    $current['title'] = trim($val);
                    break;
                case 'DESCRIPTION':
                    $current['description'] = trim($val);
                    break;
                case 'LOCATION':
                    $current['location'] = trim($val);
                    break;
                case 'UID':
                    $current['uid'] = trim($val);
                    break;
                case 'DTSTART':
                    // Pode vir no formato YYYYMMDD ou YYYYMMDDTHHMMSS ou YYYYMMDDTHHMMSSZ
                    $cleanVal = preg_replace('/[^0-9T]/', '', $val);
                    if (strpos($cleanVal, 'T') !== false) {
                        $parts = explode('T', $cleanVal);
                        $d = $parts[0];
                        $t = $parts[1] ?? '000000';
                        if (strlen($d) === 8) {
                            $current['due_date'] = substr($d, 0, 4) . '-' . substr($d, 4, 2) . '-' . substr($d, 6, 2);
                        }
                        if (strlen($t) >= 4) {
                            $current['due_time'] = substr($t, 0, 2) . ':' . substr($t, 2, 2);
                        }
                    } else {
                        if (strlen($cleanVal) === 8) {
                            $current['due_date'] = substr($cleanVal, 0, 4) . '-' . substr($cleanVal, 4, 2) . '-' . substr($cleanVal, 6, 2);
                        }
                    }
                    break;
            }
        }
    }

    return $events;
}

// -------------------------------------------------------------
// GET: Retornar tarefas e eventos formatados para visualização de calendário
// -------------------------------------------------------------
if ($method === 'GET') {
    $start = $_GET['start'] ?? date('Y-m-01', strtotime('-1 month'));
    $end   = $_GET['end']   ?? date('Y-m-t', strtotime('+2 months'));

    // Obter URLs salvas do usuário
    $userStmt = $pdo->prepare('SELECT google_calendar_url, outlook_calendar_url FROM users WHERE id = :id');
    $userStmt->execute([':id' => $user['id']]);
    $userUrls = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Buscar tarefas e eventos no período (tanto eventos de calendário quanto tarefas com prazo)
    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.description, t.priority, t.due_date, t.due_time,
               t.completed_at, t.is_bomb, t.in_focus, t.estimated_minutes, t.tags,
               t.is_calendar_event, t.record_type,
               s.id AS stage_id, s.name AS stage_name, s.color AS stage_color,
               c.company_name AS client_company
        FROM tasks t
        LEFT JOIN task_stages s ON s.id = t.stage_id
        LEFT JOIN clients c ON c.id = t.client_id
        WHERE t.deleted_at IS NULL
          AND t.archived_at IS NULL
          AND t.due_date IS NOT NULL
          AND t.due_date >= :start AND t.due_date <= :end
        ORDER BY t.due_date ASC, (t.due_time IS NULL) ASC, t.due_time ASC, t.sort_order ASC
    ");
    $stmt->execute([':start' => $start, ':end' => $end]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Carregar responsáveis para cada tarefa
    $taskIds = array_column($tasks, 'id');
    $assigneesByTask = [];
    if (!empty($taskIds)) {
        $inClause = implode(',', array_map('intval', $taskIds));
        $assStmt = $pdo->query("
            SELECT a.task_id, u.id, u.full_name
            FROM task_assignees a
            JOIN users u ON u.id = a.user_id
            WHERE a.task_id IN ($inClause)
            ORDER BY u.full_name ASC
        ");
        while ($row = $assStmt->fetch(PDO::FETCH_ASSOC)) {
            $assigneesByTask[$row['task_id']][] = [
                'id' => (int)$row['id'],
                'name' => $row['full_name']
            ];
        }
    }

    $events = [];
    foreach ($tasks as $t) {
        $tags = !empty($t['tags']) ? (is_array($t['tags']) ? $t['tags'] : json_decode($t['tags'], true)) : [];
        $events[] = [
            'id' => (int)$t['id'],
            'title' => $t['title'],
            'due_date' => $t['due_date'],
            'due_time' => !empty($t['due_time']) ? substr($t['due_time'], 0, 5) : null,
            'priority' => $t['priority'],
            'is_done' => !empty($t['completed_at']),
            'is_bomb' => (bool)$t['is_bomb'],
            'in_focus' => (bool)$t['in_focus'],
            'estimated_minutes' => (int)$t['estimated_minutes'],
            'is_calendar_event' => (int)($t['is_calendar_event'] ?? 0),
            'record_type' => $t['record_type'] ?? 'task',
            'stage_name' => $t['stage_name'],
            'stage_color' => $t['stage_color'],
            'client_company' => $t['client_company'],
            'assignees' => $assigneesByTask[$t['id']] ?? [],
            'tags' => is_array($tags) ? $tags : []
        ];
    }

    json_response([
        'success' => true,
        'events' => $events,
        'google_url' => $userUrls['google_calendar_url'] ?? '',
        'outlook_url' => $userUrls['outlook_calendar_url'] ?? ''
    ]);
}

// -------------------------------------------------------------
// POST: Sincronizar feeds iCal ou Salvar URLs de integração
// -------------------------------------------------------------
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? 'sync';

    // Salvar URLs de feed externo
    if ($action === 'save_urls') {
        $googleUrl = trim($input['google_calendar_url'] ?? '');
        $outlookUrl = trim($input['outlook_calendar_url'] ?? '');

        $stmt = $pdo->prepare('UPDATE users SET google_calendar_url = :g, outlook_calendar_url = :o WHERE id = :id');
        $stmt->execute([
            ':g' => $googleUrl ?: null,
            ':o' => $outlookUrl ?: null,
            ':id' => $user['id']
        ]);

        json_response([
            'success' => true,
            'message' => 'URLs de calendários salvas com sucesso!'
        ]);
    }

    // Sincronizar e importar eventos de feeds remotos
    if ($action === 'sync') {
        // Obter URLs salvas
        $userStmt = $pdo->prepare('SELECT google_calendar_url, outlook_calendar_url FROM users WHERE id = :id');
        $userStmt->execute([':id' => $user['id']]);
        $urls = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $sources = [
            'google' => $urls['google_calendar_url'] ?? '',
            'outlook' => $urls['outlook_calendar_url'] ?? ''
        ];

        // Obter estágio padrão "A Fazer" para tarefas sincronizadas
        $firstStageStmt = $pdo->query('SELECT id FROM task_stages ORDER BY sort_order ASC, id ASC LIMIT 1');
        $defaultStageId = (int)$firstStageStmt->fetchColumn() ?: 1;

        $importedCount = 0;
        $updatedCount = 0;

        foreach ($sources as $sourceKey => $url) {
            if (empty($url)) continue;

            // Converter protocolo webcal:// para https://
            if (strpos($url, 'webcal://') === 0) {
                $url = 'https://' . substr($url, 9);
            }

            // Buscar arquivo iCal com contexto de stream seguro
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'timeout' => 15,
                    'user_agent' => 'KanbanDoo-CalendarSync/1.0'
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            $context = stream_context_create($opts);
            $rawFeed = @file_get_contents($url, false, $context);

            if ($rawFeed === false) {
                continue;
            }

            $feedEvents = parse_ical_feed($rawFeed);

            foreach ($feedEvents as $ev) {
                if (empty($ev['title']) || empty($ev['due_date'])) continue;

                $uid = $ev['uid'] ?? md5($sourceKey . '_' . $ev['title'] . '_' . $ev['due_date']);
                $syncId = $sourceKey . '_' . $uid;

                // Verificar se já existe uma tarefa com esse external_sync_id
                $checkStmt = $pdo->prepare('SELECT id FROM tasks WHERE external_sync_id = :sync_id LIMIT 1');
                $checkStmt->execute([':sync_id' => $syncId]);
                $existingId = $checkStmt->fetchColumn();

                $timeVal = !empty($ev['due_time']) ? $ev['due_time'] . ':00' : null;

                if ($existingId) {
                    // Atualizar título, data e hora se mudou
                    $upd = $pdo->prepare('
                        UPDATE tasks
                        SET title = :title,
                            due_date = :due_date,
                            due_time = :due_time,
                            description = COALESCE(:description, description)
                        WHERE id = :id
                    ');
                    $upd->execute([
                        ':title' => $ev['title'],
                        ':due_date' => $ev['due_date'],
                        ':due_time' => $timeVal,
                        ':description' => $ev['description'] ?? null,
                        ':id' => $existingId
                    ]);
                    $updatedCount++;
                } else {
                    // Inserir nova tarefa/evento com tag do calendário
                    $ins = $pdo->prepare('
                        INSERT INTO tasks (
                            title, description, stage_id, due_date, due_time,
                            is_calendar_event, record_type, source, external_sync_id,
                            priority, user_id, created_at
                        ) VALUES (
                            :title, :description, :stage_id, :due_date, :due_time,
                            1, "event", :source, :sync_id,
                            "medium", :user_id, :now
                        )
                    ');
                    $ins->execute([
                        ':title' => $ev['title'],
                        ':description' => $ev['description'] ?? '',
                        ':stage_id' => $defaultStageId,
                        ':due_date' => $ev['due_date'],
                        ':due_time' => $timeVal,
                        ':source' => $sourceKey,
                        ':sync_id' => $syncId,
                        ':user_id' => $user['id'],
                        ':now' => now()
                    ]);
                    $importedCount++;
                }
            }
        }

        json_response([
            'success' => true,
            'message' => "Sincronização concluída! $importedCount eventos importados, $updatedCount atualizados.",
            'imported' => $importedCount,
            'updated' => $updatedCount
        ]);
    }

    json_response(['error' => 'Ação não reconhecida.'], 400);
}

json_response(['error' => 'Método inválido.'], 405);
