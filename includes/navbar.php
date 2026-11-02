<?php
/**
 * Barra de Navegação Superior do KanbanDoo
 */
require_once __DIR__ . '/auth.php';

$currentUser = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$flashMessages = get_flash_messages();

/** Classe do link de navegação conforme a página ativa. */
$navClass = function (bool $active): string {
    return $active
        ? 'kd-nav-link kd-nav-link--active'
        : 'kd-nav-link kd-nav-link--collapsible';
};

$navItems = [
    ['id' => 'quadro',     'href' => url('/tarefas'),    'icon' => 'layout-grid', 'label' => 'Quadro',     'active' => in_array(current_path(), ['/', '/tarefas'], true) || str_starts_with(current_path(), '/tarefas/'), 'admin' => false],
    ['id' => 'dashboard',  'href' => url('/dashboard'),  'icon' => 'bar-chart-3', 'label' => 'Dashboard',  'active' => current_path() === '/dashboard', 'admin' => false],
    ['id' => 'calendario', 'href' => url('/calendario'), 'icon' => 'calendar',    'label' => 'Calendário', 'active' => current_path() === '/calendario', 'admin' => false],
    ['id' => 'clientes',   'href' => url('/clientes'),   'icon' => 'building-2',  'label' => 'Clientes',   'active' => current_path() === '/clientes', 'admin' => false],
    ['id' => 'modelos',    'href' => url('/modelos'),    'icon' => 'copy-check',  'label' => 'Modelos',    'active' => current_path() === '/modelos', 'admin' => false],
    ['id' => 'notas',      'href' => url('/notas'),      'icon' => 'notebook-tabs', 'label' => 'Notas',    'active' => current_path() === '/notas', 'admin' => false],
    ['id' => 'equipe',     'href' => url('/equipe'),     'icon' => 'users',       'label' => 'Equipe',     'active' => current_path() === '/equipe', 'admin' => true],
];
?>
<header class="kd-header kd-glass sticky top-0 z-40 px-8 py-3">
    <div class="w-full flex items-center justify-between gap-4">

        <div class="flex items-center gap-6">
            <a href="<?= url('/tarefas') ?>" class="flex items-center gap-2.5 group" aria-label="KanbanDoo, ir para o quadro">
                <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white shadow-lg shadow-indigo-500/25">
                    <i data-lucide="kanban" class="w-5 h-5"></i>
                </span>
                <span class="flex flex-col">
                    <span class="font-bold text-lg text-white tracking-tight leading-none">KanbanDoo</span>
                    <span class="text-[10px] text-slate-400 font-medium tracking-wide uppercase">Tarefas &bull; Clientes</span>
                </span>
            </a>

                        <nav class="hidden md:flex items-center gap-1.5 select-none" aria-label="Navegação principal" id="mainNavLinks">
                <?php foreach ($navItems as $item): ?>
                    <?php if ($item['admin'] && !is_admin()) continue; ?>
                    <a href="<?= e($item['href']) ?>"
                       data-nav-id="<?= e($item['id'] ?? basename($item['href'])) ?>"
                       draggable="true"
                       class="<?= $navClass($item['active']) ?> cursor-grab active:cursor-grabbing transition-transform"
                       <?= $item['active'] ? 'aria-current="page"' : '' ?>
                       title="<?= e($item['label']) ?> (Arraste para reordenar)">
                        <i data-lucide="<?= e($item['icon']) ?>" class="kd-nav-link__icon w-4 h-4 flex-shrink-0 pointer-events-none"></i>
                        <span class="kd-nav-link__label pointer-events-none"><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Campo de Busca Rápida no Header -->
        <div class="flex-1 max-w-sm mx-2 lg:mx-4 hidden sm:block">
            <div class="relative flex items-center">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 pointer-events-none"></i>
                <input type="search" id="kdHeaderSearch"
                       placeholder="Buscar tarefas, clientes, tags... ( / )"
                       autocomplete="off"
                       class="w-full pl-9 pr-8 bg-slate-900/80 border border-slate-700/60 rounded-xl text-xs text-slate-200 placeholder-slate-400 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition shadow-inner">
                <button type="button" id="kdHeaderSearchClear" onclick="KD.clearHeaderSearch()" class="absolute right-2.5 text-slate-400 hover:text-white" hidden aria-label="Limpar busca">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <!-- Botão Unificado: Nova Tarefa + Dropdown -->
            <div class="relative inline-flex items-stretch rounded-xl shadow-lg shadow-indigo-600/20 kd-menu-wrap" id="newTaskDropdownWrap">
                <button type="button" onclick="KD.openTaskForm()" class="kd-btn kd-btn--primary rounded-r-none border-r border-indigo-400/30 pr-2.5">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span class="hidden sm:inline">Nova tarefa</span>
                    <kbd class="hidden lg:inline kd-menu__hint" style="color:rgba(255,255,255,.75)">N</kbd>
                </button>
                <button type="button" id="newTaskDropdownTrigger" onclick="KD.toggleNewTaskDropdown(event)"
                        class="kd-btn kd-btn--primary rounded-l-none px-2 hover:bg-indigo-500 transition"
                        aria-haspopup="menu" aria-expanded="false" title="Mais opções de criação">
                    <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                </button>

                <div id="newTaskDropdownMenu" class="kd-menu kd-glass kd-glass--raised" role="menu" hidden style="min-width:15rem">
                    <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between" role="menuitem" onclick="KD.openTaskForm(); KD.closeMenus();">
                        <span class="flex items-center gap-2">
                            <i data-lucide="plus" class="w-4 h-4 text-indigo-400"></i>
                            <span>Nova tarefa</span>
                        </span>
                        <kbd class="text-[10px] text-slate-400 font-mono">N</kbd>
                    </button>
                    <div class="kd-menu__sep"></div>
                    <button type="button" class="kd-menu__item w-full text-left flex items-center gap-2.5" role="menuitem" onclick="KD.openImportModal(); KD.closeMenus();">
                        <i data-lucide="file-spreadsheet" class="w-4 h-4 text-emerald-400 flex-shrink-0"></i>
                        <span class="min-w-0">
                            <span class="block text-xs font-semibold text-white">Importar Planilha / Lote</span>
                            <span class="block text-[10px] text-slate-400">Subir .csv ou lista .txt</span>
                        </span>
                    </button>
                    <a href="<?= url('/api/tarefas?action=export_csv') ?>" download class="kd-menu__item w-full text-left flex items-center gap-2.5" role="menuitem" onclick="KD.closeMenus();">
                        <i data-lucide="download" class="w-4 h-4 text-indigo-400 flex-shrink-0"></i>
                        <span class="min-w-0">
                            <span class="block text-xs font-semibold text-white">Exportar Planilha (.csv)</span>
                            <span class="block text-[10px] text-slate-400">Baixar tarefas para o Excel</span>
                        </span>
                    </a>
                    <div class="kd-menu__sep"></div>
                    <button type="button" class="kd-menu__item w-full text-left flex items-center gap-2.5" role="menuitem" onclick="KD.requestNotificationPermission(); KD.closeMenus();">
                        <i data-lucide="bell" class="w-4 h-4 text-amber-400 flex-shrink-0"></i>
                        <span class="min-w-0">
                            <span class="block text-xs font-semibold text-white">Notificações Push</span>
                            <span class="block text-[10px] text-slate-400">Ativar lembretes de horário</span>
                        </span>
                    </button>
                </div>
            </div>

            <button type="button" id="themeToggleBtn" onclick="KD.toggleTheme()" class="kd-icon-btn" aria-label="Alternar tema">
                <i data-lucide="sun" class="w-5 h-5" id="themeToggleIcon"></i>
            </button>

            <button type="button" id="glassToggleBtn" onclick="KD.toggleGlass()" class="kd-icon-btn"
                    aria-pressed="true" aria-label="Reduzir transparência" title="Efeito de vidro">
                <i data-lucide="sparkles" class="w-5 h-5"></i>
            </button>

            <div class="relative kd-menu-wrap" id="userMenuDropdown">
                <button type="button" id="userMenuTrigger" onclick="KD.toggleUserMenu(event)"
                        class="flex items-center gap-2.5 p-1 rounded-xl hover:bg-slate-800/80 transition"
                        aria-haspopup="menu" aria-expanded="false" aria-controls="userMenuPopup">
                    <span class="w-8 h-8 rounded-lg bg-indigo-600/20 border border-indigo-500/30 text-indigo-300 font-bold text-xs flex items-center justify-center">
                        <?= e(mb_strtoupper(mb_substr($currentUser['full_name'] ?? 'U', 0, 2, 'UTF-8'))) ?>
                    </span>
                    <span class="hidden xl:flex flex-col text-left">
                        <span class="text-xs font-semibold text-white leading-tight"><?= e($currentUser['full_name'] ?? 'Usuário') ?></span>
                        <span class="text-[10px] text-slate-400 leading-tight"><?= is_admin() ? 'Administrador' : 'Membro' ?></span>
                    </span>
                    <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
                </button>

                <div id="userMenuPopup" class="kd-menu kd-glass kd-glass--raised" role="menu" hidden style="min-width:14rem">
                    <div class="px-3 py-2 mb-1" style="border-bottom:1px solid var(--border)">
                        <p class="text-xs font-semibold text-white truncate"><?= e($currentUser['full_name'] ?? '') ?></p>
                        <p class="text-[11px] text-slate-400 truncate"><?= e($currentUser['email'] ?? '') ?></p>
                    </div>
                    <a href="<?= url('/perfil') ?>" class="kd-menu__item" role="menuitem">
                        <i data-lucide="user" class="w-4 h-4"></i> Meu perfil
                    </a>
                    <?php if (!empty($currentUser['public_token'])): ?>
                    <a href="<?= url('/coordenadoria/' . $currentUser['public_token']) ?>" target="_blank" class="kd-menu__item" role="menuitem">
                        <i data-lucide="share-2" class="w-4 h-4 text-indigo-400"></i> Link da Coordenadoria
                    </a>
                    <?php endif; ?>
                    <button type="button" class="kd-menu__item w-full text-left" role="menuitem" onclick="KD.openWorkHoursModal(); KD.toggleUserMenu();">
                        <i data-lucide="clock" class="w-4 h-4"></i> Horário de expediente
                    </button>
                    <a href="<?= url('/dashboard') ?>" class="kd-menu__item md:hidden" role="menuitem">
                        <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Dashboard
                    </a>
                    <a href="<?= url('/calendario') ?>" class="kd-menu__item md:hidden" role="menuitem">
                        <i data-lucide="calendar" class="w-4 h-4"></i> Calendário
                    </a>
                    <a href="<?= url('/clientes') ?>" class="kd-menu__item md:hidden" role="menuitem">
                        <i data-lucide="building-2" class="w-4 h-4"></i> Clientes
                    </a>
                    <a href="<?= url('/modelos') ?>" class="kd-menu__item md:hidden" role="menuitem">
                        <i data-lucide="copy-check" class="w-4 h-4"></i> Modelos
                    </a>
                    <a href="<?= url('/notas') ?>" class="kd-menu__item md:hidden" role="menuitem">
                        <i data-lucide="notebook-tabs" class="w-4 h-4"></i> Notas
                    </a>
                    <?php if (is_admin()): ?>
                        <a href="<?= url('/equipe') ?>" class="kd-menu__item md:hidden" role="menuitem">
                            <i data-lucide="users" class="w-4 h-4"></i> Equipe
                        </a>
                    <?php endif; ?>
                    <div class="kd-menu__sep"></div>
                    <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        Etiquetas nos cards
                    </div>
                    <div class="px-1 py-0.5 space-y-0.5" id="userMenuTagOptions">
                        <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs"
                                onclick="KD.setTagVisibility('hover')">
                            <span class="flex items-center gap-2">
                                <i data-lucide="mouse-pointer" class="w-3.5 h-3.5 text-indigo-400"></i> Ao passar o mouse
                            </span>
                            <span class="tag-opt-check" data-mode="hover"><i data-lucide="check" class="w-3.5 h-3.5 text-indigo-400"></i></span>
                        </button>
                        <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs"
                                onclick="KD.setTagVisibility('always')">
                            <span class="flex items-center gap-2">
                                <i data-lucide="eye" class="w-3.5 h-3.5 text-slate-400"></i> Sempre visíveis
                            </span>
                            <span class="tag-opt-check" data-mode="always" hidden><i data-lucide="check" class="w-3.5 h-3.5 text-indigo-400"></i></span>
                        </button>
                        <button type="button" class="kd-menu__item w-full text-left flex items-center justify-between text-xs"
                                onclick="KD.setTagVisibility('hidden')">
                            <span class="flex items-center gap-2">
                                <i data-lucide="eye-off" class="w-3.5 h-3.5 text-slate-400"></i> Ocultar etiquetas
                            </span>
                            <span class="tag-opt-check" data-mode="hidden" hidden><i data-lucide="check" class="w-3.5 h-3.5 text-indigo-400"></i></span>
                        </button>
                    </div>
                    <div class="kd-menu__sep"></div>
                    <a href="<?= url('/sair') ?>" class="kd-menu__item kd-menu__item--danger" role="menuitem">
                        <i data-lucide="log-out" class="w-4 h-4"></i> Sair do sistema
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<?php if (!empty($flashMessages)): ?>
    <div class="w-full px-4 lg:px-8 mt-4 space-y-2" role="status" aria-live="polite">
        <?php foreach ($flashMessages as $msg): ?>
            <?php
            $alertClass = match ($msg['type']) {
                'success' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400',
                'danger'  => 'bg-rose-500/10 border-rose-500/20 text-rose-400',
                'warning' => 'bg-amber-500/10 border-amber-500/20 text-amber-400',
                default   => 'bg-indigo-500/10 border-indigo-500/20 text-indigo-400',
            };
            ?>
            <div class="p-4 rounded-xl border <?= $alertClass ?> text-sm flex items-center justify-between animate-fade-in">
                <span><?= e($msg['text']) ?></span>
                <button type="button" onclick="this.parentElement.remove()" class="opacity-70 hover:opacity-100" aria-label="Fechar aviso">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


