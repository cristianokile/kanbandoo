<?php
/**
 * KanbanDoo - Visualização Pública da Agenda / Calendário
 *
 * Link público, somente leitura, sem permissão de abrir modais nem alterar tarefas.
 * Mesma biblioteca visual e largura (width) da agenda editável do KanbanDoo.
 * Tarefas particulares (is_private = 1) NUNCA são exibidas aqui.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

$token = trim((string)($routeParams['token'] ?? $_GET['token'] ?? ''));

$pdo = get_pdo();
$agendaUser = null;

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT id, username, full_name, email, avatar_path, role FROM users WHERE public_token = :t LIMIT 1');
    $stmt->execute([':t' => $token]);
    $agendaUser = $stmt->fetch();
}

if (!$agendaUser) {
    http_response_code(404);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda não encontrada &bull; KanbanDoo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900/90 border border-slate-800 rounded-3xl p-8 text-center shadow-2xl backdrop-blur-xl">
        <div class="w-16 h-16 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
            !
        </div>
        <h1 class="text-xl font-extrabold text-white mb-2">Agenda não encontrada</h1>
        <p class="text-xs text-slate-400 leading-relaxed mb-6">
            O link público informado é inválido ou foi revogado pelo usuário responsável.
        </p>
        <div class="p-3 rounded-xl bg-slate-950 border border-slate-800/80 text-[11px] text-slate-500">
            Verifique o endereço digitado ou solicite um novo link ao responsável.
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

$userInitials = mb_strtoupper(mb_substr($agendaUser['full_name'] ?: $agendaUser['username'], 0, 2, 'UTF-8'));
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda &bull; <?= e($agendaUser['full_name']) ?> &bull; KanbanDoo</title>
    <link rel="icon" type="image/svg+xml" href="<?= url('/favicon.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
    <style>
        :root {
            --bg-base: #0b0f19;
            --surface-glass: rgba(15, 23, 42, 0.75);
            --border: rgba(51, 65, 85, 0.45);
        }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: var(--bg-base);
        }
        .kd-glass {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(51, 65, 85, 0.5);
        }
        .kd-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.75rem;
            transition: all 0.15s ease;
            cursor: pointer;
        }
        .kd-icon-btn {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(51, 65, 85, 0.5);
            color: #94a3b8;
            transition: all 0.15s ease;
            cursor: pointer;
        }
        .kd-icon-btn:hover {
            color: #ffffff;
            background: rgba(30, 41, 59, 0.8);
            border-color: rgba(99, 102, 241, 0.5);
        }
        
        /* Scrollbar estilizada idêntica à biblioteca do projeto */
        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(71, 85, 105, 0.45) transparent;
        }
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(71, 85, 105, 0.45);
            border-radius: 9999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(99, 102, 241, 0.6);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased selection:bg-indigo-500 selection:text-white">

    <!-- Topo Oficial da Agenda Pública -->
    <header class="sticky top-0 z-30 bg-slate-900/90 backdrop-blur-xl border-b border-slate-800/80 px-4 lg:px-8 py-3 shadow-md flex-shrink-0">
        <div class="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-400 font-extrabold text-xs flex items-center justify-center shadow-inner flex-shrink-0">
                    <?= $userInitials ?>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-base font-extrabold text-white tracking-tight">Agenda de <?= e($agendaUser['full_name']) ?></span>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 uppercase tracking-wider">
                            <i data-lucide="eye" class="w-3 h-3"></i> Somente Leitura
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 flex items-center gap-1.5">
                        <span>Pauta de atividades e compromissos</span>
                        <span class="w-1 h-1 rounded-full bg-slate-600"></span>
                        <span class="text-emerald-400 flex items-center gap-1 font-medium">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Tempo real
                        </span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span id="eventsCountBadge" class="text-xs text-slate-300 font-semibold px-2.5 py-1 rounded-xl bg-slate-900 border border-slate-800 shadow-inner">
                    0 atividades
                </span>
                <button type="button" onclick="loadAgendaEvents()" class="kd-icon-btn" title="Atualizar agora">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Conteúdo Principal - Mesma largura (w-full px-4 lg:px-8) da Agenda Editável -->
    <main id="conteudo" class="kd-calendar-main flex-1 w-full pt-4 px-4 lg:px-8 flex flex-col pb-20">

        <!-- Topo da Página de Calendário (Barra de Controle Idêntica ao calendario.php) -->
        <div class="kd-glass rounded-2xl p-3 mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border border-slate-800/80 shadow-lg flex-shrink-0">
            <div class="flex items-center gap-2">
                <button type="button" onclick="changePeriod(-1)" class="kd-icon-btn" title="Período anterior" aria-label="Período anterior">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </button>
                <button type="button" onclick="goToToday()" class="kd-btn kd-glass text-xs font-semibold px-3 py-1.5 hover:text-white" title="Ir para o dia atual">
                    Hoje
                </button>
                <button type="button" onclick="changePeriod(1)" class="kd-icon-btn" title="Próximo período" aria-label="Próximo período">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
                <h2 id="calendarTitle" class="text-base font-bold text-white tracking-wide ml-2 min-w-[14rem]">
                    Carregando calendário...
                </h2>
            </div>

            <div class="flex items-center gap-2 self-end sm:self-auto">
                <div class="inline-flex rounded-xl bg-slate-900/80 p-0.5 border border-slate-800 text-xs font-semibold">
                    <button type="button" id="btnViewMonth" onclick="setViewMode('month')" class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white transition shadow-sm font-semibold">
                        Mês
                    </button>
                    <button type="button" id="btnViewWeek" onclick="setViewMode('week')" class="px-3 py-1.5 rounded-lg text-slate-400 hover:text-white transition font-semibold">
                        Semana
                    </button>
                </div>
            </div>
        </div>

        <!-- Container do Calendário (Estrutura idêntica ao calendario.php) -->
        <div id="calendarContainer" class="flex-1 pb-8 min-h-[550px] flex flex-col">
            <!-- Tabela Mensal -->
            <div id="monthViewWrap" class="kd-glass rounded-2xl overflow-hidden border border-slate-800/80 shadow-2xl flex flex-col flex-1">
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
                <div id="calendarGrid" class="grid grid-cols-7 gap-px bg-slate-800/40 flex-1">
                    <!-- Preenchido via JavaScript -->
                </div>
            </div>

            <!-- Visão Semanal -->
            <div id="weekViewWrap" class="kd-glass rounded-2xl overflow-hidden border border-slate-800/80 shadow-2xl flex flex-col flex-1" hidden>
                <div id="weekHeader" class="grid grid-cols-7 border-b border-slate-800/80 bg-slate-950/60 text-center py-2.5">
                    <!-- Dias da semana preenchidos via JS -->
                </div>
                <div id="weekBody" class="grid grid-cols-7 gap-px bg-slate-800/40 min-h-[550px] flex-1">
                    <!-- Colunas da semana preenchidas via JS -->
                </div>
            </div>
        </div>
    </main>

    <script>
        const PUBLIC_TOKEN = <?= json_encode($token) ?>;
        const MONTH_NAMES = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];
        const WEEK_NAMES = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

        let currentDate = new Date();
        let viewMode = 'week'; // Padrão semanal para agenda rápida
        let agendaEvents = [];

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

        function changePeriod(delta) {
            if (viewMode === 'month') {
                currentDate.setMonth(currentDate.getMonth() + delta);
            } else {
                currentDate.setDate(currentDate.getDate() + (delta * 7));
            }
            renderCalendar();
            loadAgendaEvents();
        }

        function goToToday() {
            currentDate = new Date();
            renderCalendar();
            loadAgendaEvents();
        }

        async function loadAgendaEvents() {
            try {
                const y = currentDate.getFullYear();
                const m = currentDate.getMonth();
                const start = new Date(y, m - 1, 1).toISOString().split('T')[0];
                const end = new Date(y, m + 2, 0).toISOString().split('T')[0];

                const res = await fetch(`<?= url('/api/agenda-publica') ?>?token=${PUBLIC_TOKEN}&start=${start}&end=${end}`);
                const data = await res.json();
                if (data.success) {
                    agendaEvents = data.events || [];
                    const badge = document.getElementById('eventsCountBadge');
                    if (badge) {
                        badge.textContent = `${agendaEvents.length} atividade${agendaEvents.length === 1 ? '' : 's'}`;
                    }
                    renderCalendar();
                }
            } catch (e) {
                console.error('Erro ao carregar eventos da agenda pública:', e);
            }
        }

        function renderCalendar() {
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();

            if (viewMode === 'month') {
                document.getElementById('calendarTitle').textContent = `${MONTH_NAMES[month]} de ${year}`;
                renderMonthGrid(year, month);
            } else {
                const curr = new Date(currentDate);
                const dayOfWeek = curr.getDay();
                const startOfWeek = new Date(curr.setDate(curr.getDate() - dayOfWeek));
                const endOfWeek = new Date(startOfWeek);
                endOfWeek.setDate(startOfWeek.getDate() + 6);

                const startMonthName = MONTH_NAMES[startOfWeek.getMonth()];
                const endMonthName = MONTH_NAMES[endOfWeek.getMonth()];
                const titleText = (startOfWeek.getMonth() === endOfWeek.getMonth())
                    ? `Semana de ${startOfWeek.getDate()} a ${endOfWeek.getDate()} de ${startMonthName}`
                    : `Semana de ${startOfWeek.getDate()} de ${startMonthName} a ${endOfWeek.getDate()} de ${endMonthName}`;

                document.getElementById('calendarTitle').textContent = titleText;
                renderWeekGrid(startOfWeek);
            }

            if (window.lucide) lucide.createIcons();
        }

        function renderMonthGrid(year, month) {
            const grid = document.getElementById('calendarGrid');
            if (!grid) return;
            grid.innerHTML = '';

            const firstDayOfMonth = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const daysInPrevMonth = new Date(year, month, 0).getDate();

            const today = new Date();
            const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

            // Dias do mês anterior
            for (let i = firstDayOfMonth - 1; i >= 0; i--) {
                const dayNum = daysInPrevMonth - i;
                const prevDate = new Date(year, month - 1, dayNum);
                const dateStr = `${prevDate.getFullYear()}-${String(prevDate.getMonth() + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
                grid.appendChild(createMonthCell(dayNum, dateStr, true, false));
            }

            // Dias do mês atual
            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const isToday = (dateStr === todayStr);
                grid.appendChild(createMonthCell(day, dateStr, false, isToday));
            }

            // Dias do próximo mês
            const totalCells = grid.children.length;
            const remaining = (7 - (totalCells % 7)) % 7;
            for (let nextDay = 1; nextDay <= remaining; nextDay++) {
                const nextDate = new Date(year, month + 1, nextDay);
                const dateStr = `${nextDate.getFullYear()}-${String(nextDate.getMonth() + 1).padStart(2, '0')}-${String(nextDay).padStart(2, '0')}`;
                grid.appendChild(createMonthCell(nextDay, dateStr, true, false));
            }
        }

        function createMonthCell(dayNumber, dateString, isOtherMonth, isToday) {
            const cell = document.createElement('div');
            cell.className = `p-2 min-h-[120px] bg-slate-950/40 hover:bg-slate-900/40 transition flex flex-col justify-between group ${isOtherMonth ? 'opacity-35' : ''} ${isToday ? 'ring-1 ring-inset ring-indigo-500/60 bg-indigo-500/5' : ''}`;

            const topRow = document.createElement('div');
            topRow.className = 'flex items-center justify-between mb-1.5';

            const numSpan = document.createElement('span');
            numSpan.className = `text-xs font-bold ${isToday ? 'w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-600/30' : 'text-slate-300'}`;
            numSpan.textContent = dayNumber;
            topRow.appendChild(numSpan);
            cell.appendChild(topRow);

            const eventsList = document.createElement('div');
            eventsList.className = 'space-y-1 flex-1 overflow-y-auto max-h-[140px] pr-0.5';

            const dayEvents = agendaEvents.filter(ev => ev.due_date === dateString);
            dayEvents.forEach(ev => {
                const evCard = document.createElement('div');
                evCard.className = `text-[11px] p-1.5 rounded-lg border flex items-center gap-1.5 leading-tight select-none shadow-sm ${ev.is_completed ? 'line-through opacity-50 bg-slate-900/50 border-slate-800 text-slate-400' : 'bg-slate-900/90 border-slate-700/60 text-slate-200'}`;

                let timeBadge = '';
                if (ev.due_time) {
                    timeBadge = `<span class="px-1 py-0.2 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 flex-shrink-0">${ev.due_time}</span>`;
                }

                evCard.innerHTML = `
                    ${timeBadge}
                    <span class="truncate font-medium flex-1">${escapeHtml(ev.title)}</span>
                `;
                eventsList.appendChild(evCard);
            });

            cell.appendChild(eventsList);
            return cell;
        }

        function renderWeekGrid(startOfWeek) {
            const header = document.getElementById('weekHeader');
            const body = document.getElementById('weekBody');
            if (!header || !body) return;
            header.innerHTML = '';
            body.innerHTML = '';

            const today = new Date();
            const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

            for (let i = 0; i < 7; i++) {
                const dayDate = new Date(startOfWeek);
                dayDate.setDate(startOfWeek.getDate() + i);
                const d = dayDate.getDate();
                const dateStr = `${dayDate.getFullYear()}-${String(dayDate.getMonth() + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                const isToday = (dateStr === todayStr);

                // Cabeçalho do dia
                const colHeader = document.createElement('div');
                colHeader.className = `text-xs py-1 ${isToday ? 'text-indigo-400 font-bold' : 'text-slate-400 font-semibold'}`;
                colHeader.innerHTML = `
                    <div class="uppercase tracking-wider text-[11px]">${WEEK_NAMES[i]}</div>
                    <div class="text-sm font-bold ${isToday ? 'w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center mx-auto mt-0.5 shadow-md shadow-indigo-600/30' : 'text-white mt-0.5'}">${d}</div>
                `;
                header.appendChild(colHeader);

                // Coluna de Eventos do dia (Idêntico ao calendario.php)
                const colBody = document.createElement('div');
                colBody.className = `p-2 min-h-[500px] bg-slate-950/40 flex flex-col space-y-2 overflow-y-auto max-h-[750px] ${isToday ? 'bg-indigo-500/5 ring-1 ring-inset ring-indigo-500/30' : ''}`;

                const dayEvents = agendaEvents.filter(ev => ev.due_date === dateStr);
                dayEvents.forEach(ev => {
                    const evCard = document.createElement('div');
                    evCard.className = `p-2.5 rounded-xl border bg-slate-900/90 border-slate-700/60 text-xs text-slate-200 transition space-y-1 shadow-sm select-none ${ev.is_completed ? 'line-through opacity-50' : 'hover:border-indigo-500/50'}`;

                    evCard.innerHTML = `
                        <div class="flex items-center justify-between gap-1">
                            ${ev.due_time ? `<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">${ev.due_time}</span>` : '<span></span>'}
                            <span class="text-[10px] text-slate-400 truncate">${escapeHtml(ev.client_company || '')}</span>
                        </div>
                        <p class="font-semibold text-white leading-snug">${escapeHtml(ev.title)}</p>
                    `;
                    colBody.appendChild(evCard);
                });

                body.appendChild(colBody);
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        document.addEventListener('DOMContentLoaded', () => {
            setViewMode('week');
            loadAgendaEvents();
        });
    </script>
</body>
</html>
