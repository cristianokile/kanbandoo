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
function format_due_date_badge(?string $dueDate): array
{
    if (empty($dueDate)) {
        return ['text' => '', 'short' => '', 'label' => '', 'tone' => 'none', 'is_overdue' => false, 'formatted' => ''];
    }

    try {
        $today = new DateTime('today');
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
function calculate_next_recurrence_date(?string $currentDueDate, ?string $recurrenceType, ?string $recurrenceConfigJson): string
{
    $baseDate = !empty($currentDueDate) ? new DateTime($currentDueDate) : new DateTime('today');
    $today = new DateTime('today');

    if ($baseDate < $today) {
        $baseDate = clone $today;
    }

    switch ($recurrenceType) {
        case 'daily':
            $baseDate->modify('+1 day');
            break;

        case 'weekly':
            $baseDate->modify('+1 week');
            break;

        case 'biweekly':
            $baseDate->modify('+2 weeks');
            break;

        case 'monthly':
            $baseDate->modify('+1 month');
            break;

        case 'yearly':
            $baseDate->modify('+1 year');
            break;

        case 'custom':
            $cfg = !empty($recurrenceConfigJson) ? json_decode($recurrenceConfigJson, true) : [];
            $type = $cfg['type'] ?? 'weekdays';

            if ($type === 'monthday' && !empty($cfg['day'])) {
                $targetDay = min(31, max(1, (int)$cfg['day']));
                $target = new DateTime();
                $target->setDate((int)$today->format('Y'), (int)$today->format('m'), $targetDay);
                if ($target <= $today) {
                    $target->modify('+1 month');
                    $target->setDate((int)$target->format('Y'), (int)$target->format('m'), $targetDay);
                }
                return $target->format('Y-m-d');
            } elseif ($type === 'weekdays' && !empty($cfg['days']) && is_array($cfg['days'])) {
                $days = array_map('intval', $cfg['days']);
                $cursor = clone $today;
                for ($i = 1; $i <= 14; $i++) {
                    $cursor->modify('+1 day');
                    $w = (int)$cursor->format('w');
                    if (in_array($w, $days, true)) {
                        return $cursor->format('Y-m-d');
                    }
                }
                $baseDate->modify('+1 week');
            } else {
                $baseDate->modify('+1 week');
            }
            break;

        default:
            $baseDate->modify('+1 week');
            break;
    }

    return $baseDate->format('Y-m-d');
}

/**
 * Cria a próxima ocorrência da tarefa se ela for recorrente.
 */
function create_next_recurrence_task(PDO $pdo, int $taskId, array $user): ?int
{
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
    $stmt->execute([':id' => $taskId]);
    $task = $stmt->fetch();

    if (!$task || (int)($task['is_recurring'] ?? 0) !== 1) {
        return null;
    }

    // Primeiro estágio operacional
    $firstStage = $pdo->query("SELECT id FROM task_stages WHERE slug IN ('todo', 'a_fazer') OR is_system = 1 ORDER BY sort_order ASC LIMIT 1")->fetchColumn();
    if (!$firstStage) {
        $firstStage = (int)$task['stage_id'];
    }

    $nextDueDate = calculate_next_recurrence_date(
        $task['due_date'],
        $task['recurrence_type'] ?? 'weekly',
        $task['recurrence_config'] ?? null
    );

    $nowStr = now();

    $ins = $pdo->prepare("
        INSERT INTO tasks (client_id, stage_id, created_by, title, description, priority, due_date,
                           estimated_minutes, tags, source, in_focus, is_bomb, status, sort_order,
                           is_recurring, recurrence_type, recurrence_config, parent_task_id,
                           created_at, updated_at)
        VALUES (:client_id, :stage_id, :created_by, :title, :description, :priority, :due_date,
                :estimated_minutes, :tags, 'recurrence', 0, 0, 'open', 10,
                1, :rec_type, :rec_cfg, :parent_id,
                :now, :now2)
    ");
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
        ':rec_type' => $task['recurrence_type'],
        ':rec_cfg' => $task['recurrence_config'],
        ':parent_id' => $task['parent_task_id'] ?: $task['id'],
        ':now' => $nowStr,
        ':now2' => $nowStr,
    ]);
    $newTaskId = (int)$pdo->lastInsertId();

    // Copiar responsáveis
    $assignees = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id) SELECT :new_id, user_id FROM task_assignees WHERE task_id = :orig_id");
    $assignees->execute([':new_id' => $newTaskId, ':orig_id' => $taskId]);

    // Copiar subtarefas limpas (desmarcadas)
    $checklists = $pdo->prepare("INSERT INTO task_checklists (task_id, title, is_completed, sort_order) SELECT :new_id, title, 0, sort_order FROM task_checklists WHERE task_id = :orig_id");
    $checklists->execute([':new_id' => $newTaskId, ':orig_id' => $taskId]);

    if (function_exists('normalize_stage_order')) {
        normalize_stage_order($pdo, (int)$firstStage);
    }

    return $newTaskId;
}
