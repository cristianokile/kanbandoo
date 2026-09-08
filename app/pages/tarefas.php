<?php
/**
 * KanbanDoo - Quadro Principal de Tarefas
 *
 * A página monta apenas a moldura (métricas, filtros e containers).
 * As colunas e os cards são renderizados por assets/js/kd-board.js a partir da API,
 * o que mantém os números e o quadro sempre em sincronia.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

require_login();

view_header('Quadro de tarefas');

$pdo = get_pdo();

$clients = $pdo->query("SELECT id, company_name FROM clients WHERE status = 'active' ORDER BY company_name ASC")->fetchAll();
$members = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name ASC")->fetchAll();
?>

<main id="conteudo" class="flex-1 w-full px-4 lg:px-8 py-6 flex flex-col">

    <!-- Cabeçalho da página: título, métricas e controles de visão -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-5">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight">Quadro de tarefas</h1>
            <p class="text-xs text-slate-400 mt-0.5 hidden md:block">
                Organize as entregas por cliente. Atalhos:
                <kbd class="kd-menu__hint">N</kbd> nova tarefa,
                <kbd class="kd-menu__hint">/</kbd> buscar,
                <kbd class="kd-menu__hint">Ctrl</kbd>+<kbd class="kd-menu__hint">←→</kbd> mover o card em foco.
            </p>
        </div>

        <!-- Métricas e controles de visão na mesma linha; os botões abrem no hover -->
        <div class="flex items-center gap-2 flex-wrap lg:flex-nowrap lg:justify-end">
            <span class="kd-metric kd-glass">
                <span class="kd-col__dot" style="background:var(--brand)"></span>
                Em aberto <span class="kd-metric__value" id="metricTotal">–</span>
            </span>
            <span class="kd-metric kd-glass">
                <span class="kd-col__dot" style="background:var(--warning)"></span>
                Hoje <span class="kd-metric__value" data-tone="warning" id="metricToday">–</span>
            </span>
            <span class="kd-metric kd-glass">
                <span class="kd-col__dot" style="background:var(--danger)"></span>
                Atrasadas <span class="kd-metric__value" data-tone="danger" id="metricOverdue">–</span>
            </span>
            <span class="kd-metric kd-glass">
                <span class="kd-col__dot" style="background:var(--success)"></span>
                Concluídas hoje <span class="kd-metric__value" data-tone="success" id="metricDoneToday">–</span>
            </span>

            <span class="kd-divider" aria-hidden="true"></span>

            <button type="button" id="toggleFilterBarBtn" class="kd-btn kd-btn--icon kd-glass" onclick="KD.toggleFilterBar()"
                    aria-expanded="false" aria-controls="kanbanFilterBar" title="Buscar e filtrar">
                <i data-lucide="filter" class="w-4 h-4"></i>
                <span class="kd-btn__label">Buscar e filtrar</span>
                <span id="filterCountBadge" class="kd-col__count" hidden></span>
            </button>

            <button type="button" id="btnOnlyMine" class="kd-btn kd-btn--icon kd-glass" onclick="KD.toggleOnlyMine()"
                    aria-pressed="false" title="Minhas tarefas">
                <i data-lucide="user-check" class="w-4 h-4"></i>
                <span class="kd-btn__label">Minhas tarefas</span>
            </button>

            <button type="button" id="btnGroupClient" class="kd-btn kd-btn--icon kd-glass" onclick="KD.toggleGroupByClient()"
                    aria-pressed="false" title="Agrupar por cliente">
                <i data-lucide="layers" class="w-4 h-4"></i>
                <span class="kd-btn__label">Agrupar por cliente</span>
            </button>
        </div>
    </div>

    <p id="groupDragHint" class="text-[11px] text-slate-400 mb-4" hidden>
        Enquanto agrupado, mova os cards pelo menu <strong>⋮</strong> do card.
    </p>

    <!-- Filtros -->
    <div id="kanbanFilterBar" class="kd-glass rounded-2xl p-3.5 mb-5 flex flex-wrap items-end gap-3 animate-fade-in" hidden>

        <div class="flex-1 min-w-[220px]">
            <label for="filterSearch" class="block text-[11px] font-semibold text-slate-400 mb-1">Buscar</label>
            <input type="search" id="filterSearch" class="kd-field" placeholder="Título, descrição ou empresa...">
        </div>

        <div class="w-full sm:w-auto min-w-[180px]">
            <label for="filterClient" class="block text-[11px] font-semibold text-slate-400 mb-1">Cliente</label>
            <select id="filterClient" class="kd-field">
                <option value="">Todos os clientes</option>
                <?php foreach ($clients as $cl): ?>
                    <option value="<?= (int)$cl['id'] ?>"><?= e($cl['company_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto min-w-[150px]">
            <label for="filterPriority" class="block text-[11px] font-semibold text-slate-400 mb-1">Prioridade</label>
            <select id="filterPriority" class="kd-field">
                <option value="">Todas</option>
                <option value="urgent">Urgente</option>
                <option value="high">Alta</option>
                <option value="medium">Média</option>
                <option value="low">Baixa</option>
            </select>
        </div>

        <div class="w-full sm:w-auto min-w-[170px]">
            <label for="filterAssignee" class="block text-[11px] font-semibold text-slate-400 mb-1">Responsável</label>
            <select id="filterAssignee" class="kd-field">
                <option value="">Qualquer pessoa</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?= (int)$m['id'] ?>"><?= e($m['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="w-full sm:w-auto min-w-[170px]">
            <label for="filterTag" class="block text-[11px] font-semibold text-slate-400 mb-1">Etiqueta</label>
            <select id="filterTag" class="kd-field">
                <option value="">Todas as etiquetas</option>
            </select>
        </div>

        <button type="button" class="kd-btn kd-glass" onclick="KD.clearFilters()">
            <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
            Limpar
        </button>
    </div>

    <!-- Seletor de coluna (somente no celular) -->
    <div id="kanbanStageTabs" class="kd-segmented" role="tablist" aria-label="Escolher coluna"></div>

    <!-- Quadro -->
    <div id="kanbanBoard" class="kd-board" aria-label="Quadro Kanban"></div>
</main>

<?php view_footer(); ?>
