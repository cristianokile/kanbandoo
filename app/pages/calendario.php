<?php
/**
 * KanbanDoo - Calendário e Integração Google / Microsoft Calendar
 */
require_once dirname(__DIR__) . '/bootstrap.php';

require_login();

view_header('Calendário');

$pdo = get_pdo();
$user = current_user();

// Obter configurações de calendário do usuário
$stmt = $pdo->prepare('SELECT google_calendar_url, outlook_calendar_url FROM users WHERE id = :id');
$stmt->execute([':id' => $user['id']]);
$userCalendar = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
?>

<main id="conteudo" class="kd-calendar-main flex-1 w-full pt-4 px-4 lg:px-8 flex flex-col pb-20">

    <!-- Topo da Página de Calendário -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4 flex-shrink-0">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold tracking-tight text-white flex items-center gap-2.5">
                    <i data-lucide="calendar" class="w-6 h-6 text-indigo-400"></i>
                    <span>Calendário</span>
                </h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                    Sincronizado com o Kanban
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Visualize os prazos e horários das tarefas. Eventos sincronizados do Google Calendar e Outlook entram como tarefas no quadro.
            </p>
        </div>

        <!-- Ações do Topo -->
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" onclick="openCalendarSyncModal()" class="kd-btn kd-glass flex items-center gap-2 text-xs font-semibold text-slate-200 hover:text-white">
                <i data-lucide="refresh-cw" class="w-4 h-4 text-indigo-400"></i>
                <span>Sincronizar Calendários</span>
            </button>

            <button type="button" onclick="KD.openTaskForm(null, null, null, null, true)" class="kd-btn kd-btn--primary flex items-center gap-2 text-xs font-semibold">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Novo Evento</span>
            </button>
        </div>
    </div>

    <!-- Barra de Controle do Calendário (Navegação de Meses e Filtros) -->
    <div class="kd-glass rounded-2xl p-3 mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border border-slate-800/80 shadow-lg">
        <div class="flex items-center gap-2">
            <button type="button" onclick="changeMonth(-1)" class="kd-icon-btn" title="Mês anterior" aria-label="Mês anterior">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
            </button>
            <button type="button" onclick="goToToday()" class="kd-btn kd-glass text-xs font-semibold px-3 py-1.5" title="Ir para o dia atual">
                Hoje
            </button>
            <button type="button" onclick="changeMonth(1)" class="kd-icon-btn" title="Próximo mês" aria-label="Próximo mês">
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
            <h2 id="calendarTitle" class="text-base font-bold text-white tracking-wide ml-2 min-w-[12rem]">
                <!-- Ex: Outubro de 2026 -->
            </h2>
        </div>

        <div class="flex items-center gap-2 self-end sm:self-auto">
            <div class="inline-flex rounded-xl bg-slate-900/80 p-0.5 border border-slate-800 text-xs font-semibold">
                <button type="button" id="btnViewMonth" onclick="setViewMode('month')" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white transition shadow-sm">
                    Mês
                </button>
                <button type="button" id="btnViewWeek" onclick="setViewMode('week')" class="px-3 py-1.5 rounded-lg text-slate-400 hover:text-white transition">
                    Semana
                </button>
            </div>
        </div>
    </div>

    <!-- Container do Calendário -->
    <div id="calendarContainer" class="flex-1 pb-8 min-h-[500px]">
        <!-- Tabela Mensal -->
        <div id="monthViewWrap" class="kd-glass rounded-2xl overflow-hidden border border-slate-800/80 shadow-2xl flex flex-col">
            <!-- Cabeçalho dos Dias da Semana -->
            <div class="grid grid-cols-7 border-b border-slate-800/80 bg-slate-950/60 text-center text-xs font-bold text-slate-400 uppercase tracking-wider py-2.5">
                <div>Dom</div>
                <div>Seg</div>
                <div>Ter</div>
                <div>Qua</div>
                <div>Qui</div>
                <div>Sex</div>
                <div>Sáb</div>
            </div>

            <!-- Grade de Células dos Dias -->
            <div id="calendarGrid" class="grid grid-cols-7 gap-px bg-slate-800/40">
                <!-- Preenchido via JavaScript -->
            </div>
        </div>

        <!-- Visão Semanal -->
        <div id="weekViewWrap" class="kd-glass rounded-2xl overflow-hidden border border-slate-800/80 shadow-2xl flex flex-col" hidden>
            <div id="weekHeader" class="grid grid-cols-7 border-b border-slate-800/80 bg-slate-950/60 text-center py-2.5">
                <!-- Dias da semana preenchidos via JS -->
            </div>
            <div id="weekBody" class="grid grid-cols-7 gap-px bg-slate-800/40 min-h-[500px]">
                <!-- Colunas da semana preenchidas via JS -->
            </div>
        </div>
    </div>
