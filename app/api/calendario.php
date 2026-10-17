<?php
/**
 * KanbanDoo - API de Integração e Eventos do Calendário
 *
 * Fornece eventos/tarefas para a visão de calendário e processa importação/sincronização
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
                case 'UID':
                    $current['uid'] = trim($val);
                    break;
                case 'RRULE':
                    $current['rrule'] = trim($val);
                    break;
                case 'RECURRENCE-ID':
                    $current['recurrence_id'] = trim($val);
                    break;
                case 'DTSTART':
                    $parsed = parse_ical_date($val);
                    if ($parsed) {
                        $current['due_date'] = $parsed['date'];
                        if (!empty($parsed['time'])) {
                            $current['due_time'] = $parsed['time'];
                        }
                        $current['start_ts'] = $parsed['timestamp'];
                    }
                    break;
                case 'DTEND':
                    $parsed = parse_ical_date($val);
                    if ($parsed) {
                        $current['end_ts'] = $parsed['timestamp'];
                    }
                    break;
            }
        }
    }

    return $events;
}

/**
 * Calcula a próxima ocorrência válida a partir de hoje para um evento recorrente (RRULE).
 */
function calculate_next_occurrence(array $event, string $todayStr): ?array
{
    $rrule = $event['rrule'] ?? '';
    if (empty($rrule)) return null;

    // Verificar UNTIL se houver (se a série já expirou antes de hoje)
    if (preg_match('/UNTIL=(\d{8}(T\d{6}Z?)?)/', $rrule, $um)) {
        $untilParsed = parse_ical_date($um[1]);
        if ($untilParsed && $untilParsed['date'] < $todayStr) {
            return null; // Série já finalizou no passado
        }
    }

    $time = $event['due_time'] ?? null;
    $origDate = $event['due_date'] ?? $todayStr;

    // FREQ=WEEKLY: encontrar o próximo dia correspondente a partir de hoje
    if (stripos($rrule, 'FREQ=WEEKLY') !== false) {
        $daysMap = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
        $targetDays = [];
        if (preg_match('/BYDAY=([A-Z,]+)/', $rrule, $bm)) {
            $parts = explode(',', $bm[1]);
            foreach ($parts as $p) {
                $code = substr(trim($p), -2);
                if (isset($daysMap[$code])) $targetDays[] = $daysMap[$code];
            }
        }
        if (empty($targetDays)) {
            $origTs = strtotime($origDate);
            $targetDays[] = (int)date('w', $origTs);
        }

        // Buscar a data de ocorrência nos próximos 14 dias a partir de hoje
        $todayDt = new DateTime($todayStr);
        for ($offset = 0; $offset < 14; $offset++) {
            $check = clone $todayDt;
            if ($offset > 0) $check->modify("+{$offset} days");
            $w = (int)$check->format('w');
            if (in_array($w, $targetDays, true)) {
                return [
                    'due_date' => $check->format('Y-m-d'),
                    'due_time' => $time,
                ];
            }
        }
    }

    // FREQ=DAILY: a tarefa ocorrerá hoje
    if (stripos($rrule, 'FREQ=DAILY') !== false) {
        return [
            'due_date' => $todayStr,
            'due_time' => $time,
        ];
    }

    return null;
}

function parse_ical_date(string $val): ?array
{
    $val = trim($val);
    // Formato dia inteiro: YYYYMMDD
    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $val, $m)) {
        return [
            'date' => "{$m[1]}-{$m[2]}-{$m[3]}",
            'time' => null,
            'timestamp' => strtotime("{$m[1]}-{$m[2]}-{$m[3]} 00:00:00")
        ];
    }
    // Formato com hora UTC: YYYYMMDDTHHMMSSZ ou local: YYYYMMDDTHHMMSS
    if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z)?$/', $val, $m)) {
        $year = $m[1]; $month = $m[2]; $day = $m[3];
        $hour = $m[4]; $min = $m[5]; $sec = $m[6];
        $isUtc = !empty($m[7]);

        if ($isUtc) {
            $dt = new DateTime("{$year}-{$month}-{$day} {$hour}:{$min}:{$sec}", new DateTimeZone('UTC'));
            // Converter para o timezone local do Brasil
            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
            return [
                'date' => $dt->format('Y-m-d'),
                'time' => $dt->format('H:i'),
                'timestamp' => $dt->getTimestamp()
            ];
        } else {
            return [
                'date' => "{$year}-{$month}-{$day}",
                'time' => "{$hour}:{$min}",
                'timestamp' => strtotime("{$year}-{$month}-{$day} {$hour}:{$min}:{$sec}")
            ];
        }
    }

    return null;
}

