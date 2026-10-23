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
    <title>Painel da Coordenadoria &bull; KanbanDoo</title>
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
           c.company_name AS client_company,
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

$overdueCount = 0;

foreach ($rawTasks as $t) {
    $t['due_badge'] = format_due_date_badge($t['due_date'], $t['original_due_date'] ?? null);
    $t['is_overdue'] = !empty($t['due_badge']['is_overdue']) && empty($t['completed_at']);
    if ($t['is_overdue']) {
        $overdueCount++;
    }

    $isCompleted = !empty($t['completed_at']) || $t['stage_slug'] === 'done';
    if ($isCompleted) {
        $doneTasks[] = $t;
    } elseif ($t['stage_slug'] === 'review' || $t['stage_slug'] === 'aguardando' || stripos($t['stage_name'] ?? '', 'revis') !== false || stripos($t['stage_name'] ?? '', 'aguard') !== false) {
        $reviewTasks[] = $t;
    } elseif ($t['stage_slug'] === 'in_progress' || $t['stage_slug'] === 'doing' || (int)($t['is_timer_running'] ?? 0) === 1 || stripos($t['stage_name'] ?? '', 'andamento') !== false || stripos($t['stage_name'] ?? '', 'fazendo') !== false) {
        $doingTasks[] = $t;
    } else {
        $todoTasks[] = $t;
    }
}

$totalTasks = count($rawTasks);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demandas de <?= htmlspecialchars($coordUser['full_name']) ?> &bull; Coordenadoria KanbanDoo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .custom-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.4); }
        .custom-scroll::-webkit-scrollbar-thumb { background: rgba(51, 65, 85, 0.6); border-radius: 9999px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: rgba(71, 85, 105, 0.9); }
    </style>
