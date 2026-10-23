<?php
/**
 * KanbanDoo - Painel de Demandas para a Coordenadoria
 *
 * Link público, somente leitura, dividido nas 4 seções solicitadas:
 * 1. A Fazer
 * 2. Em Andamento
 * 3. Revisão / Aguardando
 * 4. Concluído
 *
 * Filtros de pauta integrados:
 * - Período: Hoje (Padrão), Esta Semana, Este Mês, Todas
 * - Cliente / Empresa
 * - Prioridade
 * - Busca textual instantânea (live search)
 *
 * Tarefas particulares (is_private = 1) NUNCA são exibidas aqui.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

$token = trim((string)($routeParams['token'] ?? $_GET['token'] ?? ''));

$pdo = get_pdo();
$coordUser = null;

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT id, username, full_name, email, avatar_path, role FROM users WHERE public_token = :t LIMIT 1');
    $stmt->execute([':t' => $token]);
    $coordUser = $stmt->fetch();
}

if (!$coordUser) {
    http_response_code(404);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel não encontrado &bull; KanbanDoo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 text-center shadow-2xl">
        <div class="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mx-auto mb-4">
            <i data-lucide="shield-alert" class="w-7 h-7"></i>
        </div>
        <h1 class="text-xl font-bold text-white mb-2">Painel não encontrado</h1>
        <p class="text-xs text-slate-400 mb-6">O link que você acessou é inválido, expirou ou o acesso da coordenadoria foi desativado. Solicite um novo link ao responsável pelas demandas.</p>
        <a href="<?= url('/entrar') ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs text-slate-200 font-semibold transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Ir para o KanbanDoo</span>
        </a>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
<?php
    exit;
}

// Buscar demandas do profissional (ignora particulares: is_private = 1)
$tasksStmt = $pdo->prepare("
    SELECT DISTINCT t.id, t.title, t.description, t.priority, t.due_date, t.original_due_date, t.due_time,
           t.completed_at, t.created_at, t.is_timer_running, t.timer_started_at, t.total_seconds,
           t.tags, t.estimated_minutes, t.is_bomb,
           c.id AS client_id, c.company_name AS client_company,
           s.slug AS stage_slug, s.name AS stage_name, s.color AS stage_color,
           (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id) AS checklist_total,
           (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id AND is_completed = 1) AS checklist_done
    FROM tasks t
    LEFT JOIN clients c ON t.client_id = c.id
    LEFT JOIN task_stages s ON t.stage_id = s.id
    LEFT JOIN task_assignees ta ON ta.task_id = t.id
    WHERE (ta.user_id = :uid OR (t.created_by = :uid AND NOT EXISTS(SELECT 1 FROM task_assignees WHERE task_id = t.id)))
      AND t.deleted_at IS NULL
      AND t.archived_at IS NULL
      AND (t.is_private IS NULL OR t.is_private = 0)
      AND (t.is_calendar_event = 0 OR t.show_in_kanban = 1)
    ORDER BY t.is_bomb DESC, t.sort_order ASC, t.id DESC
");
$tasksStmt->execute([':uid' => $coordUser['id']]);
$rawTasks = $tasksStmt->fetchAll();

$todoTasks = [];
$doingTasks = [];
$reviewTasks = [];
$doneTasks = [];

$clientsMap = [];
$hasInternalTasks = false;
$overdueCount = 0;

foreach ($rawTasks as $t) {
    $t['due_badge'] = format_due_date_badge($t['due_date'], $t['original_due_date'] ?? null);
    $t['is_overdue'] = !empty($t['due_badge']['is_overdue']) && empty($t['completed_at']);
    if ($t['is_overdue']) {
        $overdueCount++;
    }

    if (!empty($t['client_company'])) {
        $clientsMap[$t['client_company']] = $t['client_company'];
    } else {
        $hasInternalTasks = true;
    }

    $isCompleted = !empty($t['completed_at']) || $t['stage_slug'] === 'done';
    if ($isCompleted) {
        $t['stage_group'] = 'done';
        $doneTasks[] = $t;
    } elseif ($t['stage_slug'] === 'review' || $t['stage_slug'] === 'aguardando' || stripos($t['stage_name'] ?? '', 'revis') !== false || stripos($t['stage_name'] ?? '', 'aguard') !== false) {
        $t['stage_group'] = 'review';
        $reviewTasks[] = $t;
    } elseif ($t['stage_slug'] === 'in_progress' || $t['stage_slug'] === 'doing' || (int)($t['is_timer_running'] ?? 0) === 1 || stripos($t['stage_name'] ?? '', 'andamento') !== false || stripos($t['stage_name'] ?? '', 'fazendo') !== false) {
        $t['stage_group'] = 'doing';
        $doingTasks[] = $t;
    } else {
        $t['stage_group'] = 'todo';
        $todoTasks[] = $t;
    }
}

ksort($clientsMap, SORT_NATURAL | SORT_FLAG_CASE);
$totalTasks = count($rawTasks);

// Período inicial via GET ou padrão 'today'
$initialPeriod = in_array($_GET['period'] ?? '', ['today', 'week', 'month', 'all'], true) ? $_GET['period'] : 'today';
$initialClient = trim((string)($_GET['client'] ?? 'all'));
$initialPriority = in_array($_GET['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $_GET['priority'] : 'all';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pauta de Demandas • <?= htmlspecialchars($coordUser['full_name']) ?> &bull; Coordenadoria</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.4); }
        .custom-scroll::-webkit-scrollbar-thumb { background: rgba(51, 65, 85, 0.6); border-radius: 9999px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: rgba(71, 85, 105, 0.9); }
        .period-tab.is-active {
            background-color: #4f46e5;
            color: #ffffff;
            font-weight: 700;
            box-shadow: 0 4px 14px -1px rgba(79, 70, 229, 0.4);
        }
    </style>
</head>
<body class="bg-[#0b0f19] text-slate-100 min-h-screen flex flex-col antialiased selection:bg-indigo-500 selection:text-white">

    <!-- Top Navigation Header -->
    <header class="border-b border-slate-800/80 bg-slate-900/70 backdrop-blur-xl sticky top-0 z-40">
        <div class="max-w-[1700px] mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4">
            
            <!-- Perfil do Profissional -->
            <div class="flex items-center gap-3.5 min-w-0">
                <?php if (!empty($coordUser['avatar_path'])): ?>
                    <img src="<?= htmlspecialchars($coordUser['avatar_path']) ?>" alt="<?= htmlspecialchars($coordUser['full_name']) ?>" class="w-12 h-12 rounded-2xl object-cover border-2 border-indigo-500/40 shadow-lg shadow-indigo-500/10">
                <?php else: ?>
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center font-bold text-white text-lg shadow-lg shadow-indigo-500/20 border border-indigo-400/30">
                        <?= strtoupper(mb_substr($coordUser['full_name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-base sm:text-lg font-bold text-white truncate"><?= htmlspecialchars($coordUser['full_name']) ?></h1>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                            <i data-lucide="shield-check" class="w-3 h-3 text-indigo-400"></i> Painel da Coordenadoria
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-800 text-slate-400 border border-slate-700">
                            <i data-lucide="eye" class="w-3 h-3"></i> Somente Leitura
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-400">
                        <span class="truncate"><?= htmlspecialchars($coordUser['email']) ?></span>
                        <span>&bull;</span>
                        <span class="text-slate-400 flex items-center gap-1 whitespace-nowrap">
                            <i data-lucide="clock" class="w-3 h-3 text-slate-400"></i>
                            Hoje: <?= date('d/m/Y') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Busca Instantânea no Topo -->
            <div class="flex items-center gap-3">
                <div class="relative w-48 sm:w-72">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input type="text" id="coordSearchInput" oninput="applyAllFilters()" placeholder="Buscar demandas, clientes, tags..." class="w-full pl-9 pr-8 py-2 bg-slate-900/90 border border-slate-700/80 rounded-xl text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition shadow-inner">
                    <button type="button" id="btnClearSearch" onclick="clearCoordSearch()" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-white text-xs">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
                <button type="button" onclick="location.reload()" class="p-2 text-slate-400 hover:text-white bg-slate-800/80 hover:bg-slate-700 border border-slate-700 rounded-xl transition" title="Recarregar demandas">
                    <i data-lucide="rotate-cw" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-[1700px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-1 flex flex-col space-y-6">

        <!-- Barra Executiva de Filtros da Pauta -->
        <section class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 shadow-xl space-y-3.5">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                
                <!-- 1. Filtro de Período (Segmented Control com HOJE como padrão) -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold text-slate-400 flex items-center gap-1.5 mr-1">
                        <i data-lucide="calendar" class="w-4 h-4 text-indigo-400"></i> Período:
                    </span>
                    <div class="inline-flex p-1 bg-slate-950/80 border border-slate-800 rounded-xl gap-1">
                        <button type="button" onclick="setPeriodFilter('today')" id="btnPeriod-today" class="period-tab px-3.5 py-1.5 rounded-lg text-xs font-medium text-slate-400 hover:text-white transition flex items-center gap-1.5">
                            <span>Hoje</span>
                            <span class="px-1.5 py-0.2 bg-indigo-500/20 text-indigo-300 text-[10px] rounded-md font-semibold">Padrão</span>
                        </button>
                        <button type="button" onclick="setPeriodFilter('week')" id="btnPeriod-week" class="period-tab px-3.5 py-1.5 rounded-lg text-xs font-medium text-slate-400 hover:text-white transition flex items-center gap-1">
                            <span>Esta Semana</span>
                        </button>
                        <button type="button" onclick="setPeriodFilter('month')" id="btnPeriod-month" class="period-tab px-3.5 py-1.5 rounded-lg text-xs font-medium text-slate-400 hover:text-white transition flex items-center gap-1">
                            <span>Este Mês</span>
                        </button>
                        <button type="button" onclick="setPeriodFilter('all')" id="btnPeriod-all" class="period-tab px-3.5 py-1.5 rounded-lg text-xs font-medium text-slate-400 hover:text-white transition flex items-center gap-1">
                            <span>Todas</span>
                        </button>
                    </div>
                </div>

                <!-- 2. Filtro por Cliente e 3. Filtro por Prioridade -->
                <div class="flex items-center gap-3 flex-wrap">
                    
                    <!-- Select de Cliente -->
                    <div class="flex items-center gap-1.5 min-w-[200px]">
                        <span class="text-xs font-semibold text-slate-400 flex items-center gap-1">
                            <i data-lucide="building-2" class="w-3.5 h-3.5 text-indigo-400"></i> Cliente:
                        </span>
                        <select id="coordClientSelect" onchange="applyAllFilters()" class="w-full bg-slate-950/80 border border-slate-700/80 text-xs text-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500 transition">
                            <option value="all">Todos os clientes (<?= count($clientsMap) + ($hasInternalTasks ? 1 : 0) ?>)</option>
                            <?php if ($hasInternalTasks): ?>
                                <option value="__internal__">Demanda Interna</option>
                            <?php endif; ?>
                            <?php foreach ($clientsMap as $cName): ?>
                                <option value="<?= htmlspecialchars($cName) ?>" <?= $initialClient === $cName ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Select de Prioridade -->
                    <div class="flex items-center gap-1.5 min-w-[170px]">
                        <span class="text-xs font-semibold text-slate-400 flex items-center gap-1">
                            <i data-lucide="flag" class="w-3.5 h-3.5 text-indigo-400"></i> Prioridade:
                        </span>
                        <select id="coordPrioritySelect" onchange="applyAllFilters()" class="w-full bg-slate-950/80 border border-slate-700/80 text-xs text-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500 transition">
                            <option value="all">Todas as prioridades</option>
                            <option value="urgent" <?= $initialPriority === 'urgent' ? 'selected' : '' ?>>🔴 Urgente</option>
                            <option value="high" <?= $initialPriority === 'high' ? 'selected' : '' ?>>🟠 Alta</option>
                            <option value="medium" <?= $initialPriority === 'medium' ? 'selected' : '' ?>>🔵 Média</option>
                            <option value="low" <?= $initialPriority === 'low' ? 'selected' : '' ?>>⚪ Baixa</option>
                        </select>
                    </div>

                    <!-- Botão Limpar Filtros -->
                    <button type="button" id="btnResetFilters" onclick="resetAllFilters()" class="hidden px-3 py-2 text-xs font-medium text-slate-400 hover:text-white bg-slate-800/80 hover:bg-slate-700 border border-slate-700 rounded-xl transition flex items-center gap-1" title="Restaurar visualização padrão">
                        <i data-lucide="filter-x" class="w-3.5 h-3.5"></i>
                        <span>Limpar</span>
                    </button>
                </div>
            </div>

            <!-- Faixa informativa do filtro ativo -->
            <div class="pt-2 border-t border-slate-800/70 flex items-center justify-between text-xs text-slate-400 flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                    <span id="coordFilterStatusText">Exibindo pauta de <strong class="text-white">Hoje</strong></span>
                    <span id="coordFilterMatchBadge" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-800 text-slate-300 border border-slate-700">0 tarefas</span>
                </div>
                <div class="text-[11px] text-slate-500">
                    Dica: selecione <strong>Esta Semana</strong> ou <strong>Todas</strong> para planejar os próximos períodos.
                </div>
            </div>
        </section>

        <!-- Top Metrics Bar (Contadores Dinâmicos) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-800/80 border border-slate-700 text-slate-300 flex items-center justify-center">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">Total</div>
                    <div id="stat-total" class="text-lg font-bold text-white"><?= $totalTasks ?></div>
                </div>
            </div>

            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center">
                    <i data-lucide="circle" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">A Fazer</div>
                    <div id="stat-todo" class="text-lg font-bold text-amber-400"><?= count($todoTasks) ?></div>
                </div>
            </div>

            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center">
                    <i data-lucide="play-circle" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">Em Andamento</div>
                    <div id="stat-doing" class="text-lg font-bold text-blue-400"><?= count($doingTasks) ?></div>
                </div>
            </div>

            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">Revisão / Aguardando</div>
                    <div id="stat-review" class="text-lg font-bold text-purple-400"><?= count($reviewTasks) ?></div>
                </div>
            </div>

            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">Concluídas</div>
                    <div id="stat-done" class="text-lg font-bold text-emerald-400"><?= count($doneTasks) ?></div>
                </div>
            </div>

            <div id="cardOverdueMetric" class="bg-slate-900/70 border <?= $overdueCount > 0 ? 'border-rose-500/40 bg-rose-950/20' : 'border-slate-800' ?> rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl <?= $overdueCount > 0 ? 'bg-rose-500/20 border border-rose-500/30 text-rose-400' : 'bg-slate-800/80 border border-slate-700 text-slate-400' ?> flex items-center justify-center">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">Atrasadas</div>
                    <div id="stat-overdue" class="text-lg font-bold <?= $overdueCount > 0 ? 'text-rose-400' : 'text-slate-400' ?>"><?= $overdueCount ?></div>
                </div>
            </div>
        </div>

        <!-- 4 Seções Executivas de Demandas -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 items-start">

            <!-- 1. A Fazer -->
            <section class="bg-slate-900/60 border border-slate-800/90 rounded-3xl p-4 shadow-xl flex flex-col min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 shadow-sm shadow-amber-400/50"></span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">A Fazer</h2>
                    </div>
                    <span id="count-todo" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-300 border border-amber-500/20">
                        <?= count($todoTasks) ?>
                    </span>
                </div>

                <div id="col-todo" class="space-y-3 flex-1">
                    <?php if (empty($todoTasks)): ?>
                        <div class="empty-placeholder py-12 text-center text-xs text-slate-500">
                            <i data-lucide="inbox" class="w-8 h-8 text-slate-600 mx-auto mb-2 opacity-50"></i>
                            Nenhuma demanda a fazer
                        </div>
                    <?php else: ?>
                        <?php foreach ($todoTasks as $t): ?>
                            <?= renderCoordCard($t) ?>
                        <?php endforeach; ?>
                        <div class="empty-placeholder hidden py-12 text-center text-xs text-slate-500">
                            <i data-lucide="filter" class="w-8 h-8 text-slate-600 mx-auto mb-2 opacity-50"></i>
                            Nenhuma demanda nesta etapa para o filtro selecionado
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 2. Em Andamento -->
            <section class="bg-slate-900/60 border border-slate-800/90 rounded-3xl p-4 shadow-xl flex flex-col min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-400 shadow-sm shadow-blue-400/50 animate-pulse"></span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">Em Andamento</h2>
                    </div>
                    <span id="count-doing" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-500/10 text-blue-300 border border-blue-500/20">
                        <?= count($doingTasks) ?>
                    </span>
                </div>

                <div id="col-doing" class="space-y-3 flex-1">
                    <?php if (empty($doingTasks)): ?>
                        <div class="empty-placeholder py-12 text-center text-xs text-slate-500">
                            <i data-lucide="play" class="w-8 h-8 text-slate-600 mx-auto mb-2 opacity-50"></i>
                            Nenhuma demanda em andamento no momento
                        </div>
                    <?php else: ?>
                        <?php foreach ($doingTasks as $t): ?>
                            <?= renderCoordCard($t) ?>
                        <?php endforeach; ?>
                        <div class="empty-placeholder hidden py-12 text-center text-xs text-slate-500">
                            <i data-lucide="filter" class="w-8 h-8 text-slate-600 mx-auto mb-2 opacity-50"></i>
                            Nenhuma demanda nesta etapa para o filtro selecionado
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 3. Revisão / Aguardando -->
            <section class="bg-slate-900/60 border border-slate-800/90 rounded-3xl p-4 shadow-xl flex flex-col min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-400 shadow-sm shadow-purple-400/50"></span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">Revisão / Aguardando</h2>
                    </div>
                    <span id="count-review" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-500/10 text-purple-300 border border-purple-500/20">
                        <?= count($reviewTasks) ?>
                    </span>
                </div>

                <div id="col-review" class="space-y-3 flex-1">
                    <?php if (empty($reviewTasks)): ?>
                        <div class="empty-placeholder py-12 text-center text-xs text-slate-500">
                            <i data-lucide="clock" class="w-8 h-8 text-slate-600 mx-auto mb-2 opacity-50"></i>
                            Nenhuma demanda aguardando revisão
                        </div>
                    <?php else: ?>
                        <?php foreach ($reviewTasks as $t): ?>
                            <?= renderCoordCard($t) ?>
                        <?php endforeach; ?>
                        <div class="empty-placeholder hidden py-12 text-center text-xs text-slate-500">
                            <i data-lucide="filter" class="w-8 h-8 text-slate-600 mx-auto mb-2 opacity-50"></i>
                            Nenhuma demanda nesta etapa para o filtro selecionado
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 4. Concluído -->
            <section class="bg-slate-900/60 border border-slate-800/90 rounded-3xl p-4 shadow-xl flex flex-col min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-sm shadow-emerald-400/50"></span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">Concluído</h2>
                    </div>
                    <span id="count-done" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">
                        <?= count($doneTasks) ?>
                    </span>
                </div>

                <div id="col-done" class="space-y-3 flex-1 custom-scroll max-h-[750px] overflow-y-auto pr-1">
                    <?php if (empty($doneTasks)): ?>
                        <div class="empty-placeholder py-12 text-center text-xs text-slate-500">
                            <i data-lucide="check-circle" class="w-8 h-8 text-slate-600 mx-auto mb-2 opacity-50"></i>
                            Nenhuma demanda concluída ainda
                        </div>
                    <?php else: ?>
                        <?php foreach ($doneTasks as $t): ?>
                            <?= renderCoordCard($t) ?>
                        <?php endforeach; ?>
                        <div class="empty-placeholder hidden py-12 text-center text-xs text-slate-500">
                            <i data-lucide="filter" class="w-8 h-8 text-slate-600 mx-auto mb-2 opacity-50"></i>
                            Nenhuma demanda nesta etapa para o filtro selecionado
                        </div>
                    <?php endif; ?>
                </div>
            </section>

        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800/80 py-4 text-center text-xs text-slate-500 mt-auto">
        <div class="max-w-[1700px] mx-auto px-4 flex items-center justify-between flex-wrap gap-2">
            <span class="flex items-center gap-1.5">
                <i data-lucide="kanban" class="w-3.5 h-3.5 text-indigo-400"></i>
                KanbanDoo &bull; Painel Executivo da Coordenadoria
            </span>
            <span class="text-slate-600">Somente leitura &bull; Atualizado em tempo real</span>
        </div>
    </footer>

    <!-- Modal de Detalhes da Demanda -->
    <div id="descModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4" onclick="if (event.target === this) closeDescModal()">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-xl w-full p-6 shadow-2xl relative">
            <div class="flex items-start justify-between gap-3 pb-3 mb-4 border-b border-slate-800">
                <div>
                    <span id="modalClient" class="text-xs font-semibold text-indigo-400 uppercase tracking-wider"></span>
                    <h3 id="modalTitle" class="text-base font-bold text-white mt-0.5"></h3>
                </div>
                <button type="button" onclick="closeDescModal()" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div id="modalBody" class="text-xs leading-relaxed text-slate-300 max-h-[60vh] overflow-y-auto whitespace-pre-line custom-scroll">
            </div>
            <div class="mt-5 pt-3 border-t border-slate-800 flex justify-end">
                <button type="button" onclick="closeDescModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white rounded-xl transition">
                    Fechar
                </button>
            </div>
        </div>
    </div>

    <!-- Script de Filtros da Pauta (Hoje, Semana, Mês, Todos, Clientes, Prioridade e Busca) -->
    <script>
        lucide.createIcons();

        // Estado dos Filtros
        let activePeriod = <?= json_encode($initialPeriod) ?>; // 'today' | 'week' | 'month' | 'all'

        // Cálculo das datas de referência do cliente (baseado no fuso horário do usuário)
        function getDateBounds() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const todayStr = `${year}-${month}-${day}`;

            // Segunda-feira da semana atual
            const currentDay = now.getDay(); // 0 = Domingo, 1 = Segunda, ...
            const diffToMonday = (currentDay === 0 ? -6 : 1) - currentDay;
            const monday = new Date(now);
            monday.setDate(now.getDate() + diffToMonday);
            const mYear = monday.getFullYear();
            const mMonth = String(monday.getMonth() + 1).padStart(2, '0');
            const mDay = String(monday.getDate()).padStart(2, '0');
            const mondayStr = `${mYear}-${mMonth}-${mDay}`;

            // Domingo da semana atual
            const sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            const sYear = sunday.getFullYear();
            const sMonth = String(sunday.getMonth() + 1).padStart(2, '0');
            const sDay = String(sunday.getDate()).padStart(2, '0');
            const sundayStr = `${sYear}-${sMonth}-${sDay}`;

            const monthPrefix = `${year}-${month}`;

            return { todayStr, mondayStr, sundayStr, monthPrefix };
        }

        function setPeriodFilter(period) {
            activePeriod = period;
            updatePeriodUI();
            applyAllFilters();
        }

        function updatePeriodUI() {
            ['today', 'week', 'month', 'all'].forEach(p => {
                const btn = document.getElementById('btnPeriod-' + p);
                if (btn) {
                    if (p === activePeriod) {
                        btn.classList.add('is-active');
                    } else {
                        btn.classList.remove('is-active');
                    }
                }
            });
        }

        function applyAllFilters() {
            const bounds = getDateBounds();
            const query = (document.getElementById('coordSearchInput')?.value || '').toLowerCase().trim();
            const selectedClient = document.getElementById('coordClientSelect')?.value || 'all';
            const selectedPriority = document.getElementById('coordPrioritySelect')?.value || 'all';

            // Botão limpar busca
            const btnClearSearch = document.getElementById('btnClearSearch');
            if (btnClearSearch) btnClearSearch.classList.toggle('hidden', query === '');

            // Botão reset geral de filtros
            const hasCustomFilters = (activePeriod !== 'today' || selectedClient !== 'all' || selectedPriority !== 'all' || query !== '');
            const btnReset = document.getElementById('btnResetFilters');
            if (btnReset) btnReset.classList.toggle('hidden', !hasCustomFilters);

            const cards = document.querySelectorAll('.coord-card');
            let totalVisible = 0;
            let overdueVisible = 0;
            const colCounts = { todo: 0, doing: 0, review: 0, done: 0 };

            cards.forEach(card => {
                const searchHaystack = card.dataset.search || '';
                const cardClient = card.dataset.client || '';
                const cardPriority = card.dataset.priority || '';
                const cardDueDate = card.dataset.dueDate || '';
                const cardCompletedAt = card.dataset.completedAt || '';
                const cardStage = card.dataset.stageGroup || 'todo';
                const isOverdue = card.dataset.isOverdue === '1';
                const isRunning = card.dataset.isRunning === '1';

                // 1. Filtro Textual
                const matchSearch = (query === '' || searchHaystack.includes(query));

                // 2. Filtro de Cliente
                let matchClient = true;
                if (selectedClient !== 'all') {
                    if (selectedClient === '__internal__') {
                        matchClient = (cardClient === '__internal__' || cardClient === '');
                    } else {
                        matchClient = (cardClient === selectedClient);
                    }
                }

                // 3. Filtro de Prioridade
                let matchPriority = true;
                if (selectedPriority !== 'all') {
                    matchPriority = (cardPriority === selectedPriority);
                }

                // 4. Filtro de Período
                let matchPeriod = true;
                if (activePeriod === 'today') {
                    // Pauta do Dia: Vencimento hoje OU atrasada pendente OU em andamento/rodando OU concluída hoje
                    const isDueToday = (cardDueDate === bounds.todayStr);
                    const isOverduePending = (isOverdue && cardStage !== 'done');
                    const isRunningOrDoing = (cardStage === 'doing' || isRunning);
                    const isCompletedToday = (cardCompletedAt === bounds.todayStr);
                    matchPeriod = (isDueToday || isOverduePending || isRunningOrDoing || isCompletedToday);
                } else if (activePeriod === 'week') {
                    // Pauta da Semana: Vencimento nesta semana OU atrasada pendente OU em andamento OU concluída nesta semana
                    const isDueThisWeek = (cardDueDate >= bounds.mondayStr && cardDueDate <= bounds.sundayStr);
                    const isOverduePending = (isOverdue && cardStage !== 'done');
                    const isRunningOrDoing = (cardStage === 'doing');
                    const isCompletedThisWeek = (cardCompletedAt >= bounds.mondayStr && cardCompletedAt <= bounds.sundayStr);
                    matchPeriod = (isDueThisWeek || isOverduePending || isRunningOrDoing || isCompletedThisWeek);
                } else if (activePeriod === 'month') {
                    // Pauta do Mês: Vencimento neste mês OU atrasada pendente OU em andamento OU concluída no mês
                    const isDueThisMonth = cardDueDate.startsWith(bounds.monthPrefix);
                    const isOverduePending = (isOverdue && cardStage !== 'done');
                    const isRunningOrDoing = (cardStage === 'doing');
                    const isCompletedThisMonth = cardCompletedAt.startsWith(bounds.monthPrefix);
                    matchPeriod = (isDueThisMonth || isOverduePending || isRunningOrDoing || isCompletedThisMonth);
                } else if (activePeriod === 'all') {
                    matchPeriod = true;
                }

                const isVisible = (matchSearch && matchClient && matchPriority && matchPeriod);

                if (isVisible) {
                    card.style.display = '';
                    totalVisible++;
                    if (isOverdue && cardStage !== 'done') overdueVisible++;
                    if (colCounts[cardStage] !== undefined) colCounts[cardStage]++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Atualiza contadores das colunas e gerencia empty-states
            ['todo', 'doing', 'review', 'done'].forEach(col => {
                const countBadge = document.getElementById('count-' + col);
                if (countBadge) countBadge.textContent = colCounts[col];

                const container = document.getElementById('col-' + col);
                if (container) {
                    const emptyEl = container.querySelector('.empty-placeholder');
                    if (emptyEl) {
                        emptyEl.classList.toggle('hidden', colCounts[col] > 0);
                    }
                }
            });

            // Atualiza métricas do topo
            const statTotal = document.getElementById('stat-total');
            if (statTotal) statTotal.textContent = totalVisible;

            const statTodo = document.getElementById('stat-todo');
            if (statTodo) statTodo.textContent = colCounts.todo;

            const statDoing = document.getElementById('stat-doing');
            if (statDoing) statDoing.textContent = colCounts.doing;

            const statReview = document.getElementById('stat-review');
            if (statReview) statReview.textContent = colCounts.review;

            const statDone = document.getElementById('stat-done');
            if (statDone) statDone.textContent = colCounts.done;

            const statOverdue = document.getElementById('stat-overdue');
            if (statOverdue) statOverdue.textContent = overdueVisible;

            // Atualiza texto explicativo do status
            const periodNames = {
                today: 'Hoje',
                week: 'Esta Semana',
                month: 'Este Mês',
                all: 'Todas as Demandas'
            };
            const statusText = document.getElementById('coordFilterStatusText');
            if (statusText) {
                let clientText = '';
                if (selectedClient !== 'all') {
                    clientText = selectedClient === '__internal__' ? ' • Cliente: Demanda Interna' : ` • Cliente: ${selectedClient}`;
                }
                let prioText = '';
                if (selectedPriority !== 'all') {
                    prioText = ` • Prioridade: ${selectedPriority.toUpperCase()}`;
                }
                statusText.innerHTML = `Exibindo pauta de <strong class="text-white">${periodNames[activePeriod] || activePeriod}</strong>${clientText}${prioText}`;
            }

            const matchBadge = document.getElementById('coordFilterMatchBadge');
            if (matchBadge) {
                matchBadge.textContent = `${totalVisible} ${totalVisible === 1 ? 'demanda' : 'demandas'}`;
            }
        }

        function clearCoordSearch() {
            const input = document.getElementById('coordSearchInput');
            if (input) {
                input.value = '';
                applyAllFilters();
                input.focus();
            }
        }

        function resetAllFilters() {
            activePeriod = 'today';
            updatePeriodUI();
            const clientSel = document.getElementById('coordClientSelect');
            if (clientSel) clientSel.value = 'all';
            const prioSel = document.getElementById('coordPrioritySelect');
            if (prioSel) prioSel.value = 'all';
            const searchInput = document.getElementById('coordSearchInput');
            if (searchInput) searchInput.value = '';
            applyAllFilters();
        }

        function showCardModal(el) {
            openDescModal(el.dataset.title || '', el.dataset.client || '', el.dataset.desc || '');
        }

        function openDescModal(title, client, desc) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalClient').textContent = client || 'Demanda Interna';
            document.getElementById('modalBody').textContent = desc || 'Nenhuma descrição detalhada informada.';
            document.getElementById('descModal').classList.remove('hidden');
        }

        function closeDescModal() {
            document.getElementById('descModal').classList.add('hidden');
        }

        // Inicializar com o período ativo (por padrão: 'today')
        document.addEventListener('DOMContentLoaded', () => {
            updatePeriodUI();
            applyAllFilters();
        });
        // Executa imediatamente para evitar flash de cards não filtrados
        updatePeriodUI();
        applyAllFilters();
    </script>
</body>
</html>
<?php

/**
 * Renderizador de Card da Coordenadoria com Atributos Ricos de Filtragem
 */
