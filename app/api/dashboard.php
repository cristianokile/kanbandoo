<?php
/**
 * KanbanDoo - API de Dashboard, Métricas e Exportação de Relatórios
 */
require_once dirname(__DIR__) . '/bootstrap.php';

require_login();

$pdo = get_pdo();
$user = current_user();

// Parâmetros de Filtro
$period = trim((string)($_GET['period'] ?? 'month'));
$startDate = trim((string)($_GET['start_date'] ?? ''));
$endDate = trim((string)($_GET['end_date'] ?? ''));
$clientId = !empty($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$userId = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$statusFilter = trim((string)($_GET['status'] ?? 'all')); // 'all', 'completed', 'pending'
$export = trim((string)($_GET['export'] ?? '')); // 'csv'

// Determinar Intervalo de Datas
$now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
$filterStart = null;
$filterEnd = null;
$periodLabel = 'Este Mês';

switch ($period) {
    case 'today':
        $filterStart = (clone $now)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
        $filterEnd = (clone $now)->setTime(23, 59, 59)->format('Y-m-d H:i:s');
        $periodLabel = 'Hoje (' . $now->format('d/m/Y') . ')';
        break;

    case 'week':
        $startOfWeek = (clone $now)->modify('this week monday')->setTime(0, 0, 0);
        $endOfWeek = (clone $now)->modify('this week sunday')->setTime(23, 59, 59);
        $filterStart = $startOfWeek->format('Y-m-d H:i:s');
        $filterEnd = $endOfWeek->format('Y-m-d H:i:s');
        $periodLabel = 'Esta Semana (' . $startOfWeek->format('d/m') . ' a ' . $endOfWeek->format('d/m/Y') . ')';
        break;

    case 'last_week':
        $startOfLastWeek = (clone $now)->modify('last week monday')->setTime(0, 0, 0);
        $endOfLastWeek = (clone $now)->modify('last week sunday')->setTime(23, 59, 59);
        $filterStart = $startOfLastWeek->format('Y-m-d H:i:s');
        $filterEnd = $endOfLastWeek->format('Y-m-d H:i:s');
        $periodLabel = 'Semana Passada (' . $startOfLastWeek->format('d/m') . ' a ' . $endOfLastWeek->format('d/m/Y') . ')';
        break;

    case 'biweek':
        $day = (int)$now->format('d');
        if ($day <= 15) {
            $start = (clone $now)->setDate((int)$now->format('Y'), (int)$now->format('m'), 1)->setTime(0, 0, 0);
            $end = (clone $now)->setDate((int)$now->format('Y'), (int)$now->format('m'), 15)->setTime(23, 59, 59);
            $periodLabel = '1ª Quinzena de ' . $now->format('F/Y') . ' (01 a 15)';
        } else {
            $lastDay = (int)$now->format('t');
            $start = (clone $now)->setDate((int)$now->format('Y'), (int)$now->format('m'), 16)->setTime(0, 0, 0);
            $end = (clone $now)->setDate((int)$now->format('Y'), (int)$now->format('m'), $lastDay)->setTime(23, 59, 59);
            $periodLabel = '2ª Quinzena de ' . $now->format('F/Y') . ' (16 a ' . $lastDay . ')';
        }
        $filterStart = $start->format('Y-m-d H:i:s');
        $filterEnd = $end->format('Y-m-d H:i:s');
        break;

    case 'month':
        $start = (clone $now)->setDate((int)$now->format('Y'), (int)$now->format('m'), 1)->setTime(0, 0, 0);
        $lastDay = (int)$now->format('t');
        $end = (clone $now)->setDate((int)$now->format('Y'), (int)$now->format('m'), $lastDay)->setTime(23, 59, 59);
        $filterStart = $start->format('Y-m-d H:i:s');
        $filterEnd = $end->format('Y-m-d H:i:s');
        $periodLabel = 'Este Mês (' . $now->format('m/Y') . ')';
        break;

    case 'last_month':
        $lastMonthObj = (clone $now)->modify('first day of last month');
        $start = (clone $lastMonthObj)->setTime(0, 0, 0);
        $lastDay = (int)$lastMonthObj->format('t');
        $end = (clone $lastMonthObj)->setDate((int)$lastMonthObj->format('Y'), (int)$lastMonthObj->format('m'), $lastDay)->setTime(23, 59, 59);
        $filterStart = $start->format('Y-m-d H:i:s');
        $filterEnd = $end->format('Y-m-d H:i:s');
        $periodLabel = 'Mês Anterior (' . $lastMonthObj->format('m/Y') . ')';
        break;

    case 'year':
        $start = (clone $now)->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0, 0);
        $end = (clone $now)->setDate((int)$now->format('Y'), 12, 31)->setTime(23, 59, 59);
        $filterStart = $start->format('Y-m-d H:i:s');
        $filterEnd = $end->format('Y-m-d H:i:s');
        $periodLabel = 'Ano Atual (' . $now->format('Y') . ')';
        break;

    case 'custom':
        if (!empty($startDate) && !empty($endDate)) {
            $filterStart = $startDate . ' 00:00:00';
            $filterEnd = $endDate . ' 23:59:59';
            $periodLabel = 'Período (' . date('d/m/Y', strtotime($startDate)) . ' a ' . date('d/m/Y', strtotime($endDate)) . ')';
        } else {
            $period = 'all';
            $periodLabel = 'Todo o Período';
        }
        break;

    case 'all':
    default:
        $filterStart = null;
        $filterEnd = null;
        $periodLabel = 'Todo o Período';
        break;
}

