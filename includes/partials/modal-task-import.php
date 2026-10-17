<?php
/**
 * Modal de Importação em Massa de Tarefas.
 * Permite colar lista em texto ou subir arquivo .txt pré-formatado.
 * Usa $teamUsers, $kanbanStages e $currentUser vindos do footer/bootstrap.
 */
$currentUser = current_user();
?>
<div id="importTasksModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="importModalTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--lg kd-glass kd-glass--raised">

        <!-- Header -->
        <div class="flex items-center justify-between gap-3 pb-4 mb-4" style="border-bottom:1px solid var(--border)">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl flex items-center justify-center bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 shadow-lg shadow-indigo-600/10">
                    <i data-lucide="list-plus" class="w-5 h-5"></i>
                </span>
                <div>
                    <h2 id="importModalTitle" class="text-lg font-bold text-white flex items-center gap-2">
                        Importar Tarefas em Lote
                        <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">TXT / Lista</span>
                    </h2>
                    <p class="text-xs text-slate-400">Cole seu texto ou arraste um arquivo .txt. As datas e títulos são detectados automaticamente.</p>
                </div>
            </div>
            <button type="button" class="kd-icon-btn" onclick="KD.closeImportModal()" aria-label="Fechar (Esc)">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="bulkImportForm" onsubmit="KD.submitBulkImport(event)" class="space-y-4">
            
            <!-- Barra de Configurações do Lote (Responsável, Estágio, Prioridade) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3.5 rounded-2xl bg-slate-900/60 border border-slate-800">
                <div>
                    <label for="bulk_assignee_id" class="block text-[11px] font-bold uppercase tracking-wider text-slate-300 mb-1 flex items-center gap-1.5">
                        <i data-lucide="user-check" class="w-3.5 h-3.5 text-indigo-400"></i> Atribuir para
                    </label>
                    <select id="bulk_assignee_id" class="kd-field text-xs">
                        <?php foreach ($teamUsers as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= ((int)$u['id'] === (int)($currentUser['id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= e($u['full_name']) ?> (<?= e($u['role'] === 'admin' ? 'Admin' : 'Membro') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="bulk_stage_id" class="block text-[11px] font-bold uppercase tracking-wider text-slate-300 mb-1 flex items-center gap-1.5">
                        <i data-lucide="columns" class="w-3.5 h-3.5 text-indigo-400"></i> Coluna Inicial
                    </label>
                    <select id="bulk_stage_id" class="kd-field text-xs">
                        <?php foreach ($kanbanStages as $st): ?>
                            <option value="<?= (int)$st['id'] ?>" <?= (stripos($st['name'], 'fazer') !== false || stripos($st['name'], 'todo') !== false) ? 'selected' : '' ?>>
                                <?= e($st['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="bulk_priority" class="block text-[11px] font-bold uppercase tracking-wider text-slate-300 mb-1 flex items-center gap-1.5">
                        <i data-lucide="flag" class="w-3.5 h-3.5 text-indigo-400"></i> Prioridade Padrão
                    </label>
                    <select id="bulk_priority" class="kd-field text-xs">
                        <option value="medium" selected>Média (Padrão)</option>
                        <option value="low">Baixa</option>
                        <option value="high">Alta</option>
                        <option value="urgent">Urgente</option>
                    </select>
                </div>
            </div>

            <!-- Área de Entrada de Texto & Upload de Arquivo -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-slate-300 flex items-center gap-1.5">
                        <i data-lucide="file-text" class="w-3.5 h-3.5 text-indigo-400"></i> Lista de Tarefas (1 por linha)
                    </span>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="KD.fillImportExample()" class="text-[11px] text-indigo-400 hover:text-indigo-300 underline underline-offset-2 transition">
                            Preencher com exemplo
                        </button>
                        <span class="text-slate-600">&bull;</span>
                        <label class="text-[11px] text-indigo-400 hover:text-indigo-300 underline underline-offset-2 cursor-pointer flex items-center gap-1">
                            <i data-lucide="upload" class="w-3 h-3"></i> Carregar .txt
                            <input type="file" id="bulkFileInput" accept=".txt,text/plain" class="hidden" onchange="KD.handleBulkFileUpload(event)">
                        </label>
                    </div>
                </div>

                <div id="bulkDropZone" class="relative group">
                    <textarea id="bulkTextInput" rows="7" 
                        oninput="KD.parseBulkInputDebounced()" 
                        placeholder="- Ajustar Lamaki - Hoje&#10;- Subir novos posts da PS Advogados - Amanhã&#10;- Criar campanha de hotel para o Vila - 12/10/2026&#10;- Impulsionar o Youtube do Vila - Segunda" 
                        class="w-full p-3.5 rounded-2xl bg-slate-950/80 border border-slate-800 text-slate-200 font-mono text-xs leading-relaxed focus:outline-none focus:border-indigo-500 transition shadow-inner resize-y"></textarea>
                </div>
            </div>

            <!-- Painel de Pré-visualização das Tarefas Identificadas -->
            <div id="bulkPreviewSection" class="space-y-2" hidden>
                <div class="flex items-center justify-between px-1">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-300">Prévia da Importação</span>
                        <span id="bulkCountBadge" class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">0 tarefas</span>
                    </div>
                    <span class="text-[11px] text-slate-400">Desmarque ou ajuste antes de criar</span>
                </div>

                <div class="max-h-56 overflow-y-auto rounded-2xl border border-slate-800 bg-slate-950/50 p-2 divide-y divide-slate-800/60" id="bulkPreviewList">
                    <!-- Linhas renderizadas via JS -->
                </div>
            </div>

            <!-- Rodapé / Ações -->
            <div class="flex items-center justify-between pt-3 border-t border-slate-800">
                <button type="button" class="kd-btn kd-glass text-xs" onclick="KD.closeImportModal()">
                    Cancelar
                </button>
                <button type="submit" id="btnSubmitBulkImport" class="kd-btn kd-btn--primary text-xs flex items-center gap-2" disabled>
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span id="btnSubmitBulkLabel">Criar Tarefas</span>
                </button>
            </div>
        </form>
    </div>
</div>
