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
        ? 'px-3.5 py-2 rounded-xl text-sm font-medium transition flex items-center gap-2 bg-indigo-600/10 text-indigo-400 border border-indigo-500/20'
        : 'px-3.5 py-2 rounded-xl text-sm font-medium transition flex items-center gap-2 text-slate-300 hover:text-white hover:bg-slate-800/50';
};

$navItems = [
    ['href' => url('/tarefas'),  'icon' => 'layout-grid', 'label' => 'Quadro',   'active' => in_array(current_path(), ['/', '/tarefas'], true) || str_starts_with(current_path(), '/tarefas/'), 'admin' => false],
    ['href' => url('/clientes'), 'icon' => 'building-2',  'label' => 'Clientes', 'active' => current_path() === '/clientes', 'admin' => false],
    ['href' => url('/modelos'),  'icon' => 'copy-check',  'label' => 'Modelos',  'active' => current_path() === '/modelos', 'admin' => false],
    ['href' => url('/equipe'),   'icon' => 'users',       'label' => 'Equipe',   'active' => current_path() === '/equipe', 'admin' => true],
];
?>
<header class="kd-header kd-glass sticky top-0 z-40 px-4 lg:px-8 py-3">
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

            <nav class="hidden md:flex items-center gap-1" aria-label="Navegação principal">
                <?php foreach ($navItems as $item): ?>
                    <?php if ($item['admin'] && !is_admin()) continue; ?>
                    <a href="<?= e($item['href']) ?>" class="<?= $navClass($item['active']) ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?>>
                        <i data-lucide="<?= e($item['icon']) ?>" class="w-4 h-4"></i>
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" onclick="KD.openTaskForm()" class="kd-btn kd-btn--primary">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span class="hidden sm:inline">Nova tarefa</span>
                <kbd class="hidden lg:inline kd-menu__hint" style="color:rgba(255,255,255,.75)">N</kbd>
            </button>

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
                    <button type="button" class="kd-menu__item w-full text-left" role="menuitem" onclick="KD.openWorkHoursModal(); KD.toggleUserMenu();">
                        <i data-lucide="clock" class="w-4 h-4"></i> Horário de expediente
                    </button>
                    <a href="<?= url('/clientes') ?>" class="kd-menu__item md:hidden" role="menuitem">
                        <i data-lucide="building-2" class="w-4 h-4"></i> Clientes
                    </a>
                    <a href="<?= url('/modelos') ?>" class="kd-menu__item md:hidden" role="menuitem">
                        <i data-lucide="copy-check" class="w-4 h-4"></i> Modelos
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
