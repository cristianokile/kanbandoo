/**
 * KanbanDoo - Modais de tarefa: formulário de criação/edição e painel de detalhes.
 */
window.KD = window.KD || {};

(function (KD) {
    'use strict';

    let activeTask = null;
    let detailTimerInterval = null;
    let formTags = [];
    let formChecklistItems = [];

    function el(id) {
        return document.getElementById(id);
    }

    // ===============================================================
    // Formulário de Nova / Editar Tarefa
    // ===============================================================

    function clearFormErrors() {
        document.querySelectorAll('#taskForm .kd-inline-error').forEach((node) => { node.hidden = true; });
        document.querySelectorAll('#taskForm .kd-field-invalid').forEach((node) => {
            node.classList.remove('kd-field-invalid');
            node.removeAttribute('aria-invalid');
        });
    }

    function showFormError(field, message) {
        const input = el(`task_${field}`);
        const error = el(`error_${field}`);

        if (error) {
            error.textContent = message;
            error.hidden = false;
        }
        if (input) {
            input.classList.add('kd-field-invalid');
            input.setAttribute('aria-invalid', 'true');
            input.focus();
        }
        if (!error && !input) KD.toastError(message);
    }

    // ---------------------------------------------------------------
    // Etiquetas do formulário
    // ---------------------------------------------------------------

    function renderFormTags() {
        const editor = el('taskTagsEditor');
        const input = el('task_tags_input');
        if (!editor || !input) return;

        editor.querySelectorAll('.kd-tag').forEach((node) => node.remove());

        formTags.forEach((tag, index) => {
            const chip = document.createElement('span');
            chip.className = 'kd-tag';
            chip.textContent = tag;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'kd-tag__remove';
            remove.setAttribute('aria-label', `Remover etiqueta ${tag}`);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                formTags.splice(index, 1);
                renderFormTags();
                input.focus();
            });

            chip.appendChild(remove);
            editor.insertBefore(chip, input);
        });

        input.placeholder = formTags.length >= 8 ? 'Limite de 8 etiquetas' : 'Digite e pressione Enter';
        input.disabled = formTags.length >= 8;
    }

    function addFormTag(raw) {
        const value = String(raw || '').replace(/\s+/g, ' ').trim().replace(/^#|,$/g, '').trim();
        if (!value || formTags.length >= 8) return;
        if (formTags.some((t) => t.toLowerCase() === value.toLowerCase())) return;

        formTags.push(value.slice(0, 24));
        renderFormTags();
    }

    function setupTagInput() {
        const input = el('task_tags_input');
        if (!input || input.dataset.ready === '1') return;
        input.dataset.ready = '1';

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                addFormTag(input.value);
                input.value = '';
                return;
            }
            if (event.key === 'Backspace' && input.value === '' && formTags.length > 0) {
                formTags.pop();
                renderFormTags();
            }
        });

        // Ao escolher uma sugestão do datalist o campo é preenchido de uma vez
        input.addEventListener('input', () => {
            if (input.value.endsWith(',')) {
                addFormTag(input.value);
                input.value = '';
            }
        });

        input.addEventListener('blur', () => {
            addFormTag(input.value);
            input.value = '';
        });
    }

    /** Sugestões vindas do que já existe no quadro. */
    function renderTagSuggestions() {
        const list = el('tagSuggestions');
        if (!list || !KD.board) return;
        list.innerHTML = (KD.board.allTags || [])
            .map((tag) => `<option value="${KD.escapeHtml(tag.name)}"></option>`)
            .join('');
    }

    // ---------------------------------------------------------------
    // Checklist do formulário
    // ---------------------------------------------------------------

    function renderFormChecklist() {
        const container = el('formChecklistContainer');
        const count = el('formChecklistCount');
        if (!container) return;

        if (count) {
            count.textContent = `${formChecklistItems.length} ite${formChecklistItems.length === 1 ? 'm' : 'ns'}`;
        }

        if (formChecklistItems.length === 0) {
            container.innerHTML = '<p class="text-slate-500 text-xs text-center py-1.5 italic" id="formChecklistEmpty">Nenhuma subtarefa adicionada.</p>';
            return;
        }

        container.innerHTML = formChecklistItems.map((item, idx) => `
            <div class="flex items-center justify-between gap-2 p-1.5 px-2.5 rounded-lg bg-slate-800/40 border border-slate-700/30 text-xs">
                <span class="truncate flex items-center gap-1.5 text-slate-200">
                    <i data-lucide="check" class="w-3.5 h-3.5 text-indigo-400 flex-shrink-0"></i>
                    ${KD.escapeHtml(item)}
                </span>
                <button type="button" onclick="KD.removeFormChecklistItem(${idx})" class="text-slate-400 hover:text-rose-400 transition" title="Remover subtarefa">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
            </div>
        `).join('');

        if (window.lucide) lucide.createIcons();
    }

    KD.addFormChecklistItem = function () {
        const input = el('formNewChecklistItem');
        if (!input) return;
        const text = input.value.trim();
        if (!text) return;
        formChecklistItems.push(text);
        input.value = '';
        renderFormChecklist();
        input.focus();
    };

    KD.removeFormChecklistItem = function (idx) {
        formChecklistItems.splice(idx, 1);
        renderFormChecklist();
    };

    function setupFormChecklistInput() {
        const input = el('formNewChecklistItem');
        if (!input || input.dataset.ready === '1') return;
        input.dataset.ready = '1';
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                KD.addFormChecklistItem();
            }
        });
    }

    // ---------------------------------------------------------------
    // Recorrência
    // ---------------------------------------------------------------

    KD.toggleRecurrenceOptions = function (checked) {
        const fields = el('recurrenceFields');
        if (fields) fields.hidden = !checked;
    };

    KD.onRecurrenceTypeChange = function (type) {
        const customWrap = el('recurrenceCustomWrap');
        if (customWrap) customWrap.hidden = (type !== 'custom');
    };

    KD.toggleCustomRecurrenceMode = function (mode) {
        const weekdaysWrap = el('customWeekdaysWrap');
        const monthdayWrap = el('customMonthdayWrap');
        if (weekdaysWrap) weekdaysWrap.hidden = (mode !== 'weekdays');
        if (monthdayWrap) monthdayWrap.hidden = (mode !== 'monthday');
    };

    KD.toggleWeekdayBtn = function (btn) {
        btn.classList.toggle('is-active');
    };

    function getSelectedWeekdays() {
        const btns = document.querySelectorAll('#recurrenceWeekdaysList .kd-day-btn.is-active');
        return Array.from(btns).map((b) => Number(b.dataset.day));
    }

    // ---------------------------------------------------------------
    // Modelos de Tarefa
    // ---------------------------------------------------------------

    KD.applyTemplateToForm = async function (templateId, templateData = null) {
        if (!templateId && !templateData) return;

        let tpl = templateData;
        if (!tpl && templateId) {
            try {
                const res = await fetch(`${KD.url('/api/modelos')}?id=${templateId}`);
                const data = await res.json();
                if (data.success) tpl = data.template;
            } catch (e) {
                console.error('Erro ao carregar modelo:', e);
            }
        }

        if (!tpl) return;

        if (tpl.title) el('task_title').value = tpl.title;
        if (tpl.estimated_minutes && el('task_estimated_minutes')) el('task_estimated_minutes').value = tpl.estimated_minutes;
        if (tpl.description) el('task_description').value = tpl.description;

        // Tags
        let tags = [];
        if (Array.isArray(tpl.tags_list)) {
            tags = tpl.tags_list;
        } else if (typeof tpl.tags === 'string') {
            try { tags = JSON.parse(tpl.tags); } catch (e) { tags = tpl.tags.split(',').map((s) => s.trim()).filter(Boolean); }
        } else if (Array.isArray(tpl.tags)) {
            tags = tpl.tags;
        }
        formTags = [];
        tags.forEach((tag) => addFormTag(tag));

        // Checklist
        const checklist = tpl.checklist_items || (typeof tpl.checklist === 'string' ? JSON.parse(tpl.checklist || '[]') : (tpl.checklist || []));
        formChecklistItems = [];
        checklist.forEach((item) => {
            const itemText = typeof item === 'object' ? (item.title || '') : String(item);
            if (itemText.trim()) formChecklistItems.push(itemText.trim());
        });
        renderFormChecklist();

        KD.toast(`Modelo "${tpl.title}" aplicado!`);
    };

    // ---------------------------------------------------------------
    // Abrir Formulário de Tarefa
    // ---------------------------------------------------------------

    KD.openTaskForm = async function (taskId, stageId, initialTemplate = null, initialDueDate = null) {
        try {
            const form = el('taskForm');
            if (!form) return;

            form.reset();
            clearFormErrors();
            setupTagInput();
            setupFormChecklistInput();
            renderTagSuggestions();
            formTags = [];
            renderFormTags();
            formChecklistItems = [];
            renderFormChecklist();

            el('task_id').value = '';
            if (el('task_estimated_minutes')) el('task_estimated_minutes').value = '';
            KD.updateEstimatedPreview('');
            document.querySelectorAll('.task-assignee-checkbox').forEach((box) => { box.checked = false; });
            el('btnDeleteTask').hidden = true;

            // Resetar opções de recorrência
            const recBox = el('task_is_recurring');
            if (recBox) {
                recBox.checked = false;
                KD.toggleRecurrenceOptions(false);
            }
            const recType = el('task_recurrence_type');
            if (recType) recType.value = 'weekly';
            KD.onRecurrenceTypeChange('weekly');
            const modeWeekdays = el('mode_weekdays');
            if (modeWeekdays) modeWeekdays.checked = true;
            KD.toggleCustomRecurrenceMode('weekdays');
            document.querySelectorAll('#recurrenceWeekdaysList .kd-day-btn').forEach(b => b.classList.remove('is-active'));
            const monthdayInput = el('recurrence_monthday');
            if (monthdayInput) monthdayInput.value = '1';

            const templateWrap = el('taskTemplateSelectWrap');
            const templateSelect = el('task_template_id');

            if (!taskId) {
                el('taskModalTitle').textContent = 'Nova tarefa';
                if (stageId) el('task_stage_id').value = stageId;
                if (initialDueDate && el('task_due_date')) el('task_due_date').value = initialDueDate;
                if (templateWrap) templateWrap.hidden = false;
                if (templateSelect) templateSelect.value = '';

                if (initialTemplate) {
                    if (templateSelect && initialTemplate.id) templateSelect.value = initialTemplate.id;
                    await KD.applyTemplateToForm(initialTemplate.id, initialTemplate);
                }

                KD.openModal('taskModal', { focus: '#task_title' });
                return;
            }

            if (templateWrap) templateWrap.hidden = true;

            const data = await KD.api.get(`${KD.url('/api/tarefas')}?id=${taskId}`);
            const task = data.task;

            el('task_id').value = task.id;
            el('task_title').value = task.title || '';
            el('task_client_id').value = task.client_id || '';
            el('task_stage_id').value = task.stage_id || '';
            el('task_priority').value = task.priority || 'medium';
            el('task_due_date').value = task.due_date || '';
            el('task_description').value = task.description || '';
            if (el('task_estimated_minutes')) el('task_estimated_minutes').value = task.estimated_minutes || '';

            // Recorrência existente
            const isRec = Number(task.is_recurring) === 1;
            if (recBox) {
                recBox.checked = isRec;
                KD.toggleRecurrenceOptions(isRec);
            }
            if (recType && task.recurrence_type) {
                recType.value = task.recurrence_type;
                KD.onRecurrenceTypeChange(task.recurrence_type);
            }
            if (task.recurrence_config) {
                try {
                    const cfg = typeof task.recurrence_config === 'string' ? JSON.parse(task.recurrence_config) : task.recurrence_config;
                    if (cfg && cfg.type === 'weekdays' && Array.isArray(cfg.days)) {
                        if (modeWeekdays) modeWeekdays.checked = true;
                        KD.toggleCustomRecurrenceMode('weekdays');
                        document.querySelectorAll('#recurrenceWeekdaysList .kd-day-btn').forEach(b => {
                            b.classList.toggle('is-active', cfg.days.includes(Number(b.dataset.day)));
                        });
                    } else if (cfg && cfg.type === 'monthday' && cfg.day) {
                        const modeMonthday = el('mode_monthday');
                        if (modeMonthday) modeMonthday.checked = true;
                        KD.toggleCustomRecurrenceMode('monthday');
                        if (monthdayInput) monthdayInput.value = cfg.day;
                    }
                } catch (e) {}
            }

            formTags = (task.tags || []).map((tag) => tag.name);
            renderFormTags();

            const assigned = (task.assignees || []).map((a) => Number(a.id));
            document.querySelectorAll('.task-assignee-checkbox').forEach((box) => {
                box.checked = assigned.includes(Number(box.value));
            });

            el('taskModalTitle').textContent = 'Editar tarefa';
            el('btnDeleteTask').hidden = false;

            KD.openModal('taskModal', { focus: '#task_title' });
        } catch (error) {
            console.error('Erro ao abrir formulário de tarefa:', error);
            KD.toastError(error.message || 'Erro ao abrir formulário');
        }
    };

    KD.closeTaskForm = function () {
        KD.closeModal('taskModal');
    };

    KD.submitTaskForm = async function (event) {
        event.preventDefault();
        clearFormErrors();

        const title = el('task_title').value.trim();
        if (!title) {
            showFormError('title', 'Informe um título para a tarefa.');
            return;
        }

        const assignees = Array.from(document.querySelectorAll('.task-assignee-checkbox:checked'))
            .map((box) => Number(box.value));

        const isRecurring = el('task_is_recurring')?.checked ? 1 : 0;
        let recurrenceType = null;
        let recurrenceConfig = null;

        if (isRecurring) {
            recurrenceType = el('task_recurrence_type')?.value || 'weekly';
            if (recurrenceType === 'custom') {
                const mode = document.querySelector('input[name="recurrence_custom_mode"]:checked')?.value || 'weekdays';
                if (mode === 'weekdays') {
                    recurrenceConfig = JSON.stringify({ type: 'weekdays', days: getSelectedWeekdays() });
                } else {
                    recurrenceConfig = JSON.stringify({ type: 'monthday', day: Number(el('recurrence_monthday')?.value || 1) });
                }
            }
        }

        const payload = {
            action: 'save',
            id: el('task_id').value || null,
            title,
            client_id: el('task_client_id').value || null,
            stage_id: el('task_stage_id').value,
            priority: el('task_priority').value,
            due_date: el('task_due_date').value || null,
            estimated_minutes: el('task_estimated_minutes')?.value || null,
            description: el('task_description').value,
            assignees,
            tags: formTags,
            is_recurring: isRecurring,
            recurrence_type: recurrenceType,
            recurrence_config: recurrenceConfig,
            initial_checklist: formChecklistItems,
        };

        const saveBtn = el('btnSaveTask');
        saveBtn.disabled = true;
        saveBtn.dataset.label = saveBtn.textContent;
        saveBtn.textContent = 'Salvando...';

        try {
            const result = await KD.api.tasks(payload);
            KD.closeTaskForm();
            await KD.loadBoard({ silent: true });
            KD.toast(result.created ? 'Tarefa criada.' : 'Alterações salvas.');
        } catch (error) {
            if (error.field) showFormError(error.field, error.message);
            else KD.toastError(error.message);
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = saveBtn.dataset.label || 'Salvar tarefa';
        }
    };

    KD.deleteFromForm = async function () {
        const taskId = Number(el('task_id').value || 0);
        if (!taskId) return;

        try {
            const result = await KD.api.tasks({ action: 'delete', task_id: taskId });
            KD.closeTaskForm();
            await KD.loadBoard({ silent: true });
            KD.toast(`"${result.title || 'Tarefa'}" excluída.`, {
                undo: async () => {
                    await KD.api.tasks({ action: 'undelete', task_id: taskId });
                    await KD.loadBoard({ silent: true });
                    KD.toast('Exclusão desfeita.', { tone: 'info' });
                },
            });
        } catch (error) {
            KD.toastError(error.message);
        }
    };

    // ===============================================================
    // Painel de Detalhes
    // ===============================================================

    KD.openTaskDetail = async function (taskId) {
        try {
            const data = await KD.api.get(`${KD.url('/api/tarefas')}?id=${taskId}`);
            activeTask = data.task;
            renderDetail(activeTask);
            KD.openModal('taskDetailModal');
            startDetailTimer();
        } catch (error) {
            KD.toastError(error.message);
        }
    };

    KD.closeTaskDetail = function () {
        stopDetailTimer();
        KD.closeModal('taskDetailModal');
        activeTask = null;
    };

    function setText(id, value) {
        const node = el(id);
        if (node) node.textContent = value;
    }

    function renderDetail(task) {
        setText('detailTitle', task.title || '');
        setText('detailIdBadge', `#${task.id}`);

        const description = el('detailDescription');
        if (description) {
            description.textContent = task.description || 'Sem descrição.';
            description.classList.toggle('italic', !task.description);
        }

        // Cliente
        const chip = el('detailClientChip');
        if (chip) {
            if (task.client_company) {
                chip.hidden = false;
                chip.textContent = task.client_company;
            } else {
                chip.hidden = true;
            }
        }

        // Etiquetas
        const tagsEl = el('detailTags');
        if (tagsEl) {
            const tags = task.tags || [];
            tagsEl.hidden = tags.length === 0;
            tagsEl.innerHTML = tags.map((tag) => `
                <span class="kd-tag">${KD.escapeHtml(tag.name)}</span>
            `).join('');
        }

        // Prioridade, coluna e prazo
        const priority = el('detailPriority');
        if (priority) {
            priority.textContent = (task.priority_info && task.priority_info.label) || 'Média';
            priority.dataset.tone = (task.priority_info && task.priority_info.tone) || 'medium';
        }
        setText('detailStage', task.stage_name || '—');

        const dueEl = el('detailDue');
        if (dueEl) {
            const due = task.due_badge || {};
            dueEl.textContent = task.due_date ? `${KD.formatDate(task.due_date)}${due.tone === 'overdue' ? ` · ${due.text}` : ''}` : 'Sem prazo definido';
            dueEl.dataset.tone = due.tone || 'none';
        }

        setText('detailCreated', task.created_at ? KD.formatRelative(task.created_at) : '—');
        setText('detailCompleted', task.completed_at ? KD.formatRelative(task.completed_at) : 'Em aberto');

        // Responsáveis
        const assignees = el('detailAssignees');
        if (assignees) {
            const list = task.assignees || [];
            assignees.innerHTML = list.length
                ? list.map((user) => `<span class="kd-avatar" title="${KD.escapeHtml(user.full_name)}">${KD.escapeHtml((user.full_name || '?').charAt(0).toUpperCase())}</span>`).join('')
                : '<span class="text-slate-400" style="font-size:0.75rem">Ninguém atribuído</span>';
        }

        // Cronômetro e Tempo Estimado
        const estMinutes = Number(task.estimated_minutes || 0);
        setText('detailEstimated', estMinutes > 0 ? KD.formatMinutes(estMinutes) : 'Não informado');
        setText('detailTimer', KD.formatSeconds(task.effective_seconds));
        updateDetailPlayButton(Boolean(task.is_running));

        // Botões de estado
        const focusBtn = el('detailFocusBtn');
        if (focusBtn) focusBtn.setAttribute('aria-pressed', String(Number(task.in_focus) === 1));
        const bombBtn = el('detailBombBtn');
        if (bombBtn) bombBtn.setAttribute('aria-pressed', String(Number(task.is_bomb) === 1));

        const completeBtn = el('detailCompleteBtn');
        if (completeBtn) {
            const isDone = Boolean(task.completed_at);
            completeBtn.dataset.detailAction = isDone ? 'reopen' : 'complete';
            completeBtn.title = isDone ? 'Reabrir tarefa' : 'Concluir tarefa';
            completeBtn.classList.toggle('kd-complete-pill--reopen', isDone);
            const icon = completeBtn.querySelector('i');
            if (icon) {
                icon.setAttribute('data-lucide', isDone ? 'rotate-ccw' : 'check-circle-2');
            }
            const span = completeBtn.querySelector('span');
            if (span) span.textContent = isDone ? 'Reabrir' : 'Concluir';
        }

        // Menção do WhatsApp
        const isMention = task.source === 'whatsapp_mention' || Boolean(task.group_name || task.mentioned_by);
        const mentionBox = el('detailMentionBox');
        if (mentionBox) {
            mentionBox.hidden = !isMention;
            if (isMention) {
                setText('detailMentionGroup', task.group_name || 'Grupo do WhatsApp');
                setText('detailMentionAuthor', task.mentioned_by ? `Mencionado por @${task.mentioned_by}` : 'Menção recebida no grupo');
                const link = el('detailMentionLink');
                if (link) link.href = KD.safeUrl(task.whatsapp_url, 'https://web.whatsapp.com');
            }
        }

        renderChecklist(task.checklists || []);
        renderComments(task.comments || []);
        renderClientLinks(task.client_links_json);
        renderClientActivities(task.client_recent_tasks || []);
        renderClientProfile(task);
        renderDetailHistory(task.history || []);

        switchTab('details');
        KD.icons();
    }

    function renderClientProfile(task) {
        setText('clientName', task.client_company || 'Sem cliente vinculado');
        setText('clientContact', task.client_contact || 'Contato não informado');

        const email = el('clientEmail');
        if (email) {
            email.textContent = task.client_email || 'Não informado';
            email.href = task.client_email ? `mailto:${task.client_email}` : '#';
        }

        const phone = el('clientPhone');
        if (phone) {
            phone.textContent = task.client_phone || 'Não informado';
            phone.href = task.client_phone ? `https://wa.me/55${String(task.client_phone).replace(/\D/g, '')}` : '#';
        }

        setText('clientNotes', task.client_notes || 'Nenhuma anotação cadastrada para este cliente.');
    }

    // ---------------------------------------------------------------
    // Abas
    // ---------------------------------------------------------------

    const TABS = ['details', 'comments', 'links', 'client', 'history'];

    function switchTab(name) {
        TABS.forEach((tab) => {
            const button = el(`tabBtn-${tab}`);
            const panel = el(`tabPanel-${tab}`);
            const selected = tab === name;
            if (button) button.setAttribute('aria-selected', String(selected));
            if (panel) panel.hidden = !selected;
        });
        KD.icons();
    }

    KD.switchDetailTab = switchTab;

    // ---------------------------------------------------------------
    // Cronômetro do painel
    // ---------------------------------------------------------------

    function updateDetailPlayButton(isRunning) {
        const button = el('detailPlayBtn');
        if (!button) return;
        button.dataset.running = isRunning ? '1' : '0';
        button.setAttribute('aria-label', isRunning ? 'Pausar cronômetro' : 'Iniciar cronômetro');
        button.innerHTML = `<i data-lucide="${isRunning ? 'pause' : 'play'}" class="w-3.5 h-3.5"></i>`;
        KD.icons();
    }

    function startDetailTimer() {
        stopDetailTimer();
        if (!activeTask || !activeTask.is_running) return;

        let seconds = Number(activeTask.effective_seconds || 0);
        detailTimerInterval = window.setInterval(() => {
            seconds += 1;
            setText('detailTimer', KD.formatSeconds(seconds));
        }, 1000);
    }

    function stopDetailTimer() {
        if (detailTimerInterval) {
            window.clearInterval(detailTimerInterval);
            detailTimerInterval = null;
        }
    }

    // ---------------------------------------------------------------
    // Subtarefas (Visualização com checklist interativo)
    // ---------------------------------------------------------------

    function renderChecklist(items) {
        const list = el('detailChecklist');
        if (!list) return;

        const total = items.length;
        const done = items.filter((item) => Number(item.is_completed) === 1).length;
        const percent = total > 0 ? Math.round((done / total) * 100) : 0;

        setText('detailChecklistProgress', total > 0 ? `${done}/${total} · ${percent}%` : 'Nenhuma subtarefa');
        const bar = el('detailProgressBar');
        if (bar) {
            bar.style.width = `${percent}%`;
            bar.parentElement.setAttribute('aria-valuenow', String(percent));
        }

        if (total === 0) {
            list.innerHTML = '<p class="text-xs text-slate-500 italic p-3 text-center">Nenhuma subtarefa vinculada. Para adicionar ou gerenciar subtarefas, clique no botão de editar acima.</p>';
            return;
        }

        list.innerHTML = items.map((item) => {
            const checked = Number(item.is_completed) === 1;
            return `
                <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-900/40 border border-slate-800/60 transition hover:border-slate-700/60">
                    <input type="checkbox" id="chk-${item.id}" ${checked ? 'checked' : ''}
                           data-detail-action="toggle-checklist" data-item-id="${item.id}"
                           class="w-4 h-4 rounded cursor-pointer accent-indigo-500">
                    <label for="chk-${item.id}" class="flex-1 text-xs cursor-pointer select-none ${checked ? 'line-through text-slate-500' : 'text-slate-200'}">
                        ${KD.escapeHtml(item.title)}
                    </label>
                </div>
            `;
        }).join('');
    }

    // ---------------------------------------------------------------
    // Comentários: Feed (50%) e Editor CRUD (50%)
    // ---------------------------------------------------------------

    function renderComments(comments) {
        const container = el('detailComments');
        if (!container) return;

        setText('detailCommentsCount', String(comments.length));
        setText('detailCommentsTabBadge', String(comments.length));

        KD.cancelEditComment();

        if (comments.length === 0) {
            container.innerHTML = '<p class="text-xs text-slate-500 italic p-4 text-center">Nenhum comentário registrado ainda. Utilize o editor ao lado para enviar uma mensagem.</p>';
            return;
        }

        container.innerHTML = comments.map((comment) => `
            <div class="p-3 rounded-xl bg-slate-900/50 border border-slate-800/80 space-y-2 group">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-indigo-500/20 text-indigo-300 font-bold text-[10px] flex items-center justify-center border border-indigo-500/30">
                            ${KD.escapeHtml((comment.user_name || 'U').charAt(0).toUpperCase())}
                        </span>
                        <span class="text-xs font-bold text-slate-200">${KD.escapeHtml(comment.user_name || 'Usuário')}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[10px] text-slate-500">${KD.escapeHtml(KD.formatRelative(comment.created_at))}</span>
                        <button type="button" class="p-1 text-slate-400 hover:text-indigo-300 transition"
                                data-detail-action="edit-comment"
                                data-comment-id="${comment.id}"
                                title="Editar comentário">
                            <i data-lucide="pencil" class="w-3 h-3"></i>
                        </button>
                        <button type="button" class="p-1 text-slate-400 hover:text-rose-400 transition"
                                data-detail-action="delete-comment"
                                data-comment-id="${comment.id}"
                                title="Excluir comentário">
                            <i data-lucide="trash-2" class="w-3 h-3"></i>
                        </button>
                    </div>
                </div>
                <p class="text-xs text-slate-300 leading-relaxed whitespace-pre-wrap pl-1" id="comment-text-${comment.id}">${KD.escapeHtml(comment.comment_text)}</p>
            </div>
        `).join('');

        KD.icons();
    }

    KD.submitComment = async function (event) {
        event.preventDefault();
        const input = el('newCommentInput');
        const text = input.value.trim();
        if (!text || !activeTask) return;

        const editId = el('editingCommentId').value;
        const btn = el('btnSubmitComment');
        btn.disabled = true;

        try {
            if (editId) {
                await KD.api.tasks({
                    action: 'update_comment',
                    comment_id: Number(editId),
                    task_id: activeTask.id,
                    comment_text: text,
                });
                KD.toast('Comentário atualizado.');
            } else {
                await KD.api.tasks({
                    action: 'add_comment',
                    task_id: activeTask.id,
                    comment_text: text,
                });
                KD.toast('Comentário enviado.');
            }
            KD.cancelEditComment();
            await refreshDetail();
        } catch (error) {
            KD.toastError(error.message);
        } finally {
            btn.disabled = false;
        }
    };

    KD.editComment = function (commentId) {
        const textEl = el(`comment-text-${commentId}`);
        if (!textEl) return;

        el('editingCommentId').value = commentId;
        el('newCommentInput').value = textEl.innerText || textEl.textContent || '';
        el('commentEditingIndicator').hidden = false;
        el('btnCancelEditComment').hidden = false;
        setText('btnSubmitCommentLabel', 'Salvar alteração');
        el('commentEditorHeading').innerHTML = '<i data-lucide="pencil" class="w-4 h-4 text-amber-400"></i> Editar Comentário';
        el('newCommentInput').focus();
        KD.icons();
    };

    KD.cancelEditComment = function () {
        if (el('editingCommentId')) el('editingCommentId').value = '';
        if (el('newCommentInput')) el('newCommentInput').value = '';
        if (el('commentEditingIndicator')) el('commentEditingIndicator').hidden = true;
        if (el('btnCancelEditComment')) el('btnCancelEditComment').hidden = true;
        setText('btnSubmitCommentLabel', 'Enviar comentário');
        if (el('commentEditorHeading')) {
            el('commentEditorHeading').innerHTML = '<i data-lucide="edit-3" class="w-4 h-4 text-emerald-400"></i> Novo Comentário';
        }
        KD.icons();
    };

    KD.deleteComment = async function (commentId) {
        if (!confirm('Deseja realmente excluir este comentário?')) return;
        if (!activeTask) return;

        try {
            await KD.api.tasks({
                action: 'delete_comment',
                comment_id: Number(commentId),
                task_id: activeTask.id,
            });
            KD.toast('Comentário excluído.');
            await refreshDetail();
        } catch (error) {
            KD.toastError(error.message);
        }
    };

    // ---------------------------------------------------------------
    // Histórico da Tarefa (Activity Log)
    // ---------------------------------------------------------------

    const HISTORY_ACTION_MAP = {
        task_created: { icon: 'plus-circle', color: 'text-emerald-400', label: 'Tarefa criada' },
        task_updated: { icon: 'edit', color: 'text-blue-400', label: 'Tarefa editada' },
        stage_changed: { icon: 'arrow-right-circle', color: 'text-indigo-400', label: 'Coluna alterada' },
        due_date_changed: { icon: 'calendar', color: 'text-amber-400', label: 'Prazo alterado' },
        timer_started: { icon: 'play', color: 'text-emerald-400', label: 'Cronômetro iniciado' },
        timer_stopped: { icon: 'pause', color: 'text-amber-400', label: 'Cronômetro pausado' },
        task_completed: { icon: 'check-circle-2', color: 'text-emerald-400', label: 'Tarefa concluída' },
        task_reopened: { icon: 'rotate-ccw', color: 'text-amber-400', label: 'Tarefa reaberta' },
        comment_added: { icon: 'message-square', color: 'text-indigo-400', label: 'Comentário adicionado' },
        comment_updated: { icon: 'edit-2', color: 'text-indigo-400', label: 'Comentário editado' },
        comment_deleted: { icon: 'trash-2', color: 'text-rose-400', label: 'Comentário excluído' },
        checklist_toggle: { icon: 'check-square', color: 'text-emerald-400', label: 'Subtarefa atualizada' },
        focus_toggled: { icon: 'target', color: 'text-amber-400', label: 'Foco alterado' },
        bomb_toggled: { icon: 'flame', color: 'text-rose-400', label: 'Urgência alterada' },
    };

    function renderDetailHistory(history) {
        const container = el('detailHistoryTimeline');
        if (!container) return;

        if (!history || history.length === 0) {
            container.innerHTML = '<p class="text-xs text-slate-500 italic p-4 text-center">Nenhuma atividade registrada no histórico desta tarefa.</p>';
            return;
        }

        container.innerHTML = history.map((item) => {
            const meta = HISTORY_ACTION_MAP[item.action] || { icon: 'activity', color: 'text-slate-400', label: item.action };
            return `
                <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-900/40 border border-slate-800/60">
                    <div class="w-7 h-7 rounded-lg bg-slate-800 flex items-center justify-center flex-shrink-0 ${meta.color}">
                        <i data-lucide="${meta.icon}" class="w-4 h-4"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-0.5">
                            <span class="text-xs font-bold text-slate-200">${meta.label}</span>
                            <span class="text-[10px] text-slate-500 flex-shrink-0">${KD.escapeHtml(KD.formatRelative(item.created_at))}</span>
                        </div>
                        ${item.details ? `<p class="text-xs text-slate-400 leading-relaxed">${KD.escapeHtml(item.details)}</p>` : ''}
                        <span class="text-[10px] text-slate-500 mt-1 block">Por: ${KD.escapeHtml(item.user_name || 'Sistema')}</span>
                    </div>
                </div>
            `;
        }).join('');

        KD.icons();
    }

    // ---------------------------------------------------------------
    // Links úteis do cliente
    // ---------------------------------------------------------------

    const LINK_ICONS = {
        site: 'globe',
        meta_bm: 'layout',
        google_mcc: 'bar-chart-2',
        instagram: 'camera',
        facebook: 'thumbs-up',
        drive: 'folder',
        outro: 'external-link',
    };

    function parseLinks(value) {
        try {
            return typeof value === 'string' ? JSON.parse(value || '[]') : (value || []);
        } catch (e) {
            return [];
        }
    }

    function renderClientLinks(value) {
        const list = el('detailLinks');
        if (!list) return;

        const links = parseLinks(value);
        if (links.length === 0) {
            list.innerHTML = '<p class="kd-col__empty" style="grid-column:1/-1">Nenhum atalho cadastrado para este cliente.<br>Cadastre em Clientes &rsaquo; Editar.</p>';
            return;
        }

        list.innerHTML = links.map((link) => {
            const href = KD.safeUrl(link.url, '#');
            return `
                <a href="${KD.escapeHtml(href)}" target="_blank" rel="noopener noreferrer"
                   class="p-3 rounded-xl flex items-center gap-3 min-w-0"
                   style="background:var(--surface-1);border:1px solid var(--border)">
                    <i data-lucide="${LINK_ICONS[link.type] || LINK_ICONS.outro}" class="w-4 h-4" style="color:var(--brand-strong);flex-shrink:0"></i>
                    <span class="min-w-0 flex-1">
                        <span style="display:block;font-size:0.75rem;font-weight:700;color:var(--text-strong)" class="truncate">${KD.escapeHtml(link.title)}</span>
                        <span style="display:block;font-size:0.6875rem;color:var(--text-faint)" class="truncate">${KD.escapeHtml(link.url)}</span>
                    </span>
                    <i data-lucide="arrow-up-right" class="w-3.5 h-3.5" style="color:var(--text-faint);flex-shrink:0"></i>
                </a>
            `;
        }).join('');
    }

    // ---------------------------------------------------------------
    // Outras demandas do mesmo cliente
    // ---------------------------------------------------------------

    function renderClientActivities(tasks) {
        const list = el('detailClientActivities');
        if (!list) return;

        setText('detailClientActivitiesCount', `${tasks.length} demanda(s)`);

        if (tasks.length === 0) {
            list.innerHTML = '<p class="kd-col__empty">Nenhuma outra demanda registrada para este cliente.</p>';
            return;
        }

        list.innerHTML = tasks.map((task) => {
            const done = Boolean(task.completed_at);
            return `
                <button type="button" class="w-full flex items-center justify-between gap-2 p-2.5 rounded-xl text-left"
                        style="background:var(--surface-1);border:1px solid var(--border)"
                        data-detail-action="open-task" data-task-id="${task.id}">
                    <span class="flex items-center gap-2 min-w-0">
                        <span class="kd-col__dot" style="background:${done ? 'var(--success)' : 'var(--brand)'}"></span>
                        <span class="truncate" style="font-size:0.75rem;${done ? 'text-decoration:line-through;color:var(--text-faint)' : 'color:var(--text)'}">
                            ${KD.escapeHtml(task.title)}
                        </span>
                    </span>
                    <span style="font-size:0.625rem;color:var(--text-faint);flex-shrink:0">${KD.escapeHtml(task.stage_name || '')}</span>
                </button>
            `;
        }).join('');
    }

    // ---------------------------------------------------------------
    // Ações do painel (delegação)
    // ---------------------------------------------------------------

    async function refreshDetail() {
        if (!activeTask) return;
        const data = await KD.api.get(`${KD.url('/api/tarefas')}?id=${activeTask.id}`);
        activeTask = data.task;
        renderDetail(activeTask);
        KD.loadBoard({ silent: true });
    }

    async function runDetailAction(action, node) {
        if (!activeTask && action !== 'close') return;

        switch (action) {
            case 'close':
                KD.closeTaskDetail();
                break;

            case 'edit': {
                const id = activeTask.id;
                KD.closeTaskDetail();
                KD.openTaskForm(id);
                break;
            }

            case 'timer': {
                const result = await KD.api.tasks({ action: 'toggle_timer', task_id: activeTask.id });
                activeTask.is_running = result.is_running;
                activeTask.effective_seconds = result.total_seconds;
                updateDetailPlayButton(result.is_running);
                setText('detailTimer', KD.formatSeconds(result.total_seconds));
                startDetailTimer();
                KD.loadBoard({ silent: true });
                break;
            }

            case 'focus':
            case 'bomb': {
                const result = await KD.api.tasks({
                    action: action === 'focus' ? 'toggle_focus' : 'toggle_bomb',
                    task_id: activeTask.id,
                });
                node.setAttribute('aria-pressed', String(Boolean(action === 'focus' ? result.in_focus : result.is_bomb)));
                if (action === 'focus') activeTask.in_focus = result.in_focus;
                else activeTask.is_bomb = result.is_bomb;
                KD.loadBoard({ silent: true });
                break;
            }

            case 'complete': {
                const result = await KD.api.tasks({ action: 'complete_task', task_id: activeTask.id });
                const undo = result.undo;
                KD.closeTaskDetail();
                await KD.loadBoard({ silent: true });
                KD.toast('Tarefa concluída.', {
                    undo: async () => {
                        await KD.api.tasks(undo);
                        await KD.loadBoard({ silent: true });
                        KD.toast('Conclusão desfeita.', { tone: 'info' });
                    },
                });
                break;
            }

            case 'reopen':
                await KD.api.tasks({ action: 'uncomplete_task', task_id: activeTask.id });
                await refreshDetail();
                KD.toast('Tarefa reaberta.', { tone: 'info' });
                break;

            case 'duplicate': {
                const result = await KD.api.tasks({ action: 'duplicate_task', task_id: activeTask.id });
                KD.closeTaskDetail();
                await KD.loadBoard({ silent: true });
                KD.toast('Tarefa duplicada.', {
                    undo: async () => {
                        await KD.api.tasks(result.undo);
                        await KD.loadBoard({ silent: true });
                    },
                });
                break;
            }

            case 'copy-link': {
                const url = `${window.location.origin}${window.location.pathname}?task=${activeTask.id}`;
                try {
                    await navigator.clipboard.writeText(url);
                    KD.toast('Link da tarefa copiado.');
                } catch (e) {
                    window.prompt('Copie o link da tarefa:', url);
                }
                break;
            }

            case 'toggle-checklist':
                await KD.api.tasks({
                    action: 'toggle_checklist',
                    item_id: Number(node.dataset.itemId),
                    is_completed: node.checked ? 1 : 0,
                });
                await refreshDetail();
                break;

            case 'delete-checklist': {
                const itemId = Number(node.dataset.itemId);
                await KD.api.tasks({ action: 'delete_checklist', item_id: itemId });
                await refreshDetail();
                break;
            }

            case 'edit-comment':
                KD.editComment(Number(node.dataset.commentId));
                break;

            case 'delete-comment':
                await KD.deleteComment(Number(node.dataset.commentId));
                break;

            case 'open-task':
                await KD.openTaskDetail(Number(node.dataset.taskId));
                break;

            case 'tab':
                switchTab(node.dataset.tab);
                break;

            default:
                break;
        }
    }

    function handleDetailEvent(event) {
        const node = event.target.closest('[data-detail-action]');
        if (!node) return;

        if (node.tagName !== 'INPUT') event.preventDefault();

        Promise.resolve(runDetailAction(node.dataset.detailAction, node))
            .catch((error) => KD.toastError(error.message));
    }

    document.addEventListener('click', (event) => {
        if (!event.target.closest('#taskDetailModal')) return;
        handleDetailEvent(event);
    });

    document.addEventListener('change', (event) => {
        if (!event.target.closest('#taskDetailModal')) return;
        if (event.target.type !== 'checkbox') return;
        handleDetailEvent(event);
    });

    // ---------------------------------------------------------------
    // Helpers de Tempo Estimado e Expediente
    // ---------------------------------------------------------------

    KD.formatMinutes = function (totalMinutes) {
        const mins = Math.max(0, Number(totalMinutes || 0));
        if (mins === 0) return '0 min';
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        if (h === 0) return `${m}m`;
        if (m === 0) return `${h}h`;
        return `${h}h ${m}m`;
    };

    KD.updateEstimatedPreview = function (val) {
        const preview = el('task_estimated_preview');
        if (!preview) return;
        const mins = Number(val || 0);
        if (mins > 0) {
            preview.textContent = `= ${KD.formatMinutes(mins)}`;
            preview.hidden = false;
        } else {
            preview.hidden = true;
        }
    };

    KD.setEstimatedMinutes = function (val) {
        const input = el('task_estimated_minutes');
        if (input) {
            input.value = val;
            KD.updateEstimatedPreview(val);
            input.focus();
        }
    };

    KD.openWorkHoursModal = async function () {
        const startInput = el('work_start_time_input');
        const endInput = el('work_end_time_input');

        if (KD.board && KD.board.workHours) {
            if (startInput) startInput.value = KD.board.workHours.start || '09:00';
            if (endInput) endInput.value = KD.board.workHours.end || '17:00';
        } else {
            try {
                const res = await fetch(KD.url('/api/preferencias'));
                const data = await res.json();
                if (data.success) {
                    if (startInput) startInput.value = data.work_start_time || '09:00';
                    if (endInput) endInput.value = data.work_end_time || '17:00';
                }
            } catch (e) {}
        }

        KD.calculateWorkHoursPreview();
        KD.openModal('workHoursModal', { focus: '#work_start_time_input' });
    };

    KD.calculateWorkHoursPreview = function () {
        const start = el('work_start_time_input')?.value || '09:00';
        const end = el('work_end_time_input')?.value || '17:00';
        const calcEl = el('workHoursCalculated');
        if (!calcEl) return;

        const startParts = start.split(':').map(Number);
        const endParts = end.split(':').map(Number);
        const startM = (startParts[0] || 0) * 60 + (startParts[1] || 0);
        const endM = (endParts[0] || 0) * 60 + (endParts[1] || 0);
        const diff = Math.max(0, endM - startM);

        calcEl.textContent = `${KD.formatMinutes(diff)} (${diff} min)`;
        if (diff <= 0) {
            calcEl.classList.add('text-rose-400');
            calcEl.classList.remove('text-indigo-400');
        } else {
            calcEl.classList.remove('text-rose-400');
            calcEl.classList.add('text-indigo-400');
        }
    };

    KD.submitWorkHoursForm = async function (event) {
        event.preventDefault();
        const start = el('work_start_time_input')?.value || '09:00';
        const end = el('work_end_time_input')?.value || '17:00';

        try {
            const res = await fetch(KD.url('/api/preferencias'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ work_start_time: start, work_end_time: end }),
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Erro ao salvar expediente.');

            if (KD.board) {
                KD.board.workHours = {
                    start: data.work_start_time,
                    end: data.work_end_time,
                    capacityMinutes: data.work_capacity_minutes,
                };
                if (el('workHoursLabel')) {
                    const h = Math.round(data.work_capacity_minutes / 60);
                    el('workHoursLabel').textContent = `${data.work_start_time.slice(0, 2)}h–${data.work_end_time.slice(0, 2)}h (${h}h)`;
                }
                KD.renderBoard();
            }

            KD.closeModal('workHoursModal');
            KD.toast('Horário de expediente salvo com sucesso!');
        } catch (err) {
            KD.toastError(err.message);
        }
    };

    // Se a página carregar com ?create_with_template=X, abre automaticamente o modal com o modelo
    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const tplId = params.get('create_with_template');
        if (tplId) {
            setTimeout(() => {
                if (window.KD && KD.openTaskForm) {
                    KD.openTaskForm(null, null, { id: Number(tplId) });
                }
            }, 250);
        }
    });
})(window.KD);
