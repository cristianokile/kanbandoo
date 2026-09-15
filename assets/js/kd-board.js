/**
 * KanbanDoo - Quadro: estado, filtros, cards, arrastar e soltar e teclado.
 */
window.KD = window.KD || {};

(function (KD) {
    'use strict';

    const board = {
        stages: [],
        tasks: [],
        metrics: null,
        doneHidden: 0,
        doneVisibleDays: 14,
        currentUserId: 0,
        loaded: false,
        dragging: false,
        collapsed: new Set(),
        groupByClient: false,
        onlyMine: false,
        mobileStageId: null,
        showArchived: false,
        allTags: [],
        viewMode: 'status', // 'status' ou 'weekdays'
        tagVisibility: 'hover', // 'hover', 'always', 'hidden'
        workHours: { start: '09:00', end: '17:00', capacityMinutes: 480 },
        workDays: ['1', '2', '3', '4', '5'],
        hiddenColumns: {
            status: new Set(),
            weekdays: new Set(),
        },
    };

    const sortables = [];
    const COLLAPSE_KEY = 'kanbandoo_collapsed_stages';
    const VIEW_KEY = 'kanbandoo_board_view';
    const VIEW_MODE_KEY = 'kanbandoo_board_view_mode';
    const HIDDEN_COLS_KEY = 'kanbandoo_hidden_columns';
    const TAG_VISIBILITY_KEY = 'kanbandoo_tag_visibility';

    KD.board = board;

    // ---------------------------------------------------------------
    // Preferências locais de visualização
    // ---------------------------------------------------------------

    function loadViewPrefs() {
        try {
            const collapsed = JSON.parse(localStorage.getItem(COLLAPSE_KEY) || '[]');
            if (Array.isArray(collapsed)) collapsed.forEach((id) => board.collapsed.add(String(id)));

            const view = JSON.parse(localStorage.getItem(VIEW_KEY) || '{}');
            board.groupByClient = Boolean(view.groupByClient);

            const savedMode = localStorage.getItem(VIEW_MODE_KEY);
            if (savedMode === 'status' || savedMode === 'weekdays') {
                board.viewMode = savedMode;
            }

            const savedTagVis = localStorage.getItem(TAG_VISIBILITY_KEY);
            if (savedTagVis === 'hover' || savedTagVis === 'always' || savedTagVis === 'hidden') {
                board.tagVisibility = savedTagVis;
            }

            const savedHidden = JSON.parse(localStorage.getItem(HIDDEN_COLS_KEY) || '{}');
            if (savedHidden && typeof savedHidden === 'object') {
                if (Array.isArray(savedHidden.status)) {
                    board.hiddenColumns.status = new Set(savedHidden.status.map(String));
                }
                if (Array.isArray(savedHidden.weekdays)) {
                    board.hiddenColumns.weekdays = new Set(savedHidden.weekdays.map(String));
                }
            }
        } catch (e) { /* localStorage indisponível */ }
    }

    function saveViewPrefs() {
        try {
            localStorage.setItem(COLLAPSE_KEY, JSON.stringify(Array.from(board.collapsed)));
            localStorage.setItem(VIEW_KEY, JSON.stringify({ groupByClient: board.groupByClient }));
            localStorage.setItem(VIEW_MODE_KEY, board.viewMode);
            localStorage.setItem(TAG_VISIBILITY_KEY, board.tagVisibility);
            localStorage.setItem(HIDDEN_COLS_KEY, JSON.stringify({
                status: Array.from(board.hiddenColumns.status),
                weekdays: Array.from(board.hiddenColumns.weekdays),
            }));
        } catch (e) { /* localStorage indisponível */ }
    }

    function applyTagVisibility() {
        const container = el('kanbanBoard');
        if (!container) return;
        container.classList.remove('kd-tags-hover', 'kd-tags-always', 'kd-tags-hidden');
        container.classList.add(`kd-tags-${board.tagVisibility || 'hover'}`);
    }

    function updateTagMenuCheckmarks() {
        document.querySelectorAll('.tag-opt-check').forEach((chk) => {
            chk.hidden = (chk.dataset.mode !== board.tagVisibility);
        });
    }

    KD.setTagVisibility = function (mode) {
        if (!['hover', 'always', 'hidden'].includes(mode)) return;
        board.tagVisibility = mode;
        saveViewPrefs();
        applyTagVisibility();
        updateTagMenuCheckmarks();

        const messages = {
            hover: 'Etiquetas serão exibidas ao passar o mouse sobre o cartão.',
            always: 'Etiquetas sempre visíveis nos cartões.',
            hidden: 'Etiquetas ocultadas nos cartões.',
        };
        KD.toast(messages[mode] || 'Preferência salva.');
    };

    // ---------------------------------------------------------------
    // Filtros: lidos do formulário e refletidos na URL (link compartilhável)
    // ---------------------------------------------------------------

    function el(id) {
        return document.getElementById(id);
    }

    function readFilters() {
        return {
            q: (el('filterSearch')?.value || '').trim(),
            client_id: el('filterClient')?.value || '',
            priority: el('filterPriority')?.value || '',
            assignee_id: el('filterAssignee')?.value || '',
            tag: el('filterTag')?.value || '',
            mine: board.onlyMine ? '1' : '',
            archived: board.showArchived ? '1' : '',
        };
    }

    function activeFilterCount() {
        const f = readFilters();
        return ['q', 'client_id', 'priority', 'assignee_id', 'tag', 'mine'].filter((k) => f[k]).length;
    }

    function syncUrl(filters) {
        const params = new URLSearchParams();
        if (filters.q) params.set('q', filters.q);
        if (filters.client_id) params.set('client', filters.client_id);
        if (filters.priority) params.set('priority', filters.priority);
        if (filters.assignee_id && !filters.mine) params.set('assignee', filters.assignee_id);
        if (filters.tag) params.set('tag', filters.tag);
        if (filters.mine) params.set('mine', '1');
        if (board.groupByClient) params.set('group', 'client');

        const query = params.toString();
        window.history.replaceState({}, '', KD.url('/tarefas') + (query ? `?${query}` : ''));
    }

    function restoreFiltersFromUrl() {
        const params = new URLSearchParams(window.location.search);
        if (el('filterSearch')) el('filterSearch').value = params.get('q') || '';
        if (el('filterClient')) el('filterClient').value = params.get('client') || '';
        if (el('filterPriority')) el('filterPriority').value = params.get('priority') || '';
        if (el('filterAssignee')) el('filterAssignee').value = params.get('assignee') || '';
        board.pendingTag = params.get('tag') || '';

        board.onlyMine = params.get('mine') === '1';
        if (params.get('group') === 'client') board.groupByClient = true;

        // Uma tarefa aberta por link direto (/tarefas/123 ou ?task=123)
        const fromPath = window.location.pathname.match(/\/tarefas\/(\d+)$/);
        const taskId = (fromPath && fromPath[1]) || params.get('task') || params.get('task_id');
        if (taskId && KD.openTaskDetail) {
            window.setTimeout(() => KD.openTaskDetail(Number(taskId)), 300);
        }

        updateToolbarState();
        if (activeFilterCount() > 0) showFilterBar(true);
    }

    function updateToolbarState() {
        const mineBtn = el('btnOnlyMine');
        if (mineBtn) {
            mineBtn.setAttribute('aria-pressed', String(board.onlyMine));
            mineBtn.classList.toggle('kd-btn--active', board.onlyMine);
        }

        const groupBtn = el('btnGroupClient');
        if (groupBtn) {
            groupBtn.setAttribute('aria-pressed', String(board.groupByClient));
            groupBtn.classList.toggle('kd-btn--active', board.groupByClient);
        }

        // Modo de visão (Status vs Semana)
        const isWeek = board.viewMode === 'weekdays';
        const btnStatus = el('btnViewStatus');
        const btnWeek = el('btnViewWeekdays');
        if (btnStatus) {
            btnStatus.classList.toggle('text-indigo-400', !isWeek);
            btnStatus.classList.toggle('bg-indigo-600/15', !isWeek);
            btnStatus.classList.toggle('border', !isWeek);
            btnStatus.classList.toggle('border-indigo-500/20', !isWeek);
            btnStatus.classList.toggle('text-slate-400', isWeek);
        }
        if (btnWeek) {
            btnWeek.classList.toggle('text-indigo-400', isWeek);
            btnWeek.classList.toggle('bg-indigo-600/15', isWeek);
            btnWeek.classList.toggle('border', isWeek);
            btnWeek.classList.toggle('border-indigo-500/20', isWeek);
            btnWeek.classList.toggle('text-slate-400', !isWeek);
        }

        // Badge de colunas ocultas
        const hiddenCount = (board.hiddenColumns && board.hiddenColumns[board.viewMode]) ? board.hiddenColumns[board.viewMode].size : 0;
        const colBadge = el('columnsHiddenBadge');
        if (colBadge) {
            colBadge.textContent = hiddenCount > 0 ? `-${hiddenCount}` : '';
            colBadge.hidden = hiddenCount === 0;
        }

        // Rótulo do botão de expediente
        if (el('workHoursLabel') && board.workHours) {
            const h = Math.round((board.workHours.capacityMinutes || 480) / 60);
            el('workHoursLabel').textContent = `${(board.workHours.start || '09:00').slice(0, 2)}h–${(board.workHours.end || '17:00').slice(0, 2)}h (${h}h)`;
        }

        const count = activeFilterCount();
        const badge = el('filterCountBadge');
        if (badge) {
            badge.textContent = count > 0 ? String(count) : '';
            badge.hidden = count === 0;
        }

        const toggleBtn = el('toggleFilterBarBtn');
        if (toggleBtn) toggleBtn.classList.toggle('kd-btn--active', count > 0);

        const dragHint = el('groupDragHint');
        if (dragHint) dragHint.hidden = !board.groupByClient;
    }

    function showFilterBar(show) {
        const bar = el('kanbanFilterBar');
        const btn = el('toggleFilterBarBtn');
        if (!bar) return;
        bar.hidden = !show;
        if (btn) btn.setAttribute('aria-expanded', String(show));
    }

    KD.toggleFilterBar = function () {
        const bar = el('kanbanFilterBar');
        if (!bar) return;
        const willShow = bar.hidden;
        showFilterBar(willShow);
        if (willShow) el('filterSearch')?.focus();
    };

    KD.clearFilters = function () {
        if (el('filterSearch')) el('filterSearch').value = '';
        if (el('filterClient')) el('filterClient').value = '';
        if (el('filterPriority')) el('filterPriority').value = '';
        if (el('filterAssignee')) el('filterAssignee').value = '';
        if (el('filterTag')) el('filterTag').value = '';
        board.onlyMine = false;
        updateToolbarState();
        KD.loadBoard();
    };

    KD.toggleOnlyMine = function () {
        board.onlyMine = !board.onlyMine;
        updateToolbarState();
        KD.loadBoard();
    };

    KD.toggleGroupByClient = function () {
        board.groupByClient = !board.groupByClient;
        saveViewPrefs();
        updateToolbarState();
        render();
    };

    // ---------------------------------------------------------------
    // Carregamento
    // ---------------------------------------------------------------

    KD.loadBoard = async function (options) {
        const opts = options || {};
        const filters = readFilters();
        syncUrl(filters);
        updateToolbarState();

        if (!board.loaded && !opts.silent) renderSkeleton();

        const params = new URLSearchParams();
        Object.entries(filters).forEach(([key, value]) => {
            if (value) params.set(key, value);
        });

        try {
            const data = await KD.api.get(`${KD.url('/api/tarefas')}?${params.toString()}`);

            board.stages = data.stages || [];
            board.tasks = data.tasks || [];
            board.metrics = data.metrics || null;
            board.doneHidden = data.done_hidden_count || 0;
            board.doneVisibleDays = data.done_visible_days || 14;
            board.currentUserId = data.current_user_id || 0;
            board.allTags = data.all_tags || [];

            if (data.work_start_time && data.work_end_time) {
                board.workHours = {
                    start: data.work_start_time,
                    end: data.work_end_time,
                    capacityMinutes: Number(data.work_capacity_minutes) || 480,
                };
            }
            if (data.work_days) board.workDays = data.work_days;
            if (data.board_view_mode && !localStorage.getItem(VIEW_MODE_KEY)) {
                board.viewMode = data.board_view_mode;
            }

            board.loaded = true;
            renderTagFilterOptions();

            if (!board.mobileStageId && board.stages.length) {
                board.mobileStageId = board.stages[0].id;
            }

            render();
            renderMetrics();
        } catch (error) {
            if (!opts.silent) {
                renderLoadError(error.message);
                KD.toastError(error.message);
            }
        }
    };

    const debouncedLoad = KD.debounce(() => KD.loadBoard(), 280);
    KD.onFilterInput = function () {
        updateToolbarState();
        debouncedLoad();
    };
    KD.onFilterChange = function () {
        updateToolbarState();
        KD.loadBoard();
    };

    // ---------------------------------------------------------------
    // Renderização
    // ---------------------------------------------------------------

    function renderSkeleton() {
        const container = el('kanbanBoard');
        if (!container) return;

        container.innerHTML = Array.from({ length: 4 }, () => `
            <div class="kd-col" aria-hidden="true">
                <div class="kd-col__header">
                    <div class="kd-skeleton" style="height:14px;width:45%"></div>
                    <div class="kd-skeleton" style="height:14px;width:24px"></div>
                </div>
                <div class="kd-col__body">
                    ${'<div class="kd-skeleton kd-skeleton-card"></div>'.repeat(3)}
                </div>
            </div>
        `).join('');
    }

    function renderLoadError(message) {
        const container = el('kanbanBoard');
        if (!container) return;
        container.innerHTML = `
            <div class="kd-col" style="max-width:none">
                <div class="kd-col__empty" style="padding:2rem">
                    <p style="font-size:0.8125rem;color:var(--danger);font-weight:600">${KD.escapeHtml(message)}</p>
                    <button type="button" class="kd-btn kd-btn--primary" style="margin-top:0.75rem" onclick="KD.loadBoard()">
                        Tentar novamente
                    </button>
                </div>
            </div>
        `;
        KD.icons();
    }

    /** Preenche o select de etiquetas com o que existe hoje no quadro. */
    function renderTagFilterOptions() {
        const select = el('filterTag');
        if (!select) return;

        const desired = board.pendingTag || select.value || '';
        select.innerHTML = '<option value="">Todas as etiquetas</option>'
            + board.allTags.map((tag) => `
                <option value="${KD.escapeHtml(tag.name)}">${KD.escapeHtml(tag.name)} (${tag.count})</option>
            `).join('');

        if (desired && board.allTags.some((t) => t.name === desired)) {
            select.value = desired;
        }
        board.pendingTag = '';
    }

    /** Filtra o quadro por uma etiqueta clicada no card. */
    KD.filterByTag = function (name) {
        const select = el('filterTag');
        if (!select) return;

        const alreadyOn = select.value === name;
        select.value = alreadyOn ? '' : name;
        showFilterBar(!alreadyOn);
        updateToolbarState();
        KD.loadBoard();
    };

    function renderMetrics() {
        if (!board.metrics) return;
        const map = {
            metricTotal: board.metrics.total_open,
            metricToday: board.metrics.due_today,
            metricOverdue: board.metrics.overdue,
            metricDoneToday: board.metrics.done_today,
        };
        Object.entries(map).forEach(([id, value]) => {
            const node = el(id);
            if (node) node.textContent = value;
        });
    }

    function tasksOfStage(stageId) {
        return board.tasks.filter((task) => Number(task.stage_id) === Number(stageId));
    }

    function getColumnTimeInfo(tasks) {
        const totalMinutes = tasks.reduce((sum, t) => sum + (Number(t.estimated_minutes) || 0), 0);
        const capacity = Number((board.workHours && board.workHours.capacityMinutes) || 480);
        const ratio = capacity > 0 ? (totalMinutes / capacity) : 0;

        let tone = 'ok';
        let label = 'Dentro da capacidade do expediente';
        if (ratio > 1.0) {
            tone = 'danger'; // Vermelho: extrapola o limite de horas do dia
            label = 'Extrapolou a capacidade do expediente!';
        } else if (ratio >= 0.8) {
            tone = 'warning'; // Amarelo: próximo de extrapolar o horário
            label = 'Próximo de extrapolar a capacidade do expediente';
        }

        return {
            totalMinutes,
            capacity,
            ratio,
            tone,
            label,
            formattedTotal: KD.formatMinutes ? KD.formatMinutes(totalMinutes) : `${totalMinutes}m`,
            formattedCapacity: KD.formatMinutes ? KD.formatMinutes(capacity) : `${capacity}m`,
        };
    }

    function getWeekDays() {
        const now = new Date();
        const currentDay = now.getDay(); // 0 = Domingo, 1 = Segunda, ...
        const sunday = new Date(now);
        sunday.setDate(now.getDate() - currentDay);
        sunday.setHours(0, 0, 0, 0);

        const days = [
            { id: 'sun', dayIndex: 0, name: 'Domingo', short: 'Dom', color: '#ec4899' },
            { id: 'mon', dayIndex: 1, name: 'Segunda-feira', short: 'Seg', color: '#6366f1' },
            { id: 'tue', dayIndex: 2, name: 'Terça-feira', short: 'Ter', color: '#3b82f6' },
            { id: 'wed', dayIndex: 3, name: 'Quarta-feira', short: 'Qua', color: '#06b6d4' },
            { id: 'thu', dayIndex: 4, name: 'Quinta-feira', short: 'Qui', color: '#10b981' },
            { id: 'fri', dayIndex: 5, name: 'Sexta-feira', short: 'Sex', color: '#f59e0b' },
            { id: 'sat', dayIndex: 6, name: 'Sábado', short: 'Sáb', color: '#8b5cf6' },
            { id: 'nodate', dayIndex: -1, name: 'Sem prazo', short: 'S/P', color: '#64748b' },
        ];

        const todayY = now.getFullYear();
        const todayM = String(now.getMonth() + 1).padStart(2, '0');
        const todayD = String(now.getDate()).padStart(2, '0');
        const todayStr = `${todayY}-${todayM}-${todayD}`;

        days.forEach((d) => {
            if (d.dayIndex >= 0) {
                const date = new Date(sunday);
                date.setDate(sunday.getDate() + d.dayIndex);
                const y = date.getFullYear();
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                d.dateString = `${y}-${m}-${day}`;
                d.formattedDate = `${day}/${m}`;
                d.isToday = (d.dateString === todayStr);
            } else {
                d.dateString = '';
                d.formattedDate = '';
                d.isToday = false;
            }
        });

        return days;
    }

    function tasksOfWeekday(day) {
        if (day.id === 'nodate') {
            return board.tasks.filter((t) => !t.due_date);
        }
        return board.tasks.filter((t) => {
            if (!t.due_date) return false;
            if (t.due_date === day.dateString) return true;
            const parts = t.due_date.split('-');
            if (parts.length === 3) {
                const dt = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
                if (dt.getDay() === day.dayIndex) return true;
            }
            return false;
        });
    }

    function destroySortables() {
        while (sortables.length) {
            const instance = sortables.pop();
            try { instance.destroy(); } catch (e) { /* já removido do DOM */ }
        }
    }

    function render() {
        const container = el('kanbanBoard');
        if (!container) return;

        // Guarda a rolagem de cada coluna para não "pular" a cada atualização.
        const scrollMemory = {};
        container.querySelectorAll('.kd-col__body').forEach((body) => {
            const key = body.dataset.stageId || body.dataset.weekdayId;
            if (key) scrollMemory[key] = body.scrollTop;
        });
        const boardScroll = container.scrollLeft;
        const focusedTaskId = document.activeElement?.closest?.('.kd-card')?.dataset.taskId || null;

        destroySortables();

        if (board.viewMode === 'weekdays') {
            const days = getWeekDays();
            const visibleDays = days.filter((d) => !board.hiddenColumns.weekdays.has(d.id));
            container.innerHTML = visibleDays.length > 0
                ? visibleDays.map(columnWeekdayHtml).join('')
                : `<div class="p-8 text-center text-slate-400 text-sm">Todas as colunas deste modo estão ocultas. <button type="button" class="text-indigo-400 underline font-semibold ml-1" onclick="KD.resetVisibleColumns()">Restaurar colunas</button></div>`;
        } else {
            const visibleStages = board.stages.filter((s) => !board.hiddenColumns.status.has(String(s.id)));
            container.innerHTML = visibleStages.length > 0
                ? visibleStages.map(columnHtml).join('')
                : `<div class="p-8 text-center text-slate-400 text-sm">Todas as colunas deste modo estão ocultas. <button type="button" class="text-indigo-400 underline font-semibold ml-1" onclick="KD.resetVisibleColumns()">Restaurar colunas</button></div>`;
        }

        // Restaura rolagem, foco e reativa o arrastar
        container.scrollLeft = boardScroll;
        container.querySelectorAll('.kd-col__body').forEach((body) => {
            const key = body.dataset.stageId || body.dataset.weekdayId;
            const remembered = scrollMemory[key];
            if (remembered) body.scrollTop = remembered;
            initSortable(body);
        });

        if (focusedTaskId) {
            const card = container.querySelector(`.kd-card[data-task-id="${focusedTaskId}"]`);
            if (card) card.focus({ preventScroll: true });
        }

        renderStageTabs();
        applyMobileStage();
        updateToolbarState();
        applyTagVisibility();
        updateTagMenuCheckmarks();
        KD.icons();
    }

    function isMentionsStage(stage) {
        if (!stage) return false;
        const slug = String(stage.slug || '').trim().toLowerCase();
        const name = String(stage.name || '').trim().toLowerCase();
        return slug === 'mentions' || name === 'menções' || name === 'mencoes' || name.startsWith('menç') || name.startsWith('menc');
    }

    function columnHtml(stage) {
        const tasks = tasksOfStage(stage.id);
        const isCollapsed = board.collapsed.has(String(stage.id));
        const overLimit = stage.wip_limit && tasks.length > stage.wip_limit;
        const countLabel = stage.wip_limit ? `${tasks.length}/${stage.wip_limit}` : String(tasks.length);
        const isMentions = isMentionsStage(stage);
        const timeInfo = isMentions ? null : getColumnTimeInfo(tasks);

        return `
            <section class="kd-col kd-glass ${isCollapsed ? 'kd-col--collapsed' : ''}"
                     data-stage-id="${stage.id}"
                     aria-label="Coluna ${KD.escapeHtml(stage.name)}, ${tasks.length} tarefa(s)">

                <header class="kd-col__header">
                    <div class="flex items-center justify-between gap-2 min-w-0 w-full">
                        <h3 class="kd-col__name flex-1 min-w-0">
                            <span class="kd-col__dot flex-shrink-0" style="background-color:${KD.escapeHtml(stage.color || '#6366f1')}"></span>
                            <span class="leading-snug break-words" title="${KD.escapeHtml(stage.name)}">${KD.escapeHtml(stage.name)}</span>
                            <span class="kd-col__count flex-shrink-0 ${overLimit ? 'kd-col__count--over' : ''}">${countLabel}</span>
                        </h3>

                        <div class="flex items-center gap-1 flex-shrink-0">
                            <button type="button" class="kd-icon-btn" data-action="toggle-collapse" data-stage-id="${stage.id}"
                                    aria-label="${isCollapsed ? 'Expandir' : 'Recolher'} coluna ${KD.escapeHtml(stage.name)}">
                                <i data-lucide="${isCollapsed ? 'chevrons-right' : 'chevrons-left'}" class="w-4 h-4"></i>
                            </button>

                            <div class="kd-menu-wrap kd-col__menu-wrap">
                                <button type="button" class="kd-icon-btn" data-action="toggle-column-menu" data-stage-id="${stage.id}"
                                        aria-haspopup="menu" aria-expanded="false" aria-label="Opções da coluna ${KD.escapeHtml(stage.name)}">
                                    <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                                </button>
                                ${columnMenuHtml(stage, tasks.length)}
                            </div>
                        </div>
                    </div>

                    ${timeInfo ? `
                        <div class="kd-col__subbar">
                            <span class="flex items-center gap-1 font-medium text-[11px] text-slate-400 whitespace-nowrap">
                                <i data-lucide="clock" class="w-3 h-3 text-indigo-400 flex-shrink-0"></i>
                                <span>Tempo:</span>
                                <strong class="font-mono text-slate-200 text-xs">${timeInfo.formattedTotal}</strong>
                            </span>
                            <span class="kd-time-pill kd-time-pill--${timeInfo.tone} text-[10px]" title="Capacidade diária: ${timeInfo.formattedCapacity} (${timeInfo.label})">
                                ${Math.round(timeInfo.ratio * 100)}% da jornada
                            </span>
                        </div>
                    ` : ''}
                </header>

                ${overLimit ? `
                    <p class="kd-col__wip">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                        Acima do limite de ${stage.wip_limit} tarefa(s) em andamento
                    </p>
                ` : ''}

                ${timeInfo && timeInfo.tone === 'danger' ? `
                    <p class="kd-col__wip" style="color:var(--danger)">
                        <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                        Tarefas extrapolaram o limite diário de ${timeInfo.formattedCapacity}
                    </p>
                ` : (timeInfo && timeInfo.tone === 'warning' ? `
                    <p class="kd-col__wip" style="color:var(--warning)">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                        Próximo de extrapolar a capacidade diária (${Math.round(timeInfo.ratio * 100)}%)
                    </p>
                ` : '')}

                <div class="kd-col__body" data-stage-id="${stage.id}" role="list">
                    ${tasks.length === 0 ? emptyStateHtml(stage) : cardsHtml(tasks, stage)}
                </div>

                ${stage.is_done && board.doneHidden > 0 ? `
                    <p class="kd-col__empty" style="margin-top:0.5rem">
                        ${board.doneHidden} tarefa(s) concluída(s) há mais de ${board.doneVisibleDays} dias saíram do quadro.
                    </p>
                ` : ''}

                <button type="button" class="kd-col__add" data-action="new-task" data-stage-id="${stage.id}">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Adicionar tarefa
                </button>
            </section>
        `;
    }

    function columnWeekdayHtml(day) {
        const tasks = tasksOfWeekday(day);
        const isCollapsed = board.collapsed.has(String(day.id));
        const timeInfo = getColumnTimeInfo(tasks);

        return `
            <section class="kd-col kd-glass ${isCollapsed ? 'kd-col--collapsed' : ''}"
                     data-weekday-id="${day.id}"
                     aria-label="Coluna ${KD.escapeHtml(day.name)}, ${tasks.length} tarefa(s)">

                <header class="kd-col__header">
                    <div class="flex items-center justify-between gap-2 min-w-0 w-full">
                        <h3 class="kd-col__name flex-1 min-w-0">
                            <span class="kd-col__dot flex-shrink-0" style="background-color:${KD.escapeHtml(day.color || '#6366f1')}"></span>
                            <span class="leading-snug break-words" title="${KD.escapeHtml(day.name)}">${KD.escapeHtml(day.name)}</span>
                            ${day.formattedDate ? `<span class="kd-col-date flex-shrink-0">${day.formattedDate}</span>` : ''}
                            ${day.isToday ? `<span class="kd-col-today-chip flex-shrink-0">Hoje</span>` : ''}
                            <span class="kd-col__count flex-shrink-0">${tasks.length}</span>
                        </h3>

                        <div class="flex items-center gap-1 flex-shrink-0">
                            <button type="button" class="kd-icon-btn" data-action="toggle-collapse-weekday" data-weekday-id="${day.id}"
                                    aria-label="${isCollapsed ? 'Expandir' : 'Recolher'} coluna ${KD.escapeHtml(day.name)}">
                                <i data-lucide="${isCollapsed ? 'chevrons-right' : 'chevrons-left'}" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <div class="kd-col__subbar">
                        <span class="flex items-center gap-1 font-medium text-[11px] text-slate-400 whitespace-nowrap">
                            <i data-lucide="clock" class="w-3 h-3 text-indigo-400 flex-shrink-0"></i>
                            <span>Tempo:</span>
                            <strong class="font-mono text-slate-200 text-xs">${timeInfo.formattedTotal}</strong>
                        </span>
                        <span class="kd-time-pill kd-time-pill--${timeInfo.tone} text-[10px]" title="Capacidade: ${timeInfo.formattedCapacity} (${timeInfo.label})">
                            ${Math.round(timeInfo.ratio * 100)}% da jornada
                        </span>
                    </div>
                </header>

                ${timeInfo.tone === 'danger' ? `
                    <p class="kd-col__wip" style="color:var(--danger)">
                        <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                        Tarefas extrapolaram o limite diário de ${timeInfo.formattedCapacity}
                    </p>
                ` : (timeInfo.tone === 'warning' ? `
                    <p class="kd-col__wip" style="color:var(--warning)">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                        Próximo de extrapolar a capacidade diária (${Math.round(timeInfo.ratio * 100)}%)
                    </p>
                ` : '')}

                <div class="kd-col__body" data-weekday-id="${day.id}" data-due-date="${day.dateString || ''}" role="list">
                    ${tasks.length === 0 ? emptyStateWeekdayHtml(day) : cardsHtml(tasks, { id: 0, name: day.name })}
                </div>

                <button type="button" class="kd-col__add" data-action="new-task-weekday" data-due-date="${day.dateString || ''}">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    Adicionar tarefa
                </button>
            </section>
        `;
    }

    function emptyStateWeekdayHtml(day) {
        if (activeFilterCount() > 0) {
            return `<p class="kd-col__empty">Nenhuma tarefa neste dia com os filtros atuais.</p>`;
        }
        if (day.id === 'nodate') {
            return `<p class="kd-col__empty">Nenhuma tarefa sem prazo.<br>Arraste um card aqui para remover a data.</p>`;
        }
        return `<p class="kd-col__empty">Nenhuma entrega programada para ${KD.escapeHtml(day.name)}.<br>Arraste cards para este dia ou adicione abaixo.</p>`;
    }

    function columnMenuHtml(stage, count) {
        return `
            <div class="kd-menu kd-glass kd-glass--raised" role="menu" hidden>
                <button type="button" class="kd-menu__item" role="menuitem" data-action="new-task" data-stage-id="${stage.id}">
                    <i data-lucide="plus" class="w-4 h-4"></i> Adicionar tarefa
                </button>
                <button type="button" class="kd-menu__item" role="menuitem" data-action="set-wip" data-stage-id="${stage.id}">
                    <i data-lucide="gauge" class="w-4 h-4"></i>
                    ${stage.wip_limit ? `Limite de WIP: ${stage.wip_limit}` : 'Definir limite de WIP'}
                </button>
                <button type="button" class="kd-menu__item" role="menuitem" data-action="toggle-collapse" data-stage-id="${stage.id}">
                    <i data-lucide="columns" class="w-4 h-4"></i>
                    ${board.collapsed.has(Number(stage.id)) ? 'Expandir coluna' : 'Recolher coluna'}
                </button>
                ${stage.is_done && count > 0 ? `
                    <div class="kd-menu__sep"></div>
                    <button type="button" class="kd-menu__item" role="menuitem" data-action="archive-done">
                        <i data-lucide="archive" class="w-4 h-4"></i> Arquivar concluídas
                    </button>
                ` : ''}
            </div>
        `;
    }

    function emptyStateHtml(stage) {
        if (activeFilterCount() > 0) {
            return `<p class="kd-col__empty">Nenhuma tarefa nesta coluna com os filtros atuais.</p>`;
        }
        if (stage.is_done) {
            return `<p class="kd-col__empty">Nada concluído por aqui ainda.<br>Arraste um card até esta coluna para fechá-lo.</p>`;
        }
        return `<p class="kd-col__empty">Coluna vazia.<br>Use <strong>Adicionar tarefa</strong> abaixo ou arraste um card para cá.</p>`;
    }

    function cardsHtml(tasks, stage) {
        if (!board.groupByClient) {
            return tasks.map((task) => cardHtml(task, stage)).join('');
        }

        // Agrupamento por cliente: rótulo discreto separando os blocos.
        const groups = new Map();
        tasks.forEach((task) => {
            const key = task.client_company || 'Sem cliente';
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(task);
        });

        return Array.from(groups.entries())
            .sort((a, b) => a[0].localeCompare(b[0], 'pt-BR'))
            .map(([name, items]) => `
                <p class="kd-group-label">${KD.escapeHtml(name)} (${items.length})</p>
                ${items.map((task) => cardHtml(task, stage)).join('')}
            `).join('');
    }

    function cardHtml(task, stage) {
        const isRunning = Number(task.is_timer_running) === 1;
        const inFocus = Number(task.in_focus) === 1;
        const isBomb = Number(task.is_bomb) === 1;
        const isDone = Boolean(task.completed_at);
        const isMention = task.source === 'whatsapp_mention' || Boolean(task.group_name || task.mentioned_by);

        const checklistTotal = Number(task.checklist_total || 0);
        const checklistDone = Number(task.checklist_done || 0);
        const percent = checklistTotal > 0 ? Math.round((checklistDone / checklistTotal) * 100) : 0;
        const comments = Number(task.comments_count || 0);
        const due = task.due_badge || {};
        const priority = (task.priority_info && task.priority_info.label) || 'Média';

        const tags = task.tags || [];
        const assignees = task.assignees || [];
        const shown = assignees.slice(0, 3);
        const extra = assignees.length - shown.length;

        const ariaLabel = [
            task.title,
            task.client_company ? `cliente ${task.client_company}` : null,
            `prioridade ${priority}`,
            due.label || null,
            checklistTotal > 0 ? `${checklistDone} de ${checklistTotal} subtarefas` : null,
            isBomb ? 'tarefa bomba, presa a esta coluna até ser resolvida' : null,
        ].filter(Boolean).join(', ');

        return `
            <article class="kd-card kd-glass kd-glass--raised"
                     role="listitem"
                     tabindex="0"
                     data-task-id="${task.id}"
                     data-stage-id="${task.stage_id}"
                     data-priority="${KD.escapeHtml(task.priority || 'medium')}"
                     data-running="${isRunning ? 1 : 0}"
                     data-focus="${inFocus ? 1 : 0}"
                     data-bomb="${isBomb ? 1 : 0}"
                     data-done="${isDone ? 1 : 0}"
                     data-seconds="${Number(task.effective_seconds || 0)}"
                     aria-label="${KD.escapeHtml(ariaLabel)}">

                <div class="kd-card__top">
                    <div class="kd-card__meta-left">
                        <span class="kd-prio">${KD.escapeHtml(priority)}</span>
                    </div>

                    <div class="kd-menu-wrap">
                        <button type="button" class="kd-icon-btn" data-action="toggle-card-menu" data-task-id="${task.id}"
                                aria-haspopup="menu" aria-expanded="false"
                                aria-label="Ações da tarefa ${KD.escapeHtml(task.title)}">
                            <i data-lucide="more-vertical" class="w-4 h-4"></i>
                        </button>
                        ${cardMenuHtml(task, inFocus, isBomb, isDone)}
                    </div>
                </div>

                <button type="button" class="kd-card__title" data-action="open" data-task-id="${task.id}">
                    ${KD.escapeHtml(task.title)}
                </button>

                ${task.client_company
                    ? `<span class="kd-client" title="${KD.escapeHtml(task.client_company)}">${KD.escapeHtml(task.client_company)}</span>`
                    : '<span class="kd-client kd-client--none">Sem cliente</span>'}

                ${tags.length > 0 ? `
                    <div class="kd-tags">
                        ${tags.slice(0, 3).map((tag) => `
                            <button type="button" class="kd-tag"
                                    data-action="filter-tag" data-tag="${KD.escapeHtml(tag.name)}"
                                    title="Filtrar por ${KD.escapeHtml(tag.name)}">${KD.escapeHtml(tag.name)}</button>
                        `).join('')}
                        ${tags.length > 3 ? `<span class="kd-tag kd-tag--more" title="${KD.escapeHtml(tags.slice(3).map((t) => t.name).join(', '))}">+${tags.length - 3}</span>` : ''}
                    </div>
                ` : ''}

                ${(inFocus || isBomb || isMention) ? `
                    <div class="flex items-center gap-1.5 flex-wrap">
                        ${inFocus ? '<span class="kd-flag kd-flag--focus"><i data-lucide="target" class="w-3 h-3"></i>Foco</span>' : ''}
                        ${isBomb ? '<span class="kd-flag kd-flag--bomb" title="Precisa ser resolvida antes de estourar. Não muda de coluna enquanto estiver marcada."><i data-lucide="flame" class="w-3 h-3"></i>Bomba</span>' : ''}
                        ${isMention ? `<span class="kd-flag kd-flag--mention"><i data-lucide="message-circle" class="w-3 h-3"></i>${KD.escapeHtml(task.mentioned_by ? '@' + task.mentioned_by : 'WhatsApp')}</span>` : ''}
                    </div>
                ` : ''}

                <div class="kd-meta">
                    ${due.text ? `
                        <span class="kd-due" data-tone="${KD.escapeHtml(due.tone || 'later')}" title="${KD.escapeHtml(due.label || due.text || '')}">
                            <i data-lucide="calendar" class="w-3 h-3"></i>
                            <span class="kd-due__short">${KD.escapeHtml(due.short || due.text)}</span>
                            <span class="kd-due__full">${KD.escapeHtml(due.text)}</span>
                        </span>
                    ` : ''}

                    ${Number(task.is_recurring) === 1 ? `
                        <span class="kd-meta__item kd-recurring-badge" title="Tarefa recorrente: ${KD.escapeHtml(task.recurrence_label || 'Ativa')}">
                            <i data-lucide="repeat" class="w-3 h-3" style="color:var(--brand-strong)"></i>
                        </span>
                    ` : ''}

                    ${checklistTotal > 0 ? `
                        <span class="kd-progress ${percent === 100 ? 'kd-progress--done' : ''}"
                              title="${checklistDone} de ${checklistTotal} subtarefas concluídas">
                            <span class="kd-progress__track"><span class="kd-progress__fill" style="width:${percent}%"></span></span>
                            <span>${checklistDone}/${checklistTotal}</span>
                        </span>
                    ` : ''}

                    ${comments > 0 ? `
                        <span class="kd-meta__item" title="${comments} comentário(s)">
                            <i data-lucide="message-square" class="w-3 h-3"></i>${comments}
                        </span>
                    ` : ''}

                    ${Number(task.estimated_minutes) > 0 ? `
                        <span class="kd-est-time-badge" title="Tempo estimado: ${KD.formatMinutes ? KD.formatMinutes(task.estimated_minutes) : `${task.estimated_minutes} min`}">
                            <i data-lucide="clock" class="w-3 h-3"></i>
                            <span>${KD.formatMinutes ? KD.formatMinutes(task.estimated_minutes) : `${task.estimated_minutes}m`}</span>
                        </span>
                    ` : ''}

                    <span class="kd-meta__item" style="opacity:.7" title="Identificador da tarefa">#${task.id}</span>
                </div>

                <div class="kd-card__footer">
                    <div class="kd-avatars">
                        ${shown.length > 0
                            ? shown.map((user) => `<span class="kd-avatar" title="${KD.escapeHtml(user.name)}">${KD.escapeHtml(user.initial)}</span>`).join('')
                              + (extra > 0 ? `<span class="kd-avatar kd-avatar--empty" title="Mais ${extra} responsável(is)">+${extra}</span>` : '')
                            : '<span class="kd-avatar kd-avatar--empty" title="Sem responsável">—</span>'}
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="kd-timer" data-running="${isRunning ? 1 : 0}" id="kdTimer-${task.id}">
                            ${KD.formatSeconds(task.effective_seconds)}
                        </span>
                        <button type="button" class="kd-play" data-running="${isRunning ? 1 : 0}"
                                data-action="timer" data-task-id="${task.id}"
                                aria-label="${isRunning ? 'Pausar' : 'Iniciar'} cronômetro de ${KD.escapeHtml(task.title)}">
                            <i data-lucide="${isRunning ? 'pause' : 'play'}" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>
            </article>
        `;
    }

    function cardMenuHtml(task, inFocus, isBomb, isDone) {
        // Bomba não muda de coluna: o menu não oferece o caminho.
        const moveOptions = isBomb ? '' : board.stages
            .filter((stage) => Number(stage.id) !== Number(task.stage_id))
            .map((stage) => `
                <button type="button" class="kd-menu__item" role="menuitem"
                        data-action="move-to" data-task-id="${task.id}" data-stage-id="${stage.id}">
                    <span class="kd-col__dot" style="background-color:${KD.escapeHtml(stage.color || '#6366f1')}"></span>
                    ${KD.escapeHtml(stage.name)}
                </button>
            `).join('');

        return `
            <div class="kd-menu kd-glass kd-glass--raised" role="menu" hidden>
                <button type="button" class="kd-menu__item" role="menuitem" data-action="edit" data-task-id="${task.id}">
                    <i data-lucide="edit-3" class="w-4 h-4"></i> Editar
                    <span class="kd-menu__hint">E</span>
                </button>
                <button type="button" class="kd-menu__item" role="menuitem" data-action="${isDone ? 'reopen' : 'complete'}" data-task-id="${task.id}">
                    <i data-lucide="${isDone ? 'rotate-ccw' : 'check-circle-2'}" class="w-4 h-4"></i>
                    ${isDone ? 'Reabrir tarefa' : 'Marcar como concluída'}
                </button>
                <button type="button" class="kd-menu__item" role="menuitem" data-action="focus" data-task-id="${task.id}">
                    <i data-lucide="target" class="w-4 h-4"></i> ${inFocus ? 'Tirar do foco' : 'Colocar em foco'}
                </button>
                <button type="button" class="kd-menu__item" role="menuitem" data-action="bomb" data-task-id="${task.id}">
                    <i data-lucide="flame" class="w-4 h-4"></i> ${isBomb ? 'Desmarcar bomba' : 'Marcar como bomba'}
                </button>

                <div class="kd-menu__sep"></div>
                ${moveOptions
                    ? `<p class="kd-group-label" style="padding:0.25rem 0.6rem">Mover para</p>${moveOptions}<div class="kd-menu__sep"></div>`
                    : `<p class="kd-group-label" style="padding:0.25rem 0.6rem;color:var(--danger)">Presa até ser resolvida</p><div class="kd-menu__sep"></div>`}

                <button type="button" class="kd-menu__item" role="menuitem" data-action="duplicate" data-task-id="${task.id}">
                    <i data-lucide="copy" class="w-4 h-4"></i> Duplicar
                </button>
                <button type="button" class="kd-menu__item" role="menuitem" data-action="copy-link" data-task-id="${task.id}">
                    <i data-lucide="link-2" class="w-4 h-4"></i> Copiar link
                </button>
                <button type="button" class="kd-menu__item kd-menu__item--danger" role="menuitem" data-action="delete" data-task-id="${task.id}">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Excluir
                </button>
            </div>
        `;
    }

    // ---------------------------------------------------------------
    // Abas de coluna no celular
    // ---------------------------------------------------------------

    function renderStageTabs() {
        const tabs = el('kanbanStageTabs');
        if (!tabs) return;

        tabs.innerHTML = board.stages.map((stage) => {
            const count = tasksOfStage(stage.id).length;
            const selected = Number(board.mobileStageId) === Number(stage.id);
            return `
                <button type="button" class="kd-segmented__item" role="tab"
                        aria-selected="${selected}" data-action="mobile-stage" data-stage-id="${stage.id}">
                    <span class="kd-col__dot" style="background-color:${KD.escapeHtml(stage.color || '#6366f1')}"></span>
                    ${KD.escapeHtml(stage.name)}
                    <span class="kd-col__count">${count}</span>
                </button>
            `;
        }).join('');
    }

    function applyMobileStage() {
        document.querySelectorAll('.kd-col[data-stage-id]').forEach((column) => {
            column.classList.toggle('is-active-mobile', Number(column.dataset.stageId) === Number(board.mobileStageId));
        });
    }

    // ---------------------------------------------------------------
    // Arrastar e soltar
    // ---------------------------------------------------------------

    function initSortable(bodyEl) {
        if (!window.Sortable) return;

        // Enquanto o quadro está agrupado por cliente, a ordem manual não faz
        // sentido dentro da coluna: mover entre colunas continua pelo menu.
        if (board.groupByClient) return;

        sortables.push(new Sortable(bodyEl, {
            group: 'kanbandoo-cards',
            animation: 160,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            draggable: '.kd-card',
            // Tarefa bomba não é arrastável de forma alguma
            filter: '.kd-card[data-bomb="1"]',
            preventOnFilter: false,
            delay: 120,
            delayOnTouchOnly: true,
            touchStartThreshold: 5,
            onStart: () => {
                board.dragging = true;
                document.documentElement.classList.add('kd-dragging');
                KD.closeMenus();
            },
            // Rede de segurança: nem arrastar para cima de/para fora de uma bomba
            onMove: (event) => event.dragged.dataset.bomb !== '1',
            onEnd: handleDragEnd,
        }));
    }

    async function handleDragEnd(event) {
        board.dragging = false;
        document.documentElement.classList.remove('kd-dragging');

        const card = event.item;
        const taskId = Number(card.dataset.taskId);
        const targetBody = event.to;
        const fromBody = event.from;

        if (!taskId || !targetBody) return;

        card.classList.add('is-busy');

        try {
            if (board.viewMode === 'weekdays') {
                const newDueDate = targetBody.dataset.dueDate || null;
                const oldDueDate = fromBody.dataset.dueDate || null;

                if (newDueDate === oldDueDate && event.oldIndex === event.newIndex) {
                    card.classList.remove('is-busy');
                    return;
                }

                const orderedIds = Array.from(targetBody.querySelectorAll('.kd-card')).map((node) => Number(node.dataset.taskId));

                await KD.api.tasks({
                    action: 'move',
                    task_id: taskId,
                    due_date: newDueDate,
                    ordered_ids: orderedIds,
                });

                KD.toast(newDueDate ? `Reagendada para ${KD.formatDate(newDueDate)}.` : 'Prazo removido.');
                await KD.loadBoard({ silent: true });
            } else {
                const stageId = Number(targetBody.dataset.stageId);
                const fromStageId = Number(fromBody.dataset.stageId);

                if (!stageId) {
                    card.classList.remove('is-busy');
                    return;
                }
                if (stageId === fromStageId && event.oldIndex === event.newIndex) {
                    card.classList.remove('is-busy');
                    return;
                }

                const orderedIds = Array.from(targetBody.querySelectorAll('.kd-card')).map((node) => Number(node.dataset.taskId));

                const result = await KD.api.tasks({
                    action: 'move',
                    task_id: taskId,
                    stage_id: stageId,
                    ordered_ids: orderedIds,
                });

                const stage = board.stages.find((s) => Number(s.id) === stageId);
                if (stage && stage.is_done) {
                    KD.toast('Tarefa concluída.', {
                        undo: () => undoMove(result.undo),
                    });
                }

                await KD.loadBoard({ silent: true });
            }
        } catch (error) {
            card.classList.remove('is-busy');
            KD.toastError(error.message || 'Não foi possível mover a tarefa. O quadro foi restaurado.');
            await KD.loadBoard({ silent: true });
        }
    }

    async function undoMove(undo) {
        if (!undo) return;
        try {
            await KD.api.tasks(undo);
            await KD.loadBoard({ silent: true });
            KD.toast('Movimentação desfeita.', { tone: 'info' });
        } catch (error) {
            KD.toastError(error.message);
        }
    }

    // ---------------------------------------------------------------
    // Ações do quadro (delegação de eventos — um único listener)
    // ---------------------------------------------------------------

    async function runAction(action, node) {
        const taskId = Number(node.dataset.taskId || 0);
        const stageId = Number(node.dataset.stageId || 0);

        switch (action) {
            case 'open':
                KD.openTaskDetail(taskId);
                break;

            case 'edit':
                KD.closeMenus();
                KD.openTaskForm(taskId);
                break;

            case 'new-task':
                KD.closeMenus();
                KD.openTaskForm(null, stageId);
                break;

            case 'new-task-weekday': {
                KD.closeMenus();
                const dueDate = node.dataset.dueDate || null;
                KD.openTaskForm(null, null, null, dueDate);
                break;
            }

            case 'toggle-card-menu':
            case 'toggle-column-menu': {
                const menu = node.parentElement.querySelector('.kd-menu');
                if (menu) KD.toggleMenu(menu, node);
                break;
            }

            case 'toggle-collapse':
                KD.closeMenus();
                if (board.collapsed.has(String(stageId))) board.collapsed.delete(String(stageId));
                else board.collapsed.add(String(stageId));
                saveViewPrefs();
                render();
                break;

            case 'toggle-collapse-weekday': {
                KD.closeMenus();
                const wId = String(node.dataset.weekdayId || '');
                if (board.collapsed.has(wId)) board.collapsed.delete(wId);
                else board.collapsed.add(wId);
                saveViewPrefs();
                render();
                break;
            }

            case 'filter-tag':
                KD.filterByTag(node.dataset.tag);
                break;

            case 'mobile-stage':
                board.mobileStageId = stageId;
                renderStageTabs();
                applyMobileStage();
                break;

            case 'timer':
                await toggleTimer(taskId);
                break;

            case 'focus':
            case 'bomb': {
                KD.closeMenus();
                const result = await KD.api.tasks({
                    action: action === 'focus' ? 'toggle_focus' : 'toggle_bomb',
                    task_id: taskId,
                });
                const on = action === 'focus' ? result.in_focus : result.is_bomb;
                KD.toast(action === 'focus'
                    ? (on ? 'Tarefa colocada em foco.' : 'Tarefa retirada do foco.')
                    : (on ? 'Tarefa marcada como bomba.' : 'Marcação de bomba removida.'));
                await KD.loadBoard({ silent: true });
                break;
            }

            case 'complete': {
                KD.closeMenus();
                const result = await KD.api.tasks({ action: 'complete_task', task_id: taskId });
                await KD.loadBoard({ silent: true });
                KD.toast('Tarefa concluída.', { undo: () => undoMove(result.undo) });
                break;
            }

            case 'reopen':
                KD.closeMenus();
                await KD.api.tasks({ action: 'uncomplete_task', task_id: taskId });
                await KD.loadBoard({ silent: true });
                KD.toast('Tarefa reaberta.', { tone: 'info' });
                break;

            case 'move-to': {
                KD.closeMenus();
                const result = await KD.api.tasks({ action: 'move', task_id: taskId, stage_id: stageId });
                await KD.loadBoard({ silent: true });
                const stage = board.stages.find((s) => Number(s.id) === stageId);
                KD.toast(`Movida para ${stage ? stage.name : 'outra coluna'}.`, { undo: () => undoMove(result.undo) });
                break;
            }

            case 'duplicate': {
                KD.closeMenus();
                const result = await KD.api.tasks({ action: 'duplicate_task', task_id: taskId });
                await KD.loadBoard({ silent: true });
                KD.toast('Tarefa duplicada.', { undo: () => undoMove(result.undo) });
                break;
            }

            case 'copy-link': {
                KD.closeMenus();
                const url = `${window.location.origin}${KD.url('/tarefas')}/${taskId}`;
                try {
                    await navigator.clipboard.writeText(url);
                    KD.toast('Link da tarefa copiado.');
                } catch (e) {
                    window.prompt('Copie o link da tarefa:', url);
                }
                break;
            }

            case 'delete': {
                KD.closeMenus();
                // Sem confirm(): exclui e oferece Desfazer por alguns segundos.
                const result = await KD.api.tasks({ action: 'delete', task_id: taskId });
                await KD.loadBoard({ silent: true });
                KD.toast(`"${result.title || 'Tarefa'}" excluída.`, {
                    undo: async () => {
                        await KD.api.tasks({ action: 'undelete', task_id: taskId });
                        await KD.loadBoard({ silent: true });
                        KD.toast('Exclusão desfeita.', { tone: 'info' });
                    },
                });
                break;
            }

            case 'set-wip': {
                KD.closeMenus();
                const stage = board.stages.find((s) => Number(s.id) === stageId);
                const answer = window.prompt(
                    `Limite de tarefas simultâneas em "${stage ? stage.name : 'coluna'}".\nDeixe vazio para não ter limite.`,
                    stage && stage.wip_limit ? String(stage.wip_limit) : ''
                );
                if (answer === null) break;
                await KD.api.tasks({ action: 'set_wip_limit', stage_id: stageId, wip_limit: answer.trim() });
                await KD.loadBoard({ silent: true });
                KD.toast(answer.trim() ? `Limite definido em ${answer.trim()}.` : 'Limite removido.');
                break;
            }

            case 'archive-done': {
                KD.closeMenus();
                const result = await KD.api.tasks({ action: 'archive_done' });
                await KD.loadBoard({ silent: true });
                KD.toast(`${result.archived} tarefa(s) arquivada(s).`, { tone: 'info' });
                break;
            }

            default:
                break;
        }
    }

    async function toggleTimer(taskId) {
        try {
            const result = await KD.api.tasks({ action: 'toggle_timer', task_id: taskId });
            await KD.loadBoard({ silent: true });
            KD.toast(result.is_running ? 'Cronômetro iniciado.' : 'Cronômetro pausado.', { tone: 'info' });
            if (result.is_running && result.paused_tasks && result.paused_tasks.length > 0) {
                KD.toast('O cronômetro da outra tarefa foi pausado.', { tone: 'info' });
            }
        } catch (error) {
            KD.toastError(error.message);
        }
    }

    document.addEventListener('click', (event) => {
        const node = event.target.closest('[data-action]');
        if (!node) return;
        // O menu aberto é movido para o body, então também conta como área do quadro.
        if (!node.closest('#kanbanBoard') && !node.closest('#kanbanStageTabs') && !node.closest('.kd-menu')) return;

        event.preventDefault();
        event.stopPropagation();

        Promise.resolve(runAction(node.dataset.action, node)).catch((error) => {
            KD.toastError(error.message);
            KD.loadBoard({ silent: true });
        });
    });

    // ---------------------------------------------------------------
    // Teclado: alternativa completa ao arrastar e soltar
    // ---------------------------------------------------------------

    document.addEventListener('keydown', (event) => {
        // Atalhos globais do quadro
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(event.target.tagName) || event.target.isContentEditable;

        if (!typing && !KD.isModalOpen()) {
            if (event.key === '/') {
                event.preventDefault();
                showFilterBar(true);
                el('filterSearch')?.focus();
                return;
            }
            if (event.key.toLowerCase() === 'n' && !event.ctrlKey && !event.metaKey) {
                event.preventDefault();
                KD.openTaskForm();
                return;
            }
        }

        const card = event.target.closest?.('.kd-card');
        if (!card) return;

        const taskId = Number(card.dataset.taskId);
        const stageId = Number(card.dataset.stageId);

        if (event.key === 'Enter' || event.key === ' ') {
            if (event.target.tagName === 'BUTTON') return;
            event.preventDefault();
            KD.openTaskDetail(taskId);
            return;
        }

        if (event.key.toLowerCase() === 'e' && !event.ctrlKey && !event.metaKey) {
            event.preventDefault();
            KD.openTaskForm(taskId);
            return;
        }

        // Ctrl + setas move a tarefa entre colunas sem usar o mouse
        if ((event.ctrlKey || event.metaKey) && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
            event.preventDefault();

            if (card.dataset.bomb === '1') {
                KD.toastError('Tarefa bomba não muda de coluna. Resolva-a ou desmarque a bomba.');
                return;
            }

            const index = board.stages.findIndex((stage) => Number(stage.id) === stageId);
            const nextIndex = event.key === 'ArrowLeft' ? index - 1 : index + 1;
            const nextStage = board.stages[nextIndex];
            if (!nextStage) return;

            KD.api.tasks({ action: 'move', task_id: taskId, stage_id: nextStage.id })
                .then((result) => KD.loadBoard({ silent: true }).then(() => {
                    KD.toast(`Movida para ${nextStage.name}.`, { undo: () => undoMove(result.undo) });
                    const moved = document.querySelector(`.kd-card[data-task-id="${taskId}"]`);
                    if (moved) moved.focus();
                }))
                .catch((error) => KD.toastError(error.message));
        }
    });

    // ---------------------------------------------------------------
    // Cronômetros em execução e atualização periódica
    // ---------------------------------------------------------------

    function startTicker() {
        window.setInterval(() => {
            document.querySelectorAll('.kd-card[data-running="1"]').forEach((card) => {
                const seconds = Number(card.dataset.seconds || 0) + 1;
                card.dataset.seconds = seconds;
                const display = document.getElementById(`kdTimer-${card.dataset.taskId}`);
                if (display) display.textContent = KD.formatSeconds(seconds);
            });
        }, 1000);
    }

    function startAutoRefresh() {
        window.setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            if (KD.isModalOpen() || board.dragging) return;
            if (document.querySelector('.kd-menu:not([hidden])')) return;
            if (document.activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;

            KD.loadBoard({ silent: true });
        }, 30000);
    }

    // ---------------------------------------------------------------
    // Controle de Modo de Visão e Colunas Visíveis
    // ---------------------------------------------------------------

    KD.renderBoard = function () {
        render();
    };

    KD.setViewMode = function (mode) {
        if (mode !== 'status' && mode !== 'weekdays') return;
        board.viewMode = mode;
        saveViewPrefs();
        updateToolbarState();
        render();
        KD.syncPrefViewMode(mode);
    };

    KD.syncPrefViewMode = async function (mode) {
        try {
            await fetch(KD.url('/api/preferencias'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ board_view_mode: mode }),
            });
        } catch (e) {}
    };

    KD.toggleColumnsMenu = function (event) {
        if (event) {
            event.stopPropagation();
        }
        const dropdown = el('columnsMenuDropdown');
        const btn = el('btnToggleColumnsMenu');
        if (!dropdown) return;
        const willOpen = dropdown.hidden;
        KD.closeMenus(willOpen ? dropdown : null);
        dropdown.hidden = !willOpen;
        if (btn) btn.setAttribute('aria-expanded', String(willOpen));
        if (willOpen) KD.renderColumnsMenu();
    };

    KD.renderColumnsMenu = function () {
        const list = el('columnsMenuList');
        if (!list) return;

        let columns = [];
        if (board.viewMode === 'weekdays') {
            const days = getWeekDays();
            columns = days.map((d) => ({
                id: d.id,
                name: `${d.name}${d.formattedDate ? ` (${d.formattedDate})` : ''}`,
                color: d.color,
                checked: !board.hiddenColumns.weekdays.has(d.id),
            }));
        } else {
            columns = board.stages.map((s) => ({
                id: String(s.id),
                name: s.name,
                color: s.color,
                checked: !board.hiddenColumns.status.has(String(s.id)),
            }));
        }

        list.innerHTML = columns.map((c) => `
            <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-800/60 cursor-pointer text-xs select-none">
                <input type="checkbox" ${c.checked ? 'checked' : ''} onchange="KD.toggleColumnVisibility('${c.id}', this.checked)" class="rounded">
                <span class="kd-col__dot" style="background-color:${KD.escapeHtml(c.color || '#6366f1')}"></span>
                <span class="truncate text-slate-200">${KD.escapeHtml(c.name)}</span>
            </label>
        `).join('');
    };

    KD.toggleColumnVisibility = function (colId, isVisible) {
        const currentSet = board.hiddenColumns[board.viewMode];
        if (!currentSet) return;
        if (isVisible) {
            currentSet.delete(String(colId));
        } else {
            currentSet.add(String(colId));
        }
        saveViewPrefs();
        updateToolbarState();
        render();
        KD.syncPrefHiddenColumns();
    };

    KD.resetVisibleColumns = function () {
        if (board.hiddenColumns[board.viewMode]) {
            board.hiddenColumns[board.viewMode].clear();
        }
        saveViewPrefs();
        KD.renderColumnsMenu();
        updateToolbarState();
        render();
        KD.syncPrefHiddenColumns();
        KD.toast('Todas as colunas estão visíveis.');
    };

    KD.syncPrefHiddenColumns = async function () {
        try {
            await fetch(KD.url('/api/preferencias'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    visible_columns: {
                        status: Array.from(board.hiddenColumns.status),
                        weekdays: Array.from(board.hiddenColumns.weekdays),
                    },
                }),
            });
        } catch (e) {}
    };

    // Fechar dropdown de colunas ao clicar fora
    document.addEventListener('click', (event) => {
        const wrap = el('columnsMenuWrap');
        const dropdown = el('columnsMenuDropdown');
        if (wrap && dropdown && !dropdown.hidden && !wrap.contains(event.target)) {
            dropdown.hidden = true;
            el('btnToggleColumnsMenu')?.setAttribute('aria-expanded', 'false');
        }
    });

    // ---------------------------------------------------------------
    // Início
    // ---------------------------------------------------------------

    document.addEventListener('DOMContentLoaded', () => {
        if (!el('kanbanBoard')) return;

        loadViewPrefs();
        restoreFiltersFromUrl();
        KD.loadBoard();
        startTicker();
        startAutoRefresh();

        el('filterSearch')?.addEventListener('input', KD.onFilterInput);
        ['filterClient', 'filterPriority', 'filterAssignee', 'filterTag'].forEach((id) => {
            el(id)?.addEventListener('change', KD.onFilterChange);
        });
    });
})(window.KD);
