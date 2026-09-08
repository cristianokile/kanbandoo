<?php
/**
 * KanbanDoo - Página não encontrada
 */
require_once dirname(__DIR__) . '/bootstrap.php';

view_header('Página não encontrada');
?>

<main id="conteudo" class="flex-1 w-full px-4 lg:px-8 py-16 flex items-center justify-center">
    <div class="text-center max-w-md">
        <div class="w-14 h-14 rounded-2xl mx-auto mb-4 flex items-center justify-center"
             style="background:var(--surface-3);color:var(--text-muted)">
            <i data-lucide="compass" class="w-7 h-7"></i>
        </div>
        <h1 class="text-2xl font-extrabold tracking-tight mb-2">Não encontramos esta página</h1>
        <p class="text-sm text-slate-400 mb-6">
            O endereço <code class="kd-menu__hint"><?= e(current_path()) ?></code> não existe no KanbanDoo.
        </p>
        <a href="<?= url('/tarefas') ?>" class="kd-btn kd-btn--primary">
            <i data-lucide="layout-grid" class="w-4 h-4"></i>
            Voltar ao quadro
        </a>
    </div>
</main>

<?php view_footer(); ?>