</main>

<!-- Modal de Sincronização / Importação de Calendários (Google & Microsoft) -->
<div id="calendarSyncModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="syncModalTitle" hidden>
    <div class="kd-modal__panel max-w-xl w-full p-6 space-y-6">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center">
                    <i data-lucide="calendar-sync" class="w-5 h-5"></i>
                </span>
                <div>
                    <h3 id="syncModalTitle" class="text-lg font-bold text-white">Sincronizar Calendários Externos</h3>
                    <p class="text-xs text-slate-400">Importe eventos do Google Calendar e Microsoft Outlook diretamente como tarefas no Kanban.</p>
                </div>
            </div>
            <button type="button" class="kd-icon-btn" onclick="KD.closeModal('calendarSyncModal')" aria-label="Fechar">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Seção Google Calendar -->
        <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 font-bold text-sm text-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-blue-500/20 text-blue-400 flex items-center justify-center text-xs">G</span>
                    <span>Google Calendar (Agenda)</span>
                </div>
                <span class="text-[11px] text-slate-400">Link iCal / Webcal</span>
            </div>
            <p class="text-xs text-slate-400 leading-relaxed">
                No Google Calendar: clique no menu ⋮ ao lado da sua agenda &gt; <b>Configurações</b> &gt; role até <b>"Endereço secreto no formato iCal"</b> e copie o link.
            </p>
            <div class="flex items-center gap-2">
                <input type="url" id="googleCalendarUrl" placeholder="https://calendar.google.com/calendar/ical/.../basic.ics"
                       value="<?= e($userCalendar['google_calendar_url'] ?? '') ?>"
                       class="kd-field text-xs flex-1" style="min-height:38px">
                <button type="button" onclick="syncCalendarFeed('google')" class="kd-btn kd-btn--primary text-xs px-3.5" style="min-height:38px" id="btnSyncGoogle">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                    <span>Sincronizar</span>
                </button>
            </div>
        </div>

        <!-- Seção Microsoft Outlook / 365 -->
        <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800 space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 font-bold text-sm text-slate-100">
                    <span class="w-6 h-6 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center text-xs font-bold">M</span>
                    <span>Microsoft Calendar (Outlook / Office 365)</span>
                </div>
                <span class="text-[11px] text-slate-400">Link iCal / ICS</span>
            </div>
            <p class="text-xs text-slate-400 leading-relaxed">
                No Outlook Web: vá em <b>Configurações ⚙</b> &gt; <b>Calendário</b> &gt; <b>Calendários compartilhados</b> &gt; <b>Publicar um calendário</b> &gt; selecione permissão e copie o <b>link ICS</b>.
            </p>
            <div class="flex items-center gap-2">
                <input type="url" id="outlookCalendarUrl" placeholder="https://outlook.live.com/owa/calendar/.../calendar.ics"
                       value="<?= e($userCalendar['outlook_calendar_url'] ?? '') ?>"
                       class="kd-field text-xs flex-1" style="min-height:38px">
                <button type="button" onclick="syncCalendarFeed('microsoft')" class="kd-btn kd-btn--primary text-xs px-3.5" style="min-height:38px" id="btnSyncOutlook">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                    <span>Sincronizar</span>
                </button>
            </div>
        </div>

        <!-- Seção Upload de arquivo .ics -->
        <div class="p-4 rounded-xl bg-slate-900/40 border border-dashed border-slate-800 space-y-2 text-center">
            <p class="text-xs font-semibold text-slate-300">Ou faça upload de um arquivo .ics exportado</p>
            <div class="flex items-center justify-center gap-2 pt-1">
                <input type="file" id="icsFileInput" accept=".ics,text/calendar" class="text-xs text-slate-400 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
                <button type="button" onclick="uploadIcsFile()" class="kd-btn kd-glass text-xs" style="min-height:34px">
                    <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                    <span>Importar arquivo</span>
                </button>
            </div>
        </div>

        <div class="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20">
            <label class="flex items-center gap-2 text-xs font-semibold text-slate-200 cursor-pointer select-none">
                <input type="checkbox" id="syncShowInKanban" checked class="rounded text-indigo-600 focus:ring-0">
                <span>Exibir os eventos importados também no Quadro Kanban</span>
            </label>
            <p class="text-[11px] text-slate-400 mt-0.5 ml-5">Se desmarcado, os eventos aparecem exclusivamente na sua visão de Calendário.</p>
        </div>

        <div class="flex items-center justify-between pt-2 border-t border-slate-800 text-xs">
            <span class="text-slate-400">Os eventos entram automaticamente na coluna <b>A FAZER</b> do Kanban.</span>
            <button type="button" onclick="KD.closeModal('calendarSyncModal')" class="kd-btn">Fechar</button>
        </div>
    </div>
