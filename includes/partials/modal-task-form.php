<?php
/**
 * Modal de criação e edição de tarefa.
 * Espera $activeClients, $teamUsers e $kanbanStages vindos do footer.
 */
?>
<div id="taskModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="taskModalTitle" hidden>
    <div class="kd-modal__panel kd-glass kd-glass--raised" style="max-width:40rem">

        <div class="flex items-center justify-between gap-3 pb-4 mb-5" style="border-bottom:1px solid var(--border)">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:var(--brand-soft);color:var(--brand-strong)">
                    <i data-lucide="check-square" class="w-5 h-5"></i>
                </span>
                <div>
                    <h2 id="taskModalTitle" class="text-lg font-bold">Nova tarefa</h2>
                    <p class="text-xs text-slate-400">Os campos com * são obrigatórios</p>
                </div>
            </div>
            <button type="button" class="kd-icon-btn" onclick="KD.closeTaskForm()" aria-label="Fechar (Esc)">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="taskForm" onsubmit="KD.submitTaskForm(event)" class="space-y-4" novalidate>
            <input type="hidden" id="task_id" name="id" value="">

            <div>
                <label for="task_title" class="block text-xs font-semibold uppercase tracking-wider mb-2">Título *</label>
                <input type="text" id="task_title" name="title" maxlength="200" autocomplete="off"
                       placeholder="Ex: Criar criativos para a campanha de Páscoa"
                       class="kd-field" aria-describedby="error_title">
                <p id="error_title" class="kd-inline-error" role="alert" hidden></p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="task_client_id" class="block text-xs font-semibold uppercase tracking-wider mb-2">Cliente</label>
                    <select id="task_client_id" name="client_id" class="kd-field">
                        <option value="">Sem cliente vinculado</option>
                        <?php foreach ($activeClients as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= e($c['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="task_stage_id" class="block text-xs font-semibold uppercase tracking-wider mb-2">Coluna *</label>
                    <select id="task_stage_id" name="stage_id" required class="kd-field">
                        <?php foreach ($kanbanStages as $stg): ?>
                            <option value="<?= (int)$stg['id'] ?>"><?= e($stg['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="task_priority" class="block text-xs font-semibold uppercase tracking-wider mb-2">Prioridade</label>
                    <select id="task_priority" name="priority" class="kd-field">
                        <option value="low">Baixa</option>
                        <option value="medium" selected>Média</option>
                        <option value="high">Alta</option>
                        <option value="urgent">Urgente</option>
                    </select>
                </div>

                <div>
                    <label for="task_due_date" class="block text-xs font-semibold uppercase tracking-wider mb-2">Prazo de entrega</label>
                    <input type="date" id="task_due_date" name="due_date" class="kd-field" aria-describedby="error_due_date">
                    <p id="error_due_date" class="kd-inline-error" role="alert" hidden></p>
                </div>
            </div>

            <fieldset>
                <legend class="block text-xs font-semibold uppercase tracking-wider mb-2">Responsáveis</legend>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-3 rounded-xl max-h-36 overflow-y-auto"
                     style="background:var(--surface-sunken);border:1px solid var(--border)">
                    <?php foreach ($teamUsers as $u): ?>
                        <label class="flex items-center gap-2 text-xs cursor-pointer select-none">
                            <input type="checkbox" name="assignees[]" value="<?= (int)$u['id'] ?>" class="task-assignee-checkbox rounded">
                            <span class="truncate"><?= e($u['full_name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div>
                <label for="task_tags_input" class="block text-xs font-semibold uppercase tracking-wider mb-2">Etiquetas</label>
                <div class="kd-tag-editor" id="taskTagsEditor">
                    <!-- as etiquetas já escolhidas são inseridas aqui por kd-task.js -->
                    <input type="text" id="task_tags_input" list="tagSuggestions" autocomplete="off"
                           placeholder="Digite e pressione Enter" aria-describedby="tagsHelp">
                </div>
                <datalist id="tagSuggestions"></datalist>
                <p id="tagsHelp" class="text-[11px] text-slate-400 mt-1">
                    Enter ou vírgula adiciona. Até 8 etiquetas por tarefa.
                </p>
            </div>

            <div>
                <label for="task_description" class="block text-xs font-semibold uppercase tracking-wider mb-2">Descrição</label>
                <textarea id="task_description" name="description" rows="3"
                          placeholder="Instruções, links ou referências para a execução..."
                          class="kd-field" style="resize:vertical"></textarea>
            </div>

            <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-4"
                 style="border-top:1px solid var(--border)">
                <button type="button" id="btnDeleteTask" onclick="KD.deleteFromForm()" class="kd-btn kd-btn--danger" hidden>
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    Excluir tarefa
                </button>

                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" onclick="KD.closeTaskForm()" class="kd-btn">Cancelar</button>
                    <button type="submit" id="btnSaveTask" class="kd-btn kd-btn--primary">Salvar tarefa</button>
                </div>
            </div>
        </form>
    </div>
</div>
