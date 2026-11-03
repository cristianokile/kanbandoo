<?php
/**
 * Funções Auxiliares do KanbanDoo
 */

// CSRF Protection
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Timestamp atual no fuso da aplicação
function now(): string
{
    return date('Y-m-d H:i:s');
}

// Flash Messages
function flash(string $type, string $message): void
{
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // success, danger, warning, info
        'text' => $message,
    ];
}

function get_flash_messages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

// Sanitização e Escape
function e(?string $string): string
{
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

// Formatação de Data Amigável
function format_date(?string $dateStr, bool $includeTime = false): string
{
    if (empty($dateStr)) {
        return '';
    }
    try {
        $date = new DateTime($dateStr);
        return $includeTime ? $date->format('d/m/Y H:i') : $date->format('d/m/Y');
    } catch (Exception $e) {
        return $dateStr;
    }
}

/**
 * Badge de prazo: texto curto para o card e rótulo completo para leitores de tela.
 */
function format_due_date_badge(?string $dueDate, ?string $originalDueDate = null): array
{
    if (empty($dueDate) && empty($originalDueDate)) {
        return ['text' => '', 'short' => '', 'label' => '', 'tone' => 'none', 'is_overdue' => false, 'formatted' => ''];
    }

    try {
        $today = new DateTime('today');

        // Se tem data de prazo original atrasada (ex: tarefa era para hoje/ontem e foi reagendada para amanhã)
        if (!empty($originalDueDate)) {
            $origDue = new DateTime($originalDueDate);
            $origDiff = (int)$today->diff($origDue)->format('%r%a');
            if ($origDiff < 0) {
                $abs = abs($origDiff);
                $origFormatted = $origDue->format('d/m/Y');
                $newFormatted = !empty($dueDate) ? (new DateTime($dueDate))->format('d/m') : '';
                return [
                    'text' => $abs === 1 ? '1 dia de atraso' : "$abs dias de atraso",
                    'short' => (string)$abs,
                    'label' => "Prazo original era em $origFormatted, atrasada em $abs dia(s)",
                    'tone' => 'overdue',
                    'is_overdue' => true,
                    'formatted' => $newFormatted ?: $origDue->format('d/m'),
                    'original_due_date' => $originalDueDate,
                ];
            }
        }

        if (empty($dueDate)) {
            return ['text' => '', 'short' => '', 'label' => '', 'tone' => 'none', 'is_overdue' => false, 'formatted' => ''];
        }

        $due = new DateTime($dueDate);
        $days = (int)$today->diff($due)->format('%r%a');
        $formatted = $due->format('d/m/Y');

        if ($days < 0) {
            $abs = abs($days);
            return [
                'text' => $abs === 1 ? '1 dia de atraso' : "$abs dias de atraso",
                'short' => (string)$abs,
                'label' => "Prazo em $formatted, atrasada em $abs dia(s)",
                'tone' => 'overdue',
                'is_overdue' => true,
                'formatted' => $due->format('d/m'),
            ];
        }
        if ($days === 0) {
            return [
                'text' => 'Entrega hoje',
                'short' => 'HJ',
                'label' => "Entrega hoje, $formatted",
                'tone' => 'today',
                'is_overdue' => false,
                'formatted' => $due->format('d/m'),
            ];
        }
        if ($days === 1) {
            return [
                'text' => 'Amanhã',
                'short' => 'Amanhã',
                'label' => "Entrega amanhã, $formatted",
                'tone' => 'soon',
                'is_overdue' => false,
                'formatted' => $due->format('d/m'),
            ];
        }
        if ($days <= 7) {
            return [
                'text' => "Em $days dias",
                'short' => "{$days}d",
                'label' => "Entrega em $formatted",
                'tone' => 'soon',
                'is_overdue' => false,
                'formatted' => $due->format('d/m'),
            ];
        }

        return [
            'text' => $due->format('d/m'),
            'short' => $due->format('d/m'),
            'label' => "Entrega em $formatted",
            'tone' => 'later',
            'is_overdue' => false,
            'formatted' => $due->format('d/m'),
        ];
    } catch (Exception $e) {
        return ['text' => $dueDate, 'short' => $dueDate, 'label' => $dueDate, 'tone' => 'later', 'is_overdue' => false, 'formatted' => $dueDate];
    }
}

// Cores e Labels de Prioridade
function priority_info(string $priority): array
{
    return match ($priority) {
        'urgent' => ['label' => 'Urgente', 'tone' => 'urgent', 'rank' => 4],
        'high'   => ['label' => 'Alta',    'tone' => 'high',   'rank' => 3],
        'medium' => ['label' => 'Média',   'tone' => 'medium', 'rank' => 2],
        default  => ['label' => 'Baixa',   'tone' => 'low',    'rank' => 1],
    };
}

/**
 * Lê as etiquetas de uma tarefa.
 * Aceita o formato atual (JSON) e o antigo (texto separado por vírgula),
 * para que registros criados antes desta versão continuem funcionando.
 */
function parse_tags($raw): array
{
    if (is_array($raw)) {
        return normalize_tags($raw);
    }

    $raw = trim((string)$raw);
    if ($raw === '' || $raw === '[]') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return normalize_tags($decoded);
    }

    return normalize_tags(explode(',', $raw));
}