</head>
<body class="bg-[#0b0f19] text-slate-100 min-h-screen flex flex-col antialiased selection:bg-indigo-500 selection:text-white">

    <!-- Top Navigation Header -->
    <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-40">
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
                        <span><?= htmlspecialchars($coordUser['email']) ?></span>
                        <span>&bull;</span>
                        <span class="text-slate-400 flex items-center gap-1">
                            <i data-lucide="clock" class="w-3 h-3 text-slate-400"></i>
                            Atualizado em <?= date('d/m/Y \à\s H:i') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Busca Instantânea -->
            <div class="flex items-center gap-3">
                <div class="relative w-48 sm:w-72">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input type="text" id="coordSearchInput" oninput="filterCoordTasks()" placeholder="Buscar demandas ou clientes..." class="w-full pl-9 pr-8 py-2 bg-slate-900/90 border border-slate-700/80 rounded-xl text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition shadow-inner">
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

        <!-- Top Metrics Bar -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-800/80 border border-slate-700 text-slate-300 flex items-center justify-center">
                    <i data-lucide="layers" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">Total</div>
                    <div class="text-lg font-bold text-white"><?= $totalTasks ?></div>
                </div>
            </div>

            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center">
                    <i data-lucide="circle" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">A Fazer</div>
                    <div class="text-lg font-bold text-amber-400"><?= count($todoTasks) ?></div>
                </div>
            </div>

            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-400 flex items-center justify-center">
                    <i data-lucide="play-circle" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">Em Andamento</div>
                    <div class="text-lg font-bold text-blue-400"><?= count($doingTasks) ?></div>
                </div>
            </div>

            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">Revisão / Aguardando</div>
                    <div class="text-lg font-bold text-purple-400"><?= count($reviewTasks) ?></div>
                </div>
            </div>

            <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">Concluídas</div>
                    <div class="text-lg font-bold text-emerald-400"><?= count($doneTasks) ?></div>
                </div>
            </div>

            <div class="bg-slate-900/70 border <?= $overdueCount > 0 ? 'border-rose-500/40 bg-rose-950/20' : 'border-slate-800' ?> rounded-2xl p-3.5 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl <?= $overdueCount > 0 ? 'bg-rose-500/20 border border-rose-500/30 text-rose-400' : 'bg-slate-800/80 border border-slate-700 text-slate-400' ?> flex items-center justify-center">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="text-[11px] font-medium text-slate-400">Atrasadas</div>
                    <div class="text-lg font-bold <?= $overdueCount > 0 ? 'text-rose-400' : 'text-slate-400' ?>"><?= $overdueCount ?></div>
                </div>
            </div>
        </div>

        <!-- 4 Columns Container -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 flex-1 items-start">

            <!-- 1. SEÇÃO: A FAZER -->
            <div class="coord-column bg-slate-900/50 border border-slate-800/90 rounded-3xl p-4 flex flex-col min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 ring-4 ring-amber-400/20"></span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">A Fazer</h2>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20" id="count-todo"><?= count($todoTasks) ?></span>
                </div>
                <div class="space-y-3.5 flex-1 custom-scroll" id="col-todo">
                    <?php if (empty($todoTasks)): ?>
                        <div class="py-12 text-center text-xs text-slate-500 empty-state">Nenhuma demanda pendente no momento.</div>
                    <?php else: foreach ($todoTasks as $t): ?>
                        <?= renderCoordCard($t) ?>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- 2. SEÇÃO: EM ANDAMENTO -->
            <div class="coord-column bg-slate-900/50 border border-slate-800/90 rounded-3xl p-4 flex flex-col min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-400 ring-4 ring-blue-400/20"></span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">Em Andamento</h2>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20" id="count-doing"><?= count($doingTasks) ?></span>
                </div>
                <div class="space-y-3.5 flex-1 custom-scroll" id="col-doing">
                    <?php if (empty($doingTasks)): ?>
                        <div class="py-12 text-center text-xs text-slate-500 empty-state">Nenhuma demanda sendo executada agora.</div>
                    <?php else: foreach ($doingTasks as $t): ?>
                        <?= renderCoordCard($t) ?>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- 3. SEÇÃO: REVISÃO / AGUARDANDO -->
            <div class="coord-column bg-slate-900/50 border border-slate-800/90 rounded-3xl p-4 flex flex-col min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-400 ring-4 ring-purple-400/20"></span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">Revisão / Aguardando</h2>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20" id="count-review"><?= count($reviewTasks) ?></span>
                </div>
                <div class="space-y-3.5 flex-1 custom-scroll" id="col-review">
                    <?php if (empty($reviewTasks)): ?>
                        <div class="py-12 text-center text-xs text-slate-500 empty-state">Nenhuma demanda aguardando revisão.</div>
                    <?php else: foreach ($reviewTasks as $t): ?>
                        <?= renderCoordCard($t) ?>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- 4. SEÇÃO: CONCLUÍDO -->
            <div class="coord-column bg-slate-900/50 border border-slate-800/90 rounded-3xl p-4 flex flex-col min-h-[500px]">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 ring-4 ring-emerald-400/20"></span>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-200">Concluído</h2>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20" id="count-done"><?= count($doneTasks) ?></span>
                </div>
                <div class="space-y-3.5 flex-1 custom-scroll" id="col-done">
                    <?php if (empty($doneTasks)): ?>
                        <div class="py-12 text-center text-xs text-slate-500 empty-state">Nenhuma demanda concluída recentemente.</div>
                    <?php else: foreach ($doneTasks as $t): ?>
                        <?= renderCoordCard($t) ?>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        </div>

    </main>

    <!-- Modal de Visualização da Descrição -->
    <div id="descModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 hidden" onclick="if(event.target===this)closeDescModal()">
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

    <!-- Script de Filtro Live e Interatividade -->
    <script>
        lucide.createIcons();

        function filterCoordTasks() {
            const query = (document.getElementById('coordSearchInput').value || '').toLowerCase().trim();
            const btnClear = document.getElementById('btnClearSearch');
            if (btnClear) btnClear.classList.toggle('hidden', query === '');

            const cards = document.querySelectorAll('.coord-card');
            cards.forEach(card => {
                const text = card.dataset.search || '';
                const match = query === '' || text.includes(query);
                card.style.display = match ? 'block' : 'none';
            });

            // Atualiza contadores visíveis
            ['todo', 'doing', 'review', 'done'].forEach(col => {
                const container = document.getElementById('col-' + col);
                if (container) {
                    const visibleCards = container.querySelectorAll('.coord-card:not([style*="display: none"])').length;
                    const countEl = document.getElementById('count-' + col);
                    if (countEl) countEl.textContent = visibleCards;
                }
            });
        }

        function clearCoordSearch() {
            const input = document.getElementById('coordSearchInput');
            if (input) {
                input.value = '';
                filterCoordTasks();
                input.focus();
            }
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
    </script>
</body>
</html>
<?php

/**
 * Renderizador de Card da Coordenadoria
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

    $searchHaystack = htmlspecialchars(strtolower(($t['title'] ?? '') . ' ' . ($t['client_company'] ?? '') . ' ' . ($t['tags'] ?? '')));

    $checklistHtml = '';
    $chkTotal = (int)($t['checklist_total'] ?? 0);
    $chkDone = (int)($t['checklist_done'] ?? 0);
    if ($chkTotal > 0) {
        $checklistHtml = '<span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-400"><i data-lucide="check-square" class="w-3 h-3 text-slate-500"></i>' . $chkDone . '/' . $chkTotal . '</span>';
    }

    $timeHtml = '';
    $effSec = (int)($t['total_seconds'] ?? 0);
    if ((int)($t['is_timer_running'] ?? 0) === 1 && !empty($t['timer_started_at'])) {
        $effSec += max(0, time() - strtotime($t['timer_started_at']));
    }
    if ($effSec > 0) {
        $hrs = floor($effSec / 3600);
        $mins = floor(($effSec % 3600) / 60);
        $timeStr = $hrs > 0 ? "{$hrs}h {$mins}m" : "{$mins}m";
        $timeHtml = '<span class="inline-flex items-center gap-1 text-[11px] font-medium text-blue-400"><i data-lucide="clock" class="w-3 h-3"></i>' . $timeStr . '</span>';
    }

    $clientBadge = !empty($t['client_company'])
        ? '<span class="text-[11px] font-semibold text-indigo-400 flex items-center gap-1 truncate"><i data-lucide="building-2" class="w-3 h-3"></i>' . htmlspecialchars($t['client_company']) . '</span>'
        : '<span class="text-[11px] text-slate-500">Demanda Interna</span>';

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
        ? '<p class="text-[11px] text-slate-400 line-clamp-2 cursor-pointer" data-title="' . $titleSafe . '" data-client="' . $clientSafe . '" data-desc="' . $descSafe . '" onclick="showCardModal(this)">' . htmlspecialchars($t['description']) . '</p>'
        : '';

    $out = '<div class="coord-card p-3.5 rounded-2xl border ' . $borderClass . ' transition-all shadow-sm space-y-2.5" data-search="' . $searchHaystack . '">';
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
