/**
 * KanbanDoo - Módulo de Importação em Lote (Bulk Import)
 * Lê listas em texto (.txt ou coladas), reconhece datas inteligentes
 * (Hoje, Amanhã, dias da semana, DD/MM/AAAA) e gera tarefas em lote.
 */
window.KD = window.KD || {};

(function (KD) {
    'use strict';

    let parsedTasks = [];
    let debounceTimer = null;

    /**
     * Calcula data YYYY-MM-DD a partir de expressões de texto (PT-BR)
     */
    function parseDateExpression(rawStr) {
        if (!rawStr) return null;
        const s = String(rawStr)
            .toLowerCase()
            .trim()
            .replace(/^[\s\-:\t\r\n]+|[\s\-:\t\r\n]+$/g, '');

        if (!s) return null;

        const now = new Date();
        const y = now.getFullYear();
        const m = now.getMonth();
        const d = now.getDate();

        // Hoje
        if (s === 'hoje') {
            return formatDateIso(new Date(y, m, d));
        }

        // Amanhã
        if (s === 'amanhã' || s === 'amanha') {
            return formatDateIso(new Date(y, m, d + 1));
        }

        // Depois de amanhã
        if (s === 'depois de amanhã' || s === 'depois de amanha') {
            return formatDateIso(new Date(y, m, d + 2));
        }

        // Dias da semana
        const weekMap = {
            'domingo': 0, 'dom': 0,
            'segunda': 1, 'seg': 1, 'segunda-feira': 1,
            'terça': 2, 'terca': 2, 'ter': 2, 'terça-feira': 2, 'terca-feira': 2,
            'quarta': 3, 'qua': 3, 'quarta-feira': 3,
            'quinta': 4, 'qui': 4, 'quinta-feira': 4,
            'sexta': 5, 'sex': 5, 'sexta-feira': 5,
            'sábado': 6, 'sabado': 6, 'sab': 6
        };

        if (weekMap[s] !== undefined) {
            const targetDay = weekMap[s];
            const currentDay = now.getDay();
            let diff = targetDay - currentDay;
            if (diff <= 0) diff += 7; // Próxima ocorrência
            return formatDateIso(new Date(y, m, d + diff));
        }

        // DD/MM/AAAA
        const mFull = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (mFull) {
            const dayNum = parseInt(mFull[1], 10);
            const monthNum = parseInt(mFull[2], 10) - 1;
            const yearNum = parseInt(mFull[3], 10);
            return formatDateIso(new Date(yearNum, monthNum, dayNum));
        }

        // DD/MM (assume ano atual)
        const mShort = s.match(/^(\d{1,2})\/(\d{1,2})$/);
        if (mShort) {
            const dayNum = parseInt(mShort[1], 10);
            const monthNum = parseInt(mShort[2], 10) - 1;
            return formatDateIso(new Date(y, monthNum, dayNum));
        }

        // AAAA-MM-DD
        const mIso = s.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (mIso) {
            return `${mIso[1]}-${mIso[2]}-${mIso[3]}`;
        }

        return null;
    }

    function formatDateIso(dateObj) {
        const y = dateObj.getFullYear();
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        const d = String(dateObj.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function formatDateDisplay(isoDate) {
        if (!isoDate) return { label: 'Sem data', tone: 'none' };
        const [y, m, d] = isoDate.split('-').map(Number);
        const taskDate = new Date(y, m - 1, d);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const diffTime = taskDate.getTime() - today.getTime();
        const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

        const formatted = `${String(d).padStart(2, '0')}/${String(m).padStart(2, '0')}`;

        if (diffDays === 0) {
            return { label: `Hoje (${formatted})`, tone: 'today' };
        } else if (diffDays === 1) {
            return { label: `Amanhã (${formatted})`, tone: 'tomorrow' };
        } else {
            return { label: formatted, tone: 'future' };
        }
    }

    /**
     * Faz o parse de uma única linha de texto
     */
    function parseLine(line) {
        let clean = String(line || '')
            .replace(/^[\s\-*•\d\.\)\:]+/, '') // remove marcadores no início (- , * , 1. , etc)
            .trim();

        if (!clean) return null;

        // Divide por traço para procurar expressões de data nos segmentos
        const parts = clean.split('-');
        let dueDate = null;
        let dateIdx = -1;

        for (let i = parts.length - 1; i >= 0; i--) {
            const seg = parts[i].trim();
            if (!seg) continue;
            const parsed = parseDateExpression(seg);
            if (parsed) {
                dueDate = parsed;
                dateIdx = i;
                break;
            }
        }

        let title = '';
        if (dateIdx !== -1) {
            title = parts.slice(0, dateIdx).join('-').trim();
        } else {
            title = clean.replace(/[\s\-:]+$/, '').trim();
        }

        // Se o título ficou vazio após remover a data, usa o texto limpo
        if (!title) title = clean;

        // Limpeza final de hífens nas pontas do título
        title = title.replace(/^[\s\-:]+|[\s\-:]+$/g, '').trim();

        return {
            title: title,
            dueDate: dueDate,
            enabled: true
        };
    }

    // ---------------------------------------------------------------
    // Interface & Eventos
    // ---------------------------------------------------------------

    KD.toggleNewTaskDropdown = function (event) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        const menu = document.getElementById('newTaskDropdownMenu');
        const trigger = document.getElementById('newTaskDropdownTrigger');
        if (menu && trigger && typeof KD.toggleMenu === 'function') {
            KD.toggleMenu(menu, trigger);
        }
    };

    KD.openImportModal = function () {
        KD.openModal('importTasksModal');
        const textInput = document.getElementById('bulkTextInput');
        if (textInput) {
            setTimeout(() => textInput.focus(), 80);
        }
        if (window.lucide) lucide.createIcons();
    };

    KD.closeImportModal = function () {
        KD.closeModal('importTasksModal');
    };

    KD.parseBulkInputDebounced = function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            KD.parseBulkInput();
        }, 120);
    };

    KD.parseBulkInput = function () {
        const textInput = document.getElementById('bulkTextInput');
        if (!textInput) return;

        const lines = textInput.value.split('\n');
        parsedTasks = [];

        lines.forEach((line) => {
            const item = parseLine(line);
            if (item && item.title) {
                parsedTasks.push(item);
            }
        });

        renderPreview();
    };

    function renderPreview() {
        const previewSection = document.getElementById('bulkPreviewSection');
        const previewList = document.getElementById('bulkPreviewList');
        const countBadge = document.getElementById('bulkCountBadge');
        const submitBtn = document.getElementById('btnSubmitBulkImport');
        const submitLabel = document.getElementById('btnSubmitBulkLabel');

        if (!previewSection || !previewList) return;

        if (parsedTasks.length === 0) {
            previewSection.hidden = true;
            previewList.innerHTML = '';
            if (submitBtn) submitBtn.disabled = true;
            if (submitLabel) submitLabel.textContent = 'Criar Tarefas';
            return;
        }

        previewSection.hidden = false;
        if (countBadge) {
            const activeCount = parsedTasks.filter(t => t.enabled).length;
            countBadge.textContent = `${activeCount} de ${parsedTasks.length} selecionada(s)`;
        }

        let html = '';
        parsedTasks.forEach((task, idx) => {
            const dateInfo = formatDateDisplay(task.dueDate);
            let badgeClass = 'bg-slate-800 text-slate-300 border-slate-700';
            if (dateInfo.tone === 'today') {
                badgeClass = 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30';
            } else if (dateInfo.tone === 'tomorrow') {
                badgeClass = 'bg-sky-500/15 text-sky-400 border-sky-500/30';
            } else if (dateInfo.tone === 'future') {
                badgeClass = 'bg-indigo-500/15 text-indigo-400 border-indigo-500/30';
            }

            html += `
                <div class="flex items-center gap-3 py-2 px-2.5 rounded-xl hover:bg-slate-900/60 transition group ${task.enabled ? '' : 'opacity-40'}">
                    <input type="checkbox" ${task.enabled ? 'checked' : ''} 
                        onchange="KD.toggleTaskItem(${idx}, this.checked)"
                        class="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-0 cursor-pointer">
                    
                    <div class="flex-1 min-w-0">
                        <input type="text" value="${KD.escapeHtml(task.title)}" 
                            onchange="KD.updateTaskTitle(${idx}, this.value)"
                            class="w-full bg-transparent text-xs text-slate-200 border-b border-transparent hover:border-slate-700 focus:border-indigo-500 focus:outline-none transition py-0.5 truncate font-medium">
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="inline-flex items-center gap-1 text-[11px] font-mono px-2 py-0.5 rounded-md border ${badgeClass}">
                            <i data-lucide="calendar" class="w-3 h-3"></i>
                            ${dateInfo.label}
                        </span>

                        <button type="button" onclick="KD.removeTaskItem(${idx})" class="text-slate-500 hover:text-rose-400 transition p-1" title="Remover item">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        previewList.innerHTML = html;
        if (window.lucide) lucide.createIcons();

        const activeTotal = parsedTasks.filter(t => t.enabled).length;
        if (submitBtn) submitBtn.disabled = activeTotal === 0;
        if (submitLabel) submitLabel.textContent = `Criar ${activeTotal} Tarefa${activeTotal > 1 ? 's' : ''}`;
    }

    KD.toggleTaskItem = function (index, enabled) {
        if (parsedTasks[index]) {
            parsedTasks[index].enabled = Boolean(enabled);
            renderPreview();
        }
    };

    KD.updateTaskTitle = function (index, newTitle) {
        if (parsedTasks[index]) {
            parsedTasks[index].title = String(newTitle || '').trim();
        }
    };

    KD.removeTaskItem = function (index) {
        parsedTasks.splice(index, 1);
        renderPreview();
    };

    KD.handleBulkFileUpload = function (event) {
        const file = event.target.files && event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const content = e.target.result;
            const textInput = document.getElementById('bulkTextInput');
            if (textInput) {
                textInput.value = content;
                KD.parseBulkInput();
            }
        };
        reader.readAsText(file, 'UTF-8');
        event.target.value = ''; // reseta para permitir subir o mesmo arquivo se quiser
    };

    KD.fillImportExample = function () {
        const example = `- Ajustar Lamaki - Hoje - 
- Subir novos posts da PS Advogados - Amanhã
- Subir videos da We Education - Hoje
- Subir campanha do ICBEU - Hoje
- Subir publico do ICBEU - Hoje
- Subir campanha de Pizzani - Hoje
- Corrigir trackeamento da pizzani - Amanhã
- Criar campanha de hotel para o Vila - 12/10/2026
- Mandar as notas fiscais de Pizzani e Sinalizar no grupo - Amanhã
- Mandar as notas fiscais para todos os clientes - Amanhã
- Subir os relatórios na pasta do SharePoint e encaminhar para todos os clientes também - Amanhã
- Gerar novo template do insights da semana - Amanhã
- Finalizar o novo relatório do Vila - Amanhã
- Publicar anúncio organico no Linkedin para a nova lp - 12/10/2026
- Verificar se chegaram os eventos da Pam - Hoje
- Impulsionar o Youtube do Vila - Segunda
- Mandar o gasto mensal do tráfego para a Alexia - Amanha
- Subir novos posts de Reserva Antecipada - 13/10/2026
- Subir Campanha de Aniversário de Cidades - 13/10/2026`;

        const textInput = document.getElementById('bulkTextInput');
        if (textInput) {
            textInput.value = example;
            KD.parseBulkInput();
        }
    };

    KD.submitBulkImport = async function (event) {
        if (event) event.preventDefault();

        const activeItems = parsedTasks.filter(t => t.enabled && t.title);
        if (activeItems.length === 0) {
            KD.toast('Nenhuma tarefa selecionada para criar.', { tone: 'warning' });
            return;
        }

        const stageSelect = document.getElementById('bulk_stage_id');
        const assigneeSelect = document.getElementById('bulk_assignee_id');
        const prioritySelect = document.getElementById('bulk_priority');
        const submitBtn = document.getElementById('btnSubmitBulkImport');
        const submitLabel = document.getElementById('btnSubmitBulkLabel');

        const stageId = stageSelect ? parseInt(stageSelect.value, 10) : 1;
        const assigneeId = assigneeSelect ? parseInt(assigneeSelect.value, 10) : 0;
        const priority = prioritySelect ? prioritySelect.value : 'medium';

        const payload = {
            action: 'bulk_import',
            stage_id: stageId,
            assignee_id: assigneeId,
            priority: priority,
            tasks: activeItems.map(item => ({
                title: item.title,
                due_date: item.dueDate
            }))
        };

        if (submitBtn) submitBtn.disabled = true;
        if (submitLabel) submitLabel.innerHTML = '<span class="inline-block animate-spin mr-1">⌛</span> Criando tarefas...';

        try {
            const res = await KD.api.post(KD.url('/api/tarefas'), payload);

            KD.toast(res.message || `${activeItems.length} tarefas criadas com sucesso!`, { tone: 'success' });
            KD.closeImportModal();

            // Limpa o textarea para a próxima vez
            const textInput = document.getElementById('bulkTextInput');
            if (textInput) textInput.value = '';
            parsedTasks = [];
            renderPreview();

            // Recarrega o quadro Kanban sem recarregar a página
            if (typeof KD.loadBoard === 'function') {
                await KD.loadBoard();
            } else {
                window.location.reload();
            }

        } catch (err) {
            KD.toast(err.message || 'Erro ao importar tarefas.', { tone: 'error' });
        } finally {
            if (submitBtn) submitBtn.disabled = false;
            if (submitLabel) submitLabel.textContent = 'Criar Tarefas';
        }
    };


    // ===============================================================
    // Importação & Exportação via Planilha (CSV / Excel)
    // ===============================================================

    let parsedSpreadsheetRows = [];

    /**
     * Alterna entre a aba de Planilha e a aba de Lista Rápida (TXT)
     */
    KD.switchImportTab = function (tab) {
        const tabSpreadsheet = document.getElementById('importTabSpreadsheet');
        const tabText = document.getElementById('importTabText');
        const btnSpreadsheet = document.getElementById('tabBtnSpreadsheet');
        const btnText = document.getElementById('tabBtnText');

        if (tab === 'spreadsheet') {
            if (tabSpreadsheet) tabSpreadsheet.hidden = false;
            if (tabText) tabText.hidden = true;
            if (btnSpreadsheet) {
                btnSpreadsheet.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm';
            }
            if (btnText) {
                btnText.className = 'px-4 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-2 text-slate-400 hover:text-white hover:bg-slate-800/80';
            }
        } else {
            if (tabSpreadsheet) tabSpreadsheet.hidden = true;
            if (tabText) tabText.hidden = false;
            if (btnText) {
                btnText.className = 'px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm';
            }
            if (btnSpreadsheet) {
                btnSpreadsheet.className = 'px-4 py-2 rounded-xl text-xs font-semibold transition flex items-center gap-2 text-slate-400 hover:text-white hover:bg-slate-800/80';
            }
        }
    };

    /**
     * Parser robusto de CSV universal (lida com aspas, BOM, quebras de linha e delimitador ; ou ,)
     */
    function parseCsvText(csvText) {
        if (!csvText) return [];
        if (csvText.charCodeAt(0) === 0xFEFF) {
            csvText = csvText.slice(1);
        }

        const firstLine = csvText.split(/\r\n|\n|\r/)[0] || '';
        const semicolonCount = (firstLine.match(/;/g) || []).length;
        const commaCount = (firstLine.match(/,/g) || []).length;
        const tabCount = (firstLine.match(/\t/g) || []).length;
        let delimiter = ';';
        if (tabCount > semicolonCount && tabCount > commaCount) delimiter = '\t';
        else if (commaCount > semicolonCount) delimiter = ',';

        const rows = [];
        let currentRow = [];
        let currentCell = '';
        let insideQuotes = false;

        for (let i = 0; i < csvText.length; i++) {
            const char = csvText[i];
            const nextChar = csvText[i + 1];

            if (char === '"') {
                if (insideQuotes && nextChar === '"') {
                    currentCell += '"';
                    i++;
                } else {
                    insideQuotes = !insideQuotes;
                }
            } else if (char === delimiter && !insideQuotes) {
                currentRow.push(currentCell.trim());
                currentCell = '';
            } else if ((char === '\r' || char === '\n') && !insideQuotes) {
                if (char === '\r' && nextChar === '\n') {
                    i++;
                }
                currentRow.push(currentCell.trim());
                if (currentRow.length > 1 || (currentRow.length === 1 && currentRow[0] !== '')) {
                    rows.push(currentRow);
                }
                currentRow = [];
                currentCell = '';
            } else {
                currentCell += char;
            }
        }
        if (currentCell !== '' || currentRow.length > 0) {
            currentRow.push(currentCell.trim());
            if (currentRow.length > 1 || (currentRow.length === 1 && currentRow[0] !== '')) {
                rows.push(currentRow);
            }
        }

        if (rows.length < 2) return [];

        const rawHeaders = rows[0].map(h => 
            h.toLowerCase()
             .normalize('NFD')
             .replace(/[\u0300-\u036f]/g, '')
             .replace(/[^a-z0-9_]/g, '_')
        );

        const result = [];
        for (let r = 1; r < rows.length; r++) {
            const rowData = rows[r];
            const obj = {};
            for (let c = 0; c < rawHeaders.length; c++) {
                const header = rawHeaders[c];
                const val = rowData[c] !== undefined ? rowData[c] : '';
                obj[header] = val;
            }

            const taskObj = {
                id: obj.id || '',
                title: obj.titulo || obj.title || obj.nome || '',
                client: obj.cliente || obj.client || obj.empresa || '',
                client_category: obj.categoria_cliente || obj.categoria || obj.category || 'C',
                stage: obj.coluna || obj.stage || obj.etapa || obj.fase || '',
                priority: obj.prioridade || obj.priority || 'Média',
                due_date: obj.prazo || obj.due_date || obj.data || '',
                due_time: obj.horario || obj.due_time || obj.hora || '',
                reminder_minutes: obj.lembrete_minutos !== undefined ? obj.lembrete_minutos : (obj.lembrete || '15'),
                estimated_minutes: obj.tempo_estimado_min || obj.tempo_estimado || obj.estimated_minutes || '',
                fixed_weekday: obj.fixar_dia || obj.dia_semana || obj.fixed_weekday || '',
                description: obj.descricao || obj.description || '',
                tags: obj.etiquetas || obj.tags || '',
                status: obj.status || 'Pendente'
            };

            if (taskObj.title) {
                result.push(taskObj);
            }
        }

        return result;
    }

    /**
     * Manipula o upload do arquivo de planilha (.csv)
     */
    KD.handleSpreadsheetFile = function (event) {
        const file = event.target.files && event.target.files[0];
        if (!file) return;

        const nameLabel = document.getElementById('spreadsheetFileName');
        if (nameLabel) {
            nameLabel.textContent = `Arquivo carregado: ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
            nameLabel.classList.remove('text-slate-500');
            nameLabel.classList.add('text-indigo-400', 'font-semibold');
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            const content = e.target.result;
            parsedSpreadsheetRows = parseCsvText(content);
            renderSpreadsheetPreview();
        };
        reader.readAsText(file, 'UTF-8');
        event.target.value = '';
    };

    /**
     * Renderiza o painel de pré-visualização das linhas da planilha
     */
    function renderSpreadsheetPreview() {
        const section = document.getElementById('spreadsheetPreviewSection');
        const tbody = document.getElementById('spreadsheetPreviewBody');
        const totalBadge = document.getElementById('csvTotalBadge');
        const updateBadge = document.getElementById('csvUpdateBadge');
        const createBadge = document.getElementById('csvCreateBadge');
        const submitBtn = document.getElementById('btnSubmitSpreadsheet');
        const submitLabel = document.getElementById('btnSubmitSpreadsheetLabel');

        if (!section || !tbody) return;

        if (parsedSpreadsheetRows.length === 0) {
            section.hidden = true;
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        section.hidden = false;
        tbody.innerHTML = '';

        let updateCount = 0;
        let createCount = 0;

        parsedSpreadsheetRows.forEach((row, idx) => {
            const hasId = Boolean(row.id && String(row.id).trim() !== '' && Number(row.id) > 0);
            if (hasId) updateCount++; else createCount++;

            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-900/60 transition';

            const actionBadge = hasId
                ? `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/15 text-blue-300 border border-blue-500/30 whitespace-nowrap">Atualizar #${KD.escapeHtml(row.id)}</span>`
                : `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 whitespace-nowrap">Novo</span>`;

            tr.innerHTML = `
                <td class="p-2.5">${actionBadge}</td>
                <td class="p-2.5 font-semibold text-white max-w-[200px] truncate" title="${KD.escapeHtml(row.title)}">${KD.escapeHtml(row.title)}</td>
                <td class="p-2.5 text-slate-300 truncate max-w-[120px]">${KD.escapeHtml(row.client || '—')}</td>
                <td class="p-2.5 text-slate-400 truncate max-w-[100px]">${KD.escapeHtml(row.stage || 'A Fazer')}</td>
                <td class="p-2.5 font-mono text-slate-300 whitespace-nowrap">${KD.escapeHtml(row.due_date || '—')}</td>
                <td class="p-2.5 font-mono text-amber-300 whitespace-nowrap">${KD.escapeHtml(row.due_time || '—')}</td>
                <td class="p-2.5 text-slate-400 capitalize">${KD.escapeHtml(row.priority || 'Média')}</td>
            `;

            tbody.appendChild(tr);
        });

        if (totalBadge) totalBadge.textContent = `${parsedSpreadsheetRows.length} tarefas detectadas`;
        if (updateBadge) {
            updateBadge.hidden = updateCount === 0;
            updateBadge.textContent = `${updateCount} para atualizar`;
        }
        if (createBadge) {
            createBadge.hidden = createCount === 0;
            createBadge.textContent = `${createCount} novas`;
        }

        if (submitBtn) submitBtn.disabled = false;
        if (submitLabel) submitLabel.textContent = `Importar ${parsedSpreadsheetRows.length} Tarefa${parsedSpreadsheetRows.length > 1 ? 's' : ''}`;
    }

    /**
     * Envia os dados parseados da planilha para o backend
     */
    KD.submitSpreadsheetImport = async function () {
        if (!parsedSpreadsheetRows || parsedSpreadsheetRows.length === 0) {
            KD.toast('Nenhuma tarefa carregada na planilha.', { tone: 'warning' });
            return;
        }

        const modeRadio = document.querySelector('input[name="spreadsheet_mode"]:checked');
        const overwriteExisting = modeRadio ? (modeRadio.value === 'overwrite') : true;

        const submitBtn = document.getElementById('btnSubmitSpreadsheet');
        const submitLabel = document.getElementById('btnSubmitSpreadsheetLabel');

        if (submitBtn) submitBtn.disabled = true;
        if (submitLabel) submitLabel.innerHTML = '<span class="inline-block animate-spin mr-1">⌛</span> Processando planilha...';

        try {
            const res = await KD.api.post(KD.url('/api/tarefas'), {
                action: 'spreadsheet_import',
                overwrite_existing: overwriteExisting,
                rows: parsedSpreadsheetRows
            });

            KD.toast(res.message || 'Planilha importada com sucesso!', { tone: 'success' });
            KD.closeImportModal();

            parsedSpreadsheetRows = [];
            const fileNameEl = document.getElementById('spreadsheetFileName');
            if (fileNameEl) {
                fileNameEl.textContent = 'Formatos aceitos: .csv (separado por ponto e vírgula ou vírgula)';
                fileNameEl.className = 'text-[11px] text-slate-500';
            }
            const previewSection = document.getElementById('spreadsheetPreviewSection');
            if (previewSection) previewSection.hidden = true;

            // Recarrega o quadro
            if (typeof KD.loadBoard === 'function') {
                await KD.loadBoard();
            } else {
                window.location.reload();
            }

        } catch (err) {
            KD.toast(err.message || 'Erro ao importar planilha.', { tone: 'error' });
        } finally {
            if (submitBtn) submitBtn.disabled = false;
            if (submitLabel) submitLabel.textContent = 'Importar Planilha';
        }
    };

})(window.KD);
