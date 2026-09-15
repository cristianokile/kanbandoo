<?php
/**
 * KanbanDoo - Gerenciador de Modelos de Tarefas
 */
require_once dirname(__DIR__) . '/bootstrap.php';

require_login();

$pdo = get_pdo();
$user = current_user();

$pageTitle = 'Modelos de Tarefas';
require_once dirname(__DIR__, 2) . '/includes/header.php';
require_once dirname(__DIR__, 2) . '/includes/navbar.php';
?>

<main class="w-full max-w-7xl mx-auto px-4 lg:px-8 py-6 space-y-6">

    <!-- Topo da Página -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-indigo-400 mb-1">
                <i data-lucide="copy-check" class="w-4 h-4"></i>
                <span>Padronização &bull; Agilidade</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Modelos de Tarefas</h1>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">
                Crie templates reutilizáveis com título, tempo estimado, etiquetas e checklist pré-configurados.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <button type="button" onclick="openTemplateModal()" class="kd-btn kd-btn--primary">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Novo Modelo</span>
            </button>
        </div>
    </div>

    <!-- Barra de Filtro e Busca -->
    <div class="flex items-center justify-between gap-4 p-2 rounded-2xl kd-glass">
        <div class="relative flex-1 max-w-md">
            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="search" id="searchTemplates" oninput="filterTemplates()"
                   placeholder="Buscar modelos por título, descrição ou tag..."
                   class="kd-field pl-10" style="min-height:38px;padding-top:0.35rem;padding-bottom:0.35rem">
        </div>
        <div class="text-xs text-slate-400 font-medium px-2">
            <span id="templatesCount">0</span> modelo(s)
        </div>
    </div>

    <!-- Grid de Modelos -->
    <div id="templatesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Renderizado dinamicamente via JS -->
    </div>

    <!-- Estado Vazio -->
    <div id="emptyState" class="kd-glass rounded-2xl p-12 text-center max-w-lg mx-auto" hidden>
        <div class="w-14 h-14 rounded-2xl bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center mx-auto mb-4">
            <i data-lucide="copy-check" class="w-7 h-7"></i>
        </div>
        <h3 class="text-base font-bold text-white mb-1">Nenhum modelo cadastrado</h3>
        <p class="text-xs text-slate-400 mb-5 leading-relaxed">
            Cadastre modelos de tarefas recorrentes ou padronizadas para economizar tempo na criação do dia a dia.
        </p>
        <button type="button" onclick="openTemplateModal()" class="kd-btn kd-btn--primary mx-auto">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Criar meu primeiro modelo
        </button>
    </div>

</main>

