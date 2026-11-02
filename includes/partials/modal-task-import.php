<?php
/**
 * Modal de Importação e Exportação de Tarefas.
 * Suporta importação por Planilha (.CSV / Excel) com opção de substituir ou manter dados,
 * download de modelo, exportação das tarefas atuais e importação rápida por texto (.txt).
 */
$currentUser = current_user();
?>
<div id="importTasksModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="importModalTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--xl kd-glass kd-glass--raised">

        <!-- Header -->
        <div class="flex items-center justify-between gap-3 pb-4 mb-4" style="border-bottom:1px solid var(--border)">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl flex items-center justify-center bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 shadow-lg shadow-indigo-600/10">
                    <i data-lucide="file-spreadsheet" class="w-5 h-5"></i>
                </span>
                <div>
                    <h2 id="importModalTitle" class="text-lg font-bold text-white flex items-center gap-2">
                        Importar & Exportar Tarefas
                    </h2>
                    <p class="text-xs text-slate-400">Edite tarefas em lote via planilha ou cadastre rapidamente a partir de listas.</p>
                </div>
            </div>
            <button type="button" class="kd-icon-btn" onclick="KD.closeImportModal()" aria-label="Fechar (Esc)">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Seletor de Abas: Planilha (CSV / Excel) vs Lista Rápida (.txt) -->
        <div class="flex items-center gap-2 border-b border-slate-800 pb-3 mb-4 select-none">
            <button type="button" id="tabBtnSpreadsheet" onclick="KD.switchImportTab('spreadsheet')"
                    class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm">
                <i data-lucide="file-spreadsheet" class="w-4 h-4"></i>
                <span>Planilha (CSV / Excel)</span>
            </button>
            <button type="button" id="tabBtnText" onclick="KD.switchImportTab('text')"
                    class="px-4 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-2 text-slate-400 hover:text-white hover:bg-slate-800/80">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                <span>Lista Rápida (.txt)</span>
            </button>
        </div>

        <!-- ============================================================== -->
        <!-- ABA 1: IMPORTAÇÃO E EXPORTAÇÃO POR PLANILHA (CSV)              -->
        <!-- ============================================================== -->
        <div id="importTabSpreadsheet" class="space-y-4">
            
            <!-- Ações Rápidas: Download de Modelo e Exportação Atual -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-2xl bg-indigo-500/5 border border-indigo-500/20">
                <div class="space-y-0.5">
                    <span class="text-xs font-bold text-white flex items-center gap-1.5">
                        <i data-lucide="info" class="w-3.5 h-3.5 text-indigo-400"></i> Edição em Lote via Planilha
                    </span>
                    <p class="text-[11px] text-slate-400">Exporte os dados atuais para o Excel, ajuste os campos e reimporte para atualizar em massa.</p>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <a href="<?= url('/api/tarefas?action=template_csv') ?>" download class="px-3 py-1.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-slate-200 text-xs font-semibold flex items-center gap-1.5 transition border border-slate-700/60 shadow-sm">
                        <i data-lucide="file-down" class="w-3.5 h-3.5 text-indigo-400"></i>
                        <span>Baixar Modelo Vazio</span>
                    </a>
                    <a href="<?= url('/api/tarefas?action=export_csv') ?>" download class="px-3 py-1.5 rounded-xl bg-indigo-600/30 hover:bg-indigo-600/50 text-indigo-200 text-xs font-semibold flex items-center gap-1.5 transition border border-indigo-500/30 shadow-sm">
                        <i data-lucide="download" class="w-3.5 h-3.5 text-indigo-300"></i>
                        <span>Exportar Tarefas Atuais</span>
                    </a>
                </div>
            </div>

            <!-- Opções de Substituição vs Manter (Conforme solicitado) -->
            <div class="p-3.5 rounded-2xl bg-slate-900/60 border border-slate-800 space-y-2">
                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-300 flex items-center gap-1.5">
                    <i data-lucide="sliders" class="w-3.5 h-3.5 text-indigo-400"></i> Modo de Tratamento de Dados
                </span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <label class="flex items-start gap-3 p-3 rounded-xl bg-slate-950/80 border border-indigo-500/40 cursor-pointer transition hover:border-indigo-400 select-none group">
                        <input type="radio" name="spreadsheet_mode" id="modeOverwrite" value="overwrite" checked class="text-indigo-600 focus:ring-0 mt-0.5">
                        <div class="space-y-0.5">
                            <span class="block font-bold text-white group-hover:text-indigo-300 transition">Substituir dados existentes</span>
                            <span class="block text-[11px] text-slate-400 leading-relaxed">Se a linha na planilha tiver um <strong>ID existente</strong>, atualiza a tarefa no sistema. Linhas sem ID serão criadas como novas.</span>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 rounded-xl bg-slate-950/80 border border-slate-800 cursor-pointer transition hover:border-slate-700 select-none group">
                        <input type="radio" name="spreadsheet_mode" id="modeKeep" value="keep" class="text-indigo-600 focus:ring-0 mt-0.5">
                        <div class="space-y-0.5">
                            <span class="block font-bold text-white group-hover:text-indigo-300 transition">Manter existentes (Apenas criar novas)</span>
                            <span class="block text-[11px] text-slate-400 leading-relaxed">Não altera nenhuma tarefa existente no sistema. Cadastra todas as linhas da planilha como tarefas novas.</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Upload da Planilha -->
            <div class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-slate-300 flex items-center gap-1.5">
                        <i data-lucide="upload-cloud" class="w-3.5 h-3.5 text-indigo-400"></i> Carregar Arquivo de Planilha (.CSV)
                    </span>
                    <span class="text-[11px] text-slate-400">Compatível com Excel, Google Sheets e LibreOffice</span>
                </div>

                <div id="spreadsheetDropZone" onclick="document.getElementById('spreadsheetFileInput').click()"
                     class="border-2 border-dashed border-slate-700/80 hover:border-indigo-500/60 rounded-2xl p-6 text-center cursor-pointer transition bg-slate-950/50 hover:bg-slate-900/40 group">
                    <input type="file" id="spreadsheetFileInput" accept=".csv,text/csv,text/comma-separated-values,application/vnd.ms-excel" class="hidden" onchange="KD.handleSpreadsheetFile(event)">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center mx-auto mb-3 group-hover:scale-105 transition-transform">
                        <i data-lucide="file-up" class="w-6 h-6"></i>
                    </div>
                    <p class="text-xs font-semibold text-white mb-1">Clique para selecionar ou arraste sua planilha aqui</p>
                    <p class="text-[11px] text-slate-500" id="spreadsheetFileName">Formatos aceitos: .csv (separado por ponto e vírgula ou vírgula)</p>
                </div>

                <!-- Opção de colar tabela/CSV diretamente -->
                <div class="pt-1">
                    <button type="button" onclick="KD.toggleSpreadsheetPaste()" class="text-[11px] text-indigo-400 hover:text-indigo-300 font-semibold flex items-center gap-1 transition">
                        <i data-lucide="clipboard-copy" class="w-3.5 h-3.5"></i>
                        <span id="btnToggleSpreadsheetPasteLabel">Ou colar texto copiado do Excel / Sheets</span>
                    </button>
                    <div id="spreadsheetPasteWrap" class="mt-2 space-y-2" hidden>
                        <textarea id="spreadsheetPasteInput" rows="4" placeholder="Cole aqui o conteúdo copiado de sua planilha (com cabeçalhos)..."
                                  class="w-full p-3 rounded-xl bg-slate-950/80 border border-slate-800 text-slate-200 font-mono text-xs leading-relaxed focus:outline-none focus:border-indigo-500 transition resize-y"></textarea>
                        <div class="flex justify-end">
                            <button type="button" onclick="KD.parsePastedCsv()" class="px-3 py-1.5 rounded-xl bg-indigo-600/30 hover:bg-indigo-600/50 text-indigo-200 text-xs font-semibold flex items-center gap-1.5 transition border border-indigo-500/30">
                                <i data-lucide="check" class="w-3.5 h-3.5"></i> Processar Texto Colado
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pré-visualização da Planilha Parseada -->
            <div id="spreadsheetPreviewSection" class="space-y-2" hidden>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between px-1 gap-2">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-300">Resumo da Planilha</span>
                        <span id="csvTotalBadge" class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">0 tarefas</span>
                        <span id="csvUpdateBadge" class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-300 border border-blue-500/30" hidden>0 atualizações</span>
                        <span id="csvCreateBadge" class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 border border-emerald-500/30" hidden>0 novas</span>
                    </div>
                    <span class="text-[11px] text-slate-400">Verifique a tabela antes de confirmar</span>
                </div>

                <div class="max-h-60 overflow-y-auto rounded-2xl border border-slate-800 bg-slate-950/70">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="sticky top-0 bg-slate-900 border-b border-slate-800 text-[10px] font-bold uppercase text-slate-400 tracking-wider">
                            <tr>
                                <th class="p-2.5">Ação</th>
                                <th class="p-2.5">Título</th>
                                <th class="p-2.5">Cliente</th>
                                <th class="p-2.5">Coluna</th>
                                <th class="p-2.5">Prazo</th>
                                <th class="p-2.5">Horário</th>
                                <th class="p-2.5">Prioridade</th>
                            </tr>
                        </thead>
                        <tbody id="spreadsheetPreviewBody" class="divide-y divide-slate-800/60 text-slate-300">
                            <!-- Renderizado via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Seção de Reset de Tarefas (Zerar Quadro) -->
            <div class="mt-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 space-y-2">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-0.5">
                        <span class="text-xs font-bold text-rose-300 flex items-center gap-1.5">
                            <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-400"></i> Opção de Reset do Quadro (Apagar Todas as Tarefas)
                        </span>
                        <p class="text-[11px] text-slate-400">
                            Apaga definitivamente todas as tarefas, checklists, comentários e histórico de execuções. Mantém suas empresas, equipe e colunas do Kanban intactos.
                        </p>
                    </div>
                    <button type="button" onclick="KD.openResetTasksModal()" class="px-3.5 py-2 rounded-xl bg-rose-600/25 hover:bg-rose-600/40 text-rose-200 text-xs font-bold flex items-center gap-1.5 transition border border-rose-500/30 shadow-sm flex-shrink-0">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5 text-rose-400"></i>
                        <span>Resetar Todas as Tarefas</span>
                    </button>
                </div>
            </div>

            <!-- Rodapé / Ações Planilha -->
            <div class="flex items-center justify-between pt-3 border-t border-slate-800">
                <button type="button" class="kd-btn kd-glass text-xs" onclick="KD.closeImportModal()">
                    Cancelar
                </button>
                <button type="button" id="btnSubmitSpreadsheet" onclick="KD.submitSpreadsheetImport()" class="kd-btn kd-btn--primary text-xs flex items-center gap-2" disabled>
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span id="btnSubmitSpreadsheetLabel">Importar Planilha</span>
                </button>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- ABA 2: LISTA RÁPIDA EM TEXTO (.TXT)                           -->
        <!-- ============================================================== -->
        <div id="importTabText" class="space-y-4" hidden>
            <form id="bulkImportForm" onsubmit="KD.submitBulkImport(event)" class="space-y-4">
                
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

                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-slate-300 flex items-center gap-1.5">
                            <i data-lucide="file-text" class="w-3.5 h-3.5 text-indigo-400"></i> Lista de Tarefas (1 por linha)
                        </span>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="KD.fillImportExample()" class="text-[11px] text-indigo-400 hover:text-indigo-300 underline underline-offset-2 transition">
                                Preencher exemplo
                            </button>
                            <span class="text-slate-600">&bull;</span>
                            <label class="text-[11px] text-indigo-400 hover:text-indigo-300 underline underline-offset-2 cursor-pointer flex items-center gap-1">
                                <i data-lucide="upload" class="w-3 h-3"></i> Carregar .txt
                                <input type="file" id="bulkFileInput" accept=".txt,text/plain" class="hidden" onchange="KD.handleBulkFileUpload(event)">
                            </label>
                        </div>
                    </div>

                    <div id="bulkDropZone" class="relative group">
                        <textarea id="bulkTextInput" rows="6" 
                            oninput="KD.parseBulkInputDebounced()" 
                            placeholder="- Ajustar Lamaki - Hoje&#10;- Subir novos posts da PS Advogados - Amanhã&#10;- Criar campanha de hotel para o Vila - 12/10/2026&#10;- Impulsionar o Youtube do Vila - Segunda" 
                            class="w-full p-3.5 rounded-2xl bg-slate-950/80 border border-slate-800 text-slate-200 font-mono text-xs leading-relaxed focus:outline-none focus:border-indigo-500 transition shadow-inner resize-y"></textarea>
                    </div>
                </div>

                <div id="bulkPreviewSection" class="space-y-2" hidden>
                    <div class="flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-300">Prévia da Lista</span>
                            <span id="bulkCountBadge" class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">0 tarefas</span>
                        </div>
                        <span class="text-[11px] text-slate-400">Desmarque ou ajuste antes de criar</span>
                    </div>

                    <div class="max-h-48 overflow-y-auto rounded-2xl border border-slate-800 bg-slate-950/50 p-2 divide-y divide-slate-800/60" id="bulkPreviewList">
                        <!-- Renderizado via JS -->
                    </div>
                </div>

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
</div>