/** Limpa, corta e remove repetições (sem diferenciar maiúsculas). */
function normalize_tags(array $tags, int $max = 8): array
{
    $clean = [];
    $seen = [];

    foreach ($tags as $tag) {
        $tag = trim(preg_replace('/\s+/u', ' ', (string)$tag));
        $tag = trim($tag, "#,");
        if ($tag === '') {
            continue;
        }

        $tag = mb_substr($tag, 0, 24);
        $key = mb_strtolower($tag, 'UTF-8');
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $clean[] = $tag;

        if (count($clean) >= $max) {
            break;
        }
    }

    return $clean;
}

/** Índice de cor estável por etiqueta (0-9). */
function tag_color_index(string $tag): int
{
    return (int)(crc32(mb_strtolower($tag, 'UTF-8')) % 10);
}

/**
 * Índice de cor estável por cliente (0-9), para o nome da empresa no card.
 */
function client_color_index(?int $clientId): int
{
    if (!$clientId) {
        return 0;
    }
    return (int)(crc32((string)$clientId) % 10);
}

// Redirecionamento
function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

// JSON Response para APIs
function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function recurrence_label(?string $type): string
{
    return match ($type) {
        'daily' => 'Diária',
        'weekly' => 'Semanal',
        'biweekly' => 'Quinzenal',
        'monthly' => 'Mensal',
        'yearly' => 'Anual',
        'custom' => 'Personalizada',
        default => '',
    };
}

/**
 * Calcula a próxima data de entrega de acordo com a regra de recorrência.
 */
function calculate_next_recurrence_date(?string $currentDueDate, ?string $recurrenceType, ?string $recurrenceConfigJson, ?int $fixedWeekday = null): string
{
    $today = new DateTime('today');

    if (!empty($currentDueDate)) {
        try {
            $baseDate = new DateTime($currentDueDate);
        } catch (Exception $e) {
            $baseDate = clone $today;
        }
    } else {
        $baseDate = clone $today;
    }

    $refDate = ($baseDate > $today) ? clone $baseDate : clone $today;

    // Se a tarefa tem dia fixo da semana (0=Dom a 6=Sáb) e é semanal/quinzenal:
    // Garante que mesmo que tenha sido remanejada para outro dia na semana, a próxima caia no dia fixo da semana seguinte!
    if ($fixedWeekday !== null && $fixedWeekday >= 0 && $fixedWeekday <= 6 && in_array($recurrenceType, ['weekly', 'biweekly'], true)) {
        $target = clone $refDate;
        do {
            $target->modify('+1 day');
        } while ((int)$target->format('w') !== $fixedWeekday || $target <= $today || $target <= $baseDate);

        if ($recurrenceType === 'biweekly') {
            $target->modify('+1 week');
        }
        return $target->format('Y-m-d');
    }

    switch ($recurrenceType) {
        case 'daily':
            $cursor = clone $refDate;
            $cursor->modify('+1 day');
            return $cursor->format('Y-m-d');

        case 'weekly':
            // Avança semana a semana preservando rigorosamente o dia da semana original
            $cursor = clone $refDate;
            do {
                $cursor->modify('+1 week');
            } while ($cursor <= $today || $cursor <= $baseDate);
            return $cursor->format('Y-m-d');

        case 'biweekly':
            // Avança a cada duas semanas preservando o dia da semana original
            $cursor = clone $refDate;
            do {
                $cursor->modify('+2 weeks');
            } while ($cursor <= $today || $cursor <= $baseDate);
            return $cursor->format('Y-m-d');

        case 'monthly':
            $targetDay = (int)$baseDate->format('d');
            $cursor = clone $refDate;
            do {
                $cursor->modify('first day of next month');
                $maxDays = (int)$cursor->format('t');
                $d = min($targetDay, $maxDays);
                $cursor->setDate((int)$cursor->format('Y'), (int)$cursor->format('m'), $d);
            } while ($cursor <= $today || $cursor <= $baseDate);
            return $cursor->format('Y-m-d');

        case 'yearly':
            $cursor = clone $refDate;
            do {
                $cursor->modify('+1 year');
            } while ($cursor <= $today || $cursor <= $baseDate);
            return $cursor->format('Y-m-d');

        case 'custom':
            $cfg = !empty($recurrenceConfigJson) ? json_decode($recurrenceConfigJson, true) : [];
            $type = $cfg['type'] ?? 'weekdays';

            if ($type === 'monthday' && !empty($cfg['day'])) {
                $targetDay = min(31, max(1, (int)$cfg['day']));
                $target = clone $refDate;
                $maxDays = (int)$target->format('t');
                $target->setDate((int)$target->format('Y'), (int)$target->format('m'), min($targetDay, $maxDays));
                while ($target <= $today || $target <= $baseDate) {
                    $target->modify('first day of next month');
                    $maxDays = (int)$target->format('t');
                    $target->setDate((int)$target->format('Y'), (int)$target->format('m'), min($targetDay, $maxDays));
                }
                return $target->format('Y-m-d');
            } elseif ($type === 'weekdays' && !empty($cfg['days']) && is_array($cfg['days'])) {
                $days = array_map('intval', $cfg['days']);
                $cursor = clone $refDate;
                for ($i = 1; $i <= 60; $i++) {
                    $cursor->modify('+1 day');
                    $w = (int)$cursor->format('w');
                    if (in_array($w, $days, true) && $cursor > $today && $cursor > $baseDate) {
                        return $cursor->format('Y-m-d');
                    }
                }
                do {
                    $cursor->modify('+1 week');
                } while ($cursor <= $today || $cursor <= $baseDate);
                return $cursor->format('Y-m-d');
            } else {
                $cursor = clone $refDate;
                do {
                    $cursor->modify('+1 week');
                } while ($cursor <= $today || $cursor <= $baseDate);
                return $cursor->format('Y-m-d');
            }

        default:
            $cursor = clone $refDate;
            do {
                $cursor->modify('+1 week');
            } while ($cursor <= $today || $cursor <= $baseDate);
            return $cursor->format('Y-m-d');
    }
}