// Montar Query de Tarefas
$where = ["t.deleted_at IS NULL"];
$params = [];

if ($filterStart !== null && $filterEnd !== null) {
    // Filtro inteligente de período: tarefa criada, concluída, com vencimento ou movimentada com tempo no período
    $startDateOnly = substr($filterStart, 0, 10);
    $endDateOnly = substr($filterEnd, 0, 10);
    $where[] = "(
        (t.created_at >= :dt_start AND t.created_at <= :dt_end)
        OR (t.completed_at >= :dt_start AND t.completed_at <= :dt_end)
        OR (t.due_date >= :d_start AND t.due_date <= :d_end)
        OR (t.updated_at >= :dt_start AND t.updated_at <= :dt_end AND t.total_seconds > 0)
    )";
    $params[':dt_start'] = $filterStart;
    $params[':dt_end'] = $filterEnd;
    $params[':d_start'] = $startDateOnly;
    $params[':d_end'] = $endDateOnly;
}

if ($clientId !== null) {
    $where[] = "t.client_id = :client_id";
    $params[':client_id'] = $clientId;
}

if ($userId !== null) {
    $where[] = "EXISTS (SELECT 1 FROM task_assignees ta WHERE ta.task_id = t.id AND ta.user_id = :user_id)";
    $params[':user_id'] = $userId;
}