<!-- Barra Inferior Flutuante Mobile (iOS / Android Fluid Style) -->
<nav id="mobileFloatingNav" class="md:hidden fixed bottom-3 inset-x-3 sm:inset-x-6 z-50 mx-auto max-w-md h-16 px-3 rounded-2xl bg-slate-950/85 backdrop-blur-2xl border border-white/10 shadow-2xl flex items-center justify-around select-none">
    <!-- 1. Quadro -->
    <?php $isQuadro = in_array(current_path(), ['/', '/tarefas'], true) || str_starts_with(current_path(), '/tarefas/'); ?>
    <a href="<?= url('/tarefas') ?>" class="flex flex-col items-center justify-center gap-1 w-12 py-1 transition active:scale-90 <?= $isQuadro ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
        <i data-lucide="layout-grid" class="w-5 h-5"></i>
        <span class="text-[10px] leading-none">Quadro</span>
    </a>

    <!-- 2. Agenda / Calendário -->
    <?php $isCal = current_path() === '/calendario'; ?>
    <a href="<?= url('/calendario') ?>" class="flex flex-col items-center justify-center gap-1 w-12 py-1 transition active:scale-90 <?= $isCal ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
        <i data-lucide="calendar" class="w-5 h-5"></i>
        <span class="text-[10px] leading-none">Agenda</span>
    </a>

    <!-- 3. Botão Central: Criar (+) -->
    <button type="button" onclick="KD.openTaskForm()" class="flex flex-col items-center justify-center -mt-5 transition-transform active:scale-90 focus:outline-none" aria-label="Nova tarefa">
        <span class="w-12 h-12 rounded-full bg-gradient-to-tr from-indigo-600 to-indigo-500 text-white flex items-center justify-center shadow-lg shadow-indigo-600/40 border-2 border-slate-950">
            <i data-lucide="plus" class="w-6 h-6 stroke-[2.5]"></i>
        </span>
        <span class="text-[10px] text-indigo-300 font-semibold mt-0.5">Criar</span>
    </button>

    <!-- 4. Notas -->
    <?php $isNotas = current_path() === '/notas'; ?>
    <a href="<?= url('/notas') ?>" class="flex flex-col items-center justify-center gap-1 w-12 py-1 transition active:scale-90 <?= $isNotas ? 'text-indigo-400 font-bold' : 'text-slate-400 hover:text-white' ?>">
        <i data-lucide="notebook-tabs" class="w-5 h-5"></i>
        <span class="text-[10px] leading-none">Notas</span>
    </a>

    <!-- 5. Mais (Menu Bottom Sheet) -->
    <button type="button" onclick="toggleMobileMoreMenu()" id="btnMobileMore" class="flex flex-col items-center justify-center gap-1 w-12 py-1 text-slate-400 hover:text-white transition active:scale-90 focus:outline-none" aria-label="Mais opções">
        <i data-lucide="menu" class="w-5 h-5"></i>
        <span class="text-[10px] leading-none">Mais</span>
    </button>
