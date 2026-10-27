<?php
/**
 * Modal de criação e edição de tarefa.
 * Espera $activeClients, $teamUsers e $kanbanStages vindos do footer.
 */
?>
<div id="taskModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="taskModalTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--xl kd-glass kd-glass--raised">

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
            <input type="hidden" id="task_is_calendar_event" name="is_calendar_event" value="0">
            <div id="showInKanbanWrap" class="p-2.5 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-between" hidden>
                <label for="task_show_in_kanban" class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-slate-200">
                    <input type="checkbox" id="task_show_in_kanban" name="show_in_kanban" value="1" checked class="rounded text-indigo-600 focus:ring-0">
                    <span>Exibir também no Quadro Kanban</span>
                </label>
                <span class="text-[11px] text-slate-400">Se desmarcado, fica visível apenas no Calendário</span>
            </div>

            <!-- Seletor de Modelo de Tarefa (visível na criação de nova tarefa) -->
            <div id="taskTemplateSelectWrap" class="p-3 rounded-xl bg-indigo-500/10 border border-indigo-500/20">
                <label for="task_template_id" class="block text-xs font-bold text-indigo-300 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
                    <i data-lucide="copy-check" class="w-3.5 h-3.5"></i> Usar Modelo de Tarefa
                </label>
                <select id="task_template_id" onchange="KD.applyTemplateToForm(this.value)" class="kd-field text-xs">
                    <option value="">-- Tarefa em branco (sem modelo) --</option>
                    <?php if (!empty($taskTemplates)): ?>
                        <?php foreach ($taskTemplates as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= e($t['title']) ?><?= !empty($t['estimated_minutes']) ? ' (' . $t['estimated_minutes'] . ' min)' : '' ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div>
                <label for="task_title" class="block text-xs font-semibold uppercase tracking-wider mb-2">Título *</label>
                <input type="text" id="task_title" name="title" maxlength="200" autocomplete="off"
                       placeholder="Ex: Criar criativos para a campanha de Páscoa"
                       class="kd-field" aria-describedby="error_title">
                <p id="error_title" class="kd-inline-error" role="alert" hidden></p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="task_client_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Empresa / Cliente</label>
                        <button type="button" onclick="KD.toggleQuickAddClient()" class="text-[11px] font-semibold text-indigo-400 hover:text-indigo-300 transition flex items-center gap-1">
                            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                            <span>Nova empresa</span>
                        </button>
                    </div>

                    <select id="task_client_id" name="client_id" class="kd-field" onchange="KD.onTaskFormClientChange(this)">
                        <option value="" data-category="C">Sem empresa vinculada</option>
                        <?php foreach ($activeClients as $c): 
                            $cat = $c['category'] ?? 'C';
                        ?>
                            <option value="<?= (int)$c['id'] ?>" data-category="<?= e($cat) ?>"><?= e($c['company_name']) ?><?= !empty($cat) ? ' (' . e($cat) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="task_client_category" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">Categoria da Empresa</label>
                        <span id="task_client_category_badge" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">Auto</span>
                    </div>
                    <select id="task_client_category" name="client_category" class="kd-field font-semibold">
                        <option value="A+">⭐ Categoria A+ (Estratégico / VIP)</option>
                        <option value="A">🟣 Categoria A (Prioritário)</option>
                        <option value="B">🔵 Categoria B (Padrão Alto)</option>
                        <option value="C" selected>🟢 Categoria C (Normal / Regular)</option>
                        <option value="D">⚪ Categoria D (Baixa Demanda)</option>
                    </select>
                </div>

                <div>
                    <label for="task_stage_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">Coluna *</label>
                    <select id="task_stage_id" name="stage_id" required class="kd-field">
                        <?php 
                        $hasAFazer = false;
                        foreach ($kanbanStages as $stg) {
                            if (stripos($stg['name'], 'fazer') !== false || stripos($stg['name'], 'to do') !== false) {
                                $hasAFazer = true;
                                break;
                            }
                        }
                        foreach ($kanbanStages as $idx => $stg): 
                            $isSel = $hasAFazer 
                                ? (stripos($stg['name'], 'fazer') !== false || stripos($stg['name'], 'to do') !== false)
                                : ($idx === 0);
                        ?>
                            <option value="<?= (int)$stg['id'] ?>" <?= $isSel ? 'selected' : '' ?>><?= e($stg['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Mini painel de Cadastro Rápido de Empresa (100% de largura, rico e balanceado) -->
            <div id="quickAddClientWrap" class="p-4 rounded-xl bg-slate-900/95 border border-indigo-500/40 shadow-xl space-y-3 animate-fade-in" hidden>
                <div class="flex items-center justify-between pb-2 border-b border-indigo-500/20">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-indigo-500/15 border border-indigo-500/30 text-indigo-400 flex items-center justify-center">
                            <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
                        </span>
                        <div>
                            <span class="text-xs font-bold text-slate-100 uppercase tracking-wider">Cadastrar Nova Empresa</span>
                            <span class="text-[10px] text-slate-400 ml-2">Vinculação imediata à tarefa</span>
                        </div>
                    </div>
                    <button type="button" onclick="KD.toggleQuickAddClient(false)" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition" title="Fechar">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div class="sm:col-span-1">
                        <label class="block text-[11px] font-semibold text-slate-300 uppercase tracking-wider mb-1">Nome da Empresa *</label>
                        <input type="text" id="quick_client_company" placeholder="Ex: Pizzani Restaurante" class="kd-field text-xs w-full" style="min-height:36px" autocomplete="off">
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-[11px] font-semibold text-slate-300 uppercase tracking-wider mb-1">Categoria</label>
                        <select id="quick_client_category" class="kd-field text-xs w-full font-semibold" style="min-height:36px">
                            <option value="A+">⭐ Categoria A+</option>
                            <option value="A">🟣 Categoria A</option>
                            <option value="B">🔵 Categoria B</option>
                            <option value="C" selected>🟢 Categoria C</option>
                            <option value="D">⚪ Categoria D</option>
                        </select>
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-[11px] font-semibold text-slate-300 uppercase tracking-wider mb-1">Pessoa de Contato</label>
                        <input type="text" id="quick_client_contact" placeholder="Ex: Carlos Oliveira" class="kd-field text-xs w-full" style="min-height:36px" autocomplete="off">
                    </div>
                    <div class="sm:col-span-1">
                        <label class="block text-[11px] font-semibold text-slate-300 uppercase tracking-wider mb-1">WhatsApp / Telefone</label>
                        <input type="text" id="quick_client_phone" placeholder="Ex: (11) 98888-7777" class="kd-field text-xs w-full" style="min-height:36px" autocomplete="off">
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between pt-2 border-t border-slate-800/80 gap-2">
                    <span class="text-[11px] text-slate-400">
                        <i data-lucide="info" class="w-3.5 h-3.5 inline mr-1 text-slate-500"></i>
                        Poderá ser gerenciada depois em <a href="<?= url('/clientes') ?>" target="_blank" class="text-indigo-400 underline font-medium">Empresas</a>.
                    </span>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" onclick="KD.toggleQuickAddClient(false)" class="px-3 py-1.5 text-xs font-medium text-slate-400 hover:text-slate-200 rounded-lg hover:bg-slate-800 transition">
                            Cancelar
                        </button>
                        <button type="button" onclick="KD.submitQuickClient()" id="btnSubmitQuickClient" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-lg transition flex items-center gap-1.5 shadow-md shadow-indigo-600/30">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            <span>Cadastrar e Vincular</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="task_priority" class="block text-xs font-semibold uppercase tracking-wider mb-2">Prioridade</label>
                    <select id="task_priority" name="priority" class="kd-field">
                        <option value="low" selected>Baixa</option>
                        <option value="medium">Média</option>
                        <option value="high">Alta</option>
                        <option value="urgent">Urgente</option>
                    </select>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="task_due_date" class="block text-xs font-semibold uppercase tracking-wider">Prazo de entrega</label>
                        <label class="flex items-center gap-1.5 cursor-pointer text-[11px] text-indigo-400 hover:text-indigo-300 font-semibold select-none">
                            <input type="checkbox" id="task_has_time" onchange="KD.toggleTimeInput(this.checked)" class="rounded text-indigo-600 focus:ring-0">
                            <span>Ativar horário</span>
                        </label>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="date" id="task_due_date" name="due_date" class="kd-field flex-1" aria-describedby="error_due_date">
                        <div id="task_time_wrap" class="flex items-center gap-1.5" hidden>
                            <input type="time" id="task_due_time" name="due_time" class="kd-field w-28 text-center" style="min-height:42px" placeholder="00:00">
                        </div>
                    </div>
                    <p id="error_due_date" class="kd-inline-error" role="alert" hidden></p>
                </div>

                <div>
                    <label for="task_estimated_minutes" class="block text-xs font-semibold uppercase tracking-wider mb-2 flex items-center justify-between">
                        <span>Tempo estim. (min)</span>
                        <span id="task_estimated_preview" class="text-[11px] text-indigo-400 font-normal lowercase" hidden></span>
                    </label>
                    <input type="number" id="task_estimated_minutes" name="estimated_minutes" min="1" step="5" placeholder="Ex: 45" class="kd-field" oninput="KD.updateEstimatedPreview(this.value)">
                    <div class="flex items-center gap-1 mt-1.5 flex-wrap">
                        <button type="button" class="text-[10px] px-2 py-0.5 rounded-md bg-slate-800/60 hover:bg-slate-700 text-slate-300 border border-slate-700/40 transition" onclick="KD.setEstimatedMinutes(15)">15m</button>
                        <button type="button" class="text-[10px] px-2 py-0.5 rounded-md bg-slate-800/60 hover:bg-slate-700 text-slate-300 border border-slate-700/40 transition" onclick="KD.setEstimatedMinutes(30)">30m</button>
                        <button type="button" class="text-[10px] px-2 py-0.5 rounded-md bg-slate-800/60 hover:bg-slate-700 text-slate-300 border border-slate-700/40 transition" onclick="KD.setEstimatedMinutes(60)">1h</button>
                        <button type="button" class="text-[10px] px-2 py-0.5 rounded-md bg-slate-800/60 hover:bg-slate-700 text-slate-300 border border-slate-700/40 transition" onclick="KD.setEstimatedMinutes(120)">2h</button>
                        <button type="button" class="text-[10px] px-2 py-0.5 rounded-md bg-slate-800/60 hover:bg-slate-700 text-slate-300 border border-slate-700/40 transition" onclick="KD.setEstimatedMinutes(240)">4h</button>
                    </div>
                </div>
            </div>

            <!-- Tarefa Particular (Privada) -->
            <div class="p-3.5 rounded-xl border border-slate-700/50 bg-slate-800/30">
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold uppercase tracking-wider text-slate-200">
                        <input type="checkbox" id="task_is_private" name="is_private" value="1" class="rounded">
                        <i data-lucide="lock" class="w-4 h-4 text-amber-400"></i>
                        <span>Tarefa Particular</span>
                    </label>
                    <span class="text-[11px] text-slate-400">Visível apenas no seu painel (oculta em links públicos)</span>
                </div>
            </div>

            <!-- Recorrência de Tarefa -->
            <div class="p-3.5 rounded-xl border border-slate-700/50 bg-slate-800/30 space-y-3">
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold uppercase tracking-wider text-slate-200">
                        <input type="checkbox" id="task_is_recurring" name="is_recurring" value="1" onchange="KD.toggleRecurrenceOptions(this.checked)" class="rounded">
                        <i data-lucide="repeat" class="w-4 h-4 text-indigo-400"></i>
                        <span>Tornar Tarefa Recorrente</span>
                    </label>
                    <span class="text-[11px] text-slate-400">Gera nova tarefa ao concluir</span>
                </div>

                <div id="recurrenceFields" class="space-y-3 pt-2 border-t border-slate-700/40" hidden>
                    <div>
                        <label for="task_recurrence_type" class="block text-xs font-medium text-slate-300 mb-1">Frequência da recorrência</label>
                        <select id="task_recurrence_type" name="recurrence_type" onchange="KD.onRecurrenceTypeChange(this.value)" class="kd-field text-xs">
                            <option value="daily">Diária (todo dia)</option>
                            <option value="weekly" selected>Semanal (toda semana)</option>
                            <option value="biweekly">Quinzenal (a cada 15 dias)</option>
                            <option value="monthly">Mensal (todo mês)</option>
                            <option value="yearly">Anual (todo ano)</option>
                            <option value="custom">Personalizada...</option>
                        </select>
                    </div>

                    <!-- Opções de Recorrência Personalizada -->
                    <div id="recurrenceCustomWrap" class="space-y-2 p-2.5 rounded-lg bg-slate-900/50 border border-slate-800" hidden>
                        <div class="flex items-center gap-4 text-xs mb-2">
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="recurrence_custom_mode" id="mode_weekdays" value="weekdays" checked onchange="KD.toggleCustomRecurrenceMode('weekdays')">
                                <span>Dias da semana</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer">
                                <input type="radio" name="recurrence_custom_mode" id="mode_monthday" value="monthday" onchange="KD.toggleCustomRecurrenceMode('monthday')">
                                <span>Dia fixo do mês</span>
                            </label>
                        </div>

                        <!-- Modo Dias da Semana -->
                        <div id="customWeekdaysWrap">
                            <p class="text-[11px] text-slate-400 mb-1.5">Repetir nos dias selecionados:</p>
                            <div class="flex items-center gap-1 flex-wrap" id="recurrenceWeekdaysList">
                                <button type="button" class="kd-day-btn" data-day="1" onclick="KD.toggleWeekdayBtn(this)">Seg</button>
                                <button type="button" class="kd-day-btn" data-day="2" onclick="KD.toggleWeekdayBtn(this)">Ter</button>
                                <button type="button" class="kd-day-btn" data-day="3" onclick="KD.toggleWeekdayBtn(this)">Qua</button>
                                <button type="button" class="kd-day-btn" data-day="4" onclick="KD.toggleWeekdayBtn(this)">Qui</button>
                                <button type="button" class="kd-day-btn" data-day="5" onclick="KD.toggleWeekdayBtn(this)">Sex</button>
                                <button type="button" class="kd-day-btn" data-day="6" onclick="KD.toggleWeekdayBtn(this)">Sáb</button>
                                <button type="button" class="kd-day-btn" data-day="0" onclick="KD.toggleWeekdayBtn(this)">Dom</button>
                            </div>
                        </div>

                        <!-- Modo Dia Fixo do Mês -->
                        <div id="customMonthdayWrap" hidden>
                            <label for="recurrence_monthday" class="block text-[11px] text-slate-400 mb-1">Repetir todo dia do mês:</label>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-slate-300">Dia</span>
                                <input type="number" id="recurrence_monthday" min="1" max="31" value="1" class="kd-field w-20 text-center" style="min-height:34px">
                                <span class="text-xs text-slate-300">de cada mês</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <fieldset>
                <legend class="block text-xs font-semibold uppercase tracking-wider mb-2">Responsáveis</legend>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 p-3 rounded-xl max-h-36 overflow-y-auto"
                     style="background:var(--surface-sunken);border:1px solid var(--border)">
                    <?php 
                    $curUser = current_user();
                    foreach ($teamUsers as $u): 
                        $isLoggedUser = ($curUser && (int)$curUser['id'] === (int)$u['id']);
                    ?>
                        <label class="flex items-center gap-2 text-xs cursor-pointer select-none">
                            <input type="checkbox" name="assignees[]" value="<?= (int)$u['id'] ?>" class="task-assignee-checkbox rounded" <?= $isLoggedUser ? 'checked' : '' ?>>
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

            <!-- Subtarefas / Checklist Inicial -->
            <div id="formChecklistWrap" class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-semibold uppercase tracking-wider">Subtarefas / Checklist</label>
                    <span id="formChecklistCount" class="text-[11px] text-slate-400">0 itens</span>
                </div>
                <div class="flex items-center gap-2">
                    <input type="text" id="formNewChecklistItem" placeholder="Adicionar subtarefa... (Enter para adicionar)" class="kd-field" style="min-height:36px;padding-top:0.35rem;padding-bottom:0.35rem">
                    <button type="button" onclick="KD.addFormChecklistItem()" class="kd-btn kd-btn--primary" style="min-height:36px;padding-inline:0.75rem">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                    </button>
                </div>
                <div id="formChecklistContainer" class="space-y-1 max-h-36 overflow-y-auto p-2 rounded-xl" style="background:var(--surface-sunken);border:1px solid var(--border)">
                    <p class="text-slate-500 text-xs text-center py-1 italic" id="formChecklistEmpty">Nenhuma subtarefa adicionada.</p>
                </div>
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
