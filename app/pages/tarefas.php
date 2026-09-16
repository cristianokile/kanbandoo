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

<main id="conteudo" class="kd-main-board flex-1 w-full pt-3 flex flex-col min-h-0 overflow-hidden">

    <!-- Cabeçalho da página: título, métricas e controles de visão -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-3 flex-shrink-0 px-4 lg:px-8">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight">Quadro de tarefas</h1>
            <p class="text-xs text-slate-400 mt-0.5 hidden md:block">
                Organize as entregas por cliente. Atalhos:
                <kbd class="kd-menu__hint">N</kbd> nova tarefa,
                <kbd class="kd-menu__hint">/</kbd> buscar,
                <kbd class="kd-menu__hint">Ctrl</kbd>+<kbd class="kd-menu__hint">←→</kbd> mover o card em foco.
            </p>
        </div>

        <!-- Métricas e controles de visão organizados sem espremer -->
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

            <!-- Seletor de Modo de Visão (Status vs Dias da Semana) - Apenas ícones -->
            <div class="inline-flex items-center p-1 rounded-xl bg-slate-900/80 border border-slate-800 gap-1" role="group" aria-label="Modo de visão do quadro">
                <button type="button" id="btnViewStatus" onclick="KD.setViewMode('status')"
                        class="w-8 h-8 rounded-lg text-xs font-semibold flex items-center justify-center transition text-indigo-400 bg-indigo-600/15 border border-indigo-500/20"
                        title="Visão por Status (A Fazer, Em Andamento, etc.)" aria-label="Visão por Status">
                    <i data-lucide="columns" class="w-4 h-4"></i>
                </button>
                <button type="button" id="btnViewWeekdays" onclick="KD.setViewMode('weekdays')"
                        class="w-8 h-8 rounded-lg text-xs font-semibold flex items-center justify-center transition text-slate-400 hover:text-white"
                        title="Visão por Dias da Semana (Dom, Seg, Ter, Qua, Qui, Sex, Sáb)" aria-label="Visão por Dias da Semana">
                    <i data-lucide="calendar-days" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Seletor de Colunas Visíveis -->
            <div class="relative inline-block text-left kd-menu-wrap" id="columnsMenuWrap">
                <button type="button" id="btnToggleColumnsMenu" class="kd-btn kd-btn--icon kd-glass" onclick="KD.toggleColumnsMenu(event)"
                        aria-haspopup="menu" aria-expanded="false" title="Exibir ou ocultar colunas" aria-label="Exibir ou ocultar colunas">
                    <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                    <span id="columnsHiddenBadge" class="kd-col__count" style="background:var(--warning);color:#000" hidden></span>
                </button>
                <div id="columnsMenuDropdown" class="kd-menu kd-glass kd-glass--raised absolute right-0 mt-2 z-50 p-2 space-y-1" role="menu" hidden style="min-width:14rem">
                    <div class="px-2 py-1 mb-1 border-b border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                        <span>Colunas Visíveis</span>
                        <button type="button" onclick="KD.resetVisibleColumns()" class="text-[10px] text-indigo-400 hover:underline">Restaurar</button>
                    </div>
                    <div id="columnsMenuList" class="space-y-1 max-h-60 overflow-y-auto"></div>
                    <div class="border-t border-slate-800 pt-1.5 mt-1.5 px-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Etiquetas nos cards</span>
                        <div class="space-y-0.5">
                            <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs py-1" onclick="KD.setTagVisibility('hover')">
                                <span class="flex items-center gap-1.5"><i data-lucide="mouse-pointer" class="w-3 h-3 text-indigo-400"></i> Ao passar o mouse</span>
                                <span class="tag-opt-check" data-mode="hover"><i data-lucide="check" class="w-3 h-3 text-indigo-400"></i></span>
                            </button>
                            <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs py-1" onclick="KD.setTagVisibility('always')">
                                <span class="flex items-center gap-1.5"><i data-lucide="eye" class="w-3 h-3 text-slate-400"></i> Sempre visíveis</span>
                                <span class="tag-opt-check" data-mode="always" hidden><i data-lucide="check" class="w-3 h-3 text-indigo-400"></i></span>
                            </button>
                            <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs py-1" onclick="KD.setTagVisibility('hidden')">
                                <span class="flex items-center gap-1.5"><i data-lucide="eye-off" class="w-3 h-3 text-slate-400"></i> Ocultar etiquetas</span>
                                <span class="tag-opt-check" data-mode="hidden" hidden><i data-lucide="check" class="w-3 h-3 text-indigo-400"></i></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <span class="kd-divider" aria-hidden="true"></span>

            <button type="button" id="toggleFilterBarBtn" class="kd-btn kd-btn--icon kd-glass" onclick="KD.toggleFilterBar()"
                    aria-expanded="false" aria-controls="kanbanFilterBar" title="Buscar e filtrar" aria-label="Buscar e filtrar">
                <i data-lucide="filter" class="w-4 h-4"></i>
                <span id="filterCountBadge" class="kd-col__count" hidden></span>
            </button>

            <button type="button" id="btnOnlyMine" class="kd-btn kd-btn--icon kd-glass" onclick="KD.toggleOnlyMine()"
                    aria-pressed="false" title="Minhas tarefas" aria-label="Minhas tarefas">
                <i data-lucide="user-check" class="w-4 h-4"></i>
            </button>

            <button type="button" id="btnGroupClient" class="kd-btn kd-btn--icon kd-glass" onclick="KD.toggleGroupByClient()"
                    aria-pressed="false" title="Agrupar por cliente" aria-label="Agrupar por cliente">
                <i data-lucide="layers" class="w-4 h-4"></i>
            </button>
        </div>
    </div>

    <p id="groupDragHint" class="text-[11px] text-slate-400 mb-4 px-4 lg:px-8" hidden>
        Enquanto agrupado, mova os cards pelo menu <strong>⋮</strong> do card.
    </p>

    <!-- Filtros -->
    <div id="kanbanFilterBar" class="kd-glass rounded-2xl p-3.5 mb-4 mx-4 lg:mx-8 flex flex-wrap items-end gap-3 animate-fade-in" hidden>

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
    <div id="kanbanStageTabs" class="kd-segmented mx-4 mb-3" role="tablist" aria-label="Escolher coluna"></div>

    <!-- Quadro Full-Width -->
    <div id="kanbanBoard" class="kd-board flex-1 min-h-0 w-full" aria-label="Quadro Kanban"></div>
</main>

<?php view_footer(); ?>