<!-- Modal de Confirmação de Reset de Tarefas -->
<div id="resetTasksModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="resetModalTitle" hidden style="z-index:999999">
    <div class="kd-modal__panel kd-modal__panel--md kd-glass kd-glass--raised border border-rose-500/30">
        <!-- Header -->
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-rose-500/20">
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-rose-500/20 border border-rose-500/30 text-rose-400 flex items-center justify-center">
                    <i data-lucide="alert-octagon" class="w-5 h-5 text-rose-400"></i>
                </span>
                <div>
                    <h3 id="resetModalTitle" class="text-base font-bold text-white flex items-center gap-2">
                        Resetar Todas as Tarefas
                    </h3>
                    <p class="text-xs text-rose-300 mt-0.5">Ação destrutiva e irreversível</p>
                </div>
            </div>
            <button type="button" class="kd-icon-btn" onclick="KD.closeResetTasksModal()" aria-label="Fechar">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="space-y-3 text-xs text-slate-300 py-1">
            <p class="leading-relaxed">
                Você está prestes a <strong>apagar todas as tarefas</strong> cadastradas no KanbanDoo. Todos os dados associados a tarefas (subtarefas/checklists, comentários, históricos, cronômetros e anexos) serão excluídos permanentemente.
            </p>
            <div class="p-3 rounded-xl bg-slate-900/80 border border-slate-800 text-[11px] text-slate-400 space-y-1">
                <p class="text-emerald-400 font-semibold flex items-center gap-1">
                    <i data-lucide="check" class="w-3.5 h-3.5"></i> O que será mantido intacto:
                </p>
                <p>&bull; Suas empresas e clientes cadastrados</p>
                <p>&bull; Usuários, membros da equipe e permissões</p>
                <p>&bull; Colunas e configurações do Kanban</p>
                <p>&bull; Anotações e base de conhecimento</p>
            </div>
            <div>
                <label for="resetConfirmInput" class="block font-bold text-slate-200 mb-1">
                    Para confirmar, digite <span class="text-rose-400 font-mono tracking-wider">RESETAR</span> abaixo:
                </label>
                <input type="text" id="resetConfirmInput" placeholder="Digite RESETAR" 
                       class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-700 text-white font-mono text-center uppercase focus:outline-none focus:border-rose-500 transition">
            </div>
        </div>

        <div class="flex items-center justify-between pt-4 mt-3 border-t border-slate-800 text-xs">
            <button type="button" class="kd-btn kd-glass" onclick="KD.closeResetTasksModal()">
                Cancelar
            </button>
            <button type="button" id="btnConfirmResetTasks" onclick="KD.submitResetTasks()" 
                    class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold flex items-center gap-1.5 transition shadow-lg shadow-rose-600/30">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
                <span id="btnConfirmResetLabel">Sim, Apagar Todas as Tarefas</span>
            </button>
        </div>
    </div>
</div>