// -------------------------------------------------------------
// GET: Retornar tarefas formatadas para visualização de calendário
// -------------------------------------------------------------
if ($method === 'GET') {
    $start = $_GET['start'] ?? date('Y-m-01', strtotime('-1 month'));
    $end   = $_GET['end']   ?? date('Y-m-t', strtotime('+2 months'));

    // Obter URLs salvas do usuário
    $userStmt = $pdo->prepare('SELECT google_calendar_url, outlook_calendar_url FROM users WHERE id = :id');
    $userStmt->execute([':id' => $user['id']]);
    $userUrls = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Buscar tarefas no período
    $stmt = $pdo->prepare("
        SELECT t.id, t.title, t.description, t.priority, t.due_date, t.due_time,
               t.completed_at, t.is_bomb, t.in_focus, t.estimated_minutes, t.tags,
               s.id AS stage_id, s.name AS stage_name, s.color AS stage_color,
               c.company_name AS client_company
        FROM tasks t
        LEFT JOIN task_stages s ON s.id = t.stage_id
        LEFT JOIN clients c ON c.id = t.client_id
        WHERE t.deleted_at IS NULL
          AND t.archived_at IS NULL
          AND t.due_date IS NOT NULL
          AND t.is_calendar_event = 1
          AND t.due_date >= :start AND t.due_date <= :end
        ORDER BY t.due_date ASC, t.due_time ASC, t.sort_order ASC
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
            'is_bomb' => (int)$t['is_bomb'] === 1,
            'stage_name' => $t['stage_name'],
            'stage_color' => $t['stage_color'] ?? '#6366f1',
            'client_company' => $t['client_company'] ?: 'Sem cliente',
            'assignees' => $assigneesByTask[$t['id']] ?? [],
            'tags' => $tags ?: []
        ];
    }

    json_response([
        'success' => true,
        'events' => $events,
        'google_calendar_url' => $userUrls['google_calendar_url'] ?? '',
        'outlook_calendar_url' => $userUrls['outlook_calendar_url'] ?? '',
    ]);
}

// -------------------------------------------------------------
// POST: Ações de Sincronização / Importação
// -------------------------------------------------------------
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';

// Salvar URLs de Calendário do Usuário
if ($action === 'save_urls') {
    $googleUrl  = trim((string)($input['google_calendar_url'] ?? ''));
    $outlookUrl = trim((string)($input['outlook_calendar_url'] ?? ''));

    // Converter prefixo webcal:// para https://
    $googleUrl  = preg_replace('#^webcal://#i', 'https://', $googleUrl);
    $outlookUrl = preg_replace('#^webcal://#i', 'https://', $outlookUrl);

    $upd = $pdo->prepare('UPDATE users SET google_calendar_url = :g, outlook_calendar_url = :o WHERE id = :id');
    $upd->execute([
        ':g' => $googleUrl ?: null,
        ':o' => $outlookUrl ?: null,
        ':id' => $user['id']
    ]);

    json_response(['success' => true, 'message' => 'Configurações de calendário salvas com sucesso!']);
}