<!-- Modal de Criação / Edição de Modelo -->
<div id="templateModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="templateModalTitle" hidden>
    <div class="kd-modal__panel kd-glass kd-glass--raised" style="max-width:42rem">

        <div class="flex items-center justify-between gap-3 pb-4 mb-5" style="border-bottom:1px solid var(--border)">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center bg-indigo-500/15 text-indigo-400">
                    <i data-lucide="copy-check" class="w-5 h-5"></i>
                </span>
                <div>
                    <h2 id="templateModalTitle" class="text-lg font-bold text-white">Novo modelo de tarefa</h2>
                    <p class="text-xs text-slate-400">Preencha os campos para padronizar novas tarefas</p>
                </div>
            </div>
            <button type="button" class="kd-icon-btn" onclick="closeTemplateModal()" aria-label="Fechar (Esc)">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="templateForm" onsubmit="saveTemplate(event)" class="space-y-4" novalidate>
            <input type="hidden" id="tpl_id" value="">

            <div>
                <label for="tpl_title" class="block text-xs font-semibold uppercase tracking-wider mb-2">Título do Modelo *</label>
                <input type="text" id="tpl_title" maxlength="200" autocomplete="off" required
                       placeholder="Ex: Campanha de Tráfego Pago - Onboarding"
                       class="kd-field">
            </div>

            <div>
                <label for="tpl_estimated_minutes" class="block text-xs font-semibold uppercase tracking-wider mb-2">Tempo Estimado (minutos)</label>
                <div class="relative">
                    <input type="number" id="tpl_estimated_minutes" min="1" step="5" placeholder="Ex: 60"
                           class="kd-field pr-16">
                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-medium">minutos</span>
                </div>
            </div>

            <div>
                <label for="tpl_tag_input" class="block text-xs font-semibold uppercase tracking-wider mb-2">Etiquetas Sugeridas</label>
                <div class="kd-tag-editor" id="tplTagEditor">
                    <input type="text" id="tpl_tag_input" autocomplete="off"
                           placeholder="Digite e pressione Enter ou vírgula">
                </div>
                <p class="text-[11px] text-slate-400 mt-1">Pressione Enter para adicionar cada etiqueta.</p>
            </div>

            <div>
                <label for="tpl_description" class="block text-xs font-semibold uppercase tracking-wider mb-2">Descrição / Instruções Padrão</label>
                <textarea id="tpl_description" rows="3"
                          placeholder="Instruções, referências e orientações para quem executar esta tarefa..."
                          class="kd-field" style="resize:vertical"></textarea>
            </div>

            <!-- Checklist Sugerido -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider">Checklist Sugerido</label>
                    <span id="tplChecklistCount" class="text-[11px] text-slate-400">0 itens</span>
                </div>

                <div class="flex items-center gap-2 mb-2">
                    <input type="text" id="tplNewChecklistItem" autocomplete="off"
                           placeholder="Adicionar subtarefa ao checklist... (Enter para adicionar)"
                           class="kd-field" style="min-height:36px;padding-top:0.35rem;padding-bottom:0.35rem">
                    <button type="button" onclick="addChecklistItemFromInput()" class="kd-btn kd-btn--primary" style="min-height:36px;padding-inline:0.75rem">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Adicionar</span>
                    </button>
                </div>

                <div id="tplChecklistContainer" class="space-y-1.5 max-h-48 overflow-y-auto p-2 rounded-xl"
                     style="background:var(--surface-sunken);border:1px solid var(--border)">
                    <!-- Itens inseridos aqui -->
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 pt-4" style="border-top:1px solid var(--border)">
                <button type="button" id="btnDeleteTpl" onclick="deleteActiveTemplate()" class="kd-btn kd-btn--danger" hidden>
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    Excluir Modelo
                </button>

                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" onclick="closeTemplateModal()" class="kd-btn">Cancelar</button>
                    <button type="submit" id="btnSaveTpl" class="kd-btn kd-btn--primary">Salvar Modelo</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let allTemplates = [];
let tplTags = [];
let tplChecklist = [];

document.addEventListener('DOMContentLoaded', () => {
    loadTemplates();
    setupTplTagInput();
    setupTplChecklistInput();
});

async function loadTemplates() {
    try {
        const res = await fetch('<?= url('/api/modelos') ?>');
        const data = await res.json();
        if (data.success) {
            allTemplates = data.templates || [];
            renderTemplates();
        }
    } catch (e) {
        if (window.KD && KD.toastError) KD.toastError('Erro ao carregar modelos: ' + e.message);
    }
}

function renderTemplates(filtered = null) {
    const list = filtered !== null ? filtered : allTemplates;
    const grid = document.getElementById('templatesGrid');
    const empty = document.getElementById('emptyState');
    const count = document.getElementById('templatesCount');

    count.textContent = list.length;

    if (list.length === 0) {
        grid.innerHTML = '';
        empty.hidden = false;
        return;
    }

    empty.hidden = true;
    grid.innerHTML = list.map((tpl) => {
        const chkItems = tpl.checklist_items || [];
        const tags = tpl.tags_list || [];

        return `
            <div class="kd-card kd-glass kd-glass--raised flex flex-col justify-between p-5 rounded-2xl relative group" data-tpl-id="${tpl.id}">
                <div>
                    <div class="flex items-start justify-between gap-3 mb-2">
                        <h3 class="font-bold text-base text-white leading-snug line-clamp-2">${escapeHtml(tpl.title)}</h3>
                        ${tpl.estimated_minutes ? `
                            <span class="kd-due text-[11px] whitespace-nowrap" data-tone="soon" title="Tempo estimado">
                                <i data-lucide="clock" class="w-3 h-3"></i> ${tpl.estimated_minutes} min
                            </span>
                        ` : ''}
                    </div>

                    ${tpl.description ? `
                        <p class="text-xs text-slate-400 mb-3 line-clamp-3 leading-relaxed">${escapeHtml(tpl.description)}</p>
                    ` : '<p class="text-xs text-slate-500 italic mb-3">Sem descrição.</p>'}

                    ${tags.length > 0 ? `
                        <div class="kd-tags mb-3">
                            ${tags.map(t => `<span class="kd-tag">${escapeHtml(t)}</span>`).join('')}
                        </div>
                    ` : ''}

                    ${chkItems.length > 0 ? `
                        <div class="p-2.5 rounded-xl mb-3 text-xs" style="background:var(--surface-sunken);border:1px solid var(--border)">
                            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                                <i data-lucide="check-square" class="w-3 h-3 text-indigo-400"></i>
                                Checklist (${chkItems.length} subtarefas)
                            </div>
                            <ul class="space-y-1 text-slate-300">
                                ${chkItems.slice(0, 3).map(item => `
                                    <li class="truncate flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-500 flex-shrink-0"></span>
                                        ${escapeHtml(item)}
                                    </li>
                                `).join('')}
                                ${chkItems.length > 3 ? `<li class="text-[10px] text-slate-500 italic">+${chkItems.length - 3} outra(s) subtarefa(s)</li>` : ''}
                            </ul>
                        </div>
                    ` : ''}
                </div>

                <div class="flex items-center justify-between gap-2 pt-3 mt-2 border-t border-slate-700/40">
                    <button type="button" onclick="useTemplateToCreate(${tpl.id})" class="kd-btn kd-btn--primary text-xs flex-1">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Usar Modelo
                    </button>
                    <button type="button" onclick="editTemplate(${tpl.id})" class="kd-icon-btn" title="Editar modelo">
                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                    </button>
                    <button type="button" onclick="deleteTemplateDirect(${tpl.id}, '${escapeHtml(tpl.title)}')" class="kd-icon-btn text-rose-400 hover:text-rose-300" title="Excluir modelo">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        `;
    }).join('');

    if (window.lucide) lucide.createIcons();
}

