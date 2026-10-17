<?php
/**
 * Modal de Lixeira de Tarefas.
 * Permite visualizar tarefas excluídas, restaurá-las para o Kanban ou esvaziar a lixeira.
 * Tarefas permanecem por até 30 dias antes de serem expurgadas automaticamente.
 */
?>
<div id="taskTrashModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="trashModalTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--lg kd-glass kd-glass--raised">
        <!-- Cabeçalho -->
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 flex items-center justify-center">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </span>
                <div>
                    <h3 id="trashModalTitle" class="text-base font-bold text-slate-100 flex items-center gap-2">
                        Lixeira de Tarefas
                        <span id="trashModalBadge" class="text-xs px-2 py-0.5 rounded-full bg-red-500/10 text-red-400 font-semibold border border-red-500/20">0</span>
                    </h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Tarefas excluídas são apagadas definitivamente após <strong>30 dias</strong>.
                    </p>
                </div>
            </div>
            <button type="button" class="kd-icon-btn" onclick="KD.closeTrashModal()" aria-label="Fechar" title="Fechar (Esc)">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Conteúdo: Lista de Tarefas Excluídas -->
        <div id="trashLoading" class="py-8 text-center text-slate-400 text-xs flex items-center justify-center gap-2" hidden>
            <i data-lucide="loader" class="w-4 h-4 animate-spin text-indigo-400"></i>
            <span>Carregando lixeira...</span>
        </div>

        <div id="trashEmpty" class="py-12 text-center text-slate-400 space-y-2" hidden>
            <div class="w-12 h-12 rounded-2xl bg-slate-800/50 border border-slate-700/50 text-slate-500 flex items-center justify-center mx-auto">
                <i data-lucide="check-circle" class="w-6 h-6 text-emerald-400"></i>
            </div>
            <p class="text-sm font-semibold text-slate-300">A lixeira está vazia</p>
            <p class="text-xs text-slate-500">Nenhuma tarefa excluída no momento.</p>
        </div>

        <div id="trashListContainer" class="space-y-2 max-h-[50vh] overflow-y-auto pr-1"></div>

        <!-- Rodapé com Ações -->
        <div class="flex items-center justify-between pt-3 mt-4 border-t border-slate-800 text-xs">
            <button type="button" id="btnEmptyTrash" onclick="KD.emptyTrashNow()" class="kd-btn text-red-400 hover:text-red-300 hover:bg-red-500/10 transition border border-red-500/20" disabled>
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                Esvaziar lixeira agora
            </button>
            <button type="button" onclick="KD.closeTrashModal()" class="kd-btn">
                Fechar
            </button>
        </div>
    </div>
</div>