</nav>

<!-- Bottom Sheet do Menu Mais (Mobile) -->
<div id="mobileMoreSheetBackdrop" onclick="toggleMobileMoreMenu(false)" class="md:hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm transition-opacity opacity-0 pointer-events-none" style="transition: opacity 0.25s ease;"></div>
<div id="mobileMoreSheet" class="md:hidden fixed inset-x-3 bottom-20 z-50 mx-auto max-w-md p-4 rounded-3xl bg-slate-950/95 backdrop-blur-2xl border border-white/10 shadow-2xl transition-all transform translate-y-8 opacity-0 pointer-events-none space-y-3" style="transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);">
    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Navegação & Gestão</span>
        <button type="button" onclick="toggleMobileMoreMenu(false)" class="text-slate-400 hover:text-white p-1">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>
    <div class="grid grid-cols-2 gap-2 text-xs">
        <a href="<?= url('/dashboard') ?>" class="flex items-center gap-2.5 p-3 rounded-2xl bg-slate-900/80 border border-slate-800/80 text-slate-200 hover:text-white hover:bg-slate-800 transition">
            <i data-lucide="bar-chart-3" class="w-4 h-4 text-indigo-400"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?= url('/clientes') ?>" class="flex items-center gap-2.5 p-3 rounded-2xl bg-slate-900/80 border border-slate-800/80 text-slate-200 hover:text-white hover:bg-slate-800 transition">
            <i data-lucide="building-2" class="w-4 h-4 text-purple-400"></i>
            <span>Clientes</span>
        </a>
        <a href="<?= url('/modelos') ?>" class="flex items-center gap-2.5 p-3 rounded-2xl bg-slate-900/80 border border-slate-800/80 text-slate-200 hover:text-white hover:bg-slate-800 transition">
            <i data-lucide="copy-check" class="w-4 h-4 text-emerald-400"></i>
            <span>Modelos</span>
        </a>
        <?php if (is_admin()): ?>
            <a href="<?= url('/equipe') ?>" class="flex items-center gap-2.5 p-3 rounded-2xl bg-slate-900/80 border border-slate-800/80 text-slate-200 hover:text-white hover:bg-slate-800 transition">
                <i data-lucide="users" class="w-4 h-4 text-blue-400"></i>
                <span>Equipe</span>
            </a>
        <?php endif; ?>
        <a href="<?= url('/perfil') ?>" class="flex items-center gap-2.5 p-3 rounded-2xl bg-slate-900/80 border border-slate-800/80 text-slate-200 hover:text-white hover:bg-slate-800 transition">
            <i data-lucide="user" class="w-4 h-4 text-amber-400"></i>
            <span>Meu Perfil</span>
        </a>
        <button type="button" onclick="KD.openImportModal(); toggleMobileMoreMenu(false);" class="flex items-center gap-2.5 p-3 rounded-2xl bg-slate-900/80 border border-slate-800/80 text-slate-200 hover:text-white hover:bg-slate-800 transition text-left">
            <i data-lucide="list-plus" class="w-4 h-4 text-sky-400"></i>
            <span>Importar</span>
        </button>
    </div>
