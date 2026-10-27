/**
 * KanbanDoo - Quadro: estado, filtros, cards, arrastar e soltar e teclado.
 */
window.KD = window.KD || {};

(function (KD) {
    'use strict';

    const dismissedColAlerts = new Set();
    KD.dismissColumnAlert = function(id) {
        dismissedColAlerts.add(id);
    };

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
        showCalendarEvents: (localStorage.getItem('kanbandoo_show_calendar_events') !== '0'),
        mobileStageId: null,
        showArchived: false,
        allTags: [],
        viewMode: 'status', // 'status' ou 'weekdays'
        weekStartMode: localStorage.getItem('kanbandoo_week_start') || 'today', // 'today' (começa em hoje) ou 'monday'
        weekdayDaysCount: parseInt(localStorage.getItem('kanbandoo_weekday_days_count') || '5', 10),
        weekOffset: 0,
        tagVisibility: 'hover', // 'hover', 'always', 'hidden'
        showCardAvatar: true,
        showCardStatusBar: true,
        workHours: { start: '09:00', end: '17:00', capacityMinutes: 480 },
        workDays: ['1', '2', '3', '4', '5'],
        hiddenColumns: {
            status: new Set(),
            weekdays: new Set(),
        },
        pausedTimers: new Set(),
        compactMode: (localStorage.getItem('kanbandoo_compact_mode') === '1'),
    };

    const sortables = [];
    const COLLAPSE_KEY = 'kanbandoo_collapsed_stages';
    const VIEW_KEY = 'kanbandoo_board_view';
    const VIEW_MODE_KEY = 'kanbandoo_board_view_mode';
    const HIDDEN_COLS_KEY = 'kanbandoo_hidden_columns';
    const TAG_VISIBILITY_KEY = 'kanbandoo_tag_visibility';
    const SHOW_CARD_AVATAR_KEY = 'kanbandoo_show_card_avatar';
    const SHOW_CARD_STATUS_BAR_KEY = 'kanbandoo_show_card_status_bar';
    const SHOW_CALENDAR_EVENTS_KEY = 'kanbandoo_show_calendar_events';
    const WEEKDAY_DAYS_COUNT_KEY = 'kanbandoo_weekday_days_count';

    KD.board = board;
    KD.sortTasksForColumn = sortTasksForColumn;

    // ---------------------------------------------------------------
    // Preferências locais de visualização
    // ---------------------------------------------------------------

    function loadViewPrefs() {
        try {
            const collapsed = JSON.parse(localStorage.getItem(COLLAPSE_KEY) || '[]');
            if (Array.isArray(collapsed)) collapsed.forEach((id) => board.collapsed.add(String(id)));

            const view = JSON.parse(localStorage.getItem(VIEW_KEY) || '{}');
            board.groupByClient = Boolean(view.groupByClient);

            const savedCalEvents = localStorage.getItem(SHOW_CALENDAR_EVENTS_KEY);
            if (savedCalEvents !== null) board.showCalendarEvents = (savedCalEvents !== '0');

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

            const savedAvatar = localStorage.getItem(SHOW_CARD_AVATAR_KEY);
            if (savedAvatar !== null) {
                board.showCardAvatar = savedAvatar === 'true';
            }

            const savedStatusBar = localStorage.getItem(SHOW_CARD_STATUS_BAR_KEY);
            if (savedStatusBar !== null) {
                board.showCardStatusBar = savedStatusBar === 'true';
            }

            const savedDaysCount = localStorage.getItem(WEEKDAY_DAYS_COUNT_KEY);
            if (savedDaysCount !== null) {
                const parsed = parseInt(savedDaysCount, 10);
                if (!isNaN(parsed) && parsed >= 1 && parsed <= 14) {
                    board.weekdayDaysCount = parsed;
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
            localStorage.setItem(SHOW_CARD_AVATAR_KEY, String(board.showCardAvatar));
            localStorage.setItem(SHOW_CARD_STATUS_BAR_KEY, String(board.showCardStatusBar));
            localStorage.setItem(WEEKDAY_DAYS_COUNT_KEY, String(board.weekdayDaysCount || 5));
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

    function applyCardVisualPrefs() {
        const container = el('kanbanBoard');
        if (!container) return;
        container.classList.toggle('kd-hide-card-avatar', !board.showCardAvatar);
        container.classList.toggle('kd-hide-card-status-bar', !board.showCardStatusBar);
        updateCardVisualCheckmarks();
    }

    function updateCardVisualCheckmarks() {
        const chkAvatar = el('cardAvatarCheck');
        if (chkAvatar) chkAvatar.hidden = !board.showCardAvatar;

        const chkStatusBar = el('cardStatusBarCheck');
        if (chkStatusBar) chkStatusBar.hidden = !board.showCardStatusBar;
    }

    KD.toggleCardAvatar = function () {
        board.showCardAvatar = !board.showCardAvatar;
        saveViewPrefs();
        applyCardVisualPrefs();
        render();
        KD.toast(board.showCardAvatar ? 'Avatares visíveis nos cards.' : 'Avatares ocultados nos cards.');
    };

    KD.toggleCardStatusBar = function () {
        board.showCardStatusBar = !board.showCardStatusBar;
        saveViewPrefs();
        applyCardVisualPrefs();
        render();
        KD.toast(board.showCardStatusBar ? 'Barra de status visível nos cards.' : 'Barra de status ocultada nos cards.');
    };

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
            q: (el('kdHeaderSearch')?.value || el('filterSearch')?.value || '').trim(),
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
        const qVal = params.get('q') || '';
        if (el('filterSearch')) el('filterSearch').value = qVal;
        const hSearch = el('kdHeaderSearch');
        if (hSearch) {
            hSearch.value = qVal;
            const cBtn = el('kdHeaderSearchClear');
            if (cBtn) cBtn.hidden = !qVal;
        }
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

        const calBtn = el('btnToggleCalendarEvents');
        if (calBtn) {
            calBtn.setAttribute('aria-pressed', String(board.showCalendarEvents));
            calBtn.classList.toggle('kd-btn--active', board.showCalendarEvents);
            calBtn.title = board.showCalendarEvents ? 'Ocultar eventos do calendário no quadro' : 'Exibir eventos do calendário no quadro';
        }
        const calCheck = el('calendarEventsInKanbanCheck');
        if (calCheck) {
            calCheck.hidden = !board.showCalendarEvents;
            calCheck.style.display = board.showCalendarEvents ? 'inline-flex' : 'none';
        }

        const groupBtn = el('btnGroupClient');
        if (groupBtn) {
            groupBtn.setAttribute('aria-pressed', String(board.groupByClient));
            groupBtn.classList.toggle('kd-btn--active', board.groupByClient);
        }

        // Modo Compacto (estilo Trello)
        const compactBtn = el('btnToggleCompact');
        if (compactBtn) {
            compactBtn.setAttribute('aria-pressed', String(board.compactMode));
            compactBtn.classList.toggle('text-indigo-400', Boolean(board.compactMode));
            compactBtn.classList.toggle('bg-indigo-600/15', Boolean(board.compactMode));
            compactBtn.classList.toggle('border', Boolean(board.compactMode));
            compactBtn.classList.toggle('border-indigo-500/20', Boolean(board.compactMode));
            compactBtn.classList.toggle('text-slate-400', !board.compactMode);
            compactBtn.title = board.compactMode
                ? 'Modo Compacto Ativo (clique para modo normal)'
                : 'Modo Compacto (estilo Trello: apenas título e detalhes essenciais)';
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
        updateWeekNav();

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

    KD.filterByMetric = function (type) {
        if (type === 'today') {
            if (board.viewMode !== 'weekdays') {
                KD.setViewMode('weekdays');
            }
            KD.toast('Focado na visão de dias da semana (Hoje).', { tone: 'info' });
        } else if (type === 'overdue') {
            showFilterBar(true);
            const searchInput = el('filterSearch');
            if (searchInput) searchInput.focus();
            KD.toast('Localize tarefas em atraso.', { tone: 'info' });
        } else if (type === 'total') {
            KD.clearFilters();
            KD.toast('Filtros limpos. Exibindo todas as tarefas.', { tone: 'info' });
        }
    };

    KD.toggleOnlyMine = function () {
        board.onlyMine = !board.onlyMine;
        updateToolbarState();
        KD.loadBoard();
    };

    KD.toggleCalendarEventsInKanban = function () {
        board.showCalendarEvents = !board.showCalendarEvents;
        localStorage.setItem(SHOW_CALENDAR_EVENTS_KEY, board.showCalendarEvents ? '1' : '0');
        updateToolbarState();
        render();
        if (typeof lucide !== 'undefined') lucide.createIcons();
        KD.toast(board.showCalendarEvents ? 'Eventos do calendário visíveis no quadro.' : 'Eventos do calendário ocultados do quadro.');
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
            const todayStr = getTodayString();
            board.tasks.forEach((t) => applyOverdueEscalationToTask(t, todayStr));
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

        function isCalendarTask(task) {
        if (!task) return false;
        if (Number(task.is_calendar_event) === 1) return true;
        if (task.source && /^(google|outlook|microsoft|calendar|ical)$/i.test(task.source)) return true;
        if (Array.isArray(task.tags)) {
            return task.tags.some(function (t) {
                var name = (typeof t === 'string') ? t : (t && t.name ? t.name : '');
                return /google calendar|outlook|calendário|calendario|ical/i.test(name);
            });
        }
        if (typeof task.tags === 'string' && /google calendar|outlook|calendário|calendario|ical/i.test(task.tags)) {
            return true;
        }
        return false;
    }

    function tasksOfStage(stageId) {
        const list = board.tasks.filter((task) => {
            if (Number(task.stage_id) !== Number(stageId)) return false;
            if (!board.showCalendarEvents && isCalendarTask(task)) return false;
            return true;
        });
        return sortTasksForColumn(list, false, getTodayString());
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

    const WEEKDAY_DEFS = [
        { dayIndex: 0, id: 'sun', name: 'Domingo', short: 'Dom', color: '#ec4899' },
        { dayIndex: 1, id: 'mon', name: 'Segunda', short: 'Seg', color: '#6366f1' },
        { dayIndex: 2, id: 'tue', name: 'Terça', short: 'Ter', color: '#3b82f6' },
        { dayIndex: 3, id: 'wed', name: 'Quarta', short: 'Qua', color: '#06b6d4' },
        { dayIndex: 4, id: 'thu', name: 'Quinta', short: 'Qui', color: '#10b981' },
        { dayIndex: 5, id: 'fri', name: 'Sexta', short: 'Sex', color: '#f59e0b' },
        { dayIndex: 6, id: 'sat', name: 'Sábado', short: 'Sáb', color: '#8b5cf6' },
    ];

    function getWeekDays() {
        const now = new Date();
        now.setHours(0, 0, 0, 0);

        const todayY = now.getFullYear();
        const todayM = String(now.getMonth() + 1).padStart(2, '0');
        const todayD = String(now.getDate()).padStart(2, '0');
        const todayStr = `${todayY}-${todayM}-${todayD}`;

        const days = [];

        // Início da semana: 'monday' (começa na Segunda-feira) ou 'today' (começa no dia de Hoje)
        const isMondayStart = (board.weekStartMode === 'monday');
        let cursor;
        if (isMondayStart) {
            const dayOfWeek = now.getDay(); // 0 = Domingo, 1 = Segunda, ..., 6 = Sábado
            const diffToMonday = (dayOfWeek === 0 ? -6 : 1 - dayOfWeek);
            cursor = new Date(now.getFullYear(), now.getMonth(), now.getDate() + diffToMonday);
        } else {
            cursor = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        }

        // Deslocamento de semanas (ex: +1 para próxima semana, -1 para semana anterior)
        if (board.weekOffset) {
            cursor.setDate(cursor.getDate() + (board.weekOffset * 7));
        }

        const daysCount = Math.max(1, Math.min(14, Number(board.weekdayDaysCount) || 5));

        // Gera os dias consecutivos a partir do cursor
        for (let i = 0; i < daysCount; i++) {
            const dayIdx = cursor.getDay();
            const base = WEEKDAY_DEFS[dayIdx];
            const y = cursor.getFullYear();
            const m = String(cursor.getMonth() + 1).padStart(2, '0');
            const d = String(cursor.getDate()).padStart(2, '0');
            const dateString = `${y}-${m}-${d}`;

            days.push({
                id: base.id,
                dayIndex: dayIdx,
                name: base.name,
                short: base.short,
                color: base.color,
                dateString: dateString,
                formattedDate: `${d}/${m}`,
                isToday: (dateString === todayStr),
            });

            cursor.setDate(cursor.getDate() + 1);
        }

        // Coluna fixa para tarefas sem prazo
        days.push({
            id: 'nodate',
            dayIndex: -1,
            name: 'Sem prazo',
            short: 'S/P',
            color: '#64748b',
            dateString: '',
            formattedDate: '',
            isToday: false,
        });

        // Coluna para tarefas concluídos no modo semana
        days.push({
            id: 'done',
            dayIndex: 99,
            name: 'Concluídos',
            short: 'Concl',
            color: '#10b981',
            dateString: '',
            formattedDate: '',
            isToday: false,
            isDoneColumn: true,
        });

        return days;
    }

    function getTodayString() {
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const d = String(now.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    /**
     * Escalação automática de prioridade para tarefas atrasadas:
     * - Tarefas de ontem sobem de grau (baixa -> média, média -> alta, alta -> urgente)
     * - Tarefas com 2+ dias de atraso sobem proporcionalmente até urgente
     */
    function applyOverdueEscalationToTask(t, todayStr) {
        if (!t.due_date || t.completed_at || t.due_date >= todayStr) {
            t.is_overdue = false;
            return;
        }

        const PRIORITY_LEVELS = ['low', 'medium', 'high', 'urgent'];
        const currentPrio = t.original_priority || t.priority || 'medium';
        let idx = PRIORITY_LEVELS.indexOf(currentPrio);
        if (idx === -1) idx = 1;

        const pToday = todayStr.split('-').map(Number);
        const pDue = t.due_date.split('-').map(Number);
        const dtToday = new Date(pToday[0], pToday[1] - 1, pToday[2]);
        const dtDue = new Date(pDue[0], pDue[1] - 1, pDue[2]);
        const diffDays = Math.max(1, Math.round((dtToday.getTime() - dtDue.getTime()) / (1000 * 60 * 60 * 24)));

        const newIdx = Math.min(3, idx + diffDays);
        t.original_priority = currentPrio;
        t.priority = PRIORITY_LEVELS[newIdx];
        t.is_overdue = true;
        t.days_overdue = diffDays;
        t.priority_escalated = (newIdx > idx);
    }

            /**
     * Ordenação de tarefas para cada coluna:
     * 1. Concluídas sempre no final da coluna.
     * 2. Tarefa bomba sempre no topo absoluto.
     * 3. Se houver ordenação manual ativa entre as tarefas (sort_order > 0), a ordem manual sobrepõe!
     * 4. Por padrão (sem ordenação manual / sort_order == 0) ou ao reordenar:
     *    - 1º: Tarefa bomba
     *    - 2º: Tarefa atrasada por ordem de mais atrasadas primeiro (due_date ASC)
     *    - 3º: Tarefa de prioridade urgente
     *    - 4º: Tarefa de prioridade alta
     *    - 5º: Tarefa de prioridade média
     *    - 6º: Tarefa de prioridade baixa
     */
        function getClientCategory(task) {
        if (task && task.client_category && ['A+', 'A', 'B', 'C', 'D'].includes(task.client_category)) {
            return task.client_category;
        }
        const company = (task && (task.client_company || task.company_name)) || '';
        const m = company.match(/\((A\+|A|B|C|D)\)/i);
        if (m) {
            return m[1].toUpperCase();
        }
        return 'D';
    }

    const CAT_WEIGHT = {
        'A+': 1,
        'A': 2,
        'B': 3,
        'C': 4,
        'D': 5,
    };

    function isNearDueTime(task, todayStr) {
        if (!task || task.completed_at || Number(task.is_bomb) === 1 || task.priority === 'urgent') {
            return false;
        }
        if (!task.due_time) return false;
        if (task.due_date && task.due_date !== todayStr) return false;

        const parts = String(task.due_time).split(':');
        if (parts.length < 2) return false;
        const taskMinutes = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);

        const now = new Date();
        const nowMinutes = now.getHours() * 60 + now.getMinutes();
        const diff = taskMinutes - nowMinutes;

        // Horário dentro dos próximos 60 minutos (com 15 min de tolerância caso recém passado)
        return diff >= -15 && diff <= 60;
    }

    const PRIORITY_WEIGHT = {
        'urgent': 1,
        'high': 2,
        'medium': 3,
        'low': 4
    };

    /**
     * Ordenação Estrita do KanbanDoo:
     * 1. Concluídas sempre no final da coluna
     * 2. Tarefas Bomba acima de todas as tarefas (&&)
     * 3. Tarefas em Foco acima de tarefas mais antigas (&&)
     * 4. Tarefas mais antigas acima de tarefas mais recentes (tarefas com mais dias de atraso têm prioridade acima das tarefas dos dias) (&&)
     * 5. Tarefas A+ acima de tarefas A (&&)
     * 6. Tarefas A acima de tarefas B (&&)
     * 7. Tarefas B acima de tarefas C (&&)
     * 8. Tarefas C acima de tarefas D
     * Desempates na mesma data e mesma categoria:
     * - Horário próximo nos próximos 60m
     * - Prioridade cadastrada (Urgente > Alta > Média > Baixa)
     * - Horário mais cedo (due_time)
     * - Ordem manual (sort_order)
     * - ID
     */
    function sortTasksForColumn(tasks, isTodayColumn, todayStr) {
        todayStr = todayStr || getTodayString();

        // Identifica até 3 tarefas em foco prioritárias na coluna por categoria de empresa (A+, A, B, C, D) e antiguidade
        const focusCandidates = tasks.filter((t) => !t.completed_at && Number(t.is_bomb) !== 1 && Number(t.in_focus) === 1);
        focusCandidates.sort((a, b) => {
            const wA = CAT_WEIGHT[getClientCategory(a)] || 5;
            const wB = CAT_WEIGHT[getClientCategory(b)] || 5;
            if (wA !== wB) return wA - wB;
            const dateA = a.due_date || '9999-99-99';
            const dateB = b.due_date || '9999-99-99';
            if (dateA !== dateB) return dateA.localeCompare(dateB);
            if (a.due_time && b.due_time && a.due_time !== b.due_time) {
                return a.due_time.localeCompare(b.due_time);
            }
            return (Number(a.id) || 0) - (Number(b.id) || 0);
        });
        const topFocusIds = new Set(focusCandidates.slice(0, 3).map((t) => Number(t.id)));

        return [...tasks].sort((a, b) => {
            // 1. Concluídas sempre no final absoluto da coluna
            const aDone = Boolean(a.completed_at);
            const bDone = Boolean(b.completed_at);
            if (aDone !== bDone) return aDone ? 1 : -1;

            // 2. Tarefas Bomba acima de todas as tarefas
            const aBomb = Number(a.is_bomb) === 1;
            const bBomb = Number(b.is_bomb) === 1;
            if (aBomb !== bBomb) return aBomb ? -1 : 1;
            if (aBomb && bBomb) {
                // Entre bombas: mais antiga primeiro, depois categoria A+ a D
                const dateA = a.due_date || '9999-99-99';
                const dateB = b.due_date || '9999-99-99';
                if (dateA !== dateB) return dateA.localeCompare(dateB);
                const catA = CAT_WEIGHT[getClientCategory(a)] || 5;
                const catB = CAT_WEIGHT[getClientCategory(b)] || 5;
                if (catA !== catB) return catA - catB;
                return (Number(a.id) || 0) - (Number(b.id) || 0);
            }

            // 3. Tarefas em Foco acima de tarefas mais antigas
            const aFocus = topFocusIds.has(Number(a.id));
            const bFocus = topFocusIds.has(Number(b.id));
            if (aFocus !== bFocus) return aFocus ? -1 : 1;
            if (aFocus && bFocus) {
                // Entre tarefas em foco: categoria A+ a D, depois mais antiga
                const catA = CAT_WEIGHT[getClientCategory(a)] || 5;
                const catB = CAT_WEIGHT[getClientCategory(b)] || 5;
                if (catA !== catB) return catA - catB;
                const dateA = a.due_date || '9999-99-99';
                const dateB = b.due_date || '9999-99-99';
                if (dateA !== dateB) return dateA.localeCompare(dateB);
                return (Number(a.id) || 0) - (Number(b.id) || 0);
            }

            // 4. Tarefas mais antigas acima de tarefas mais recentes
            // (Tarefas com mais dias de atraso têm prioridade acima das tarefas dos dias)
            // due_date mais antiga no passado fica no topo. Tarefas sem data ficam por último.
            const dateA = a.due_date || '9999-99-99';
            const dateB = b.due_date || '9999-99-99';
            if (dateA !== dateB) {
                return dateA.localeCompare(dateB);
            }

            // 5. Para tarefas da mesma data / mesmo atraso:
            // Tarefas A+ acima de tarefas A &&
            // Tarefas A acima de tarefas B &&
            // Tarefas B acima de tarefas C &&
            // Tarefas C acima de tarefas D
            const catA = CAT_WEIGHT[getClientCategory(a)] || 5;
            const catB = CAT_WEIGHT[getClientCategory(b)] || 5;
            if (catA !== catB) {
                return catA - catB;
            }

            // 6. Desempates dentro da mesma data e mesma categoria:
            // 6.1 Horário próximo nos próximos 60 minutos
            const aNear = isNearDueTime(a, todayStr);
            const bNear = isNearDueTime(b, todayStr);
            if (aNear !== bNear) return aNear ? -1 : 1;

            // 6.2 Prioridade cadastrada (Urgente > Alta > Média > Baixa)
            const pA = PRIORITY_WEIGHT[a.priority] || 3;
            const pB = PRIORITY_WEIGHT[b.priority] || 3;
            if (pA !== pB) return pA - pB;

            // 6.3 Horário de entrega mais cedo (due_time)
            if (a.due_time && b.due_time && a.due_time !== b.due_time) {
                return a.due_time.localeCompare(b.due_time);
            }
            if (a.due_time && !b.due_time) return -1;
            if (!a.due_time && b.due_time) return 1;

            // 6.4 Ordem manual do usuário se existir dentro da mesma faixa
            const aOrder = Number(a.sort_order || 0);
            const bOrder = Number(b.sort_order || 0);
            if (aOrder > 0 && bOrder > 0 && aOrder !== bOrder) {
                return aOrder - bOrder;
            }

            return (Number(a.id) || 0) - (Number(b.id) || 0);
        });
    }

    function isDoneTask(t) {
        if (!t) return false;
        if (Boolean(t.completed_at)) return true;
        const stg = board.stages.find((s) => Number(s.id) === Number(t.stage_id));
        return Boolean(stg && stg.is_done);
    }

    function tasksOfWeekday(day) {
        const todayStr = getTodayString();

        // 1. Coluna de Tarefas Concluídas no modo semana
        if (day.id === 'done') {
            const list = board.tasks.filter((t) => {
                if (!board.showCalendarEvents && isCalendarTask(t)) return false;
                return isDoneTask(t);
            });
            return list.sort((a, b) => {
                const ta = a.completed_at ? new Date(a.completed_at).getTime() : 0;
                const tb = b.completed_at ? new Date(b.completed_at).getTime() : 0;
                return tb - ta;
            });
        }

        // 2. Coluna Sem Prazo (apenas tarefas abertas/pendentes)
        if (day.id === 'nodate') {
            const list = board.tasks.filter((t) => {
                if (isDoneTask(t)) return false;
                if (t.due_date) return false;
                if (!board.showCalendarEvents && isCalendarTask(t)) return false;
                return true;
            });
            return sortTasksForColumn(list, false, todayStr);
        }

        let tasks = [];
        if (day.isToday) {
            // Coluna do dia de HOJE (apenas tarefas abertas/pendentes):
            // 1. Tarefas com data de hoje (due_date === day.dateString)
            // 2. TODAS as tarefas atrasadas pendentes (due_date < day.dateString)
            tasks = board.tasks.filter((t) => {
                if (isDoneTask(t)) return false;
                if (!t.due_date) return false;
                if (!board.showCalendarEvents && isCalendarTask(t)) return false;
                if (t.due_date === day.dateString) return true;
                if (t.due_date < day.dateString) return true;
                return false;
            });
        } else {
            // Colunas dos outros dias da semana (apenas tarefas abertas/pendentes agendadas para aquele dia)
            tasks = board.tasks.filter((t) => {
                if (isDoneTask(t)) return false;
                if (!t.due_date) return false;
                if (!board.showCalendarEvents && isCalendarTask(t)) return false;
                return t.due_date === day.dateString;
            });
        }

        return sortTasksForColumn(tasks, Boolean(day.isToday), todayStr);
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

        const uncollapsedCols = container.querySelectorAll('.kd-col:not(.kd-col--collapsed)');
        container.classList.toggle('kd-board--many-cols', uncollapsedCols.length > 5);

        renderStageTabs();
        applyMobileStage();
        updateToolbarState();
        applyTagVisibility();
        applyCardVisualPrefs();
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

        if (isCollapsed) {
            return `
                <section class="kd-col kd-glass kd-col--collapsed"
                         data-stage-id="${stage.id}"
                         aria-label="Coluna ${KD.escapeHtml(stage.name)} recolhida, ${tasks.length} tarefa(s)">
                    <div class="kd-col-collapsed__inner" data-action="toggle-collapse" data-stage-id="${stage.id}" title="Clique para expandir a coluna ${KD.escapeHtml(stage.name)}">
                        <!-- 1. Nome da coluna na vertical -->
                        <div class="kd-col-collapsed__title-wrap">
                            <span class="kd-col-collapsed__pill" style="--pill-color:${KD.escapeHtml(stage.color || '#6366f1')};">
                                <span class="kd-col-collapsed__title-text">${KD.escapeHtml(stage.name)}</span>
                            </span>
                        </div>

                        <!-- 2. Quantidade de cards embaixo do título -->
                        <div class="kd-col-collapsed__count-wrap">
                            <span class="kd-col-collapsed__count ${overLimit ? 'kd-col__count--over' : ''}" title="${tasks.length} tarefa(s)">
                                ${countLabel}
                            </span>
                        </div>

                        <!-- 3. Botão para restaurar o tamanho da coluna embaixo do número -->
                        <button type="button" class="kd-icon-btn kd-col-collapsed__restore-btn"
                                data-action="toggle-collapse" data-stage-id="${stage.id}"
                                title="Restaurar tamanho da coluna ${KD.escapeHtml(stage.name)}"
                                aria-label="Restaurar tamanho da coluna ${KD.escapeHtml(stage.name)}">
                            <i data-lucide="chevrons-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </section>
            `;
        }

        return `
            <section class="kd-col kd-glass"
                     data-stage-id="${stage.id}"
                     aria-label="Coluna ${KD.escapeHtml(stage.name)}, ${tasks.length} tarefa(s)">

                <header class="kd-col__header">
                    <div class="flex items-center justify-between gap-2 min-w-0 w-full">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <button type="button" class="kd-col__pill" style="--pill-color:${KD.escapeHtml(stage.color || '#6366f1')};"
                                    data-action="pick-stage-color" data-stage-id="${stage.id}"
                                    title="Clique para alterar a cor da coluna ${KD.escapeHtml(stage.name)}">
                                <span class="kd-col__pill-icon flex-shrink-0">
                                    ${stage.is_done
                                        ? '<i data-lucide="check-circle-2" class="w-3.5 h-3.5 stroke-[2.5]"></i>'
                                        : '<span class="kd-col__pill-ring"></span>'
                                    }
                                </span>
                                <span class="kd-col__pill-text">${KD.escapeHtml(stage.name)}</span>
                            </button>
                            <span class="kd-col__count flex-shrink-0 ${overLimit ? 'kd-col__count--over' : ''}">${countLabel}</span>
                        </div>

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

                ${overLimit && !dismissedColAlerts.has('stage_wip_' + stage.id) ? `
                    <p class="kd-col__wip flex items-center justify-between gap-1.5">
                        <span class="flex items-center gap-1.5 flex-1 min-w-0">
                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 flex-shrink-0"></i>
                            <span class="truncate">Acima do limite de ${stage.wip_limit} tarefa(s)</span>
                        </span>
                        <button type="button" class="p-0.5 rounded hover:bg-white/10 opacity-70 hover:opacity-100 transition flex-shrink-0" onclick="this.closest('.kd-col__wip').remove(); KD.dismissColumnAlert('stage_wip_${stage.id}')" title="Fechar aviso nesta sessão">
                            <i data-lucide="x" class="w-3 h-3"></i>
                        </button>
                    </p>
                ` : ''}

                ${!dismissedColAlerts.has('stage_' + stage.id) && timeInfo && timeInfo.tone === 'danger' ? `
                    <p class="kd-col__wip flex items-center justify-between gap-1.5" style="color:var(--danger)">
                        <span class="flex items-center gap-1.5 flex-1 min-w-0">
                            <i data-lucide="alert-circle" class="w-3.5 h-3.5 flex-shrink-0"></i>
                            <span class="truncate">Tarefas extrapolaram o limite diário de ${timeInfo.formattedCapacity}</span>
                        </span>
                        <button type="button" class="p-0.5 rounded hover:bg-white/10 opacity-70 hover:opacity-100 transition flex-shrink-0" onclick="this.closest('.kd-col__wip').remove(); KD.dismissColumnAlert('stage_${stage.id}')" title="Fechar aviso nesta sessão">
                            <i data-lucide="x" class="w-3 h-3"></i>
                        </button>
                    </p>
                ` : (!dismissedColAlerts.has('stage_' + stage.id) && timeInfo && timeInfo.tone === 'warning' ? `
                    <p class="kd-col__wip flex items-center justify-between gap-1.5" style="color:var(--warning)">
                        <span class="flex items-center gap-1.5 flex-1 min-w-0">
                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 flex-shrink-0"></i>
                            <span class="truncate">Próximo de extrapolar capacidade (${Math.round(timeInfo.ratio * 100)}%)</span>
                        </span>
                        <button type="button" class="p-0.5 rounded hover:bg-white/10 opacity-70 hover:opacity-100 transition flex-shrink-0" onclick="this.closest('.kd-col__wip').remove(); KD.dismissColumnAlert('stage_${stage.id}')" title="Fechar aviso nesta sessão">
                            <i data-lucide="x" class="w-3 h-3"></i>
                        </button>
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

        if (isCollapsed) {
            return `
                <section class="kd-col kd-glass kd-col--collapsed"
                         data-weekday-id="${day.id}"
                         aria-label="Coluna ${KD.escapeHtml(day.name)} recolhida, ${tasks.length} tarefa(s)">
                    <div class="kd-col-collapsed__inner" data-action="toggle-collapse-weekday" data-weekday-id="${day.id}" title="Clique para expandir a coluna ${KD.escapeHtml(day.name)}">
                        <!-- 1. Nome da coluna na vertical -->
                        <div class="kd-col-collapsed__title-wrap">
                            <span class="kd-col-collapsed__pill" style="--pill-color:${KD.escapeHtml(day.color || '#6366f1')};">
                                <span class="kd-col-collapsed__title-text">${KD.escapeHtml(day.name)}</span>
                            </span>
                        </div>

                        <!-- 2. Quantidade de cards embaixo do título -->
                        <div class="kd-col-collapsed__count-wrap">
                            <span class="kd-col-collapsed__count" title="${tasks.length} tarefa(s)">
                                ${tasks.length}
                            </span>
                        </div>

                        <!-- 3. Botão para restaurar o tamanho da coluna embaixo do número -->
                        <button type="button" class="kd-icon-btn kd-col-collapsed__restore-btn"
                                data-action="toggle-collapse-weekday" data-weekday-id="${day.id}"
                                title="Restaurar tamanho da coluna ${KD.escapeHtml(day.name)}"
                                aria-label="Restaurar tamanho da coluna ${KD.escapeHtml(day.name)}">
                            <i data-lucide="chevrons-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </section>
            `;
        }

        return `
            <section class="kd-col kd-glass"
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
                            <div class="kd-menu-wrap kd-col__menu-wrap">
                                <button type="button" class="kd-icon-btn" data-action="toggle-column-menu"
                                        aria-haspopup="menu" aria-expanded="false" aria-label="Opções do dia ${KD.escapeHtml(day.name)}">
                                    <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                                </button>
                                <div class="kd-menu kd-glass kd-glass--raised" role="menu" hidden>
                                    ${day.id !== 'done' ? `
                                        <button type="button" class="kd-menu__item" role="menuitem" data-action="new-task-weekday" data-due-date="${day.dateString || ''}">
                                            <i data-lucide="plus" class="w-4 h-4"></i> Adicionar tarefa
                                        </button>
                                    ` : ''}
                                    <button type="button" class="kd-menu__item text-indigo-400 font-medium" role="menuitem" data-action="reset-weekday-order" data-weekday-id="${day.id}" data-due-date="${day.dateString || ''}">
                                        <i data-lucide="arrow-down-up" class="w-4 h-4"></i> Reordenar por prioridade
                                    </button>
                                    <button type="button" class="kd-menu__item" role="menuitem" data-action="toggle-collapse-weekday" data-weekday-id="${day.id}">
                                        <i data-lucide="columns" class="w-4 h-4"></i>
                                        ${board.collapsed.has(String(day.id)) ? 'Expandir coluna' : 'Recolher coluna'}
                                    </button>
                                    <div class="kd-menu__sep"></div>
                                    <button type="button" class="kd-menu__item" role="menuitem" onclick="KD.toggleCardAvatar()">
                                        <i data-lucide="user" class="w-4 h-4"></i>
                                        ${board.showCardAvatar ? 'Ocultar avatares' : 'Exibir avatares'}
                                    </button>
                                    <button type="button" class="kd-menu__item" role="menuitem" onclick="KD.toggleCardStatusBar()">
                                        <i data-lucide="minus" class="w-4 h-4"></i>
                                        ${board.showCardStatusBar ? 'Ocultar barra de status' : 'Exibir barra de status'}
                                    </button>
                                </div>
                            </div>
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

                ${!dismissedColAlerts.has('day_' + day.id) && timeInfo.tone === 'danger' ? `
                    <p class="kd-col__wip flex items-center justify-between gap-1.5" style="color:var(--danger)">
                        <span class="flex items-center gap-1.5 flex-1 min-w-0">
                            <i data-lucide="alert-circle" class="w-3.5 h-3.5 flex-shrink-0"></i>
                            <span class="truncate">Tarefas extrapolaram o limite diário de ${timeInfo.formattedCapacity}</span>
                        </span>
                        <button type="button" class="p-0.5 rounded hover:bg-white/10 opacity-70 hover:opacity-100 transition flex-shrink-0" onclick="this.closest('.kd-col__wip').remove(); KD.dismissColumnAlert('day_${day.id}')" title="Fechar aviso nesta sessão">
                            <i data-lucide="x" class="w-3 h-3"></i>
                        </button>
                    </p>
                ` : (!dismissedColAlerts.has('day_' + day.id) && timeInfo.tone === 'warning' ? `
                    <p class="kd-col__wip flex items-center justify-between gap-1.5" style="color:var(--warning)">
                        <span class="flex items-center gap-1.5 flex-1 min-w-0">
                            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 flex-shrink-0"></i>
                            <span class="truncate">Próximo de extrapolar capacidade (${Math.round(timeInfo.ratio * 100)}%)</span>
                        </span>
                        <button type="button" class="p-0.5 rounded hover:bg-white/10 opacity-70 hover:opacity-100 transition flex-shrink-0" onclick="this.closest('.kd-col__wip').remove(); KD.dismissColumnAlert('day_${day.id}')" title="Fechar aviso nesta sessão">
                            <i data-lucide="x" class="w-3 h-3"></i>
                        </button>
                    </p>
                ` : '')}

                <div class="kd-col__body" data-weekday-id="${day.id}" data-due-date="${day.dateString || ''}" role="list">
                    ${tasks.length === 0 ? emptyStateWeekdayHtml(day) : cardsHtml(tasks, { id: 0, name: day.name })}
                </div>

                ${day.id !== 'done' ? `
                    <button type="button" class="kd-col__add" data-action="new-task-weekday" data-due-date="${day.dateString || ''}">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Adicionar tarefa
                    </button>
                ` : ''}
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
                <button type="button" class="kd-menu__item text-indigo-400 font-medium" role="menuitem" data-action="reset-stage-order" data-stage-id="${stage.id}">
                    <i data-lucide="arrow-down-up" class="w-4 h-4"></i> Reordenar por prioridade
                </button>
                <button type="button" class="kd-menu__item" role="menuitem" data-action="pick-stage-color" data-stage-id="${stage.id}">
                    <i data-lucide="palette" class="w-4 h-4"></i> Alterar cor
                </button>
                <button type="button" class="kd-menu__item" role="menuitem" data-action="set-wip" data-stage-id="${stage.id}">
                    <i data-lucide="gauge" class="w-4 h-4"></i>
                    ${stage.wip_limit ? `Limite de WIP: ${stage.wip_limit}` : 'Definir limite de WIP'}
                </button>
                <button type="button" class="kd-menu__item" role="menuitem" data-action="toggle-collapse" data-stage-id="${stage.id}">
                    <i data-lucide="columns" class="w-4 h-4"></i>
                    ${board.collapsed.has(String(stage.id)) ? 'Expandir coluna' : 'Recolher coluna'}
                </button>
                ${stage.is_done && count > 0 ? `
                    <div class="kd-menu__sep"></div>
                    <button type="button" class="kd-menu__item" role="menuitem" data-action="archive-done">
                        <i data-lucide="archive" class="w-4 h-4"></i> Arquivar concluídas
                    </button>
                ` : ''}
                <div class="kd-menu__sep"></div>
                <button type="button" class="kd-menu__item" role="menuitem" onclick="KD.toggleCardAvatar()">
                    <i data-lucide="user" class="w-4 h-4"></i>
                    ${board.showCardAvatar ? 'Ocultar avatares' : 'Exibir avatares'}
                </button>
                <button type="button" class="kd-menu__item" role="menuitem" onclick="KD.toggleCardStatusBar()">
                    <i data-lucide="minus" class="w-4 h-4"></i>
                    ${board.showCardStatusBar ? 'Ocultar barra de status' : 'Exibir barra de status'}
                </button>
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
        const isRecurring = Number(task.is_recurring) === 1;
        const recLabels = {
            daily: 'Diária',
            weekly: 'Semanal',
            biweekly: 'Quinzenal',
            monthly: 'Mensal',
            yearly: 'Anual',
            custom: 'Personalizada'
        };
        const recLabel = recLabels[task.recurrence_type] || 'Recorrente';

        const checklistTotal = Number(task.checklist_total || 0);
        const checklistDone = Number(task.checklist_done || 0);
        const percent = checklistTotal > 0 ? Math.round((checklistDone / checklistTotal) * 100) : 0;
        const comments = Number(task.comments_count || 0);
        const due = task.due_badge || {};
        const prioritySlug = task.priority || 'medium';

        const tags = task.tags || [];
        const assignees = task.assignees || [];
        const primaryUser = assignees[0] || null;

        // Determinação unificada da cor de status (border-bottom, botão de play/timer e texto tracking-wide)
        let statusColor = '#eab308';
        if (isDone) {
            statusColor = '#10b981';
        } else if (due.tone === 'overdue') {
            statusColor = '#ef4444';
        } else if (due.tone === 'today') {
            statusColor = '#eab308';
        } else if (due.tone === 'soon') {
            statusColor = '#38bdf8';
        } else if (prioritySlug === 'urgent') {
            statusColor = '#ef4444';
        } else if (prioritySlug === 'high') {
            statusColor = '#f97316';
        } else if (prioritySlug === 'medium') {
            statusColor = '#eab308';
        } else if (prioritySlug === 'low') {
            statusColor = '#38bdf8';
        } else if (stage && stage.color) {
            statusColor = stage.color;
        }

        // 1. Avatar do responsável em primeiro (topo à esquerda) - 1.5rem
        let avatarHtml = '';
        if (primaryUser && primaryUser.avatar) {
            avatarHtml = `<img src="${primaryUser.avatar}" alt="${KD.escapeHtml(primaryUser.name || '')}" class="rounded-full object-cover border border-slate-600/80 shadow-sm" style="width: 1.5rem; height: 1.5rem;" title="${KD.escapeHtml(primaryUser.name || '')}">`;
        } else if (primaryUser) {
            const initial = (primaryUser.initial || primaryUser.name?.charAt(0) || 'U').toUpperCase();
            avatarHtml = `<div class="rounded-full bg-slate-700/80 border border-slate-600/80 text-white font-bold text-[10px] flex items-center justify-center shadow-sm" style="width: 1.5rem; height: 1.5rem;" title="${KD.escapeHtml(primaryUser.name || '')}">${KD.escapeHtml(initial)}</div>`;
        } else {
            avatarHtml = `<div class="rounded-full bg-slate-800 border border-slate-700 text-slate-500 flex items-center justify-center" style="width: 1.5rem; height: 1.5rem;" title="Sem responsável"><i data-lucide="user" class="w-3 h-3"></i></div>`;
        }

        // 2. Etapas / Subtarefas (ex: 01 de 02)
        let stepsHtml = '';
        if (checklistTotal > 0) {
            const donePad = String(checklistDone).padStart(2, '0');
            const totalPad = String(checklistTotal).padStart(2, '0');
            stepsHtml = `
                <div class="kd-card__steps" title="${donePad} de ${totalPad} etapas concluídas">
                    <i data-lucide="check-square" class="w-3.5 h-3.5 text-slate-400"></i>
                    <span>${donePad} de ${totalPad}</span>
                </div>
            `;
        }

        // 3. Prazo no rodapé à esquerda (texto do tracking-wide na mesma cor do status)
        let dueHtml = '';
        const timeBadge = task.due_time ? `<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 shadow-xs"><i data-lucide="clock" class="w-3 h-3"></i>${KD.escapeHtml(task.due_time.substring(0, 5))}</span>` : '';
        if (due.text) {
            dueHtml = `<span class="text-xs font-bold tracking-wide leading-tight flex items-center gap-1.5" style="color: var(--card-status-color);">${KD.escapeHtml(due.text)} ${timeBadge}</span>`;
        } else {
            dueHtml = `<span class="text-xs font-mono font-bold tracking-wide leading-tight flex items-center gap-1.5" style="color: var(--card-status-color);">#${task.id} ${timeBadge}</span>`;
        }

        // 4. Botão Play / Timer Expandido (toggle suave)
        const isPaused = !isRunning && board.pausedTimers && board.pausedTimers.has(Number(task.id));
        const isTimerActive = isRunning || isPaused;

        const timerControlHtml = `
            <div class="kd-timer-toggle ${isTimerActive ? 'is-active' : ''}">
                <button type="button" class="kd-play-btn" data-action="timer-play" data-task-id="${task.id}" title="Iniciar tarefa" tabindex="${isTimerActive ? '-1' : '0'}">
                    <i data-lucide="play" class="w-3 h-3 fill-current"></i>
                </button>
                <div class="kd-timer-pill ${isPaused ? 'is-paused' : ''}" id="kdTimerWrap-${task.id}">
                    <button type="button" class="kd-timer-pill__stop" data-action="timer-stop" data-task-id="${task.id}" title="Parar tarefa e voltar ao play" tabindex="${isTimerActive ? '0' : '-1'}">
                        <i data-lucide="square" class="w-2.5 h-2.5 fill-current"></i>
                    </button>
                    <span class="kd-timer-pill__time ${isPaused ? 'kd-timer-pill__time--paused' : ''}" id="kdTimer-${task.id}">${KD.formatSeconds(task.effective_seconds)}</span>
                    <button type="button" class="kd-timer-pill__pause" data-action="${isRunning ? 'timer-pause' : 'timer-play'}" data-task-id="${task.id}" title="${isRunning ? 'Pausar tarefa' : 'Retomar tarefa'}" tabindex="${isTimerActive ? '0' : '-1'}">
                        <i data-lucide="${isRunning ? 'pause' : 'play'}" class="w-2.5 h-2.5 ${isRunning ? '' : 'fill-current'}"></i>
                    </button>
                </div>
            </div>
        `;

        if (board.compactMode) {
            return `
            <article class="kd-card kd-card--compact kd-glass"
                     role="listitem"
                     tabindex="0"
                     data-task-id="${task.id}"
                     data-stage-id="${task.stage_id}"
                     data-priority="${KD.escapeHtml(prioritySlug)}"
                     data-running="${isRunning ? 1 : 0}"
                     data-paused="${isPaused ? 1 : 0}"
                     data-focus="${inFocus ? 1 : 0}"
                     data-bomb="${isBomb ? 1 : 0}"
                     data-done="${isDone ? 1 : 0}"
                     data-seconds="${Number(task.effective_seconds || 0)}"
                     data-recurring="${isRecurring ? 1 : 0}"
                     style="--card-status-color: ${statusColor};"
                     aria-label="${KD.escapeHtml(task.title)}">

                <!-- Barra superior de cor sutil (se houver status/cliente) -->
                <div class="kd-card__compact-bar" style="background-color: var(--card-status-color);"></div>

                <!-- Título da tarefa (estilo Trello) -->
                <div class="kd-card__title kd-card__title--compact" data-action="open" data-task-id="${task.id}" role="button" tabindex="0">
                    ${Number(task.is_private) === 1 ? `<i data-lucide="lock" class="w-3 h-3 text-amber-400 inline-block mr-1"></i>` : ""}${KD.escapeHtml(task.title)}
                </div>

                <!-- Linha inferior: Ícones discretos (descrição, checklist, recorrência, bomba) e Avatares -->
                <div class="kd-card__compact-footer flex items-center justify-between gap-1.5 border-t border-slate-800/40 text-slate-400 text-[11px]">
                    <div class="flex items-center gap-2 flex-wrap min-w-0">
                        ${task.description && task.description.trim() ? `
                            <span title="Possui descrição" class="text-slate-400 hover:text-slate-200 transition">
                                <i data-lucide="align-left" class="w-3.5 h-3.5"></i>
                            </span>
                        ` : ''}

                        ${checklistTotal > 0 ? `
                            <span class="flex items-center gap-1 font-medium ${checklistDone === checklistTotal ? 'text-emerald-400' : 'text-slate-400'}" title="${checklistDone} de ${checklistTotal} subtarefas concluídas">
                                <i data-lucide="check-square" class="w-3.5 h-3.5"></i>
                                <span>${checklistDone}/${checklistTotal}</span>
                            </span>
                        ` : ''}

                        ${isRecurring ? `
                            <span class="text-emerald-400" title="Recorrente (${recLabel})">
                                <i data-lucide="repeat" class="w-3.5 h-3.5"></i>
                            </span>
                        ` : ''}

                        ${isBomb ? `
                            <span class="text-rose-500" title="Bomba (Urgente)">
                                <i data-lucide="bomb" class="w-3.5 h-3.5"></i>
                            </span>
                        ` : ''}

                        ${due.text ? `
                            <span class="text-[10px] font-semibold tracking-tight" style="color: var(--card-status-color);" title="Prazo: ${KD.escapeHtml(due.text)}">
                                ${KD.escapeHtml(due.text)}
                            </span>
                        ` : ''}
                    </div>

                    <!-- Avatares compactos à direita -->
                    <div class="flex items-center -space-x-1.5 flex-shrink-0">
                        ${assignees.length > 0 ? assignees.slice(0, 2).map((u) => {
                            if (u.avatar) {
                                return `<img src="${u.avatar}" alt="${KD.escapeHtml(u.name || '')}" class="w-5 h-5 rounded-full object-cover border border-slate-700 shadow-xs" title="${KD.escapeHtml(u.name || '')}">`;
                            }
                            const initial = (u.initial || u.name?.charAt(0) || 'U').toUpperCase();
                            return `<div class="w-5 h-5 rounded-full bg-slate-700 border border-slate-600 text-white font-bold text-[9px] flex items-center justify-center shadow-xs" title="${KD.escapeHtml(u.name || '')}">${KD.escapeHtml(initial)}</div>`;
                        }).join('') : `
                            <div class="w-5 h-5 rounded-full bg-slate-800/80 border border-slate-700/80 text-slate-500 flex items-center justify-center" title="Sem responsável">
                                <i data-lucide="user" class="w-2.5 h-2.5"></i>
                            </div>
                        `}
                    </div>
                </div>
            </article>
            `;
        }

        return `
            <article class="kd-card kd-glass"
                     role="listitem"
                     tabindex="0"
                     data-task-id="${task.id}"
                     data-stage-id="${task.stage_id}"
                     data-priority="${KD.escapeHtml(prioritySlug)}"
                     data-running="${isRunning ? 1 : 0}"
                     data-paused="${isPaused ? 1 : 0}"
                     data-focus="${inFocus ? 1 : 0}"
                     data-bomb="${isBomb ? 1 : 0}"
                     data-done="${isDone ? 1 : 0}"
                     data-seconds="${Number(task.effective_seconds || 0)}"
                     data-recurring="${isRecurring ? 1 : 0}"
                     style="--card-status-color: ${statusColor};"
                     aria-label="${KD.escapeHtml(task.title)}">

                <!-- Topo: Avatar do responsável (com hover que revela botão de Concluir) e menu ⋮ à direita -->
                <div class="kd-card__top flex items-center justify-between gap-3 w-full">
                    <div class="kd-card__avatar-wrap flex items-center gap-1.5 min-w-0">
                        <button type="button"
                                class="kd-card__check-trigger"
                                data-action="${isDone ? 'reopen' : 'complete'}"
                                data-task-id="${task.id}"
                                title="${isDone ? 'Concluída (clique para reabrir)' : 'Concluir tarefa'}"
                                aria-label="${isDone ? 'Concluída (clique para reabrir)' : 'Concluir tarefa'}">
                            <span class="kd-card__thumb-view">
                                ${avatarHtml}
                            </span>
                            <span class="kd-card__check-view">
                                <i data-lucide="${isDone ? 'check-circle-2' : 'check'}" class="w-3.5 h-3.5"></i>
                            </span>
                        </button>
                        ${isRecurring ? `
                            <span class="text-emerald-400 flex items-center justify-center opacity-85 hover:opacity-100 transition" title="Tarefa Recorrente (${recLabel})" style="line-height:0">
                                <i data-lucide="repeat" class="w-3 h-3"></i>
                            </span>
                        ` : ''}
                        ${isBomb ? `
                            <span class="text-rose-500 flex items-center justify-center opacity-90 hover:opacity-100 transition" title="Tarefa Bomba (Urgência Crítica)" style="line-height:0">
                                <i data-lucide="bomb" class="w-3.5 h-3.5"></i>
                            </span>
                        ` : ''}
                        ${Number(task.is_private) === 1 ? `
                            <span class="text-amber-400 flex items-center justify-center opacity-90 hover:opacity-100 transition" title="Tarefa Particular (não visível em links públicos)" style="line-height:0">
                                <i data-lucide="lock" class="w-3 h-3"></i>
                            </span>
                        ` : ''}
                    </div>

                    <div class="flex items-center gap-1 ml-auto">
                        <div class="kd-menu-wrap relative flex items-center">
                            <button type="button" class="w-7 h-7 rounded-lg text-slate-400 hover:text-white flex items-center justify-center transition"
                                    data-action="toggle-card-menu" data-task-id="${task.id}"
                                    aria-haspopup="menu" aria-expanded="false"
                                    aria-label="Ações da tarefa ${KD.escapeHtml(task.title)}">
                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                            </button>
                            ${cardMenuHtml(task, inFocus, isBomb, isDone)}
                        </div>
                    </div>
                </div>

                <!-- Subtítulo: Nome do cliente (12px, tom cinza) -->
                <div class="kd-card__client truncate" title="${KD.escapeHtml(task.client_company || 'Sem cliente')}">
                    ${KD.escapeHtml(task.client_company || 'Sem cliente')}
                </div>

                <!-- Título da tarefa (18px, branco) -->
                <div class="kd-card__title" data-action="open" data-task-id="${task.id}" role="button" tabindex="0">
                    ${KD.escapeHtml(task.title)}
                </div>

                <!-- Etapas / Subtarefas -->
                ${stepsHtml}

                <!-- Etiquetas (Tags nos cards - com hover) -->
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

                ${(inFocus || isBomb || isMention || (task.is_overdue && !isDone)) ? `
                    <div class="flex items-center gap-1.5 flex-wrap">
                        ${isCalendarTask(task) ? '<span class="kd-flag kd-flag--calendar" style="background:rgba(99,102,241,0.18);color:#a5b4fc;border:1px solid rgba(99,102,241,0.35);font-weight:600" title="Evento sincronizado do calendário"><i data-lucide="calendar" class="w-3 h-3"></i>Calendário</span>' : ''}
                        
                        
                        ${(task.is_overdue && !isDone) ? `<span class="kd-flag kd-flag--overdue" style="background:rgba(239,68,68,0.22);color:#ef4444;border:1px solid rgba(239,68,68,0.45);font-weight:700" title="Atrasada em ${task.days_overdue || 1} dia(s). Prioridade escalada para ${KD.escapeHtml(task.priority || 'urgente')}."><i data-lucide="alert-triangle" class="w-3 h-3"></i>Atrasada${task.priority_escalated ? ' · ' + (task.priority === 'urgent' ? 'Urgente' : 'Alta') : ''}</span>` : ''}
                        ${inFocus ? '<span class="kd-flag kd-flag--focus"><i data-lucide="target" class="w-3 h-3"></i>Foco</span>' : ''}
                        ${isMention ? `<span class="kd-flag kd-flag--mention"><i data-lucide="message-circle" class="w-3 h-3"></i>${KD.escapeHtml(task.mentioned_by ? '@' + task.mentioned_by : 'WhatsApp')}</span>` : ''}
                    </div>
                ` : ''}

                <!-- Rodapé: Prazo à esquerda e Botão Play/Timer à direita -->
                <div class="kd-card__footer flex items-center justify-between gap-3 mt-auto">
                    <div>
                        ${dueHtml}
                    </div>

                    <div>
                        ${timerControlHtml}
                    </div>
                </div>

                <!-- Barra de status colorida no rodapé -->
                <div class="kd-card__status-bar" aria-hidden="true"></div>
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

                <button type="button" class="kd-menu__item" role="menuitem" data-action="adjust-card-time" data-task-id="${task.id}">
                    <i data-lucide="clock" class="w-4 h-4 text-indigo-400"></i> Ajustar tempo (+ / -)
                </button>
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

        if (board.viewMode === 'weekdays') {
            const days = getWeekDays().filter((d) => !board.hiddenColumns.weekdays.has(d.id));
            if (!board.mobileStageId || !days.some((d) => String(d.id) === String(board.mobileStageId))) {
                const todayCol = days.find((d) => d.isToday);
                board.mobileStageId = todayCol ? todayCol.id : (days[0]?.id || null);
            }
            tabs.innerHTML = days.map((day) => {
                const count = tasksOfWeekday(day).length;
                const selected = String(board.mobileStageId) === String(day.id);
                return `
                    <button type="button" class="kd-segmented__item" role="tab"
                            aria-selected="${selected}" data-action="mobile-stage" data-weekday-id="${day.id}">
                        <span class="kd-col__dot" style="background-color:${KD.escapeHtml(day.color || '#6366f1')}"></span>
                        ${KD.escapeHtml(day.name)}
                        <span class="kd-col__count">${count}</span>
                    </button>
                `;
            }).join('');
            return;
        }

        const visibleStages = board.stages.filter((s) => !board.hiddenColumns.status.has(String(s.id)));
        if (!board.mobileStageId || !visibleStages.some((s) => Number(s.id) === Number(board.mobileStageId))) {
            board.mobileStageId = visibleStages[0]?.id || null;
        }

        tabs.innerHTML = visibleStages.map((stage) => {
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
        if (board.viewMode === 'weekdays') {
            document.querySelectorAll('.kd-col[data-weekday-id]').forEach((column) => {
                column.classList.toggle('is-active-mobile', String(column.dataset.weekdayId) === String(board.mobileStageId));
            });
            return;
        }
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
            dragClass: 'sortable-drag',
            draggable: '.kd-card',
            // Tarefas bomba e controles interativos não iniciam arrasto
            filter: '.kd-card[data-bomb="1"], .kd-card__check-trigger, .kd-menu-wrap, .kd-timer-toggle, .kd-play-btn, .kd-tag, input, select, textarea',
            preventOnFilter: false,
            delay: 0,
            touchStartThreshold: 3,
            forceFallback: true,
            fallbackClass: 'sortable-fallback',
            fallbackOnBody: true,
            fallbackTolerance: 3,
            onChoose: () => {
                if (window.getSelection) {
                    window.getSelection().removeAllRanges();
                }
            },
            onStart: () => {
                board.dragging = true;
                document.documentElement.classList.add('kd-dragging');
                if (window.getSelection) {
                    window.getSelection().removeAllRanges();
                }
                KD.closeMenus();
            },
            
            // Rede de segurança: nem arrastar bomba nem soltar card acima de uma bomba
            onMove: (event) => {
                if (event.dragged && event.dragged.dataset && event.dragged.dataset.bomb === '1') return false;
                if (event.related && event.related.dataset && event.related.dataset.bomb === '1' && event.willInsertAfter === false) return false;
                return true;
            },
            onEnd: handleDragEnd,
        }));
    }

    async function handleDragEnd(event) {
        console.log('[DEBUG SORTABLE] handleDragEnd fired! oldIndex:', event.oldIndex, 'newIndex:', event.newIndex, 'to:', event.to, 'from:', event.from);
        board.dragging = false;
        document.documentElement.classList.remove('kd-dragging');
        if (window.getSelection) {
            window.getSelection().removeAllRanges();
        }

        const card = event.item;
        const taskId = Number(card.dataset.taskId);
        const targetBody = event.to;
        const fromBody = event.from;

        if (!taskId || !targetBody) return;

        card.classList.add('is-busy');

        try {
            if (board.viewMode === 'weekdays') {
                const targetWeekdayId = targetBody.dataset.weekdayId;
                const fromWeekdayId = fromBody.dataset.weekdayId;
                const newDueDate = targetBody.dataset.dueDate || null;
                const oldDueDate = fromBody.dataset.dueDate || null;

                if (targetWeekdayId === fromWeekdayId && event.oldIndex === event.newIndex) {
                    card.classList.remove('is-busy');
                    return;
                }

                // 1. Arrastou PARA a coluna "Concluídos"
                if (targetWeekdayId === 'done') {
                    const result = await KD.api.tasks({ action: 'complete_task', task_id: taskId });
                    await KD.loadBoard({ silent: true });
                    KD.toast('Tarefa concluída.', { undo: () => undoMove(result.undo) });
                    return;
                }

                // 2. Arrastou DE "Concluídos" para outra coluna da semana
                if (fromWeekdayId === 'done') {
                    await KD.api.tasks({ action: 'uncomplete_task', task_id: taskId });
                    if (newDueDate) {
                        await KD.api.tasks({ action: 'move', task_id: taskId, due_date: newDueDate });
                    }
                    await KD.loadBoard({ silent: true });
                    KD.toast(newDueDate ? `Tarefa reaberta e agendada para ${KD.formatDate(newDueDate)}.` : 'Tarefa reaberta sem prazo.');
                    return;
                }

                // 3. Arrastou entre colunas normais de dias ou na mesma coluna
                const orderedIds = Array.from(targetBody.querySelectorAll('.kd-card:not(.sortable-ghost):not(.sortable-fallback)')).map((node) => Number(node.dataset.taskId)).filter((id) => id > 0);

                if (targetWeekdayId !== fromWeekdayId) {
                    const cardTask = board.tasks.find((t) => Number(t.id) === taskId);
                    const PRIORITY_ORDER = ['low', 'medium', 'high', 'urgent'];
                    const currentPrio = (cardTask ? cardTask.priority : 'medium') || 'medium';
                    const curIdx = PRIORITY_ORDER.indexOf(currentPrio);
                    const nextPrio = (curIdx >= 0 && curIdx < 3) ? PRIORITY_ORDER[curIdx + 1] : 'urgent';

                    if (cardTask) {
                        if (cardTask.due_date && !cardTask.original_due_date) {
                            cardTask.original_due_date = cardTask.due_date;
                        }
                        cardTask.priority = nextPrio;
                        if (newDueDate) {
                            cardTask.due_date = newDueDate;
                        }
                    }

                    const payload = {
                        action: 'move',
                        task_id: taskId,
                        priority: nextPrio,
                        due_date: newDueDate,
                        ordered_ids: orderedIds,
                    };
                    await KD.api.tasks(payload);

                    const prioLabel = nextPrio === 'urgent' ? 'Urgente' : (nextPrio === 'high' ? 'Alta' : 'Média');
                    KD.toast(`Prioridade elevada para ${prioLabel}. Prazo original mantido.`);
                    await KD.loadBoard({ silent: true });
                    return;
                }

                                // Atualizacao otimista imediata no cache local para evitar qualquer "pulo"
                const todayStrLocal = getTodayString();
                orderedIds.forEach((id, idx) => {
                    const t = board.tasks.find((task) => Number(task.id) === id);
                    if (t) {
                        t.sort_order = (idx + 1) * 10;
                        if (targetWeekdayId !== fromWeekdayId) {
                            if (newDueDate && t.due_date && t.due_date <= todayStrLocal && newDueDate > t.due_date) {
                                t.original_due_date = t.original_due_date || t.due_date;
                            }
                            t.due_date = newDueDate;
                        }
                    }
                });

                const payload = {
                    action: 'move',
                    task_id: taskId,
                    ordered_ids: orderedIds,
                };
                if (targetWeekdayId !== fromWeekdayId) {
                    payload.due_date = newDueDate;
                }

                await KD.api.tasks(payload);

                if (targetWeekdayId === fromWeekdayId) {
                    KD.toast('Ordem das tarefas atualizada.');
                } else {
                    KD.toast(newDueDate ? `Reagendada para ${KD.formatDate(newDueDate)}.` : 'Prazo removido.');
                }
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

                const orderedIds = Array.from(targetBody.querySelectorAll('.kd-card:not(.sortable-ghost):not(.sortable-fallback)')).map((node) => Number(node.dataset.taskId)).filter((id) => id > 0);

                // Atualização otimista imediata no cache local
                orderedIds.forEach((id, idx) => {
                    const t = board.tasks.find((task) => Number(task.id) === id);
                    if (t) {
                        t.sort_order = (idx + 1) * 10;
                        if (stageId !== fromStageId) {
                            t.stage_id = stageId;
                        }
                    }
                });

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
                } else if (stageId === fromStageId) {
                    KD.toast('Ordem das tarefas atualizada.');
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

            case 'edit': {
                KD.closeMenus();
                const t = board.tasks.find((task) => Number(task.id) === taskId);
                const isCal = t ? Number(t.is_calendar_event || 0) === 1 : null;
                KD.openTaskForm(taskId, null, null, null, isCal);
                break;
            }

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

            case 'reset-weekday-order': {
                KD.closeMenus();
                const dStr = node.dataset.dueDate || null;
                const wId = node.dataset.weekdayId;
                board.tasks.forEach((t) => {
                    if (dStr && t.due_date === dStr && Number(t.is_bomb) !== 1) {
                        t.sort_order = 0;
                    } else if (!dStr && wId === 'nodate' && !t.due_date && Number(t.is_bomb) !== 1) {
                        t.sort_order = 0;
                    }
                });
                render();
                if (dStr) {
                    await KD.api.tasks({ action: 'reset_stage_order', due_date: dStr });
                }
                KD.toast('Coluna reordenada por prioridade com sucesso.');
                await KD.loadBoard({ silent: true });
                break;
            }

            case 'reset-stage-order': {
                KD.closeMenus();
                const stgId = Number(node.dataset.stageId);
                board.tasks.forEach((t) => {
                    if (Number(t.stage_id) === stgId && Number(t.is_bomb) !== 1) {
                        t.sort_order = 0;
                    }
                });
                render();
                await KD.api.tasks({ action: 'reset_stage_order', stage_id: stgId });
                KD.toast('Coluna reordenada pelo padrão de prioridades.');
                await KD.loadBoard({ silent: true });
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
                board.mobileStageId = node.dataset.weekdayId || node.dataset.stageId || stageId;
                renderStageTabs();
                applyMobileStage();
                break;

            case 'timer':
            case 'timer-play':
            case 'timer-pause':
                node.blur();
                await toggleTimer(taskId, node);
                break;

            case 'timer-stop':
                node.blur();
                await stopTimer(taskId, node);
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

            case 'adjust-card-time':
                KD.closeMenus();
                if (KD.openAdjustTimeModal) {
                    KD.openAdjustTimeModal(taskId);
                }
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
                const task = board.tasks.find((t) => Number(t.id) === Number(taskId));
                let recurrenceScope = 'single';
                if (task && (Number(task.is_recurring) === 1 || Number(task.parent_task_id) > 0) && typeof KD.promptRecurrenceScope === 'function') {
                    const scope = await KD.promptRecurrenceScope('delete');
                    if (!scope) break;
                    recurrenceScope = scope;
                }
                const result = await KD.api.tasks({ action: 'delete', task_id: taskId, recurrence_scope: recurrenceScope });
                await KD.loadBoard({ silent: true });
                if (typeof loadCalendarEvents === 'function') { await loadCalendarEvents(); }
                KD.toast(`"${result.title || 'Tarefa'}" movida para a lixeira.`, {
                    undo: async () => {
                        await KD.api.tasks({ action: 'restore_task', task_id: taskId });
                        await KD.loadBoard({ silent: true });
                        if (typeof loadCalendarEvents === 'function') { await loadCalendarEvents(); }
                        KD.toast('Tarefa restaurada da lixeira.', { tone: 'info' });
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

            case 'pick-stage-color': {
                openStageColorPicker(stageId, node);
                break;
            }

            default:
                break;
        }
    }

    function closeColorPopover() {
        const existing = document.getElementById('kdStageColorPopover');
        if (existing) existing.remove();
    }

    function openStageColorPicker(stageId, anchorEl) {
        closeColorPopover();
        KD.closeMenus();

        const stage = board.stages.find((s) => Number(s.id) === Number(stageId));
        if (!stage) return;

        const currentColor = (stage.color || '#6366f1').toLowerCase();

        const palette = [
            '#5c52d6', // Roxo ClickUp (Em Progresso)
            '#7c3aed', // Violeta
            '#6366f1', // Índigo
            '#3b82f6', // Azul
            '#0ea5e9', // Ciano / Sky
            '#00b884', // Verde ClickUp (Feito)
            '#10b981', // Esmeralda
            '#eab308', // Amarelo
            '#f97316', // Laranja
            '#ef4444', // Vermelho
            '#ec4899', // Rosa
            '#64748b', // Slate / Cinza
        ];

        const popover = document.createElement('div');
        popover.id = 'kdStageColorPopover';
        popover.className = 'kd-color-popover kd-glass kd-glass--raised';
        popover.innerHTML = `
            <div class="kd-color-popover__title">
                <span>Cor da coluna</span>
                <span class="text-[10px] font-bold text-slate-400 font-mono">${currentColor}</span>
            </div>
            <div class="kd-color-grid">
                ${palette.map((hex) => {
                    const isSelected = hex.toLowerCase() === currentColor;
                    return `
                        <button type="button" class="kd-color-swatch ${isSelected ? 'is-active' : ''}"
                                style="background-color: ${hex};"
                                data-color="${hex}"
                                title="${hex}">
                            ${isSelected ? '<i data-lucide="check" class="w-3 h-3 text-white stroke-[3]"></i>' : ''}
                        </button>
                    `;
                }).join('')}
                <label class="kd-color-custom-btn" title="Cor personalizada">
                    <i data-lucide="plus" class="w-3.5 h-3.5 text-white stroke-[3]"></i>
                    <input type="color" value="${currentColor}" id="kdCustomColorInput">
                </label>
            </div>
        `;

        document.body.appendChild(popover);
        KD.icons();

        // Posicionamento inteligente abaixo do anchorEl
        const rect = anchorEl.getBoundingClientRect();
        const popoverWidth = 210;
        let left = rect.left;
        if (left + popoverWidth > window.innerWidth - 12) {
            left = window.innerWidth - popoverWidth - 12;
        }
        if (left < 12) left = 12;
        let top = rect.bottom + 6;
        if (top + 120 > window.innerHeight) {
            top = rect.top - 120;
        }

        popover.style.left = `${left}px`;
        popover.style.top = `${top}px`;

        const applyColor = async (newColor) => {
            closeColorPopover();
            if (!newColor || newColor.toLowerCase() === currentColor) return;
            try {
                stage.color = newColor;
                render();
                await KD.api.tasks({ action: 'set_stage_color', stage_id: stage.id, color: newColor });
                KD.toast(`Cor de "${stage.name}" atualizada.`);
            } catch (err) {
                KD.toastError(err.message || 'Erro ao alterar a cor.');
                await KD.loadBoard({ silent: true });
            }
        };

        popover.querySelectorAll('.kd-color-swatch').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                applyColor(btn.dataset.color);
            });
        });

        const customInput = popover.querySelector('#kdCustomColorInput');
        if (customInput) {
            customInput.addEventListener('change', (e) => {
                applyColor(e.target.value);
            });
        }

        const onDocClick = (e) => {
            if (!popover.contains(e.target) && !anchorEl.contains(e.target)) {
                closeColorPopover();
                document.removeEventListener('click', onDocClick);
            }
        };
        setTimeout(() => document.addEventListener('click', onDocClick), 20);
    }

    async function toggleTimer(taskId, triggerNode) {
        try {
            const numId = Number(taskId);
            const card = document.querySelector(`.kd-card[data-task-id="${numId}"]`);
            const toggleWrap = card?.querySelector('.kd-timer-toggle');
            if (toggleWrap && !toggleWrap.classList.contains('is-active')) {
                toggleWrap.classList.add('is-active');
            }
            if (triggerNode) triggerNode.blur();

            const p = KD.api.tasks({ action: 'toggle_timer', task_id: numId });
            const result = await p;
            
            if (result.is_running) {
                board.pausedTimers.delete(numId);
            } else {
                board.pausedTimers.add(numId);
            }

            if (result.paused_tasks && Array.isArray(result.paused_tasks)) {
                result.paused_tasks.forEach((pId) => board.pausedTimers.delete(Number(pId)));
            }

            await KD.loadBoard({ silent: true });
            KD.toast(result.is_running ? 'Cronômetro iniciado. Tarefa em andamento.' : 'Cronômetro pausado. Tarefa continua em andamento.', { tone: 'info' });
            if (result.is_running && result.paused_tasks && result.paused_tasks.length > 0) {
                KD.toast('O cronômetro da outra tarefa foi pausado.', { tone: 'info' });
            }
        } catch (error) {
            KD.toastError(error.message);
        }
    }

    async function stopTimer(taskId, triggerNode) {
        try {
            const numId = Number(taskId);
            board.pausedTimers.delete(numId);
            const card = document.querySelector(`.kd-card[data-task-id="${numId}"]`);
            const toggleWrap = card?.querySelector('.kd-timer-toggle');
            if (toggleWrap) {
                toggleWrap.classList.remove('is-active');
            }
            if (triggerNode) triggerNode.blur();

            const p = KD.api.tasks({ action: 'stop_timer', task_id: numId });

            // Aguarda a animação suave de recolhimento antes de re-renderizar o quadro
            await Promise.all([
                p,
                new Promise((resolve) => setTimeout(resolve, 240))
            ]);

            await KD.loadBoard({ silent: true });
            KD.toast('Tarefa parada e movida de volta para A Fazer.', { tone: 'info' });
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

        // Acessibilidade: Enter ou Espaço no título abre a tarefa
        if ((event.key === 'Enter' || event.key === ' ') && event.target?.matches?.('.kd-card__title[data-action="open"]')) {
            event.preventDefault();
            const taskId = Number(event.target.dataset.taskId);
            if (taskId) KD.openTaskDetail(taskId);
            return;
        }

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
        // Ctrl + Setas para cima/baixo: reordenar o card dentro da coluna
        if ((event.ctrlKey || event.metaKey) && (event.key === 'ArrowUp' || event.key === 'ArrowDown')) {
            event.preventDefault();
            const colBody = card.closest('.kd-col__body');
            if (!colBody) return;

            const cards = Array.from(colBody.querySelectorAll('.kd-card'));
            const currentIndex = cards.indexOf(card);
            if (currentIndex < 0) return;

            const targetIndex = event.key === 'ArrowUp' ? currentIndex - 1 : currentIndex + 1;
            if (targetIndex < 0 || targetIndex >= cards.length) return;

            const targetCard = cards[targetIndex];
            if (event.key === 'ArrowUp' && targetCard.dataset.bomb === '1') {
                KD.toastError('Tarefas bomba permanecem no topo absoluto.');
                return;
            }

            if (event.key === 'ArrowUp') {
                colBody.insertBefore(card, targetCard);
            } else {
                colBody.insertBefore(card, targetCard.nextSibling);
            }

            const orderedIds = Array.from(colBody.querySelectorAll('.kd-card')).map((node) => Number(node.dataset.taskId));
            const isWeekdays = board.viewMode === 'weekdays';
            const payload = {
                action: 'move',
                task_id: taskId,
                ordered_ids: orderedIds,
            };
            if (!isWeekdays) {
                payload.stage_id = stageId;
            }

            KD.api.tasks(payload)
                .then(() => KD.loadBoard({ silent: true }).then(() => {
                    KD.toast('Ordem da tarefa atualizada.');
                    const moved = document.querySelector(`.kd-card[data-task-id="${taskId}"]`);
                    if (moved) moved.focus();
                }))
                .catch((err) => KD.toastError(err.message));
            return;
        }

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

    KD.getTaskById = function (taskId) {
        return board.tasks.find((t) => Number(t.id) === Number(taskId)) || null;
    };

    KD.renderBoard = function () {
        render();
    };

    KD.toggleCompactMode = function () {
        board.compactMode = !board.compactMode;
        try {
            localStorage.setItem('kanbandoo_compact_mode', board.compactMode ? '1' : '0');
        } catch (e) {}
        updateToolbarState();
        render();
        KD.toast(board.compactMode ? 'Modo compacto ativado.' : 'Modo normal ativado.', { tone: 'info' });
    };

    
    function updateWeekNav() {
        const wrap = el('weekNavControls');
        if (!wrap) return;
        const isWeek = (board.viewMode === 'weekdays');
        wrap.style.display = isWeek ? 'inline-flex' : 'none';
        if (!isWeek) return;

        const label = el('weekNavLabelText');
        const btnCurrent = el('btnWeekCurrent');
        const offset = board.weekOffset || 0;

        if (label) {
            if (offset === 0) {
                label.textContent = 'Esta Semana';
            } else if (offset === 1) {
                label.textContent = 'Próx. Semana';
            } else if (offset === -1) {
                label.textContent = 'Semana Anterior';
            } else if (offset > 0) {
                label.textContent = `+${offset} Semanas`;
            } else {
                label.textContent = `${offset} Semanas`;
            }
        }

        if (btnCurrent) {
            btnCurrent.classList.toggle('text-indigo-400', offset !== 0);
            btnCurrent.classList.toggle('bg-indigo-600/15', offset !== 0);
            btnCurrent.classList.toggle('border', offset !== 0);
            btnCurrent.classList.toggle('border-indigo-500/20', offset !== 0);
        }
    }

    KD.navigateWeek = function (step) {
        if (step === 0) {
            board.weekOffset = 0;
        } else {
            board.weekOffset = (board.weekOffset || 0) + step;
        }
        updateWeekNav();
        render();
        if (typeof lucide !== 'undefined') lucide.createIcons();
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

    KD.setWeekStartMode = function (mode) {
        board.weekStartMode = (mode === 'monday' ? 'monday' : 'today');
        try { localStorage.setItem('kanbandoo_week_start', board.weekStartMode); } catch (e) {}
        KD.renderColumnsMenu();
        render();
        KD.toast(board.weekStartMode === 'monday' ? 'Semana iniciando na Segunda-feira.' : 'Semana iniciando no dia de Hoje.');
    };

    KD.setWeekdayDaysCount = function (n) {
        const parsed = parseInt(n, 10);
        const val = Math.max(1, Math.min(14, isNaN(parsed) ? 5 : parsed));
        board.weekdayDaysCount = val;
        try { localStorage.setItem(WEEKDAY_DAYS_COUNT_KEY, String(val)); } catch (e) {}
        KD.renderColumnsMenu();
        render();
        KD.toast(`Visão configurada para exibir ${val} dia(s).`);
    };

    KD.stepWeekdayDaysCount = function (step) {
        const current = Number(board.weekdayDaysCount) || 5;
        KD.setWeekdayDaysCount(current + step);
    };

    KD.renderColumnsMenu = function () {
        const list = el('columnsMenuList');
        if (!list) return;

        // Configurações específicas da Visão por Dias (Início da semana e Quantidade de dias)
        const isWeekView = (board.viewMode === 'weekdays');
        const weekdayConfig = el('weekdayConfigSection');
        if (weekdayConfig) {
            weekdayConfig.hidden = !isWeekView;
            if (isWeekView) {
                const daysCount = Math.max(1, Math.min(14, Number(board.weekdayDaysCount) || 5));
                const badge = el('weekdayDaysCountBadge');
                if (badge) badge.textContent = `${daysCount} dia${daysCount > 1 ? 's' : ''}`;

                const inputCount = el('inputWeekdayDaysCount');
                if (inputCount) inputCount.value = daysCount;

                document.querySelectorAll('.btn-days-count').forEach((b) => {
                    const d = Number(b.dataset.days);
                    const isActive = (d === daysCount);
                    b.className = isActive
                        ? 'btn-days-count flex-1 py-1 rounded text-center font-medium transition text-xs bg-indigo-600 text-white shadow-sm'
                        : 'btn-days-count flex-1 py-1 rounded text-center font-medium transition text-xs text-slate-400 hover:text-white';
                });

                const btnToday = el('btnWeekStartToday');
                const btnMonday = el('btnWeekStartMonday');
                const isTodayActive = (board.weekStartMode !== 'monday');
                if (btnToday) {
                    btnToday.className = isTodayActive
                        ? 'px-2 py-1 rounded-md text-center font-medium transition text-xs bg-indigo-600 text-white shadow-sm'
                        : 'px-2 py-1 rounded-md text-center font-medium transition text-xs text-slate-400 hover:text-white';
                }
                if (btnMonday) {
                    btnMonday.className = !isTodayActive
                        ? 'px-2 py-1 rounded-md text-center font-medium transition text-xs bg-indigo-600 text-white shadow-sm'
                        : 'px-2 py-1 rounded-md text-center font-medium transition text-xs text-slate-400 hover:text-white';
                }
            }
        }

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

        updateCardVisualCheckmarks();
        KD.icons();
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
    // Panning do Quadro (Arrastar e mover o board estilo ClickUp)
    // ---------------------------------------------------------------

    function initBoardPan() {
        const boardEl = el('kanbanBoard');
        if (!boardEl) return;

        let isDown = false;
        let startX = 0;
        let startY = 0;
        let scrollLeft = 0;
        let scrollTop = 0;
        let hasMoved = false;

        boardEl.addEventListener('mousedown', (e) => {
            // Apenas botão esquerdo do mouse
            if (e.button !== 0) return;

            // Se estiver arrastando card (Sortable), não faz pan do board
            if (board.dragging) return;

            // Ignora se o clique for sobre a barra de rolagem nativa do board
            const rect = boardEl.getBoundingClientRect();
            if (e.clientY >= rect.bottom - 14) return;

            // Não inicia arrasto de tela se o clique foi em cartões, botões, inputs, links ou menus
            if (e.target.closest('.kd-card, button, input, select, textarea, a, label, [data-action], .kd-menu, .kd-color-popover, .tag-opt-check')) {
                return;
            }

            isDown = true;
            hasMoved = false;
            startX = e.clientX;
            startY = e.clientY;
            scrollLeft = boardEl.scrollLeft;
            scrollTop = boardEl.scrollTop;
        });

        window.addEventListener('mousemove', (e) => {
            if (!isDown) return;

            const dx = e.clientX - startX;
            const dy = e.clientY - startY;

            // Limiar de 3px para não bloquear cliques normais
            if (!hasMoved && (Math.abs(dx) > 3 || Math.abs(dy) > 3)) {
                hasMoved = true;
                document.body.classList.add('kd-board-panning');
            }

            if (hasMoved) {
                e.preventDefault();
                boardEl.scrollLeft = scrollLeft - dx;
                if (boardEl.scrollHeight > boardEl.clientHeight) {
                    boardEl.scrollTop = scrollTop - dy;
                }
            }
        }, { passive: false });

        const endPan = () => {
            if (!isDown) return;
            isDown = false;

            if (hasMoved) {
                document.body.classList.remove('kd-board-panning');

                // Evita disparar cliques acidentais em elementos ao soltar o mouse após arrastar
                const captureClick = (ev) => {
                    ev.preventDefault();
                    ev.stopPropagation();
                    window.removeEventListener('click', captureClick, true);
                };
                window.addEventListener('click', captureClick, true);
                setTimeout(() => {
                    window.removeEventListener('click', captureClick, true);
                }, 80);
            }
        };

        window.addEventListener('mouseup', endPan);
        window.addEventListener('blur', endPan);
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isDown) endPan();
        });
    }

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
        initBoardPan();

        el('filterSearch')?.addEventListener('input', (e) => {
            const hSearch = el('kdHeaderSearch');
            if (hSearch && hSearch.value !== e.target.value) {
                hSearch.value = e.target.value;
                const cBtn = el('kdHeaderSearchClear');
                if (cBtn) cBtn.hidden = !hSearch.value;
            }
            KD.onFilterInput(e);
        });
        el('kdHeaderSearch')?.addEventListener('input', (e) => {
            const fSearch = el('filterSearch');
            if (fSearch && fSearch.value !== e.target.value) {
                fSearch.value = e.target.value;
            }
            KD.onFilterInput(e);
        });
        ['filterClient', 'filterPriority', 'filterAssignee', 'filterTag'].forEach((id) => {
            el(id)?.addEventListener('change', KD.onFilterChange);
        });
    });
})(window.KD);

    // Evita selecao acidental de texto ao arrastar cards ou clicar nas colunas do quadro
    document.addEventListener('selectstart', (e) => {
        if (typeof board !== 'undefined' && board && board.dragging) {
            e.preventDefault();
            return;
        }
        if (e.target && (e.target.closest('.kd-card') || e.target.closest('.kd-col__body') || e.target.closest('.kd-col'))) {
            if (!e.target.closest('input, textarea, [contenteditable="true"], .kd-search, select')) {
                e.preventDefault();
            }
        }
    });
