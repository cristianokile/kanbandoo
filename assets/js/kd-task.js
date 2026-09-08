/**
 * KanbanDoo - Modais de tarefa: formulário de criação/edição e painel de detalhes.
 */
window.KD = window.KD || {};

(function (KD) {
    'use strict';

    let activeTask = null;
    let detailTimerInterval = null;
    let formTags = [];

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

    KD.openTaskForm = async function (taskId, stageId) {
        const form = el('taskForm');
        if (!form) return;

        form.reset();
        clearFormErrors();
        setupTagInput();
        renderTagSuggestions();
        formTags = [];
        renderFormTags();
        el('task_id').value = '';
        document.querySelectorAll('.task-assignee-checkbox').forEach((box) => { box.checked = false; });
        el('btnDeleteTask').hidden = true;

        if (!taskId) {
            el('taskModalTitle').textContent = 'Nova tarefa';
            if (stageId) el('task_stage_id').value = stageId;
            KD.openModal('taskModal', { focus: '#task_title' });
            return;
        }

        try {
            const data = await KD.api.get(`${KD.url('/api/tarefas')}?id=${taskId}`);
            const task = data.task;

            el('task_id').value = task.id;
            el('task_title').value = task.title || '';
            el('task_client_id').value = task.client_id || '';
            el('task_stage_id').value = task.stage_id || '';
            el('task_priority').value = task.priority || 'medium';
            el('task_due_date').value = task.due_date || '';
            el('task_description').value = task.description || '';

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
            KD.toastError(error.message);
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

        const payload = {
            action: 'save',
            id: el('task_id').value || null,
            title,
            client_id: el('task_client_id').value || null,
            stage_id: el('task_stage_id').value,
            priority: el('task_priority').value,
            due_date: el('task_due_date').value || null,
            description: el('task_description').value,
            assignees,
            tags: formTags,
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

        // Cronômetro
        setText('detailTimer', KD.formatSeconds(task.effective_seconds));
        updateDetailPlayButton(Boolean(task.is_running));

        // Botões de estado
        const focusBtn = el('detailFocusBtn');
        if (focusBtn) focusBtn.setAttribute('aria-pressed', String(Number(task.in_focus) === 1));
        const bombBtn = el('detailBombBtn');
        if (bombBtn) bombBtn.setAttribute('aria-pressed', String(Number(task.is_bomb) === 1));

        const completeBtn = el('detailCompleteBtn');
        if (completeBtn) {
            completeBtn.dataset.detailAction = task.completed_at ? 'reopen' : 'complete';
            completeBtn.querySelector('span').textContent = task.completed_at ? 'Reabrir' : 'Concluir';
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
        renderSummaries(task.summaries || []);
        renderClientLinks(task.client_links_json);
        renderClientActivities(task.client_recent_tasks || []);
        renderClientProfile(task);

        switchTab('activities');
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

    const TABS = ['activities', 'links', 'summary', 'client'];

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
    // Subtarefas
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
            list.innerHTML = '<p class="kd-col__empty">Quebre a tarefa em passos menores para acompanhar o progresso no card.</p>';
            return;
        }

        list.innerHTML = items.map((item) => {
            const checked = Number(item.is_completed) === 1;
            return `
                <div class="flex items-center gap-3 p-2.5 rounded-xl" style="background:var(--surface-1);border:1px solid var(--border)">
                    <input type="checkbox" id="chk-${item.id}" ${checked ? 'checked' : ''}
                           data-detail-action="toggle-checklist" data-item-id="${item.id}"
                           class="w-4 h-4 rounded cursor-pointer">
                    <label for="chk-${item.id}" style="flex:1;font-size:0.75rem;cursor:pointer;${checked ? 'text-decoration:line-through;color:var(--text-faint)' : 'color:var(--text)'}">
                        ${KD.escapeHtml(item.title)}
                    </label>
                    <button type="button" class="kd-icon-btn" data-detail-action="delete-checklist" data-item-id="${item.id}"
                            aria-label="Remover subtarefa ${KD.escapeHtml(item.title)}">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            `;
        }).join('');
    }

    KD.addChecklistItem = async function (event) {
        event.preventDefault();
        const input = el('newChecklistInput');
        const title = input.value.trim();
        if (!title || !activeTask) return;

        try {
            await KD.api.tasks({ action: 'add_checklist', task_id: activeTask.id, title });
            input.value = '';
            await refreshDetail();
            input.focus();
        } catch (error) {
            KD.toastError(error.message);
        }
    };

    // ---------------------------------------------------------------
    // Comentários e resumos
    // ---------------------------------------------------------------

    function renderComments(comments) {
        const container = el('detailComments');
        if (!container) return;

        if (comments.length === 0) {
            container.innerHTML = '<p class="kd-col__empty">Nenhum comentário ainda.</p>';
            return;
        }

        container.innerHTML = comments.map((comment) => `
            <div class="p-3 rounded-xl" style="background:var(--surface-1);border:1px solid var(--border)">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <span style="font-size:0.75rem;font-weight:700;color:var(--brand-strong)">${KD.escapeHtml(comment.user_name)}</span>
                    <span style="font-size:0.6875rem;color:var(--text-faint)">${KD.escapeHtml(KD.formatRelative(comment.created_at))}</span>
                </div>
                <p style="font-size:0.75rem;line-height:1.6;white-space:pre-wrap">${KD.escapeHtml(comment.comment_text)}</p>
            </div>
        `).join('');
    }

    KD.addComment = async function (event) {
        event.preventDefault();
        const input = el('newCommentInput');
        const text = input.value.trim();
        if (!text || !activeTask) return;

        try {
            await KD.api.tasks({ action: 'add_comment', task_id: activeTask.id, comment_text: text });
            input.value = '';
            await refreshDetail();
            input.focus();
        } catch (error) {
            KD.toastError(error.message);
        }
    };

    function renderSummaries(summaries) {
        const container = el('detailSummaries');
        if (!container) return;

        if (summaries.length === 0) {
            container.innerHTML = '<p class="kd-col__empty">Nenhum registro no histórico de execução.</p>';
            return;
        }

        container.innerHTML = summaries.map((summary) => `
            <div class="p-3.5 rounded-xl" style="background:var(--surface-1);border:1px solid var(--border)">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <span style="font-size:0.75rem;font-weight:700;color:var(--text-strong)">${KD.escapeHtml(summary.user_name)}</span>
                    <span style="font-size:0.6875rem;color:var(--text-faint)">${KD.escapeHtml(KD.formatRelative(summary.created_at))}</span>
                </div>
                <p style="font-size:0.75rem;line-height:1.65;white-space:pre-wrap">${KD.escapeHtml(summary.summary_text)}</p>
            </div>
        `).join('');
    }

    KD.addSummary = async function (event) {
        event.preventDefault();
        const input = el('taskSummaryInput');
        const text = input.value.trim();
        if (!text || !activeTask) return;

        try {
            await KD.api.tasks({ action: 'add_summary', task_id: activeTask.id, summary_text: text });
            input.value = '';
            await refreshDetail();
            KD.toast('Resumo registrado.');
        } catch (error) {
            KD.toastError(error.message);
        }
    };

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
})(window.KD);
