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
        return ['text' => '', 'label' => '', 'tone' => 'none', 'is_overdue' => false, 'formatted' => ''];
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
                'label' => "Prazo em $formatted, atrasada em $abs dia(s)",
                'tone' => 'overdue',
                'is_overdue' => true,
                'formatted' => $due->format('d/m'),
            ];
        }
        if ($days === 0) {
            return [
                'text' => 'Entrega hoje',
                'label' => "Entrega hoje, $formatted",
                'tone' => 'today',
                'is_overdue' => false,
                'formatted' => $due->format('d/m'),
            ];
        }
        if ($days === 1) {
            return [
                'text' => 'Amanhã',
                'label' => "Entrega amanhã, $formatted",
                'tone' => 'soon',
                'is_overdue' => false,
                'formatted' => $due->format('d/m'),
            ];
        }
        if ($days <= 7) {
            return [
                'text' => "Em $days dias",
                'label' => "Entrega em $formatted",
                'tone' => 'soon',
                'is_overdue' => false,
                'formatted' => $due->format('d/m'),
            ];
        }

        return [
            'text' => $due->format('d/m'),
            'label' => "Entrega em $formatted",
            'tone' => 'later',
            'is_overdue' => false,
            'formatted' => $due->format('d/m'),
        ];
    } catch (Exception $e) {
        return ['text' => $dueDate, 'label' => $dueDate, 'tone' => 'later', 'is_overdue' => false, 'formatted' => $dueDate];
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