if ($statusFilter === 'completed') {
    $where[] = "(t.completed_at IS NOT NULL OR s.slug = 'done')";
} elseif ($statusFilter === 'pending') {
    $where[] = "(t.completed_at IS NULL AND s.slug != 'done')";
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT t.id, t.title, t.priority, t.due_date, t.due_time, t.total_seconds,
           t.is_timer_running, t.timer_started_at, t.estimated_minutes,
           t.completed_at, t.created_at, t.updated_at, t.tags,
           t.client_id, c.company_name as client_name,
           t.stage_id, s.name as stage_name, s.slug as stage_slug, s.color as stage_color
    FROM tasks t
    LEFT JOIN clients c ON c.id = t.client_id
    LEFT JOIN task_stages s ON s.id = t.stage_id
    WHERE {$whereSql}
    ORDER BY t.total_seconds DESC, t.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rawTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Carregar Responsáveis
$allTaskIds = array_column($rawTasks, 'id');
$assigneesMap = [];
if (!empty($allTaskIds)) {
    $inPlaceholders = implode(',', array_fill(0, count($allTaskIds), '?'));
    $assigneesStmt = $pdo->prepare("
        SELECT ta.task_id, u.id as user_id, u.full_name, u.role
        FROM task_assignees ta
        JOIN users u ON u.id = ta.user_id
        WHERE ta.task_id IN ({$inPlaceholders})
        ORDER BY u.full_name ASC
    ");
    $assigneesStmt->execute($allTaskIds);
    while ($row = $assigneesStmt->fetch(PDO::FETCH_ASSOC)) {
        $assigneesMap[$row['task_id']][] = [
            'id' => (int)$row['user_id'],
            'name' => $row['full_name'],
            'initials' => mb_strtoupper(mb_substr($row['full_name'], 0, 2, 'UTF-8')),
            'role' => $row['role']
        ];
    }
}

// Funções Auxiliares de Formatação
function format_seconds(int $sec): string {
    $hours = floor($sec / 3600);
    $minutes = floor(($sec % 3600) / 60);
    $seconds = $sec % 60;
    if ($hours > 0) {
        return sprintf('%dh %02dm', $hours, $minutes);
    }
    if ($minutes > 0) {
        return sprintf('%dm %02ds', $minutes, $seconds);
    }
    return sprintf('%ds', $seconds);
}

function format_hms(int $sec): string {
    $hours = floor($sec / 3600);
    $minutes = floor(($sec % 3600) / 60);
    $seconds = $sec % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

// Processar Métricas
$totalSeconds = 0;
$totalEstimatedMinutes = 0;
$totalTasks = count($rawTasks);
$completedTasks = 0;
$onTimeTasks = 0;
$tasksWithDueDateCount = 0;

$clientStats = [];
$userStats = [];
$priorityStats = [
    'urgent' => ['count' => 0, 'seconds' => 0, 'label' => 'Urgente', 'color' => '#f43f5e'],
    'high'   => ['count' => 0, 'seconds' => 0, 'label' => 'Alta',    'color' => '#f97316'],
    'medium' => ['count' => 0, 'seconds' => 0, 'label' => 'Média',   'color' => '#eab308'],
    'low'    => ['count' => 0, 'seconds' => 0, 'label' => 'Baixa',   'color' => '#3b82f6'],
];

$processedTasks = [];

foreach ($rawTasks as $t) {
    $taskId = (int)$t['id'];
    $sec = (int)($t['total_seconds'] ?? 0);
    
    // Adicionar tempo do cronômetro rodando agora
    $isRunning = (int)($t['is_timer_running'] ?? 0) === 1;
    if ($isRunning && !empty($t['timer_started_at'])) {
        $started = strtotime($t['timer_started_at']);
        if ($started > 0) {
            $sec += max(0, time() - $started);
        }
    }

    $estimatedSec = ((int)($t['estimated_minutes'] ?? 0)) * 60;
    $totalSeconds += $sec;
    $totalEstimatedMinutes += (int)($t['estimated_minutes'] ?? 0);

    $isCompleted = !empty($t['completed_at']) || ($t['stage_slug'] === 'done');
    if ($isCompleted) {
        $completedTasks++;
        if (!empty($t['due_date'])) {
            $tasksWithDueDateCount++;
            $dueEnd = strtotime($t['due_date'] . ' 23:59:59');
            $completedTime = !empty($t['completed_at']) ? strtotime($t['completed_at']) : time();
            if ($completedTime <= $dueEnd) {
                $onTimeTasks++;
            }
        }
    }

    // Prioridade
    $p = $t['priority'] ?? 'medium';
    if (!isset($priorityStats[$p])) {
        $priorityStats[$p] = ['count' => 0, 'seconds' => 0, 'label' => ucfirst($p), 'color' => '#64748b'];
    }
    $priorityStats[$p]['count']++;
    $priorityStats[$p]['seconds'] += $sec;

    // Cliente
    $cId = !empty($t['client_id']) ? (int)$t['client_id'] : 0;
    $cName = !empty($t['client_name']) ? $t['client_name'] : 'Interno / Sem Cliente';
    if (!isset($clientStats[$cId])) {
        $clientStats[$cId] = [
            'id' => $cId,
            'name' => $cName,
            'seconds' => 0,
            'estimated_seconds' => 0,
            'tasks_count' => 0,
            'completed_count' => 0
        ];
    }
    $clientStats[$cId]['seconds'] += $sec;
    $clientStats[$cId]['estimated_seconds'] += $estimatedSec;
    $clientStats[$cId]['tasks_count']++;
    if ($isCompleted) $clientStats[$cId]['completed_count']++;

    // Usuários Responsáveis
    $assignees = $assigneesMap[$taskId] ?? [];
    if (empty($assignees)) {
        $uId = 0;
        $uName = 'Não Atribuído';
        if (!isset($userStats[$uId])) {
            $userStats[$uId] = ['id' => 0, 'name' => $uName, 'initials' => 'NA', 'seconds' => 0, 'tasks_count' => 0, 'completed_count' => 0];
        }
        $userStats[$uId]['seconds'] += $sec;
        $userStats[$uId]['tasks_count']++;
        if ($isCompleted) $userStats[$uId]['completed_count']++;
    } else {
        foreach ($assignees as $u) {
            $uId = $u['id'];
            if (!isset($userStats[$uId])) {
                $userStats[$uId] = ['id' => $uId, 'name' => $u['name'], 'initials' => $u['initials'], 'seconds' => 0, 'tasks_count' => 0, 'completed_count' => 0];
            }
            $userStats[$uId]['seconds'] += $sec;
            $userStats[$uId]['tasks_count']++;
            if ($isCompleted) $userStats[$uId]['completed_count']++;
        }
    }

    $processedTasks[] = [
        'id' => $taskId,
        'title' => $t['title'],
        'client_id' => $cId,
        'client_name' => $cName,
        'stage_id' => (int)$t['stage_id'],
        'stage_name' => $t['stage_name'] ?? 'Sem Coluna',
        'stage_color' => $t['stage_color'] ?? '#6366f1',
        'stage_slug' => $t['stage_slug'] ?? '',
        'priority' => $p,
        'priority_label' => $priorityStats[$p]['label'] ?? ucfirst($p),
        'due_date' => $t['due_date'],
        'due_date_formatted' => !empty($t['due_date']) ? date('d/m/Y', strtotime($t['due_date'])) : 'Sem prazo',
        'is_completed' => $isCompleted,
        'completed_at' => $t['completed_at'],
        'completed_at_formatted' => !empty($t['completed_at']) ? date('d/m/Y H:i', strtotime($t['completed_at'])) : null,
        'created_at' => $t['created_at'],
        'created_at_formatted' => !empty($t['created_at']) ? date('d/m/Y', strtotime($t['created_at'])) : '',
        'total_seconds' => $sec,
        'time_formatted' => format_seconds($sec),
        'time_hms' => format_hms($sec),
        'hours_decimal' => round($sec / 3600, 2),
        'is_timer_running' => $isRunning,
        'estimated_minutes' => (int)($t['estimated_minutes'] ?? 0),
        'estimated_formatted' => ((int)($t['estimated_minutes'] ?? 0) > 0) ? format_seconds($estimatedSec) : '-',
        'is_over_estimate' => ($estimatedSec > 0 && $sec > $estimatedSec),
        'assignees' => $assignees
    ];
}

// Ordenar Clientes por Segundos DESC
uasort($clientStats, function($a, $b) {
    return $b['seconds'] <=> $a['seconds'];
});

$clientStatsList = [];
foreach ($clientStats as $cs) {
    $pct = $totalSeconds > 0 ? round(($cs['seconds'] / $totalSeconds) * 100, 1) : 0;
    $clientStatsList[] = [
        'id' => $cs['id'],
        'name' => $cs['name'],
        'seconds' => $cs['seconds'],
        'time_formatted' => format_seconds($cs['seconds']),
        'time_hms' => format_hms($cs['seconds']),
        'hours_decimal' => round($cs['seconds'] / 3600, 2),
        'percentage' => $pct,
        'tasks_count' => $cs['tasks_count'],
        'completed_count' => $cs['completed_count'],
        'estimated_seconds' => $cs['estimated_seconds'],
        'estimated_formatted' => format_seconds($cs['estimated_seconds'])
    ];
}

// Ordenar Usuários por Segundos DESC
uasort($userStats, function($a, $b) {
    return $b['seconds'] <=> $a['seconds'];
});

$userStatsList = [];
foreach ($userStats as $us) {
    $pct = $totalSeconds > 0 ? round(($us['seconds'] / $totalSeconds) * 100, 1) : 0;
    $userStatsList[] = [
        'id' => $us['id'],
        'name' => $us['name'],
        'initials' => $us['initials'],
        'seconds' => $us['seconds'],
        'time_formatted' => format_seconds($us['seconds']),
        'hours_decimal' => round($us['seconds'] / 3600, 2),
        'percentage' => $pct,
        'tasks_count' => $us['tasks_count'],
        'completed_count' => $us['completed_count']
    ];
}

// Tarefas Mais Demoradas (Top 10)
$longestTasks = array_slice($processedTasks, 0, 10);

// Resumo Executivo
$completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
$onTimeRate = $tasksWithDueDateCount > 0 ? round(($onTimeTasks / $tasksWithDueDateCount) * 100, 1) : 100;
$avgSecondsPerTask = $totalTasks > 0 ? (int)round($totalSeconds / $totalTasks) : 0;
$totalEstimatedSeconds = $totalEstimatedMinutes * 60;
$timeVarianceSeconds = $totalSeconds - $totalEstimatedSeconds;

$summary = [
    'period' => $period,
    'period_label' => $periodLabel,
    'start_date' => $filterStart,
    'end_date' => $filterEnd,
    'total_seconds' => $totalSeconds,
    'total_hours_formatted' => format_seconds($totalSeconds),
    'total_hms' => format_hms($totalSeconds),
    'total_hours_decimal' => round($totalSeconds / 3600, 2),
    'total_estimated_seconds' => $totalEstimatedSeconds,
    'total_estimated_formatted' => format_seconds($totalEstimatedSeconds),
    'total_estimated_decimal' => round($totalEstimatedSeconds / 3600, 2),
    'time_variance_seconds' => $timeVarianceSeconds,
    'time_variance_formatted' => ($timeVarianceSeconds >= 0 ? '+' : '-') . format_seconds(abs($timeVarianceSeconds)),
    'total_tasks' => $totalTasks,
    'completed_tasks' => $completedTasks,
    'pending_tasks' => $totalTasks - $completedTasks,
    'completion_rate' => $completionRate,
    'on_time_rate' => $onTimeRate,
    'avg_seconds_per_task' => $avgSecondsPerTask,
    'avg_time_formatted' => format_seconds($avgSecondsPerTask),
    'active_clients_count' => count($clientStats),
];

// Se solicitado EXPORTAÇÃO CSV
if ($export === 'csv') {
    $filename = 'relatorio_atividades_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Saída com UTF-8 BOM para Excel ler acentuação corretamente
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Cabeçalho de Metadados
    fputcsv($output, ['KANBANDOO - RELATÓRIO DE ATIVIDADES E TEMPO'], ';');
    fputcsv($output, ['Período:', $periodLabel], ';');
    fputcsv($output, ['Gerado em:', date('d/m/Y H:i:s')], ';');
    fputcsv($output, ['Total de Horas:', $summary['total_hours_formatted'], '(' . $summary['total_hours_decimal'] . 'h decimais)'], ';');
    fputcsv($output, ['Total de Tarefas:', $totalTasks, 'Concluídas:', $completedTasks], ';');
    fputcsv($output, [], ';');

    // Cabeçalho das Colunas
    fputcsv($output, [
        'ID',
        'Tarefa / Atividade',
        'Cliente / Empresa',
        'Coluna / Status',
        'Prioridade',
        'Responsáveis',
        'Data Criação',
        'Data Limite',
        'Data Conclusão',
        'Tempo Estimado',
        'Tempo Dedicado (HMS)',
        'Horas Decimais',
        'Status Entrega'
    ], ';');

    foreach ($processedTasks as $task) {
        $assigneesStr = !empty($task['assignees'])
            ? implode(', ', array_column($task['assignees'], 'name'))
            : 'Não Atribuído';

        $deliveryStatus = $task['is_completed'] ? 'Concluída' : 'Em Andamento';
        if ($task['is_completed'] && !empty($task['due_date'])) {
            $deliveryStatus .= (strtotime($task['completed_at'] ?? 'now') <= strtotime($task['due_date'] . ' 23:59:59'))
                ? ' (No Prazo)'
                : ' (Com Atraso)';
        }

        fputcsv($output, [
            $task['id'],
            $task['title'],
            $task['client_name'],
            $task['stage_name'],
            $task['priority_label'],
            $assigneesStr,
            $task['created_at_formatted'],
            $task['due_date_formatted'],
            $task['completed_at_formatted'] ?? '-',
            $task['estimated_formatted'],
            $task['time_hms'],
            str_replace('.', ',', (string)$task['hours_decimal']),
            $deliveryStatus
        ], ';');
    }

    fclose($output);
    exit;
}

// Retorno JSON para a Interface
json_response([
    'success' => true,
    'summary' => $summary,
    'by_client' => $clientStatsList,
    'by_user' => $userStatsList,
    'by_priority' => array_values($priorityStats),
    'longest_tasks' => $longestTasks,
    'tasks' => $processedTasks
]);
