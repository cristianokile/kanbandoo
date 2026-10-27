<?php
/**
 * KanbanDoo - Visualização Pública da Agenda / Calendário
 *
 * Link público, somente leitura, sem permissão de abrir modais nem alterar tarefas.
 * Exibe a pauta de atividades e compromissos do usuário.
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
        <div class="w-16 h-16 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <h1 class="text-xl font-bold text-white mb-2">Agenda não encontrada</h1>
        <p class="text-xs text-slate-400 leading-relaxed mb-6">O link que você tentou acessar é inválido, foi desativado ou a chave pública informada não existe mais.</p>
        <a href="<?= url('/entrar') ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition shadow-lg shadow-indigo-600/30">
            Acessar o KanbanDoo
        </a>
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
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .kd-glass {
            background: rgba(15, 23, 42, 0.82);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(51, 65, 85, 0.6);
        }
        .cal-cell {
            min-height: 110px;
            background: rgba(15, 23, 42, 0.5);
            transition: all 0.15s ease;
        }
        .cal-cell:hover {
            background: rgba(30, 41, 59, 0.4);
        }
        .cal-cell.is-other-month {
            opacity: 0.35;
            background: rgba(15, 23, 42, 0.2);
        }
        .cal-cell.is-today {
            background: rgba(99, 102, 241, 0.08);
            border: 1px solid rgba(99, 102, 241, 0.4);
        }
        .cal-ev-card {
            border-left: 3px solid #6366f1;
            background: rgba(30, 41, 59, 0.85);
            border-radius: 6px;
            padding: 3px 6px;
            font-size: 11px;
            line-height: 1.25;
            cursor: default;
            user-select: none;
            transition: transform 0.15s ease;
        }
        .cal-ev-card:hover {
            transform: translateY(-1px);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased selection:bg-indigo-500 selection:text-white">

    <!-- Topo Fixo da Agenda Pública -->
    <header class="sticky top-0 z-30 bg-slate-900/90 backdrop-blur-xl border-b border-slate-800/80 px-4 lg:px-8 py-3.5 shadow-md">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600/15 border border-indigo-500/30 text-indigo-400 font-extrabold text-sm flex items-center justify-center shadow-inner flex-shrink-0">
                    <?= $userInitials ?>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-base sm:text-lg font-extrabold text-white tracking-tight flex items-center gap-2">
                            <span>Agenda de <?= e($agendaUser['full_name']) ?></span>
                        </h1>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 uppercase tracking-wider">
                            <i data-lucide="eye" class="w-3 h-3"></i> Somente Leitura
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                        <span>Pauta de atividades e horários agendados</span>
                        <span class="w-1 h-1 rounded-full bg-slate-600"></span>
                        <span id="liveIndicator" class="text-emerald-400 flex items-center gap-1 font-medium">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Tempo real
                        </span>
                    </p>
                </div>
            </div>

            <!-- Controles do Calendário -->
            <div class="flex items-center gap-2 flex-wrap">
                <div class="inline-flex items-center p-1 rounded-xl bg-slate-900 border border-slate-800 shadow-inner">
                    <button type="button" onclick="changeMonth(-1)" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" title="Mês anterior">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </button>
                    <button type="button" onclick="goToToday()" class="px-2.5 py-1 text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800 rounded-lg transition">
                        Hoje
                    </button>
                    <button type="button" onclick="changeMonth(1)" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" title="Próximo mês">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                </div>

                <div class="inline-flex items-center p-1 rounded-xl bg-slate-900 border border-slate-800">
                    <button type="button" id="btnViewMonth" onclick="setViewMode('month')" class="px-3 py-1 rounded-lg text-xs font-bold bg-indigo-600 text-white shadow-sm transition">
                        Mês
                    </button>
                    <button type="button" id="btnViewWeek" onclick="setViewMode('week')" class="px-3 py-1 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition">
                        Semana
                    </button>
                </div>

                <button type="button" onclick="loadAgendaEvents()" class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-white hover:bg-slate-800 transition" title="Recarregar agenda">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Conteúdo Principal -->
    <main class="max-w-7xl w-full mx-auto p-4 lg:p-8 flex-1 flex flex-col">
        <!-- Título do Mês Atual -->
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800/80">
            <h2 id="calendarMonthTitle" class="text-xl sm:text-2xl font-black text-white tracking-tight capitalize flex items-center gap-2">
                <i data-lucide="calendar" class="w-6 h-6 text-indigo-400"></i>
                <span>Carregando...</span>
            </h2>
            <div id="eventsCountBadge" class="text-xs text-slate-400 font-semibold bg-slate-900 px-3 py-1 rounded-full border border-slate-800">
                0 atividades
            </div>
        </div>

        <!-- Grade do Calendário -->
        <div class="kd-glass rounded-2xl overflow-hidden shadow-2xl flex-1 flex flex-col min-h-[550px]">
            <!-- Cabeçalho dos Dias da Semana -->
            <div class="grid grid-cols-7 border-b border-slate-800 bg-slate-900/90 text-center text-xs font-bold uppercase tracking-wider text-slate-400 py-3">
                <div class="text-rose-400">Dom</div>
                <div>Seg</div>
                <div>Ter</div>
                <div>Qua</div>
                <div>Qui</div>
                <div>Sex</div>
                <div class="text-indigo-400">Sáb</div>
            </div>

            <!-- Grade de Células -->
            <div id="calendarGrid" class="grid grid-cols-7 gap-px bg-slate-800/60 flex-1">
                <!-- Preenchido via JavaScript -->
            </div>
        </div>

        <!-- Rodapé explicativo -->
        <div class="mt-4 pt-4 border-t border-slate-800/60 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-slate-500">
            <div class="flex items-center gap-2">
                <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i>
                <span>Visualização protegida &bull; Apenas leitura de demandas públicas e compromissos.</span>
            </div>
            <div>
                KanbanDoo &bull; Produtividade e Gestão Ágil
            </div>
        </div>
    </main>

    <script>
        const AGENDA_TOKEN = <?= json_encode($token) ?>;
        const MONTH_NAMES = [
            'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
            'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
        ];

        let currentDate = new Date();
        let viewMode = 'month';
        let agendaEvents = [];

        function changeMonth(delta) {
            if (viewMode === 'month') {
                currentDate.setMonth(currentDate.getMonth() + delta);
            } else {
                currentDate.setDate(currentDate.getDate() + (delta * 7));
            }
            loadAgendaEvents();
        }

        function goToToday() {
            currentDate = new Date();
            loadAgendaEvents();
        }

        function setViewMode(mode) {
            viewMode = mode;
            const btnM = document.getElementById('btnViewMonth');
            const btnW = document.getElementById('btnViewWeek');
            if (mode === 'month') {
                btnM.className = 'px-3 py-1 rounded-lg text-xs font-bold bg-indigo-600 text-white shadow-sm transition';
                btnW.className = 'px-3 py-1 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition';
            } else {
                btnW.className = 'px-3 py-1 rounded-lg text-xs font-bold bg-indigo-600 text-white shadow-sm transition';
                btnM.className = 'px-3 py-1 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition';
            }
            renderCalendar();
        }

        async function loadAgendaEvents() {
            try {
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();

                let startStr, endStr;
                if (viewMode === 'month') {
                    const firstDay = new Date(year, month - 1, 20);
                    const lastDay = new Date(year, month + 2, 10);
                    startStr = firstDay.toISOString().split('T')[0];
                    endStr = lastDay.toISOString().split('T')[0];
                } else {
                    const startOfWeek = new Date(currentDate);
                    startOfWeek.setDate(currentDate.getDate() - currentDate.getDay());
                    const endOfWeek = new Date(startOfWeek);
                    endOfWeek.setDate(startOfWeek.getDate() + 6);
                    startStr = startOfWeek.toISOString().split('T')[0];
                    endStr = endOfWeek.toISOString().split('T')[0];
                }

                const res = await fetch(`<?= url('/api/agenda-publica') ?>?token=${encodeURIComponent(AGENDA_TOKEN)}&start=${startStr}&end=${endStr}`);
                const data = await res.json();
                if (data.success) {
                    agendaEvents = data.events || [];
                    document.getElementById('eventsCountBadge').textContent = `${agendaEvents.length} atividade${agendaEvents.length === 1 ? '' : 's'}`;
                    renderCalendar();
                }
            } catch (e) {
                console.error('Erro ao carregar eventos da agenda pública:', e);
            }
        }

        function renderCalendar() {
            const grid = document.getElementById('calendarGrid');
            const title = document.getElementById('calendarMonthTitle');
            if (!grid) return;

            grid.innerHTML = '';

            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();
            const today = new Date();
            const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

            if (viewMode === 'month') {
                title.innerHTML = `<i data-lucide="calendar" class="w-6 h-6 text-indigo-400 inline"></i> <span>${MONTH_NAMES[month]} de ${year}</span>`;

                const firstDayOfMonth = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const daysInPrevMonth = new Date(year, month, 0).getDate();

                // Dias do mês anterior
                for (let i = firstDayOfMonth - 1; i >= 0; i--) {
                    const d = daysInPrevMonth - i;
                    const prevDate = new Date(year, month - 1, d);
                    const dateStr = `${prevDate.getFullYear()}-${String(prevDate.getMonth() + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                    grid.appendChild(createDayCell(d, dateStr, true, false));
                }

                // Dias do mês atual
                for (let d = 1; d <= daysInMonth; d++) {
                    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                    const isToday = (dateStr === todayStr);
                    grid.appendChild(createDayCell(d, dateStr, false, isToday));
                }

                // Dias do próximo mês
                const totalCells = firstDayOfMonth + daysInMonth;
                const nextDays = (totalCells % 7 === 0) ? 0 : 7 - (totalCells % 7);
                for (let d = 1; d <= nextDays; d++) {
                    const nextDate = new Date(year, month + 1, d);
                    const dateStr = `${nextDate.getFullYear()}-${String(nextDate.getMonth() + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                    grid.appendChild(createDayCell(d, dateStr, true, false));
                }
            } else {
                // Modo Semana
                const startOfWeek = new Date(currentDate);
                startOfWeek.setDate(currentDate.getDate() - currentDate.getDay());
                const endOfWeek = new Date(startOfWeek);
                endOfWeek.setDate(startOfWeek.getDate() + 6);

                title.innerHTML = `<i data-lucide="calendar" class="w-6 h-6 text-indigo-400 inline"></i> <span>Semana de ${startOfWeek.getDate()} de ${MONTH_NAMES[startOfWeek.getMonth()]} a ${endOfWeek.getDate()} de ${MONTH_NAMES[endOfWeek.getMonth()]}</span>`;

                for (let i = 0; i < 7; i++) {
                    const dayDate = new Date(startOfWeek);
                    dayDate.setDate(startOfWeek.getDate() + i);
                    const d = dayDate.getDate();
                    const dateStr = `${dayDate.getFullYear()}-${String(dayDate.getMonth() + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                    const isToday = (dateStr === todayStr);
                    grid.appendChild(createDayCell(d, dateStr, false, isToday));
                }
            }

            if (window.lucide) lucide.createIcons();
        }

        function createDayCell(dayNumber, dateString, isOtherMonth, isToday) {
            const cell = document.createElement('div');
            cell.className = `cal-cell p-2 flex flex-col justify-between ${isOtherMonth ? 'is-other-month' : ''} ${isToday ? 'is-today' : ''}`;

            const topRow = document.createElement('div');
            topRow.className = 'flex items-center justify-between mb-1.5';

            const numSpan = document.createElement('span');
            numSpan.className = `text-xs font-bold ${isToday ? 'w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-md' : 'text-slate-400'}`;
            numSpan.textContent = dayNumber;
            topRow.appendChild(numSpan);
            cell.appendChild(topRow);

            // Lista de eventos deste dia
            const eventsList = document.createElement('div');
            eventsList.className = 'space-y-1 overflow-y-auto max-h-[130px] pr-0.5 custom-scrollbar';

            const dayEvents = agendaEvents.filter(ev => ev.due_date === dateString);
            dayEvents.forEach(ev => {
                const evCard = document.createElement('div');
                const stageColor = ev.stage_color || '#6366f1';
                evCard.className = 'cal-ev-card';
                evCard.style.borderLeftColor = stageColor;

                let timeBadge = '';
                if (ev.due_time) {
                    timeBadge = `<span class="px-1 py-0.2 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 mr-1">${ev.due_time}</span>`;
                }

                let clientHtml = '';
                if (ev.client_company) {
                    clientHtml = `<div class="text-[9px] text-indigo-300 font-semibold truncate">${escapeHtml(ev.client_company)}</div>`;
                }

                evCard.innerHTML = `
                    <div class="flex items-center justify-between gap-1">
                        <span class="font-bold text-white truncate">${timeBadge}${escapeHtml(ev.title)}</span>
                    </div>
                    ${clientHtml}
                `;

                eventsList.appendChild(evCard);
            });

            cell.appendChild(eventsList);
            return cell;
        }

        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', () => {
            loadAgendaEvents();
            // Auto reload a cada 5 minutos
            setInterval(loadAgendaEvents, 300000);
        });
    </script>
</body>
</html>