function filterTemplates() {
    const q = document.getElementById('searchTemplates').value.toLowerCase().trim();
    if (!q) {
        renderTemplates();
        return;
    }

    const filtered = allTemplates.filter(t => {
        const inTitle = (t.title || '').toLowerCase().includes(q);
        const inDesc = (t.description || '').toLowerCase().includes(q);
        const inTags = (t.tags_list || []).some(tag => tag.toLowerCase().includes(q));
        return inTitle || inDesc || inTags;
    });

    renderTemplates(filtered);
}

function openTemplateModal(tpl = null) {
    document.getElementById('templateForm').reset();
    document.getElementById('tpl_id').value = tpl ? tpl.id : '';
    document.getElementById('tpl_title').value = tpl ? tpl.title : '';
    document.getElementById('tpl_estimated_minutes').value = (tpl && tpl.estimated_minutes) ? tpl.estimated_minutes : '';
    document.getElementById('tpl_description').value = tpl ? tpl.description || '' : '';

    tplTags = tpl ? [...(tpl.tags_list || [])] : [];
    renderTplTags();

    tplChecklist = tpl ? [...(tpl.checklist_items || [])] : [];
    renderTplChecklist();

    document.getElementById('templateModalTitle').textContent = tpl ? 'Editar modelo de tarefa' : 'Novo modelo de tarefa';
    document.getElementById('btnDeleteTpl').hidden = !tpl;

    if (window.KD && KD.openModal) {
        KD.openModal('templateModal', { focus: '#tpl_title' });
    } else {
        document.getElementById('templateModal').hidden = false;
    }
}

function closeTemplateModal() {
    if (window.KD && KD.closeModal) {
        KD.closeModal('templateModal');
    } else {
        document.getElementById('templateModal').hidden = true;
    }
}

function editTemplate(id) {
    const tpl = allTemplates.find(t => Number(t.id) === Number(id));
    if (tpl) openTemplateModal(tpl);
}

async function deleteActiveTemplate() {
    const id = document.getElementById('tpl_id').value;
    if (!id || !confirm('Deseja realmente excluir este modelo?')) return;

    try {
        const res = await fetch('<?= url('/api/modelos') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: Number(id) })
        });
        const data = await res.json();
        if (data.success) {
            closeTemplateModal();
            if (window.KD && KD.toast) KD.toast('Modelo excluído.');
            await loadTemplates();
        } else {
            alert(data.error || 'Erro ao excluir.');
        }
    } catch (e) {
        alert('Erro ao excluir: ' + e.message);
    }
}

async function deleteTemplateDirect(id, title) {
    if (!confirm(`Deseja realmente excluir o modelo "${title}"?`)) return;

    try {
        const res = await fetch('<?= url('/api/modelos') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: Number(id) })
        });
        const data = await res.json();
        if (data.success) {
            if (window.KD && KD.toast) KD.toast('Modelo excluído.');
            await loadTemplates();
        } else {
            alert(data.error || 'Erro ao excluir.');
        }
    } catch (e) {
        alert('Erro ao excluir: ' + e.message);
    }
}