function renderCoordCard(array $t): string
{
    $isOverdue = !empty($t['is_overdue']);
    $priority = $t['priority'] ?? 'medium';
    $prioLabels = [
        'urgent' => ['label' => 'Urgente', 'class' => 'bg-rose-500/15 text-rose-400 border-rose-500/30'],
        'high'   => ['label' => 'Alta', 'class' => 'bg-amber-500/15 text-amber-400 border-amber-500/30'],
        'medium' => ['label' => 'Média', 'class' => 'bg-blue-500/15 text-blue-400 border-blue-500/30'],
        'low'    => ['label' => 'Baixa', 'class' => 'bg-slate-700 text-slate-300 border-slate-600'],
    ];
    $prio = $prioLabels[$priority] ?? $prioLabels['medium'];

    $borderClass = $isOverdue
        ? 'border-rose-500/60 bg-rose-950/15 hover:border-rose-400'
        : 'border-slate-800 hover:border-slate-700 bg-slate-900/90';

    $searchHaystack = htmlspecialchars(strtolower(($t['title'] ?? '') . ' ' . ($t['client_company'] ?? '') . ' ' . ($t['tags'] ?? '') . ' ' . ($t['description'] ?? '')));

    $checklistHtml = '';
    $chkTotal = (int)($t['checklist_total'] ?? 0);
    $chkDone = (int)($t['checklist_done'] ?? 0);
    if ($chkTotal > 0) {
        $checklistHtml = '<span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-400" title="Checklist concluído"><i data-lucide="check-square" class="w-3 h-3 text-slate-500"></i>' . $chkDone . '/' . $chkTotal . '</span>';
    }

    $timeHtml = '';
    $effSec = (int)($t['total_seconds'] ?? 0);
    $isRunning = (int)($t['is_timer_running'] ?? 0) === 1;
    if ($isRunning && !empty($t['timer_started_at'])) {
        $effSec += max(0, time() - strtotime($t['timer_started_at']));
    }
    if ($effSec > 0) {
        $hrs = floor($effSec / 3600);
        $mins = floor(($effSec % 3600) / 60);
        $timeStr = $hrs > 0 ? "{$hrs}h {$mins}m" : "{$mins}m";
        $pulseClass = $isRunning ? 'text-blue-400 animate-pulse' : 'text-slate-400';
        $timeHtml = '<span class="inline-flex items-center gap-1 text-[11px] font-medium ' . $pulseClass . '" title="Tempo trabalhado"><i data-lucide="clock" class="w-3 h-3"></i>' . $timeStr . '</span>';
    }

    $clientBadge = !empty($t['client_company'])
        ? '<span class="text-[11px] font-semibold text-indigo-400 flex items-center gap-1 truncate"><i data-lucide="building-2" class="w-3 h-3"></i>' . htmlspecialchars($t['client_company']) . '</span>'
        : '<span class="text-[11px] text-slate-500 flex items-center gap-1"><i data-lucide="hash" class="w-3 h-3"></i>Demanda Interna</span>';

    $dueBadgeHtml = '';
    if (!empty($t['due_badge']['text'])) {
        $badgeClass = $isOverdue
            ? 'bg-rose-500/20 text-rose-300 border-rose-500/40 font-bold'
            : 'bg-slate-800/80 text-slate-300 border-slate-700/80';
        $dueBadgeHtml = '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] border ' . $badgeClass . '"><i data-lucide="calendar" class="w-3 h-3"></i>' . htmlspecialchars($t['due_badge']['text']) . '</span>';
    }

    $titleSafe = htmlspecialchars($t['title'] ?? '', ENT_QUOTES);
    $clientSafe = htmlspecialchars($t['client_company'] ?? '', ENT_QUOTES);
    $descSafe = htmlspecialchars($t['description'] ?? '', ENT_QUOTES);

    $descHtml = !empty($t['description'])
        ? '<p class="text-[11px] text-slate-400 line-clamp-2 cursor-pointer hover:text-slate-300 transition" data-title="' . $titleSafe . '" data-client="' . $clientSafe . '" data-desc="' . $descSafe . '" onclick="showCardModal(this)">' . htmlspecialchars($t['description']) . '</p>'
        : '';

    $clientAttr = !empty($t['client_company']) ? htmlspecialchars($t['client_company']) : '__internal__';
    $dueDateAttr = !empty($t['due_date']) ? htmlspecialchars($t['due_date']) : '';
    $completedAtAttr = !empty($t['completed_at']) ? htmlspecialchars(substr($t['completed_at'], 0, 10)) : '';
    $stageGroup = $t['stage_group'] ?? 'todo';

    $out = '<div class="coord-card p-3.5 rounded-2xl border ' . $borderClass . ' transition-all shadow-sm space-y-2.5" '
         . 'data-search="' . $searchHaystack . '" '
         . 'data-client="' . $clientAttr . '" '
         . 'data-priority="' . htmlspecialchars($priority) . '" '
         . 'data-due-date="' . $dueDateAttr . '" '
         . 'data-completed-at="' . $completedAtAttr . '" '
         . 'data-stage-group="' . htmlspecialchars($stageGroup) . '" '
         . 'data-is-overdue="' . ($isOverdue ? '1' : '0') . '" '
         . 'data-is-running="' . ($isRunning ? '1' : '0') . '">';

    $out .= '  <div class="flex items-center justify-between gap-2">';
    $out .= '    ' . $clientBadge;
    $out .= '    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border ' . $prio['class'] . '">' . $prio['label'] . '</span>';
    $out .= '  </div>';
    $out .= '  <h3 class="text-xs font-bold text-white leading-snug line-clamp-2 hover:text-indigo-300 transition cursor-pointer" data-title="' . $titleSafe . '" data-client="' . $clientSafe . '" data-desc="' . $descSafe . '" onclick="showCardModal(this)">' . htmlspecialchars($t['title']) . '</h3>';
    $out .= '  ' . $descHtml;
    $out .= '  <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-800/60 flex-wrap">';
    $out .= '    <div class="flex items-center gap-2">' . $dueBadgeHtml . '</div>';
    $out .= '    <div class="flex items-center gap-2.5 ml-auto">' . $checklistHtml . $timeHtml . '</div>';
    $out .= '  </div>';
    $out .= '</div>';

    return $out;
}
