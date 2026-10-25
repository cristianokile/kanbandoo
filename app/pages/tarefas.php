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
            <button type="button" class="kd-metric kd-glass cursor-pointer transition hover:opacity-80 text-left" onclick="KD.filterByMetric('total')" title="Ver todas as tarefas em aberto">
                <span class="kd-col__dot" style="background:var(--brand)"></span>
                Em aberto <span class="kd-metric__value" id="metricTotal">–</span>
            </button>
            <button type="button" class="kd-metric kd-glass cursor-pointer transition hover:opacity-80 text-left" onclick="KD.filterByMetric('today')" title="Ver tarefas de hoje">
                <span class="kd-col__dot" style="background:var(--warning)"></span>
                Hoje <span class="kd-metric__value" data-tone="warning" id="metricToday">–</span>
            </button>
            <button type="button" class="kd-metric kd-glass cursor-pointer transition hover:opacity-80 text-left" onclick="KD.filterByMetric('overdue')" title="Localizar tarefas atrasadas">
                <span class="kd-col__dot" style="background:var(--danger)"></span>
                Atrasadas <span class="kd-metric__value" data-tone="danger" id="metricOverdue">–</span>
            </button>
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
                <div class="w-px h-4 bg-slate-800 my-auto"></div>
                <button type="button" id="btnToggleCompact" onclick="KD.toggleCompactMode()"
                        class="w-8 h-8 rounded-lg text-xs font-semibold flex items-center justify-center transition text-slate-400 hover:text-white"
                        title="Modo Compacto (estilo Trello: apenas título e detalhes essenciais)" aria-label="Modo Compacto">
                    <i data-lucide="rows-3" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Navega��o de Semanas (apenas Vis�o por Dias da Semana) -->
            <div id="weekNavControls" class="flex items-center gap-1 bg-slate-900/60 p-1 rounded-xl border border-slate-800/80" style="display: none;">
                <button type="button" onclick="KD.navigateWeek(-1)"
                        class="w-8 h-8 rounded-lg text-xs font-semibold flex items-center justify-center transition text-slate-400 hover:text-white hover:bg-slate-800"
                        title="Semana Anterior" aria-label="Semana Anterior">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </button>
                <button type="button" id="btnWeekCurrent" onclick="KD.navigateWeek(0)"
                        class="px-2.5 h-8 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition text-slate-300 hover:text-white hover:bg-slate-800"
                        title="Ir para Esta Semana" aria-label="Semana Atual">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-indigo-400"></i>
                    <span id="weekNavLabelText">Esta Semana</span>
                </button>
                <button type="button" onclick="KD.navigateWeek(1)"
                        class="w-8 h-8 rounded-lg text-xs font-semibold flex items-center justify-center transition text-slate-400 hover:text-white hover:bg-slate-800"
                        title="Pr�xima Semana" aria-label="Pr�xima Semana">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- Seletor de Colunas Visíveis -->
            <div class="relative inline-block text-left kd-menu-wrap" id="columnsMenuWrap">
                <button type="button" id="btnToggleColumnsMenu" class="kd-btn kd-btn--icon kd-glass" onclick="KD.toggleColumnsMenu(event)"
                        aria-haspopup="menu" aria-expanded="false" title="Opções de exibição" aria-label="Opções de exibição">
                    <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
                    <span id="columnsHiddenBadge" class="kd-col__count" style="background:var(--warning);color:#000" hidden></span>
                </button>
                <div id="columnsMenuDropdown" class="kd-menu kd-glass kd-glass--raised absolute right-0 mt-2 z-50 p-2.5 space-y-2 rounded-xl shadow-2xl border border-slate-700/60" role="menu" hidden style="width: 17.5rem; max-height: calc(100vh - 5rem); overflow-y: auto; overflow-x: hidden;">
                    <!-- Configurações específicas da Visão por Dias -->
                    <div id="weekdayConfigSection" class="border-b border-slate-800/80 pb-2.5 mb-1" hidden>
                        <!-- Quantidade de dias a exibir -->
                        <div class="mb-2.5">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dias na visão por dias</span>
                                <span id="weekdayDaysCountBadge" class="text-[11px] font-bold text-indigo-400 bg-indigo-500/10 px-1.5 py-0.5 rounded border border-indigo-500/20">5 dias</span>
                            </div>
                            <div class="flex items-center gap-1 bg-slate-900/90 p-1 rounded-lg border border-slate-800">
                                <button type="button" class="btn-days-count flex-1 py-1 rounded text-center font-medium transition text-xs" data-days="3" onclick="KD.setWeekdayDaysCount(3)">3d</button>
                                <button type="button" class="btn-days-count flex-1 py-1 rounded text-center font-medium transition text-xs" data-days="5" onclick="KD.setWeekdayDaysCount(5)">5d</button>
                                <button type="button" class="btn-days-count flex-1 py-1 rounded text-center font-medium transition text-xs" data-days="7" onclick="KD.setWeekdayDaysCount(7)">7d</button>
                                <div class="flex items-center gap-0.5 pl-1 border-l border-slate-800">
                                    <button type="button" title="Diminuir 1 dia" class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition" onclick="KD.stepWeekdayDaysCount(-1)">−</button>
                                    <input type="number" id="inputWeekdayDaysCount" min="1" max="14" class="w-8 h-6 text-center text-xs bg-slate-950 border border-slate-700 rounded text-slate-100 font-mono focus:border-indigo-500 focus:outline-none" onchange="KD.setWeekdayDaysCount(this.value)">
                                    <button type="button" title="Aumentar 1 dia" class="w-6 h-6 flex items-center justify-center rounded bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition" onclick="KD.stepWeekdayDaysCount(1)">+</button>
                                </div>
                            </div>
                        </div>

                        <!-- Alternar Início da Semana -->
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Início da semana</span>
                            <div class="grid grid-cols-2 gap-1 bg-slate-900/90 p-0.5 rounded-lg border border-slate-800">
                                <button type="button" id="btnWeekStartToday" onclick="KD.setWeekStartMode('today')" class="px-2 py-1 rounded-md text-center font-medium transition text-xs">A partir de Hoje</button>
                                <button type="button" id="btnWeekStartMonday" onclick="KD.setWeekStartMode('monday')" class="px-2 py-1 rounded-md text-center font-medium transition text-xs">Segunda-feira</button>
                            </div>
                        </div>
                    </div>

                    <!-- Colunas Visíveis -->
                    <div>
                        <div class="px-1 py-1 mb-1 border-b border-slate-800/80 text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                            <span>Colunas Visíveis</span>
                            <button type="button" onclick="KD.resetVisibleColumns()" class="text-[10px] text-indigo-400 hover:underline">Restaurar</button>
                        </div>
                        
                        <div id="columnsMenuList" class="space-y-0.5 max-h-44 overflow-y-auto pr-1"></div>
                    </div>
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
                    <div class="border-t border-slate-800 pt-1.5 mt-1.5 px-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Elementos dos cards</span>
                        <div class="space-y-0.5">
                            <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs py-1" onclick="KD.toggleCardAvatar()">
                                <span class="flex items-center gap-1.5"><i data-lucide="user" class="w-3 h-3 text-slate-400"></i> Avatar do responsável</span>
                                <span id="cardAvatarCheck" class="card-avatar-check"><i data-lucide="check" class="w-3 h-3 text-indigo-400"></i></span>
                            </button>
                            <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs py-1" onclick="KD.toggleCardStatusBar()">
                                <span class="flex items-center gap-1.5"><i data-lucide="minus" class="w-3 h-3 text-slate-400"></i> Barra de status</span>
                                <span id="cardStatusBarCheck" class="card-status-bar-check"><i data-lucide="check" class="w-3 h-3 text-indigo-400"></i></span>
                            </button>
                        </div>
                    </div>
                    <div class="border-t border-slate-800 pt-1.5 mt-1.5 px-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Eventos do Calendário</span>
                        <div class="space-y-0.5">
                            <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs py-1" onclick="KD.toggleCalendarEventsInKanban()">
                                <span class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5 text-indigo-400"></i> Exibir eventos do calendário</span>
                                <span id="calendarEventsInKanbanCheck"><i data-lucide="check" class="w-3.5 h-3.5 text-indigo-400"></i></span>
                            </button>
                        </div>
                    </div>
                    <div class="border-t border-slate-800 pt-1.5 mt-1.5 px-2">
                        <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs py-1 text-red-400 hover:text-red-300 transition" onclick="KD.openTrashModal()">
                            <span class="flex items-center gap-1.5"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Lixeira de Tarefas</span>
                            <span class="text-[10px] text-slate-500">30 dias</span>
                        </button>
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
