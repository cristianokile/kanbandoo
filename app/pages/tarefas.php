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
        <!-- BARRA DE CONTROLE MOBILE ULTRA-LIMPA (Estilo ClickUp / Trello / Runrun.it) - Visível apenas no Mobile (< 768px) -->
    <div class="md:hidden flex items-center justify-between gap-2 px-3 py-2 border-b border-slate-800/80 bg-slate-950/70 backdrop-blur-xl flex-shrink-0 z-20">
        <!-- Esquerda: Seletor de Modo / Navegador de Semanas -->
        <div class="flex items-center gap-1.5 min-w-0">
            <!-- Navegação de Semanas (apenas Visão por Dias da Semana) -->
            <div id="mobileWeekNavWrap" class="flex items-center gap-0.5 bg-slate-900/90 border border-slate-800 rounded-xl p-0.5" style="display: none;">
                <button type="button" onclick="KD.navigateWeek(-1)" class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-white rounded-lg active:bg-slate-800" aria-label="Semana Anterior">
                    <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i>
                </button>
                <button type="button" onclick="KD.navigateWeek(0)" class="px-2 h-7 text-[11px] font-semibold text-slate-200 flex items-center gap-1 active:bg-slate-800 rounded-lg" aria-label="Esta Semana">
                    <i data-lucide="calendar" class="w-3 h-3 text-indigo-400"></i>
                    <span id="mobileWeekNavLabel">Esta Semana</span>
                </button>
                <button type="button" onclick="KD.navigateWeek(1)" class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-white rounded-lg active:bg-slate-800" aria-label="Próxima Semana">
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                </button>
            </div>

            <!-- Botão Alternador de Visão Rápido: Status vs Dias -->
            <button type="button" onclick="KD.toggleViewModeMobile()" id="mobileViewToggleBtn"
                    class="h-7 px-2.5 rounded-xl bg-slate-900/90 border border-slate-800 text-[11px] font-semibold text-slate-300 flex items-center gap-1.5 active:bg-slate-800 shadow-sm"
                    title="Alternar visão">
                <i data-lucide="columns" class="w-3 h-3 text-indigo-400" id="mobileViewIcon"></i>
                <span id="mobileViewLabel">Status</span>
            </button>
        </div>

        <!-- Direita: Resumo de Métricas (sheet), Filtro, Menu de Opções -->
        <div class="flex items-center gap-1.5 flex-shrink-0">
            <!-- Botão de Métricas Resumo -->
            <button type="button" onclick="KD.toggleMobileMetricsSheet(true)" class="h-7 px-2 rounded-xl bg-slate-900/90 border border-slate-800 text-slate-300 text-[11px] font-semibold flex items-center gap-1.5 active:bg-slate-800 shadow-sm" title="Resumo das tarefas">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                <span id="mobileMetricTotalBadge">–</span>
            </button>

            <!-- Botão de Filtro -->
            <button type="button" onclick="KD.toggleFilterBar()" class="w-7 h-7 rounded-xl bg-slate-900/90 border border-slate-800 text-slate-300 flex items-center justify-center relative active:bg-slate-800 shadow-sm" title="Buscar e filtrar">
                <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                <span id="mobileFilterCountBadge" class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-indigo-500 text-white rounded-full text-[8px] font-bold flex items-center justify-center" hidden></span>
            </button>

            <!-- Botão Mais Opções do Quadro (Bottom Sheet) -->
            <button type="button" onclick="KD.toggleMobileBoardOptions(true)" class="w-7 h-7 rounded-xl bg-slate-900/90 border border-slate-800 text-slate-300 flex items-center justify-center active:bg-slate-800 shadow-sm" title="Opções do Quadro">
                <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
            </button>
        </div>
    </div>

    <div class="hidden md:flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-3 flex-shrink-0 px-4 lg:px-8">
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
                <div class="w-px h-4 bg-slate-800 my-auto"></div>
                <button type="button" onclick="KD.openImportModal()"
                        class="w-8 h-8 rounded-lg text-xs font-semibold flex items-center justify-center transition text-slate-400 hover:text-white"
                        title="Importar Planilha (.csv) ou Lista (.txt)" aria-label="Importar Planilha">
                    <i data-lucide="upload" class="w-4 h-4"></i>
                </button>
                <a href="<?= url('/api/tarefas?action=export_csv') ?>" download
                   class="w-8 h-8 rounded-lg text-xs font-semibold flex items-center justify-center transition text-slate-400 hover:text-white"
                   title="Exportar Todas as Tarefas para Planilha (.csv)" aria-label="Exportar Planilha">
                    <i data-lucide="download" class="w-4 h-4"></i>
                </a>
            </div>

            <!-- Navegação de Semanas (apenas Visão por Dias da Semana) -->
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
                        title="Próxima Semana" aria-label="Próxima Semana">
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
                    <div class="border-t border-slate-800 pt-1.5 mt-1.5 px-2 space-y-0.5">
                        <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs py-1 text-red-400 hover:text-red-300 transition" onclick="KD.openTrashModal()">
                            <span class="flex items-center gap-1.5"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Lixeira de Tarefas</span>
                            <span class="text-[10px] text-slate-500">30 dias</span>
                        </button>
                        <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs py-1 text-rose-400 hover:text-rose-300 transition" onclick="KD.openResetTasksModal()">
                            <span class="flex items-center gap-1.5"><i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i> Resetar Tarefas</span>
                            <span class="text-[10px] text-rose-500/80 font-bold">Zerar</span>
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
    <!-- Bottom Sheet de Métricas Resumo (Mobile) -->
    <div id="mobileMetricsSheetBackdrop" onclick="KD.toggleMobileMetricsSheet(false)" class="md:hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm transition-opacity opacity-0 pointer-events-none" style="transition: opacity 0.2s ease;"></div>
    <div id="mobileMetricsSheet" class="md:hidden fixed inset-x-3 bottom-20 z-50 mx-auto max-w-sm p-4 rounded-3xl bg-slate-950/95 backdrop-blur-2xl border border-white/10 shadow-2xl transition-all transform translate-y-8 opacity-0 pointer-events-none space-y-3" style="transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Resumo de Tarefas</span>
            <button type="button" onclick="KD.toggleMobileMetricsSheet(false)" class="text-slate-400 hover:text-white p-1">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <div class="grid grid-cols-2 gap-2 text-xs">
            <button type="button" onclick="KD.filterByMetric('total'); KD.toggleMobileMetricsSheet(false);" class="p-3 rounded-2xl bg-slate-900/80 border border-slate-800 flex flex-col gap-1 text-left active:bg-slate-800">
                <span class="flex items-center gap-1.5 text-slate-400 text-[11px] font-medium">
                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span> Em aberto
                </span>
                <span class="text-lg font-bold text-white font-mono" id="mobileMetricTotal">–</span>
            </button>
            <button type="button" onclick="KD.filterByMetric('today'); KD.toggleMobileMetricsSheet(false);" class="p-3 rounded-2xl bg-slate-900/80 border border-slate-800 flex flex-col gap-1 text-left active:bg-slate-800">
                <span class="flex items-center gap-1.5 text-slate-400 text-[11px] font-medium">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span> Hoje
                </span>
                <span class="text-lg font-bold text-amber-400 font-mono" id="mobileMetricToday">–</span>
            </button>
            <button type="button" onclick="KD.filterByMetric('overdue'); KD.toggleMobileMetricsSheet(false);" class="p-3 rounded-2xl bg-slate-900/80 border border-slate-800 flex flex-col gap-1 text-left active:bg-slate-800">
                <span class="flex items-center gap-1.5 text-slate-400 text-[11px] font-medium">
                    <span class="w-2 h-2 rounded-full bg-rose-500"></span> Atrasadas
                </span>
                <span class="text-lg font-bold text-rose-400 font-mono" id="mobileMetricOverdue">–</span>
            </button>
            <div class="p-3 rounded-2xl bg-slate-900/80 border border-slate-800 flex flex-col gap-1 text-left">
                <span class="flex items-center gap-1.5 text-slate-400 text-[11px] font-medium">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Concluídas hoje
                </span>
                <span class="text-lg font-bold text-emerald-400 font-mono" id="mobileMetricDoneToday">–</span>
            </div>
        </div>
    </div>

    <!-- Bottom Sheet de Opções do Quadro (Mobile) -->
    <div id="mobileBoardOptionsBackdrop" onclick="KD.toggleMobileBoardOptions(false)" class="md:hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm transition-opacity opacity-0 pointer-events-none" style="transition: opacity 0.2s ease;"></div>
    <div id="mobileBoardOptionsSheet" class="md:hidden fixed inset-x-3 bottom-20 z-50 mx-auto max-w-sm p-4 rounded-3xl bg-slate-950/95 backdrop-blur-2xl border border-white/10 shadow-2xl transition-all transform translate-y-8 opacity-0 pointer-events-none space-y-3" style="transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1); max-height: calc(100vh - 7rem); overflow-y: auto;">
        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Opções do Quadro</span>
            <button type="button" onclick="KD.toggleMobileBoardOptions(false)" class="text-slate-400 hover:text-white p-1">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Modos de Visão -->
        <div>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Visualização</span>
            <div class="grid grid-cols-2 gap-1.5 bg-slate-900/90 p-1 rounded-xl border border-slate-800">
                <button type="button" id="mobileBtnViewStatus" onclick="KD.setViewMode('status'); KD.toggleMobileBoardOptions(false);" class="py-2 px-2.5 rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 transition">
                    <i data-lucide="columns" class="w-3.5 h-3.5"></i> Status
                </button>
                <button type="button" id="mobileBtnViewWeekdays" onclick="KD.setViewMode('weekdays'); KD.toggleMobileBoardOptions(false);" class="py-2 px-2.5 rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 transition">
                    <i data-lucide="calendar-days" class="w-3.5 h-3.5"></i> Dias da Semana
                </button>
            </div>
        </div>

        <!-- Ajustes Rápidos -->
        <div class="space-y-1.5 pt-1 border-t border-slate-800">
            <button type="button" onclick="KD.toggleCompactMode()" id="mobileCompactToggle" class="w-full py-2.5 px-3 rounded-xl bg-slate-900/80 border border-slate-800 text-xs font-semibold text-slate-200 flex items-center justify-between active:bg-slate-800">
                <span class="flex items-center gap-2"><i data-lucide="rows-3" class="w-4 h-4 text-indigo-400"></i> Modo Compacto</span>
                <span id="mobileCompactIndicator" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-400">Desligado</span>
            </button>

            <button type="button" onclick="KD.toggleOnlyMine(); KD.toggleMobileBoardOptions(false);" class="w-full py-2.5 px-3 rounded-xl bg-slate-900/80 border border-slate-800 text-xs font-semibold text-slate-200 flex items-center justify-between active:bg-slate-800">
                <span class="flex items-center gap-2"><i data-lucide="user-check" class="w-4 h-4 text-purple-400"></i> Apenas Minhas Tarefas</span>
                <span id="mobileOnlyMineIndicator" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-400">Desligado</span>
            </button>

            <button type="button" onclick="KD.toggleGroupByClient(); KD.toggleMobileBoardOptions(false);" class="w-full py-2.5 px-3 rounded-xl bg-slate-900/80 border border-slate-800 text-xs font-semibold text-slate-200 flex items-center justify-between active:bg-slate-800">
                <span class="flex items-center gap-2"><i data-lucide="layers" class="w-4 h-4 text-amber-400"></i> Agrupar por Cliente</span>
                <span id="mobileGroupIndicator" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-400">Desligado</span>
            </button>

            <button type="button" onclick="KD.toggleCalendarEventsInKanban();" class="w-full py-2.5 px-3 rounded-xl bg-slate-900/80 border border-slate-800 text-xs font-semibold text-slate-200 flex items-center justify-between active:bg-slate-800">
                <span class="flex items-center gap-2"><i data-lucide="calendar" class="w-4 h-4 text-cyan-400"></i> Eventos do Calendário</span>
                <span id="mobileCalIndicator" class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-400">Ligado</span>
            </button>
        </div>

        <!-- Mais Ferramentas -->
        <div class="pt-1 border-t border-slate-800 space-y-1">
            <button type="button" onclick="KD.openImportModal(); KD.toggleMobileBoardOptions(false);" class="w-full py-2 px-3 rounded-xl text-xs font-medium text-slate-300 hover:text-white flex items-center gap-2.5 text-left active:bg-slate-800">
                <i data-lucide="upload" class="w-4 h-4 text-emerald-400"></i> Importar Planilha (.csv / .txt)
            </button>
            <a href="<?= url('/api/tarefas?action=export_csv') ?>" download class="w-full py-2 px-3 rounded-xl text-xs font-medium text-slate-300 hover:text-white flex items-center gap-2.5 text-left active:bg-slate-800">
                <i data-lucide="download" class="w-4 h-4 text-blue-400"></i> Exportar Planilha (.csv)
            </a>
            <button type="button" onclick="KD.openTrashModal(); KD.toggleMobileBoardOptions(false);" class="w-full py-2 px-3 rounded-xl text-xs font-medium text-red-400 hover:text-red-300 flex items-center gap-2.5 text-left active:bg-slate-800">
                <i data-lucide="trash-2" class="w-4 h-4"></i> Lixeira de Tarefas
            </button>
        </div>
    </div>

</main>

<?php view_footer(); ?>
