<?php
/**
 * KanbanDoo - Dashboard de Atividades, Controle de Horas e Relatórios
 */
require_once dirname(__DIR__) . '/bootstrap.php';

require_login();

$pdo = get_pdo();
$user = current_user();

// Carregar listas para os filtros
$clientsList = $pdo->query("SELECT id, company_name FROM clients ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$usersList = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

view_header('Dashboard de Atividades');
?>

<div class="w-full px-4 lg:px-8 py-6 space-y-6 max-w-[1600px] mx-auto" id="dashboardApp">

    <!-- Header do Dashboard -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800/80 pb-5">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 shadow-inner">
                    <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                </span>
                <h1 class="text-2xl font-bold text-white tracking-tight">Dashboard de Atividades</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Controle de Horas
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1" id="dashPeriodSub">
                Visão consolidada de produtividade, gargalos e horas dedicadas por empresa.
            </p>
        </div>

        <!-- Botões de Ação e Exportação -->
        <div class="flex items-center flex-wrap gap-2.5">
            <button type="button" onclick="KD_DASHBOARD.exportCSV()"
                    class="kd-btn kd-btn--ghost text-xs flex items-center gap-2 bg-slate-900/60 hover:bg-slate-800 border border-slate-700/60 text-slate-200 shadow-sm"
                    title="Baixar planilha para Excel/Google Sheets">
                <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-400"></i>
                <span>Exportar CSV</span>
            </button>

            <button type="button" onclick="window.print()"
                    class="kd-btn kd-btn--ghost text-xs flex items-center gap-2 bg-slate-900/60 hover:bg-slate-800 border border-slate-700/60 text-slate-200 shadow-sm"
                    title="Imprimir ou Salvar como PDF para o cliente">
                <i data-lucide="printer" class="w-4 h-4 text-indigo-400"></i>
                <span>Relatório PDF</span>
            </button>

            <button type="button" onclick="KD_DASHBOARD.loadData()"
                    class="kd-btn kd-btn--ghost p-2 text-slate-400 hover:text-white border border-slate-700/60 rounded-xl"
                    title="Atualizar dados">
                <i data-lucide="refresh-cw" class="w-4 h-4" id="dashRefreshIcon"></i>
            </button>
        </div>
    </div>

    <!-- Barra de Filtros Integrada -->
    <div class="kd-glass rounded-2xl p-4 border border-slate-800/80 shadow-lg space-y-4">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
            
            <!-- Período Rápido em Pílulas -->
            <div class="flex items-center flex-wrap gap-1.5 bg-slate-950/40 p-1.5 rounded-xl border border-slate-800/60" id="periodTabs">
                <button type="button" onclick="KD_DASHBOARD.setPeriod('today')" data-period="today"
                        class="period-tab px-3 py-1.5 rounded-lg text-xs font-medium text-slate-300 hover:text-white transition">Hoje</button>
                <button type="button" onclick="KD_DASHBOARD.setPeriod('week')" data-period="week"
                        class="period-tab px-3 py-1.5 rounded-lg text-xs font-medium text-slate-300 hover:text-white transition">Esta Semana</button>
                <button type="button" onclick="KD_DASHBOARD.setPeriod('biweek')" data-period="biweek"
                        class="period-tab px-3 py-1.5 rounded-lg text-xs font-medium text-slate-300 hover:text-white transition">Esta Quinzena</button>
                <button type="button" onclick="KD_DASHBOARD.setPeriod('month')" data-period="month"
                        class="period-tab active px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 text-white shadow transition">Este Mês</button>
                <button type="button" onclick="KD_DASHBOARD.setPeriod('last_month')" data-period="last_month"
                        class="period-tab px-3 py-1.5 rounded-lg text-xs font-medium text-slate-300 hover:text-white transition">Mês Anterior</button>
                <button type="button" onclick="KD_DASHBOARD.setPeriod('all')" data-period="all"
                        class="period-tab px-3 py-1.5 rounded-lg text-xs font-medium text-slate-300 hover:text-white transition">Todo o Período</button>
                <button type="button" onclick="KD_DASHBOARD.setPeriod('custom')" data-period="custom"
                        class="period-tab px-3 py-1.5 rounded-lg text-xs font-medium text-slate-300 hover:text-white transition">Personalizado</button>
            </div>

            <!-- Filtros Dropdowns (Cliente, Usuário, Status) -->
            <div class="flex items-center flex-wrap gap-2.5">
                <!-- Filtro de Cliente -->
                <div class="flex items-center gap-1.5 bg-slate-900/60 border border-slate-700/60 rounded-xl px-2.5 py-1.5">
                    <i data-lucide="building-2" class="w-3.5 h-3.5 text-slate-400"></i>
                    <select id="filterClient" onchange="KD_DASHBOARD.loadData()"
                            class="bg-transparent text-xs text-slate-200 focus:outline-none cursor-pointer pr-2">
                        <option value="" class="bg-slate-900 text-slate-200">Todos os Clientes</option>
                        <?php foreach ($clientsList as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" class="bg-slate-900 text-slate-200"><?= e($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtro de Usuário -->
                <div class="flex items-center gap-1.5 bg-slate-900/60 border border-slate-700/60 rounded-xl px-2.5 py-1.5">
                    <i data-lucide="users" class="w-3.5 h-3.5 text-slate-400"></i>
                    <select id="filterUser" onchange="KD_DASHBOARD.loadData()"
                            class="bg-transparent text-xs text-slate-200 focus:outline-none cursor-pointer pr-2">
                        <option value="" class="bg-slate-900 text-slate-200">Toda a Equipe</option>
                        <?php foreach ($usersList as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" class="bg-slate-900 text-slate-200"><?= e($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtro de Status -->
                <div class="flex items-center gap-1.5 bg-slate-900/60 border border-slate-700/60 rounded-xl px-2.5 py-1.5">
                    <i data-lucide="check-circle-2" class="w-3.5 h-3.5 text-slate-400"></i>
                    <select id="filterStatus" onchange="KD_DASHBOARD.loadData()"
                            class="bg-transparent text-xs text-slate-200 focus:outline-none cursor-pointer pr-2">
                        <option value="all" class="bg-slate-900 text-slate-200">Todos os Status</option>
                        <option value="completed" class="bg-slate-900 text-slate-200">Concluídas</option>
                        <option value="pending" class="bg-slate-900 text-slate-200">Em Andamento</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Seletor de Período Customizado (Oculto por padrão) -->
        <div id="customDateRow" class="hidden pt-3 border-t border-slate-800/80 flex items-center flex-wrap gap-3">
            <span class="text-xs text-slate-400 flex items-center gap-1">
                <i data-lucide="calendar" class="w-3.5 h-3.5 text-indigo-400"></i> Intervalo de datas:
            </span>
            <input type="date" id="customStartDate" class="bg-slate-900/90 border border-slate-700 rounded-lg px-2.5 py-1 text-xs text-slate-200">
            <span class="text-xs text-slate-500">até</span>
            <input type="date" id="customEndDate" class="bg-slate-900/90 border border-slate-700 rounded-lg px-2.5 py-1 text-xs text-slate-200">
            <button type="button" onclick="KD_DASHBOARD.applyCustomDates()" class="kd-btn kd-btn--primary px-3 py-1 text-xs">
                Filtrar
            </button>
        </div>
    </div>

    <!-- Cabeçalho de Relatório Oficial para Impressão (Aparece apenas ao imprimir) -->
    <div class="print-only hidden p-6 border-b-2 border-slate-400 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-black tracking-tight">KANBANDOO &bull; RELATÓRIO DE ATIVIDADES E TEMPO</h1>
                <p class="text-sm text-gray-700 mt-1" id="printPeriodText"></p>
                <p class="text-xs text-gray-500" id="printClientText"></p>
            </div>
            <div class="text-right text-xs text-gray-500">
                <p>Emitido em: <?= date('d/m/Y H:i:s') ?></p>
                <p>Usuário: <?= e($user['full_name']) ?></p>
            </div>
        </div>
    </div>

    <!-- Grid de 6 KPIs Principais -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3.5">
        
        <!-- KPI 1: Horas Totais -->
        <div class="kd-glass rounded-2xl p-4 border border-indigo-500/20 shadow-md relative overflow-hidden group">
            <div class="absolute -right-3 -bottom-3 w-16 h-16 rounded-full bg-indigo-500/10 blur-xl pointer-events-none"></div>
            <div class="flex items-center justify-between text-slate-400 mb-1.5">
                <span class="text-[11px] font-semibold uppercase tracking-wider">Tempo Total</span>
                <i data-lucide="clock" class="w-4 h-4 text-indigo-400"></i>
            </div>
            <div class="text-xl font-bold text-white tracking-tight" id="kpiTotalTime">0h 00m</div>
            <div class="text-[10px] text-indigo-300/80 mt-1 flex items-center justify-between">
                <span id="kpiDecimalTime">0.00h decimais</span>
                <span id="kpiActiveTimerBadge" class="hidden text-emerald-400 font-semibold flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Timer
                </span>
            </div>
        </div>

        <!-- KPI 2: Tarefas Concluídas -->
        <div class="kd-glass rounded-2xl p-4 border border-emerald-500/20 shadow-md relative overflow-hidden group">
            <div class="absolute -right-3 -bottom-3 w-16 h-16 rounded-full bg-emerald-500/10 blur-xl pointer-events-none"></div>
            <div class="flex items-center justify-between text-slate-400 mb-1.5">
                <span class="text-[11px] font-semibold uppercase tracking-wider">Concluídas</span>
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400"></i>
            </div>
            <div class="text-xl font-bold text-white tracking-tight" id="kpiCompletedTasks">0 / 0</div>
            <div class="text-[10px] text-emerald-300/80 mt-1 flex items-center justify-between">
                <span id="kpiCompletionRate">0% concluídas</span>
                <span id="kpiPendingTasks" class="text-slate-400">0 pendentes</span>
            </div>
        </div>

        <!-- KPI 3: Entrega no Prazo (On-Time) -->
        <div class="kd-glass rounded-2xl p-4 border border-cyan-500/20 shadow-md relative overflow-hidden group">
            <div class="absolute -right-3 -bottom-3 w-16 h-16 rounded-full bg-cyan-500/10 blur-xl pointer-events-none"></div>
            <div class="flex items-center justify-between text-slate-400 mb-1.5">
                <span class="text-[11px] font-semibold uppercase tracking-wider">No Prazo</span>
                <i data-lucide="calendar-check" class="w-4 h-4 text-cyan-400"></i>
            </div>
            <div class="text-xl font-bold text-white tracking-tight" id="kpiOnTimeRate">100%</div>
            <div class="text-[10px] text-cyan-300/80 mt-1">
                Taxa de cumprimento de prazos
            </div>
        </div>

        <!-- KPI 4: Tempo Médio por Tarefa -->
        <div class="kd-glass rounded-2xl p-4 border border-amber-500/20 shadow-md relative overflow-hidden group">
            <div class="absolute -right-3 -bottom-3 w-16 h-16 rounded-full bg-amber-500/10 blur-xl pointer-events-none"></div>
            <div class="flex items-center justify-between text-slate-400 mb-1.5">
                <span class="text-[11px] font-semibold uppercase tracking-wider">Tempo Médio</span>
                <i data-lucide="timer" class="w-4 h-4 text-amber-400"></i>
            </div>
            <div class="text-xl font-bold text-white tracking-tight" id="kpiAvgTime">0m</div>
            <div class="text-[10px] text-amber-300/80 mt-1">
                Dedicado por atividade
            </div>
        </div>

        <!-- KPI 5: Estimado vs Real -->
        <div class="kd-glass rounded-2xl p-4 border border-purple-500/20 shadow-md relative overflow-hidden group">
            <div class="absolute -right-3 -bottom-3 w-16 h-16 rounded-full bg-purple-500/10 blur-xl pointer-events-none"></div>
            <div class="flex items-center justify-between text-slate-400 mb-1.5">
                <span class="text-[11px] font-semibold uppercase tracking-wider">Orçado vs Real</span>
                <i data-lucide="scale" class="w-4 h-4 text-purple-400"></i>
            </div>
            <div class="text-xl font-bold text-white tracking-tight" id="kpiEstimateDiff">0h 00m</div>
            <div class="text-[10px] text-purple-300/80 mt-1 truncate" id="kpiEstimateSub">
                Estimado: 0h 00m
            </div>
        </div>

        <!-- KPI 6: Clientes Ativos -->
        <div class="kd-glass rounded-2xl p-4 border border-blue-500/20 shadow-md relative overflow-hidden group">
            <div class="absolute -right-3 -bottom-3 w-16 h-16 rounded-full bg-blue-500/10 blur-xl pointer-events-none"></div>
            <div class="flex items-center justify-between text-slate-400 mb-1.5">
                <span class="text-[11px] font-semibold uppercase tracking-wider">Clientes</span>
                <i data-lucide="building" class="w-4 h-4 text-blue-400"></i>
            </div>
            <div class="text-xl font-bold text-white tracking-tight" id="kpiActiveClients">0</div>
            <div class="text-[10px] text-blue-300/80 mt-1">
                Empresas atendidas no período
            </div>
        </div>
    </div>

    <!-- Bloco Central em Duas Colunas: Horas por Cliente & Tarefas Mais Demoradas -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Painel: Horas Somadas por Empresa / Cliente (7 Colunas) -->
        <div class="lg:col-span-7 kd-glass rounded-2xl p-5 border border-slate-800/80 shadow-lg flex flex-col">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800/80">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                        <i data-lucide="pie-chart" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-white">Horas Somadas por Empresa</h2>
                </div>
                <span class="text-xs text-slate-400" id="clientCountBadge">0 empresas</span>
            </div>

            <!-- Lista de Empresas e Barras de Progresso -->
            <div class="space-y-3.5 flex-1 overflow-y-auto max-h-[460px] pr-1" id="dashClientList">
                <div class="text-center py-10 text-slate-500 text-xs">Carregando dados...</div>
            </div>
        </div>

        <!-- Painel: Tarefas Mais Demoradas / Gargalos (5 Colunas) -->
        <div class="lg:col-span-5 kd-glass rounded-2xl p-5 border border-slate-800/80 shadow-lg flex flex-col">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800/80">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400">
                        <i data-lucide="flame" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-white">Tarefas Mais Demoradas</h2>
                </div>
                <span class="text-xs text-slate-400">Top Gargalos</span>
            </div>

            <!-- Lista de Tarefas Longas -->
            <div class="space-y-3 flex-1 overflow-y-auto max-h-[460px] pr-1" id="dashLongestTasksList">
                <div class="text-center py-10 text-slate-500 text-xs">Carregando tarefas...</div>
            </div>
        </div>
    </div>

    <!-- Produtividade da Equipe & Distribuição por Prioridade -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Carga de Trabalho por Usuário / Colaborador (8 Colunas) -->
        <div class="lg:col-span-8 kd-glass rounded-2xl p-5 border border-slate-800/80 shadow-lg">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800/80">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                        <i data-lucide="users" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-white">Tempo Dedicado pela Equipe</h2>
                </div>
                <span class="text-xs text-slate-400">Carga Horária</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3" id="dashTeamList">
                <div class="text-center py-8 text-slate-500 text-xs col-span-full">Carregando equipe...</div>
            </div>
        </div>

        <!-- Distribuição por Prioridade (4 Colunas) -->
        <div class="lg:col-span-4 kd-glass rounded-2xl p-5 border border-slate-800/80 shadow-lg">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-800/80">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    </span>
                    <h2 class="text-sm font-bold text-white">Tempo por Prioridade</h2>
                </div>
            </div>

            <div class="space-y-3" id="dashPriorityList">
                <div class="text-center py-8 text-slate-500 text-xs">Carregando prioridades...</div>
            </div>
        </div>
    </div>

    <!-- Tabela Detalhada de Atividades / Extrato de Comprovação de Horas -->
    <div class="kd-glass rounded-2xl p-5 border border-slate-800/80 shadow-lg space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-800/80">
            <div>
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <i data-lucide="list-checks" class="w-4 h-4 text-indigo-400"></i>
                    Extrato de Atividades Realizadas & Comprovação de Horas
                </h2>
                <p class="text-xs text-slate-400 mt-0.5" id="dashTableSubtitle">Lista de demandas para verificação e prestação de contas</p>
            </div>

            <!-- Busca Rápida na Tabela -->
            <div class="relative w-full sm:w-72">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5 pointer-events-none"></i>
                <input type="text" id="dashTableSearch" oninput="KD_DASHBOARD.filterTable()"
                       placeholder="Filtrar por tarefa, cliente, responsável..."
                       class="w-full pl-9 pr-3 py-1.5 bg-slate-900/90 border border-slate-700/70 rounded-xl text-xs text-slate-200 placeholder-slate-400 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <!-- Tabela Responsiva -->
        <div class="overflow-x-auto rounded-xl border border-slate-800/60">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-900/80 border-b border-slate-800 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="py-2.5 px-3">ID</th>
                        <th class="py-2.5 px-3">Tarefa / Demanda</th>
                        <th class="py-2.5 px-3">Cliente</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-3">Prioridade</th>
                        <th class="py-2.5 px-3">Responsáveis</th>
                        <th class="py-2.5 px-3">Prazo</th>
                        <th class="py-2.5 px-3">Estimado</th>
                        <th class="py-2.5 px-3 text-right">Tempo Real</th>
                    </tr>
                </thead>
                <tbody id="dashTableBody" class="divide-y divide-slate-800/50 text-slate-300">
                    <tr>
                        <td colspan="9" class="py-8 text-center text-slate-500">Carregando atividades...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Rodapé da Tabela com Totais -->
        <div class="flex flex-col sm:flex-row items-center justify-between text-xs text-slate-400 pt-2 gap-2">
            <span id="dashTableSummary">Exibindo 0 atividades</span>
            <span id="dashTableTimeSum" class="font-semibold text-slate-200">Total: 0h 00m</span>
        </div>
    </div>
</div>

<style>
/* Estilos Customizados do Dashboard */
.period-tab.active {
    background-color: rgb(79 70 229) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.25);
}

/* Regras de Impressão de Alta Qualidade para Relatórios PDF */
@media print {
    body {
        background: #ffffff !important;
        color: #111827 !important;
    }
    .kd-header, #periodTabs, #filterClient, #filterUser, #filterStatus, 
    #customDateRow, .kd-btn, #dashTableSearch, #themeToggleBtn, #glassToggleBtn,
    #newTaskDropdownWrap, .kd-toast-stack, #userMenuDropdown {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
    .kd-glass {
        background: #ffffff !important;
        border: 1px solid #e5e7eb !important;
        box-shadow: none !important;
        color: #111827 !important;
    }
    .text-white {
        color: #111827 !important;
    }
    .text-slate-400, .text-slate-300, .text-slate-500 {
        color: #4b5563 !important;
    }
    table {
        border: 1px solid #d1d5db !important;
    }
    th {
        background-color: #f3f4f6 !important;
        color: #111827 !important;
        border-bottom: 2px solid #9ca3af !important;
    }
    td {
        border-bottom: 1px solid #e5e7eb !important;
        color: #1f2937 !important;
    }
}
</style>

<script>
/**
 * Módulo JavaScript do Dashboard KanbanDoo
 */
window.KD_DASHBOARD = (function() {
    let currentPeriod = 'month';
    let currentData = null;
    let allTasks = [];

    function getApiUrl() {
        if (typeof KD !== 'undefined' && typeof KD.url === 'function') {
            return KD.url('/api/dashboard');
        }
        return '<?= url('/api/dashboard') ?>';
    }

    async function loadData() {
        const refreshIcon = document.getElementById('dashRefreshIcon');
        if (refreshIcon) refreshIcon.classList.add('animate-spin');

        const clientVal = document.getElementById('filterClient').value;
        const userVal = document.getElementById('filterUser').value;
        const statusVal = document.getElementById('filterStatus').value;
        const startDate = document.getElementById('customStartDate').value;
        const endDate = document.getElementById('customEndDate').value;

        const params = new URLSearchParams({
            period: currentPeriod,
            client_id: clientVal,
            user_id: userVal,
            status: statusVal
        });

        if (currentPeriod === 'custom' && startDate && endDate) {
            params.set('start_date', startDate);
            params.set('end_date', endDate);
        }

        try {
            const resp = await fetch(getApiUrl() + '?' + params.toString());
            const json = await resp.json();

            if (json.success) {
                currentData = json;
                allTasks = json.tasks || [];
                renderDashboard(json);
            } else {
                if (typeof KD !== 'undefined' && KD.toast) {
                    KD.toast('Erro ao carregar dados do dashboard: ' + (json.error || 'Desconhecido'), 'danger');
                }
            }
        } catch (err) {
            console.error('Erro na requisição do dashboard:', err);
            if (typeof KD !== 'undefined' && KD.toast) {
                KD.toast('Falha ao comunicar com o servidor.', 'danger');
            }
        } finally {
            if (refreshIcon) refreshIcon.classList.remove('animate-spin');
        }
    }

    function renderDashboard(data) {
        const s = data.summary;

        // Subtítulo do período
        const sub = document.getElementById('dashPeriodSub');
        if (sub) sub.textContent = 'Período: ' + s.period_label + ' • ' + s.total_tasks + ' atividades registradas';

        // Cabeçalho de Impressão
        const printP = document.getElementById('printPeriodText');
        if (printP) printP.textContent = 'Período: ' + s.period_label;
        const printC = document.getElementById('printClientText');
        const selClientText = document.getElementById('filterClient').selectedOptions[0].text;
        if (printC) printC.textContent = 'Filtro de Cliente: ' + selClientText;

        // KPIs
        document.getElementById('kpiTotalTime').textContent = s.total_hours_formatted || '0h 00m';
        document.getElementById('kpiDecimalTime').textContent = s.total_hours_decimal + 'h decimais';
        document.getElementById('kpiCompletedTasks').textContent = s.completed_tasks + ' / ' + s.total_tasks;
        document.getElementById('kpiCompletionRate').textContent = s.completion_rate + '% concluídas';
        document.getElementById('kpiPendingTasks').textContent = s.pending_tasks + ' pendentes';
        document.getElementById('kpiOnTimeRate').textContent = s.on_time_rate + '%';
        document.getElementById('kpiAvgTime').textContent = s.avg_time_formatted || '0m';
        document.getElementById('kpiActiveClients').textContent = s.active_clients_count || '0';

        // KPI Estimado vs Real
        const estDiffEl = document.getElementById('kpiEstimateDiff');
        const estSubEl = document.getElementById('kpiEstimateSub');
        estDiffEl.textContent = s.total_hours_formatted;
        estSubEl.textContent = 'Estimado: ' + (s.total_estimated_formatted || '0h') + ' (' + s.time_variance_formatted + ')';
        if (s.time_variance_seconds > 0) {
            estSubEl.className = 'text-[10px] text-rose-400 mt-1 truncate';
        } else {
            estSubEl.className = 'text-[10px] text-emerald-400 mt-1 truncate';
        }

        // Renderizar Horas por Cliente
        renderClientList(data.by_client);

        // Renderizar Tarefas Mais Demoradas
        renderLongestTasks(data.longest_tasks);

        // Renderizar Equipe
        renderTeamList(data.by_user);

        // Renderizar Prioridades
        renderPriorityList(data.by_priority);

        // Renderizar Tabela
        renderTasksTable(allTasks);

        if (window.lucide) lucide.createIcons();
    }

    function renderClientList(clients) {
        const wrap = document.getElementById('dashClientList');
        const countBadge = document.getElementById('clientCountBadge');
        if (!wrap) return;

        if (!clients || clients.length === 0) {
            wrap.innerHTML = '<div class="text-center py-8 text-slate-500 text-xs">Nenhum cliente com horas no período selecionado.</div>';
            if (countBadge) countBadge.textContent = '0 empresas';
            return;
        }

        if (countBadge) countBadge.textContent = clients.length + (clients.length === 1 ? ' empresa' : ' empresas');

        let html = '';
        clients.forEach(c => {
            const initials = c.name.substring(0, 2).toUpperCase();
            html += `
                <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800/80 hover:border-indigo-500/30 transition group cursor-pointer"
                     onclick="KD_DASHBOARD.filterByClient(${c.id})" title="Clique para filtrar apenas ${escapeHtml(c.name)}">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-lg bg-indigo-600/20 border border-indigo-500/30 text-indigo-300 font-bold text-xs flex items-center justify-center flex-shrink-0">
                                ${escapeHtml(initials)}
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-xs font-semibold text-white truncate group-hover:text-indigo-300 transition">${escapeHtml(c.name)}</h3>
                                <p class="text-[10px] text-slate-400">${c.tasks_count} atividades &bull; ${c.completed_count} concluídas</p>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="text-xs font-bold text-white">${c.time_formatted}</span>
                            <span class="block text-[10px] text-slate-400">${c.hours_decimal}h (${c.percentage}%)</span>
                        </div>
                    </div>
                    <!-- Barra de Progresso de Participação -->
                    <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-500 to-indigo-400 h-1.5 rounded-full transition-all duration-500" style="width: ${c.percentage}%"></div>
                    </div>
                </div>
            `;
        });
        wrap.innerHTML = html;
    }

    function filterByClient(clientId) {
        const sel = document.getElementById('filterClient');
        if (sel) {
            sel.value = clientId || '';
            loadData();
        }
    }

    function renderLongestTasks(tasks) {
        const wrap = document.getElementById('dashLongestTasksList');
        if (!wrap) return;

        if (!tasks || tasks.length === 0) {
            wrap.innerHTML = '<div class="text-center py-8 text-slate-500 text-xs">Nenhuma tarefa encontrada.</div>';
            return;
        }

        let html = '';
        tasks.forEach((t, idx) => {
            const pos = idx + 1;
            const posBadgeClass = pos === 1 ? 'bg-amber-500/20 text-amber-300 border-amber-500/40' : (pos === 2 ? 'bg-slate-300/20 text-slate-200 border-slate-400/40' : (pos === 3 ? 'bg-amber-700/20 text-amber-500 border-amber-600/40' : 'bg-slate-800 text-slate-400 border-slate-700'));

            html += `
                <div class="p-2.5 rounded-xl bg-slate-900/60 border border-slate-800/80 hover:border-slate-700 transition flex items-center justify-between gap-3 cursor-pointer group"
                     onclick="if(typeof KD!=='undefined' && KD.openTaskDetail) KD.openTaskDetail(${t.id})">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-6 h-6 rounded-md border text-[11px] font-bold flex items-center justify-center flex-shrink-0 ${posBadgeClass}">
                            ${pos}
                        </span>
                        <div class="min-w-0">
                            <h4 class="text-xs font-medium text-white truncate group-hover:text-indigo-400 transition" title="${escapeHtml(t.title)}">
                                ${escapeHtml(t.title)}
                            </h4>
                            <div class="flex items-center gap-1.5 text-[10px] text-slate-400 mt-0.5">
                                <span class="truncate">${escapeHtml(t.client_name)}</span>
                                <span>&bull;</span>
                                <span style="color: ${t.stage_color}">${escapeHtml(t.stage_name)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="text-xs font-bold text-amber-400">${t.time_formatted}</span>
                        ${t.is_over_estimate ? '<span class="block text-[9px] text-rose-400 font-semibold">Excedeu orçado</span>' : ''}
                    </div>
                </div>
            `;
        });
        wrap.innerHTML = html;
    }

    function renderTeamList(users) {
        const wrap = document.getElementById('dashTeamList');
        if (!wrap) return;

        if (!users || users.length === 0) {
            wrap.innerHTML = '<div class="text-center py-6 text-slate-500 text-xs col-span-full">Nenhum colaborador com atividades.</div>';
            return;
        }

        let html = '';
        users.forEach(u => {
            html += `
                <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800/80 flex items-center justify-between gap-2.5">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-7 h-7 rounded-lg bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 font-bold text-xs flex items-center justify-center flex-shrink-0">
                            ${escapeHtml(u.initials)}
                        </span>
                        <div class="min-w-0">
                            <h4 class="text-xs font-semibold text-white truncate">${escapeHtml(u.name)}</h4>
                            <p class="text-[10px] text-slate-400">${u.tasks_count} tarefas &bull; ${u.completed_count} prontas</p>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="text-xs font-bold text-emerald-400">${u.time_formatted}</span>
                        <span class="block text-[10px] text-slate-500">${u.hours_decimal}h</span>
                    </div>
                </div>
            `;
        });
        wrap.innerHTML = html;
    }

    function renderPriorityList(priorities) {
        const wrap = document.getElementById('dashPriorityList');
        if (!wrap) return;

        let html = '';
        priorities.forEach(p => {
            html += `
                <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900/40 border border-slate-800/60 text-xs">
                    <span class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" style="background-color: ${p.color}"></span>
                        <span class="text-slate-300 font-medium">${p.label}</span>
                    </span>
                    <div class="text-right">
                        <span class="font-bold text-white">${p.count} tarefas</span>
                    </div>
                </div>
            `;
        });
        wrap.innerHTML = html;
    }

    function renderTasksTable(tasks) {
        const tbody = document.getElementById('dashTableBody');
        const summary = document.getElementById('dashTableSummary');
        const timeSum = document.getElementById('dashTableTimeSum');
        if (!tbody) return;

        if (!tasks || tasks.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="py-8 text-center text-slate-500">Nenhuma atividade corresponde aos filtros atuais.</td></tr>';
            if (summary) summary.textContent = 'Exibindo 0 atividades';
            if (timeSum) timeSum.textContent = 'Total: 0h 00m';
            return;
        }

        let totalSec = 0;
        let html = '';
        tasks.forEach(t => {
            totalSec += t.total_seconds;
            const priorityBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-semibold" style="background: rgba(255,255,255,0.06);">${escapeHtml(t.priority_label)}</span>`;
            const assigneesNames = t.assignees && t.assignees.length ? t.assignees.map(a => escapeHtml(a.name)).join(', ') : '<span class="text-slate-500">-</span>';

            html += `
                <tr class="hover:bg-slate-800/40 transition cursor-pointer" onclick="if(typeof KD!=='undefined' && KD.openTaskDetail) KD.openTaskDetail(${t.id})">
                    <td class="py-2.5 px-3 text-slate-500 font-mono text-[11px]">#${t.id}</td>
                    <td class="py-2.5 px-3 font-medium text-white max-w-[280px] truncate" title="${escapeHtml(t.title)}">
                        ${escapeHtml(t.title)}
                    </td>
                    <td class="py-2.5 px-3 text-slate-300 max-w-[160px] truncate">${escapeHtml(t.client_name)}</td>
                    <td class="py-2.5 px-3">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-medium"
                              style="background-color: ${t.stage_color}22; color: ${t.stage_color}; border: 1px solid ${t.stage_color}44;">
                            ${escapeHtml(t.stage_name)}
                        </span>
                    </td>
                    <td class="py-2.5 px-3">${priorityBadge}</td>
                    <td class="py-2.5 px-3 text-slate-400 max-w-[140px] truncate">${assigneesNames}</td>
                    <td class="py-2.5 px-3 text-slate-400 text-[11px]">${t.due_date_formatted}</td>
                    <td class="py-2.5 px-3 text-slate-400 text-[11px]">${t.estimated_formatted}</td>
                    <td class="py-2.5 px-3 text-right font-bold ${t.is_over_estimate ? 'text-rose-400' : 'text-emerald-400'}">
                        ${t.time_formatted}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        if (summary) summary.textContent = 'Exibindo ' + tasks.length + (tasks.length === 1 ? ' atividade' : ' atividades');
        if (timeSum) timeSum.textContent = 'Tempo Total da Lista: ' + formatSecondsJs(totalSec);
    }

    function filterTable() {
        const query = (document.getElementById('dashTableSearch').value || '').toLowerCase().trim();
        if (!query) {
            renderTasksTable(allTasks);
            return;
        }

        const filtered = allTasks.filter(t => {
            const titleMatch = t.title.toLowerCase().includes(query);
            const clientMatch = t.client_name.toLowerCase().includes(query);
            const stageMatch = t.stage_name.toLowerCase().includes(query);
            const assigneesMatch = t.assignees && t.assignees.some(a => a.name.toLowerCase().includes(query));
            const idMatch = ('#' + t.id).includes(query);
            return titleMatch || clientMatch || stageMatch || assigneesMatch || idMatch;
        });

        renderTasksTable(filtered);
    }

    function setPeriod(period) {
        currentPeriod = period;

        // Atualizar classes das tabs
        document.querySelectorAll('#periodTabs .period-tab').forEach(btn => {
            if (btn.getAttribute('data-period') === period) {
                btn.classList.add('active', 'bg-indigo-600', 'text-white', 'font-semibold');
                btn.classList.remove('text-slate-300');
            } else {
                btn.classList.remove('active', 'bg-indigo-600', 'text-white', 'font-semibold');
                btn.classList.add('text-slate-300');
            }
        });

        const customRow = document.getElementById('customDateRow');
        if (period === 'custom') {
            if (customRow) customRow.classList.remove('hidden');
        } else {
            if (customRow) customRow.classList.add('hidden');
            loadData();
        }
    }

    function applyCustomDates() {
        const start = document.getElementById('customStartDate').value;
        const end = document.getElementById('customEndDate').value;
        if (!start || !end) {
            if (typeof KD !== 'undefined' && KD.toast) {
                KD.toast('Preencha a data inicial e final.', 'warning');
            }
            return;
        }
        loadData();
    }

    function exportCSV() {
        const clientVal = document.getElementById('filterClient').value;
        const userVal = document.getElementById('filterUser').value;
        const statusVal = document.getElementById('filterStatus').value;
        const startDate = document.getElementById('customStartDate').value;
        const endDate = document.getElementById('customEndDate').value;

        const params = new URLSearchParams({
            period: currentPeriod,
            client_id: clientVal,
            user_id: userVal,
            status: statusVal,
            export: 'csv'
        });

        if (currentPeriod === 'custom' && startDate && endDate) {
            params.set('start_date', startDate);
            params.set('end_date', endDate);
        }

        window.location.href = getApiUrl() + '?' + params.toString();
    }

    function formatSecondsJs(sec) {
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        if (h > 0) return h + 'h ' + (m < 10 ? '0' : '') + m + 'm';
        return m + 'm';
    }

    function escapeHtml(str) {
        if (!str) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(str).replace(/[&<>"']/g, m => map[m]);
    }

    // Inicialização ao carregar a página
    document.addEventListener('DOMContentLoaded', function() {
        loadData();
    });

    return {
        loadData,
        setPeriod,
        applyCustomDates,
        exportCSV,
        filterTable,
        filterByClient
    };
})();
</script>

<?php view_footer(); ?>