/**
 * Cria a próxima ocorrência da tarefa se ela for recorrente.
 */
/**
 * Garante que a serie recorrente tenha ocorrencias futuras agendadas.
 * Respeita rigorosamente o modo 'duplicate' (copia comentarios e subtarefas) ou 'new' (sem comentarios).
 */
function generate_recurring_series_occurrences(PDO $pdo, int $taskId, array $user, ?int $targetCount = null): array
{
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
    $stmt->execute([':id' => $taskId]);
    $task = $stmt->fetch();

    if (!$task || (int)($task['is_recurring'] ?? 0) !== 1) {
        return [];
    }

    $seriesParentId = !empty($task['parent_task_id']) ? (int)$task['parent_task_id'] : (int)$task['id'];

    // Pegar a tarefa matriz para dados base se for o caso
    if ($seriesParentId !== (int)$task['id']) {
        $parentStmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
        $parentStmt->execute([':id' => $seriesParentId]);
        $parentTask = $parentStmt->fetch();
        if ($parentTask) {
            $task = $parentTask;
        }
    }

    $firstStage = $pdo->query("SELECT id FROM task_stages WHERE slug IN ('todo', 'a_fazer') ORDER BY sort_order ASC LIMIT 1")->fetchColumn();
    if (!$firstStage) {
        $firstStage = $pdo->query("SELECT id FROM task_stages WHERE slug NOT IN ('mentions', 'done', 'concluido') ORDER BY sort_order ASC LIMIT 1")->fetchColumn();
    }
    if (!$firstStage) {
        $firstStage = 1;
    }

    $todayStr = date('Y-m-d');
    $recurrenceType = $task['recurrence_type'] ?? 'weekly';
    $recurrenceConfig = $task['recurrence_config'] ?? null;

    // Se a recorrência for personalizada por dias da semana, remove quaisquer ocorrências futuras órfãs ou geradas incorretamente fora dos dias permitidos
    if ($recurrenceType === 'custom') {
        $cfg = !empty($recurrenceConfig) ? json_decode($recurrenceConfig, true) : [];
        if (($cfg['type'] ?? '') === 'weekdays' && !empty($cfg['days']) && is_array($cfg['days'])) {
            $allowedDays = array_map('intval', $cfg['days']);
            $futureStmt = $pdo->prepare("
                SELECT id, due_date FROM tasks
                WHERE (id = :p OR parent_task_id = :p)
                  AND id != :orig_id
                  AND due_date >= :today
                  AND deleted_at IS NULL
                  AND completed_at IS NULL
            ");
            $futureStmt->execute([':p' => $seriesParentId, ':orig_id' => $taskId, ':today' => $todayStr]);
            $futureOccs = $futureStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($futureOccs as $fOcc) {
                if (!empty($fOcc['due_date'])) {
                    $w = (int)date('w', strtotime($fOcc['due_date']));
                    if (!in_array($w, $allowedDays, true)) {
                        $pdo->prepare("DELETE FROM tasks WHERE id = :id")->execute([':id' => $fOcc['id']]);
                    }
                }
            }
        }
    }

    // Horizonte de ocorrencias por tipo de frequencia:
    if ($targetCount === null || $targetCount <= 4) {
        switch ($recurrenceType) {
            case 'daily':
                $targetCount = 60; // 60 dias continuos
                break;
            case 'weekly':
                $targetCount = 52; // 52 semanas (1 ano completo no mínimo)
                break;
            case 'biweekly':
                $targetCount = 26; // 26 quinzenas (1 ano completo)
                break;
            case 'monthly':
                $targetCount = 12; // 12 meses (1 ano completo)
                break;
            case 'yearly':
                $targetCount = 5;  // 5 anos
                break;
            case 'custom':
                $cfg = !empty($recurrenceConfig) ? json_decode($recurrenceConfig, true) : [];
                $targetCount = (($cfg['type'] ?? '') === 'weekdays') ? 60 : 24;
                break;
            default:
                $targetCount = 52;
                break;
        }
    }

    // Quantas ocorrencias abertas ou futuras ja existem na serie?
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM tasks
        WHERE (id = :p OR parent_task_id = :p)
          AND due_date >= :today
          AND deleted_at IS NULL
          AND completed_at IS NULL
    ");
    $countStmt->execute([':p' => $seriesParentId, ':today' => $todayStr]);
    $existingFutureCount = (int)$countStmt->fetchColumn();

    $toCreate = max(0, $targetCount - $existingFutureCount);
    if ($toCreate === 0) {
        return [];
    }

    // Achar a ultima data de vencimento agendada da serie
    $lastDateStmt = $pdo->prepare("
        SELECT due_date FROM tasks
        WHERE (id = :p OR parent_task_id = :p)
          AND deleted_at IS NULL
          AND due_date IS NOT NULL
        ORDER BY due_date DESC LIMIT 1
    ");
    $lastDateStmt->execute([':p' => $seriesParentId]);
    $lastDueDate = $lastDateStmt->fetchColumn() ?: ($task['due_date'] ?: $todayStr);

    $createdIds = [];
    $fixedWd = isset($task['fixed_weekday']) && $task['fixed_weekday'] !== null && $task['fixed_weekday'] !== '' ? (int)$task['fixed_weekday'] : null;
    $recurrenceMode = in_array($task['recurrence_mode'] ?? '', ['duplicate', 'new'], true) ? $task['recurrence_mode'] : 'duplicate';
    $dueTime = $task['due_time'] ?? null;
    $recordType = (!empty($dueTime) || ($task['record_type'] ?? '') === 'event') ? 'event' : ($task['record_type'] ?? 'task');
    $isCalendarEvent = (!empty($dueTime) || (int)($task['is_calendar_event'] ?? 0) === 1) ? 1 : 0;
    $nowStr = now();

    $ins = $pdo->prepare("
        INSERT INTO tasks (client_id, stage_id, created_by, title, description, priority, due_date,
                           estimated_minutes, tags, source, in_focus, is_bomb, status, sort_order,
                           is_recurring, recurrence_type, recurrence_config, recurrence_mode, parent_task_id,
                           due_time, is_calendar_event, show_in_kanban, is_private, fixed_weekday, record_type,
                           created_at, updated_at)
        VALUES (:client_id, :stage_id, :created_by, :title, :description, :priority, :due_date,
                :estimated_minutes, :tags, 'recurrence', 0, 0, 'open', 10,
                1, :rec_type, :rec_cfg, :rec_mode, :parent_id,
                :due_time, :is_cal, :show_kanban, :is_private, :fixed_wd, :rec_type_record,
                :now, :now2)
    ");

    $assigneesStmt = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id) SELECT :new_id, user_id FROM task_assignees WHERE task_id = :orig_id");
    $checklistStmt = $pdo->prepare("INSERT INTO task_checklists (task_id, title, is_completed, sort_order) SELECT :new_id, title, 0, sort_order FROM task_checklists WHERE task_id = :orig_id");
    $commentsStmt = $pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment_text, created_at) SELECT :new_id, user_id, comment_text, :now FROM task_comments WHERE task_id = :orig_id");

    for ($i = 0; $i < $toCreate; $i++) {
        $nextDueDate = calculate_next_recurrence_date($lastDueDate, $recurrenceType, $recurrenceConfig, $fixedWd);
        if ($nextDueDate <= $lastDueDate) {
            $dt = new DateTime($lastDueDate);
            if ($recurrenceType === 'custom') {
                $cfg = !empty($recurrenceConfig) ? json_decode($recurrenceConfig, true) : [];
                if (($cfg['type'] ?? '') === 'weekdays' && !empty($cfg['days']) && is_array($cfg['days'])) {
                    $days = array_map('intval', $cfg['days']);
                    for ($s = 1; $s <= 14; $s++) {
                        $dt->modify('+1 day');
                        if (in_array((int)$dt->format('w'), $days, true)) {
                            break;
                        }
                    }
                } else {
                    $dt->modify('+1 day');
                }
            } else {
                $dt->modify('+1 day');
            }
            $nextDueDate = $dt->format('Y-m-d');
        }

        $ins->execute([
            ':client_id' => $task['client_id'],
            ':stage_id' => $firstStage,
            ':created_by' => $user['id'] ?? $task['created_by'],
            ':title' => $task['title'],
            ':description' => $task['description'],
            ':priority' => $task['priority'],
            ':due_date' => $nextDueDate,
            ':estimated_minutes' => $task['estimated_minutes'],
            ':tags' => $task['tags'],
            ':rec_type' => $recurrenceType,
            ':rec_cfg' => $recurrenceConfig,
            ':rec_mode' => $recurrenceMode,
            ':parent_id' => $seriesParentId,
            ':due_time' => $dueTime,
            ':is_cal' => $isCalendarEvent,
            ':show_kanban' => (int)($task['show_in_kanban'] ?? 1),
            ':is_private' => (int)($task['is_private'] ?? 0),
            ':fixed_wd' => $fixedWd,
            ':rec_type_record' => $recordType,
            ':now' => $nowStr,
            ':now2' => $nowStr,
        ]);

        $newTaskId = (int)$pdo->lastInsertId();
        $createdIds[] = $newTaskId;

        $assigneesStmt->execute([':new_id' => $newTaskId, ':orig_id' => $seriesParentId]);
        $checklistStmt->execute([':new_id' => $newTaskId, ':orig_id' => $seriesParentId]);

        if ($recurrenceMode === 'duplicate') {
            $commentsStmt->execute([':new_id' => $newTaskId, ':orig_id' => $seriesParentId, ':now' => $nowStr]);
        }

        $lastDueDate = $nextDueDate;
    }

    if (!empty($createdIds) && function_exists('normalize_stage_order')) {
        normalize_stage_order($pdo, (int)$firstStage);
    }

    return $createdIds;
}

function create_next_recurrence_task(PDO $pdo, int $taskId, array $user): ?int
{
    $created = generate_recurring_series_occurrences($pdo, $taskId, $user);
    return !empty($created) ? $created[0] : null;
}

function escalate_overdue_priority(string $originalPriority, ?string $dueDate, ?string $completedAt): array
{
    $levels = ['low', 'medium', 'high', 'urgent'];
    $currentIndex = array_search($originalPriority, $levels);
    if ($currentIndex === false) {
        $currentIndex = 1; // padrão 'medium'
    }

    if (empty($dueDate) || !empty($completedAt)) {
        return [
            'priority' => $originalPriority,
            'original_priority' => $originalPriority,
            'is_overdue' => false,
            'is_escalated' => false,
            'days_overdue' => 0,
        ];
    }

    try {
        $today = new DateTime('today');
        $due = new DateTime($dueDate);
        $diff = (int)$today->diff($due)->format('%r%a');

        if ($diff < 0) {
            $daysOverdue = abs($diff);
            // Sobe 1 grau por dia de atraso até urgent
            $newIndex = min(3, $currentIndex + max(1, $daysOverdue));
            $escalatedPriority = $levels[$newIndex];

            return [
                'priority' => $escalatedPriority,
                'original_priority' => $originalPriority,
                'is_overdue' => true,
                'is_escalated' => ($newIndex > $currentIndex),
                'days_overdue' => $daysOverdue,
            ];
        }
    } catch (Exception $e) {
        // Fallback seguro
    }

    return [
        'priority' => $originalPriority,
        'original_priority' => $originalPriority,
        'is_overdue' => false,
        'is_escalated' => false,
        'days_overdue' => 0,
    ];
}