// Sincronizar via URL ou Upload de arquivo .ics
if ($action === 'sync_ical') {
    $provider = $input['provider'] ?? 'google'; // 'google', 'microsoft' ou 'file'
    $url = trim((string)($input['url'] ?? ''));
    $url = preg_replace('#^webcal://#i', 'https://', $url);

    $rawIcs = '';

    if (!empty($_FILES['file']['tmp_name'])) {
        $rawIcs = file_get_contents($_FILES['file']['tmp_name']);
    } elseif (!empty($url)) {
        // Baixar feed iCal
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'KanbanDoo Calendar Sync/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $rawIcs = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$rawIcs || $httpCode >= 400) {
            json_response(['error' => 'Não foi possível baixar o calendário da URL informada. Verifique se o link está correto e acessível.'], 400);
        }

        // Salvar a URL informada no perfil do usuário
        $field = ($provider === 'microsoft') ? 'outlook_calendar_url' : 'google_calendar_url';
        $pdo->prepare("UPDATE users SET {$field} = :url WHERE id = :id")->execute([':url' => $url, ':id' => $user['id']]);
    } else {
        // Tentar usar URL salva
        $uStmt = $pdo->prepare('SELECT google_calendar_url, outlook_calendar_url FROM users WHERE id = :id');
        $uStmt->execute([':id' => $user['id']]);
        $saved = $uStmt->fetch(PDO::FETCH_ASSOC);

        $savedUrl = ($provider === 'microsoft') ? ($saved['outlook_calendar_url'] ?? '') : ($saved['google_calendar_url'] ?? '');
        if (empty($savedUrl)) {
            json_response(['error' => 'Informe uma URL do calendário ou faça upload de um arquivo .ics.'], 400);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $savedUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'KanbanDoo Calendar Sync/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $rawIcs = curl_exec($ch);
        curl_close($ch);

        if (!$rawIcs) {
            json_response(['error' => 'Falha ao sincronizar o calendário salvo. Verifique sua conexão e a validade da URL.'], 400);
        }
    }

    if (empty($rawIcs)) {
        json_response(['error' => 'Conteúdo do calendário vazio ou inválido.'], 400);
    }

    $parsedEvents = parse_ical_feed($rawIcs);
    if (empty($parsedEvents)) {
        json_response(['error' => 'Nenhum evento encontrado no arquivo do calendário.'], 400);
    }

    // Localizar a coluna padrão "A FAZER"
    $stageStmt = $pdo->query("SELECT id FROM task_stages WHERE name LIKE '%fazer%' OR name LIKE '%to do%' ORDER BY sort_order ASC LIMIT 1");
    $stageId = (int)($stageStmt->fetchColumn() ?: 1);

    $showInKanban = isset($input['show_in_kanban']) ? ((int)$input['show_in_kanban'] === 1 ? 1 : 0) : 1;
    $providerTag = ($provider === 'microsoft') ? 'Outlook' : 'Google Calendar';
    $importedCount = 0;
    $updatedCount = 0;

    $checkStmt = $pdo->prepare("
        SELECT id FROM tasks
        WHERE created_by = :uid
          AND title = :title
          AND due_date = :due_date
          AND deleted_at IS NULL
        LIMIT 1
    ");

    $insertStmt = $pdo->prepare("
        INSERT INTO tasks (title, description, priority, stage_id, due_date, due_time, is_calendar_event, show_in_kanban, estimated_minutes, created_by, status, tags, created_at, updated_at)
        VALUES (:title, :description, 'low', :stage_id, :due_date, :due_time, 1, :show_kanban, :est, :created_by, 'open', :tags, :now, :now2)
    ");

    $updateStmt = $pdo->prepare("
        UPDATE tasks
        SET due_time = :due_time, description = :description, updated_at = :now
        WHERE id = :id
    ");

    $assignStmt = $pdo->prepare("INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (:tid, :uid)");

    $now = now();

    $todayStr = date('Y-m-d');

    foreach ($parsedEvents as $ev) {
        $title = mb_substr($ev['title'], 0, 200, 'UTF-8');
        $desc = $ev['description'] ?? '';
        $hasRrule = !empty($ev['rrule']);

        if ($hasRrule) {
            // Evento recorrente: calcular a data real da ocorrência atual/próxima a partir de hoje
            $nextOcc = calculate_next_occurrence($ev, $todayStr);
            if ($nextOcc) {
                $dueDate = $nextOcc['due_date'];
                $dueTime = $nextOcc['due_time'] ?? ($ev['due_time'] ?? null);
            } else {
                continue; // Série já expirou
            }
        } else {
            $dueDate = $ev['due_date'] ?? null;
            $dueTime = $ev['due_time'] ?? null;

            // Eventos pontuais com data anterior a hoje não devem entrar como tarefas a fazer atrasadas
            if ($dueDate && $dueDate < $todayStr) {
                continue;
            }
        }

        // Calcular tempo estimado se disponível
        $estimatedMinutes = null;
        if (!empty($ev['start_ts']) && !empty($ev['end_ts']) && $ev['end_ts'] > $ev['start_ts']) {
            $diffMins = round(($ev['end_ts'] - $ev['start_ts']) / 60);
            if ($diffMins > 0 && $diffMins <= 1440) {
                $estimatedMinutes = (int)$diffMins;
            }
        }

        if (empty($dueDate)) continue;

        // Verificar se já existe
        $checkStmt->execute([
            ':uid' => $user['id'],
            ':title' => $title,
            ':due_date' => $dueDate,
        ]);
        $existingId = $checkStmt->fetchColumn();

        if ($existingId) {
            // Atualizar horário se alterado
            $updateStmt->execute([
                ':due_time' => $dueTime,
                ':description' => $desc,
                ':now' => $now,
                ':id' => $existingId
            ]);
            $updatedCount++;
        } else {
            // Inserir nova tarefa no Kanban na coluna A FAZER
            $tagsJson = json_encode([$providerTag], JSON_UNESCAPED_UNICODE);
            $insertStmt->execute([
                ':title' => $title,
                ':description' => $desc,
                ':stage_id' => $stageId,
                ':due_date' => $dueDate,
                ':due_time' => $dueTime,
                ':est' => $estimatedMinutes,
                ':created_by' => $user['id'],
                ':show_kanban' => $showInKanban,
                ':tags' => $tagsJson,
                ':now' => $now,
                ':now2' => $now,
            ]);
            $newTaskId = (int)$pdo->lastInsertId();

            // Vincular ao usuário logado
            if ($newTaskId > 0) {
                try {
                    $assignStmt->execute([':tid' => $newTaskId, ':uid' => $user['id']]);
                } catch (\Exception $e) {}
            }
            $importedCount++;
        }
    }

    json_response([
        'success' => true,
        'imported' => $importedCount,
        'updated' => $updatedCount,
        'total_events' => count($parsedEvents),
        'message' => "Sincronização concluída! {$importedCount} novas tarefas importadas e {$updatedCount} atualizadas para o Kanban."
    ]);
}

json_response(['error' => 'Ação não reconhecida.'], 400);