</div>

<script>
// Menu Mobile Mais
function toggleMobileMoreMenu(show) {
    const backdrop = document.getElementById('mobileMoreSheetBackdrop');
    const sheet = document.getElementById('mobileMoreSheet');
    if (!backdrop || !sheet) return;

    const isVisible = backdrop.classList.contains('opacity-100');
    const shouldShow = (typeof show === 'boolean') ? show : !isVisible;

    if (shouldShow) {
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        backdrop.classList.add('opacity-100');
        sheet.classList.remove('translate-y-8', 'opacity-0', 'pointer-events-none');
        sheet.classList.add('translate-y-0', 'opacity-100');
    } else {
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0', 'pointer-events-none');
        sheet.classList.remove('translate-y-0', 'opacity-100');
        sheet.classList.add('translate-y-8', 'opacity-0', 'pointer-events-none');
    }
}

// Reordenação por Arraste da Navegação Desktop com Persistência
(function() {
    function initNavDragOrder() {
        const nav = document.getElementById('mainNavLinks');
        if (!nav) return;

        // 1. Restaurar ordem salva no localStorage
        const savedOrder = localStorage.getItem('kanbandoo_nav_order');
        if (savedOrder) {
            try {
                const ids = JSON.parse(savedOrder);
                if (Array.isArray(ids)) {
                    ids.forEach(id => {
                        const el = nav.querySelector(`[data-nav-id="${id}"]`);
                        if (el) nav.appendChild(el);
                    });
                }
            } catch (e) {
                console.error('Erro ao restaurar ordem de navegação:', e);
            }
        }

        // 2. Drag & Drop
        let draggedItem = null;

        nav.querySelectorAll('[data-nav-id]').forEach(link => {
            link.addEventListener('dragstart', (e) => {
                draggedItem = link;
                link.classList.add('opacity-40', 'scale-95');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', link.dataset.navId);
            });

            link.addEventListener('dragend', () => {
                if (draggedItem) draggedItem.classList.remove('opacity-40', 'scale-95');
                draggedItem = null;
                
                // Salvar nova ordem no localStorage
                const newOrder = Array.from(nav.querySelectorAll('[data-nav-id]')).map(el => el.dataset.navId);
                localStorage.setItem('kanbandoo_nav_order', JSON.stringify(newOrder));
            });

            link.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (!draggedItem || draggedItem === link) return;

                const rect = link.getBoundingClientRect();
                const midpoint = rect.left + rect.width / 2;
                if (e.clientX < midpoint) {
                    nav.insertBefore(draggedItem, link);
                } else {
                    nav.insertBefore(draggedItem, link.nextSibling);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNavDragOrder);
    } else {
        initNavDragOrder();
    }
})();
</script>