async function saveTemplate(event) {
    event.preventDefault();

    const title = document.getElementById('tpl_title').value.trim();
    if (!title) {
        alert('Informe o título do modelo.');
        document.getElementById('tpl_title').focus();
        return;
    }

    const payload = {
        action: 'save',
        id: document.getElementById('tpl_id').value || null,
        title: title,
        estimated_minutes: document.getElementById('tpl_estimated_minutes').value || null,
        description: document.getElementById('tpl_description').value.trim(),
        tags: tplTags,
        checklist: tplChecklist,
    };

    const btn = document.getElementById('btnSaveTpl');
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    try {
        const res = await fetch('<?= url('/api/modelos') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            closeTemplateModal();
            if (window.KD && KD.toast) KD.toast('Modelo salvo com sucesso!');
            await loadTemplates();
        } else {
            alert(data.error || 'Erro ao salvar.');
        }
    } catch (e) {
        alert('Erro ao salvar: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Salvar Modelo';
    }
}

function useTemplateToCreate(templateId) {
    const tpl = allTemplates.find(t => Number(t.id) === Number(templateId));
    if (!tpl) return;

    if (window.KD && KD.openTaskForm) {
        KD.openTaskForm(null, null, tpl);
    } else {
        window.location.href = '<?= url('/tarefas') ?>?create_with_template=' + templateId;
    }
}

// -------------------------------------------------------------
// Tags no Modal de Modelo
// -------------------------------------------------------------
function renderTplTags() {
    const editor = document.getElementById('tplTagEditor');
    const input = document.getElementById('tpl_tag_input');
    if (!editor || !input) return;

    editor.querySelectorAll('.kd-tag').forEach(n => n.remove());

    tplTags.forEach((tag, idx) => {
        const chip = document.createElement('span');
        chip.className = 'kd-tag';
        chip.textContent = tag;

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'kd-tag__remove';
        removeBtn.textContent = '×';
        removeBtn.onclick = () => {
            tplTags.splice(idx, 1);
            renderTplTags();
            input.focus();
        };

        chip.appendChild(removeBtn);
        editor.insertBefore(chip, input);
    });
}

function addTplTag(raw) {
    const clean = String(raw || '').replace(/\s+/g, ' ').trim().replace(/^#|,$/g, '').trim();
    if (!clean || tplTags.length >= 8) return;
    if (tplTags.some(t => t.toLowerCase() === clean.toLowerCase())) return;

    tplTags.push(clean.slice(0, 24));
    renderTplTags();
}

function setupTplTagInput() {
    const input = document.getElementById('tpl_tag_input');
    if (!input) return;

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addTplTag(input.value);
            input.value = '';
        } else if (e.key === 'Backspace' && input.value === '' && tplTags.length > 0) {
            tplTags.pop();
            renderTplTags();
        }
    });

    input.addEventListener('blur', () => {
        if (input.value.trim()) {
            addTplTag(input.value);
            input.value = '';
        }
    });
}

// -------------------------------------------------------------
// Checklist no Modal de Modelo
// -------------------------------------------------------------
function renderTplChecklist() {
    const container = document.getElementById('tplChecklistContainer');
    const count = document.getElementById('tplChecklistCount');
    if (!container) return;

    count.textContent = `${tplChecklist.length} ite${tplChecklist.length === 1 ? 'm' : 'ns'}`;

    if (tplChecklist.length === 0) {
        container.innerHTML = '<p class="text-slate-500 text-xs text-center py-2 italic">Nenhuma subtarefa adicionada ao modelo.</p>';
        return;
    }

    container.innerHTML = tplChecklist.map((item, idx) => `
        <div class="flex items-center justify-between gap-2 p-1.5 px-2.5 rounded-lg bg-slate-800/40 border border-slate-700/30 text-xs">
            <span class="truncate flex items-center gap-2 text-slate-200">
                <i data-lucide="check" class="w-3.5 h-3.5 text-indigo-400 flex-shrink-0"></i>
                ${escapeHtml(item)}
            </span>
            <button type="button" onclick="removeChecklistItem(${idx})" class="text-slate-400 hover:text-rose-400 transition" title="Remover item">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
            </button>
        </div>
    `).join('');

    if (window.lucide) lucide.createIcons();
}

function addChecklistItemFromInput() {
    const input = document.getElementById('tplNewChecklistItem');
    const text = input.value.trim();
    if (!text) return;

    tplChecklist.push(text);
    input.value = '';
    renderTplChecklist();
    input.focus();
}

function removeChecklistItem(idx) {
    tplChecklist.splice(idx, 1);
    renderTplChecklist();
}

function setupTplChecklistInput() {
    const input = document.getElementById('tplNewChecklistItem');
    if (!input) return;

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addChecklistItemFromInput();
        }
    });
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[m]);
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