</div>

<script>
    let currentDate = new Date();
    let calendarEvents = [];
    let viewMode = 'month'; // 'month' ou 'week'

    const MONTH_NAMES = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];

    function openCalendarSyncModal() {
        KD.openModal('calendarSyncModal');
    }

    function changeMonth(delta) {
        currentDate.setMonth(currentDate.getMonth() + delta);
        renderCalendar();
    }

    function goToToday() {
        currentDate = new Date();
        renderCalendar();
    }

    function setViewMode(mode) {
        viewMode = mode;
        const btnM = document.getElementById('btnViewMonth');
        const btnW = document.getElementById('btnViewWeek');
        const wrapM = document.getElementById('monthViewWrap');
        const wrapW = document.getElementById('weekViewWrap');

        if (mode === 'month') {
            btnM.classList.add('bg-indigo-600', 'text-white');
            btnM.classList.remove('text-slate-400');
            btnW.classList.remove('bg-indigo-600', 'text-white');
            btnW.classList.add('text-slate-400');
            wrapM.hidden = false;
            wrapW.hidden = true;
        } else {
            btnW.classList.add('bg-indigo-600', 'text-white');
            btnW.classList.remove('text-slate-400');
            btnM.classList.remove('bg-indigo-600', 'text-white');
            btnM.classList.add('text-slate-400');
            wrapM.hidden = true;
            wrapW.hidden = false;
        }
        renderCalendar();
    }

    async function loadCalendarEvents() {
        try {
            const y = currentDate.getFullYear();
            const m = currentDate.getMonth();
            const start = new Date(y, m - 1, 1).toISOString().split('T')[0];
            const end = new Date(y, m + 2, 0).toISOString().split('T')[0];

            const res = await fetch(`<?= url('/api/calendario') ?>?start=${start}&end=${end}`);
            const data = await res.json();
            if (data.success) {
                calendarEvents = data.events || [];
                renderCalendar();
            }
        } catch (e) {
            console.error('Erro ao carregar eventos do calendário:', e);
        }
    }

    function renderCalendar() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();

        // Título do Cabeçalho
        document.getElementById('calendarTitle').textContent = `${MONTH_NAMES[month]} de ${year}`;

        if (viewMode === 'month') {
            renderMonthGrid(year, month);
        } else {
            renderWeekGrid();
        }

        if (window.lucide) lucide.createIcons();
    }

    function renderMonthGrid(year, month) {
        const grid = document.getElementById('calendarGrid');
        grid.innerHTML = '';

        const firstDayOfMonth = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        const todayStr = new Date().toISOString().split('T')[0];

        // Dias do mês anterior para preencher a primeira semana
        for (let i = firstDayOfMonth - 1; i >= 0; i--) {
            const dayNum = daysInPrevMonth - i;
            const prevMonthDate = new Date(year, month - 1, dayNum);
            const dateStr = prevMonthDate.toISOString().split('T')[0];
            grid.appendChild(createDayCell(dayNum, dateStr, true, false));
        }

        // Dias do mês atual
        for (let day = 1; day <= daysInMonth; day++) {
            const thisDate = new Date(year, month, day);
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = (dateStr === todayStr);
            grid.appendChild(createDayCell(day, dateStr, false, isToday));
        }

        // Dias do próximo mês para completar a última semana
        const totalCells = grid.children.length;
        const remaining = (7 - (totalCells % 7)) % 7;
        for (let nextDay = 1; nextDay <= remaining; nextDay++) {
            const nextMonthDate = new Date(year, month + 1, nextDay);
            const dateStr = nextMonthDate.toISOString().split('T')[0];
            grid.appendChild(createDayCell(nextDay, dateStr, true, false));
        }
    }

    function createDayCell(dayNumber, dateString, isOtherMonth, isToday) {
        const cell = document.createElement('div');
        cell.className = `p-2 min-h-[110px] bg-slate-950/40 hover:bg-slate-900/60 transition flex flex-col justify-between group ${isOtherMonth ? 'opacity-40' : ''} ${isToday ? 'ring-1 ring-inset ring-indigo-500/60 bg-indigo-500/5' : ''}`;

        // Topo da célula: número do dia e botão "+"
        const topRow = document.createElement('div');
        topRow.className = 'flex items-center justify-between mb-1.5';

        const numSpan = document.createElement('span');
        numSpan.className = `text-xs font-bold ${isToday ? 'w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-600/30' : 'text-slate-300'}`;
        numSpan.textContent = dayNumber;
        topRow.appendChild(numSpan);

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'opacity-0 group-hover:opacity-100 transition p-1 text-slate-400 hover:text-white hover:bg-slate-800 rounded-md';
        addBtn.title = `Criar tarefa em ${dateString}`;
        addBtn.innerHTML = '<i data-lucide="plus" class="w-3.5 h-3.5"></i>';
        addBtn.onclick = (e) => {
            e.stopPropagation();
            KD.openTaskForm(null, null, null, dateString, true);
        };
        topRow.appendChild(addBtn);

        cell.appendChild(topRow);

        // Lista de eventos deste dia
        const eventsList = document.createElement('div');
        eventsList.className = 'space-y-1 flex-1 overflow-y-auto max-h-[120px]';

        const dayEvents = calendarEvents.filter(ev => ev.due_date === dateString);

        dayEvents.forEach(ev => {
            const evCard = document.createElement('div');
            evCard.className = `text-[11px] p-1.5 rounded-lg border cursor-pointer transition flex items-center gap-1.5 leading-tight ${ev.is_done ? 'line-through opacity-60 bg-slate-900/50 border-slate-800 text-slate-400' : 'bg-slate-900/90 hover:bg-slate-800/90 border-slate-700/60 text-slate-200 hover:border-indigo-500/50'}`;
            evCard.onclick = () => KD.openTaskDetail(ev.id);

            let timeBadge = '';
            if (ev.due_time) {
                timeBadge = `<span class="px-1 py-0.2 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 flex-shrink-0">${ev.due_time}</span>`;
            }

            evCard.innerHTML = `
                ${timeBadge}
                <span class="truncate font-medium flex-1">${KD.escapeHtml(ev.title)}</span>
            `;

            eventsList.appendChild(evCard);
        });

        cell.appendChild(eventsList);
        return cell;
    }

    function renderWeekGrid() {
        const header = document.getElementById('weekHeader');
        const body = document.getElementById('weekBody');
        header.innerHTML = '';
        body.innerHTML = '';

        // Obter início da semana atual de currentDate (domingo)
        const curr = new Date(currentDate);
        const dayOfWeek = curr.getDay();
        const startOfWeek = new Date(curr.setDate(curr.getDate() - dayOfWeek));

        const todayStr = new Date().toISOString().split('T')[0];

        for (let i = 0; i < 7; i++) {
            const dayDate = new Date(startOfWeek);
            dayDate.setDate(dayDate.getDate() + i);
            const dateStr = dayDate.toISOString().split('T')[0];
            const isToday = (dateStr === todayStr);

            // Cabeçalho
            const colHeader = document.createElement('div');
            colHeader.className = `text-xs py-1 ${isToday ? 'text-indigo-400 font-bold' : 'text-slate-400 font-semibold'}`;
            colHeader.innerHTML = `<div>${['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][i]}</div><div class="text-sm ${isToday ? 'w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center mx-auto mt-0.5' : 'text-white'}">${dayDate.getDate()}</div>`;
            header.appendChild(colHeader);

            // Coluna
            const colBody = document.createElement('div');
            colBody.className = `p-2 min-h-[450px] bg-slate-950/40 flex flex-col space-y-1.5 ${isToday ? 'bg-indigo-500/5 ring-1 ring-inset ring-indigo-500/30' : ''}`;

            const dayEvents = calendarEvents.filter(ev => ev.due_date === dateStr);
            dayEvents.forEach(ev => {
                const evCard = document.createElement('div');
                evCard.className = `p-2 rounded-xl border bg-slate-900/90 border-slate-700/60 hover:border-indigo-500 text-xs text-slate-200 cursor-pointer transition space-y-1 shadow-sm ${ev.is_done ? 'line-through opacity-60' : ''}`;
                evCard.onclick = () => KD.openTaskDetail(ev.id);

                evCard.innerHTML = `
                    <div class="flex items-center justify-between gap-1">
                        ${ev.due_time ? `<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">${ev.due_time}</span>` : '<span></span>'}
                        <span class="text-[10px] text-slate-400 truncate">${KD.escapeHtml(ev.client_company)}</span>
                    </div>
                    <p class="font-semibold text-white truncate">${KD.escapeHtml(ev.title)}</p>
                `;
                colBody.appendChild(evCard);
            });

            body.appendChild(colBody);
        }
    }

    async function syncCalendarFeed(provider) {
        const urlInput = provider === 'microsoft' ? document.getElementById('outlookCalendarUrl') : document.getElementById('googleCalendarUrl');
        const btn = provider === 'microsoft' ? document.getElementById('btnSyncOutlook') : document.getElementById('btnSyncGoogle');

        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Sincronizando...';
        if (window.lucide) lucide.createIcons();

        try {
            const res = await fetch(`<?= url('/api/calendario') ?>`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'sync_ical',
                    provider: provider,
                    url: urlInput ? urlInput.value.trim() : '',
                    show_in_kanban: document.getElementById('syncShowInKanban')?.checked ? 1 : 0
                })
            });
            const data = await res.json();
            if (data.success) {
                KD.toast(data.message || 'Sincronização realizada com sucesso!');
                KD.closeModal('calendarSyncModal');
                await loadCalendarEvents();
            } else {
                KD.toastError(data.error || 'Erro ao sincronizar.');
            }
        } catch (e) {
            KD.toastError('Erro de conexão ao sincronizar.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (window.lucide) lucide.createIcons();
        }
    }

    async function uploadIcsFile() {
        const fileInput = document.getElementById('icsFileInput');
        if (!fileInput || !fileInput.files || !fileInput.files[0]) {
            KD.toastError('Selecione um arquivo .ics para importar.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'sync_ical');
        formData.append('provider', 'file');
        formData.append('file', fileInput.files[0]);
        formData.append('show_in_kanban', document.getElementById('syncShowInKanban')?.checked ? 1 : 0);

        try {
            const res = await fetch(`<?= url('/api/calendario') ?>`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                KD.toast(data.message || 'Arquivo importado com sucesso!');
                KD.closeModal('calendarSyncModal');
                await loadCalendarEvents();
            } else {
                KD.toastError(data.error || 'Erro ao importar arquivo.');
            }
        } catch (e) {
            KD.toastError('Erro ao enviar arquivo.');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderCalendar();
        loadCalendarEvents();
    });
</script>

<?php view_footer(); ?>
