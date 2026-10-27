<?php
/**
 * KanbanDoo - Gestao de Notas & Conhecimento
 */
require_once dirname(__DIR__) . '/bootstrap.php';

require_login();

$pdo = get_pdo();
$currentUser = current_user();

$clientsList = $pdo->query("SELECT id, company_name FROM clients ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$groupsList = $pdo->query("
    SELECT g.*, c.company_name AS client_name 
    FROM note_groups g 
    LEFT JOIN clients c ON c.id = g.client_id 
    ORDER BY g.name ASC
")->fetchAll(PDO::FETCH_ASSOC);
$authorsList = $pdo->query("SELECT id, full_name, username FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

view_header('Notas & Conhecimento');
?>
<!-- Quill.js 2.0 para Formatação Rica e Colar do Word / Docs -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

<style>
/* Estilos Dark Mode para o Quill Editor no KanbanDoo */
.ql-toolbar.ql-snow {
    background: rgba(15, 23, 42, 0.75);
    border-color: rgba(51, 65, 85, 0.8) !important;
    border-top-left-radius: 0.75rem;
    border-top-right-radius: 0.75rem;
    padding: 8px 10px;
}
.ql-container.ql-snow {
    background: rgba(2, 6, 23, 0.9);
    border-color: rgba(51, 65, 85, 0.8) !important;
    border-bottom-left-radius: 0.75rem;
    border-bottom-right-radius: 0.75rem;
    color: #f1f5f9;
    font-family: inherit;
    font-size: 0.875rem;
}
.ql-snow .ql-stroke { stroke: #94a3b8; }
.ql-snow .ql-fill { fill: #94a3b8; }
.ql-snow .ql-picker { color: #94a3b8; font-weight: 500; }
.ql-snow .ql-picker-options {
    background-color: #0f172a;
    border-color: #334155 !important;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
    border-radius: 0.5rem;
    padding: 6px;
}
.ql-snow .ql-picker-item { color: #cbd5e1; }
.ql-snow .ql-picker-item:hover { color: #818cf8; }
.ql-snow.ql-toolbar button:hover .ql-stroke,
.ql-snow .ql-toolbar button.ql-active .ql-stroke { stroke: #818cf8; }
.ql-snow.ql-toolbar button:hover .ql-fill,
.ql-snow .ql-toolbar button.ql-active .ql-fill { fill: #818cf8; }
.ql-snow.ql-toolbar button:hover,
.ql-snow .ql-toolbar button.ql-active { color: #818cf8; }
.ql-editor.ql-blank::before { color: #64748b; font-style: normal; }
.ql-editor { 
    min-height: 160px; 
    max-height: 340px; 
    overflow-y: auto; 
    line-height: 1.65; 
    color: #f8fafc !important; /* Texto em branco contrastante com o fundo escuro por padrão */
    font-size: 0.9375rem;
}
.ql-editor p { color: #f8fafc; }
.ql-editor.ql-blank::before { color: #94a3b8 !important; font-style: normal; }
.ql-snow .ql-stroke { stroke: #cbd5e1 !important; }
.ql-snow .ql-fill { fill: #cbd5e1 !important; }
.ql-snow .ql-picker { color: #cbd5e1 !important; }
.ql-snow .ql-picker-options {
    background-color: #0f172a !important;
    border: 1px solid #334155 !important;
    border-radius: 8px !important;
    padding: 6px !important;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.6) !important;
}
.ql-snow .ql-picker-item:hover { border-color: #818cf8 !important; }
.ql-editor h2, .ql-editor h3 { color: #ffffff; font-weight: 700; margin-top: 0.75rem; margin-bottom: 0.5rem; }
.ql-editor h2 { font-size: 1.25rem; }
.ql-editor h3 { font-size: 1.1rem; }
.ql-editor ul, .ql-editor ol { padding-left: 1.25rem; }
.ql-editor blockquote { border-left: 3px solid #6366f1; padding-left: 0.75rem; color: #94a3b8; font-style: italic; }
.ql-editor a { color: #818cf8; text-decoration: underline; }
.ql-editor code { background: #1e293b; padding: 2px 5px; border-radius: 4px; font-family: monospace; font-size: 0.85em; }
.ql-editor pre { background: #0f172a; padding: 10px; border-radius: 6px; border: 1px solid #334155; }

/* Renderizacao de Conteudo Rico de Notas */
.note-rich-content {
    line-height: 1.65;
    color: #f8fafc;
}
.note-rich-content p { margin-bottom: 0.75rem; color: #f8fafc; }
.note-rich-content h1, .note-rich-content h2, .note-rich-content h3 { color: #fff; font-weight: 700; margin-top: 1rem; margin-bottom: 0.5rem; }
.note-rich-content h2 { font-size: 1.25rem; }
.note-rich-content h3 { font-size: 1.1rem; }
.note-rich-content ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 0.75rem; }
.note-rich-content ol { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 0.75rem; }
.note-rich-content li { margin-bottom: 0.25rem; }
.note-rich-content blockquote { border-left: 3px solid #6366f1; padding-left: 0.75rem; color: #94a3b8; font-style: italic; margin: 0.75rem 0; }
.note-rich-content a { color: #818cf8; text-decoration: underline; }
.note-rich-content code { background: #1e293b; padding: 2px 5px; border-radius: 4px; font-family: monospace; }
.note-rich-content pre { background: #0f172a; padding: 12px; border-radius: 8px; border: 1px solid #334155; overflow-x: auto; margin-bottom: 0.75rem; }
.note-rich-content table { border-collapse: collapse; width: 100%; margin: 0.75rem 0; }
.note-rich-content th, .note-rich-content td { border: 1px solid #334155; padding: 6px 10px; font-size: 0.8125rem; }
.note-rich-content th { background: #1e293b; font-weight: 600; color: #fff; }
</style>
<main class="w-full px-4 lg:px-8 py-6 space-y-6 max-w-[1600px] mx-auto" id="notasApp">
    <!-- Topo da Pagina -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-amber-500/15 border border-amber-500/25 text-amber-400 flex items-center justify-center shadow-inner">
                    <i data-lucide="notebook-tabs" class="w-5 h-5"></i>
                </span>
                <h1 class="text-2xl font-bold text-white tracking-tight">Notas & Conhecimento</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-500/10 border border-amber-500/20 text-amber-400" id="notesCounter">
                    Carregando...
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">
                Anotações estratégicas, diretrizes, atas de reunião e alinhamentos por cliente e equipe.
            </p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <button type="button" onclick="openGroupsModal()" class="kd-btn kd-btn--ghost text-xs flex items-center gap-2 bg-slate-900/60 hover:bg-slate-800 border border-slate-700/60 text-slate-200">
                <i data-lucide="folder" class="w-4 h-4 text-indigo-400"></i>
                <span>Gerenciar Grupos</span>
            </button>
            <button type="button" onclick="openNoteModal()" class="kd-btn kd-btn--primary text-xs flex items-center gap-2 py-2.5 shadow-lg shadow-indigo-600/25">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>Nova Nota</span>
            </button>
        </div>
    </div>

    <!-- Barra Completa de Filtros (Clientes, Grupos, Conteudos, Periodo e Quem Criou) -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-3.5 space-y-3 kd-glass shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-center">
            
            <!-- 1. Busca por Conteudo / Titulo -->
            <div class="lg:col-span-4 relative">
                <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="search" id="filterSearch" oninput="debounceNotesFilter()"
                       placeholder="Buscar por título, conteúdo ou palavras-chave..."
                       class="kd-field pl-10" style="min-height:38px;padding-top:0.35rem;padding-bottom:0.35rem">
            </div>

            <!-- 2. Filtro por Cliente -->
            <div class="lg:col-span-2">
                <select id="filterClient" onchange="loadNotes()" class="kd-field text-xs" style="min-height:38px;padding-top:0.35rem;padding-bottom:0.35rem">
                    <option value="">Clientes: Todos</option>
                    <option value="none">Sem cliente (Gerais)</option>
                    <?php foreach ($clientsList as $cl): ?>
                        <option value="<?= (int)$cl['id'] ?>"><?= e($cl['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 3. Filtro por Grupo -->
            <div class="lg:col-span-2">
                <select id="filterGroup" onchange="loadNotes()" class="kd-field text-xs" style="min-height:38px;padding-top:0.35rem;padding-bottom:0.35rem">
                    <option value="">Grupos: Todos</option>
                    <option value="none">Sem grupo</option>
                    <?php foreach ($groupsList as $grp): ?>
                        <option value="<?= (int)$grp['id'] ?>">
                            <?= e($grp['name']) ?><?= !empty($grp['client_name']) ? ' (' . e($grp['client_name']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 4. Filtro por Periodo -->
            <div class="lg:col-span-2">
                <select id="filterPeriod" onchange="loadNotes()" class="kd-field text-xs" style="min-height:38px;padding-top:0.35rem;padding-bottom:0.35rem">
                    <option value="all">Período: Todo</option>
                    <option value="today">Criadas Hoje</option>
                    <option value="week">Últimos 7 dias</option>
                    <option value="month">Últimos 30 dias</option>
                </select>
            </div>

            <!-- 5. Filtro por Quem Criou (Autor) -->
            <div class="lg:col-span-2">
                <select id="filterAuthor" onchange="loadNotes()" class="kd-field text-xs" style="min-height:38px;padding-top:0.35rem;padding-bottom:0.35rem">
                    <option value="">Autor: Todos</option>
                    <?php foreach ($authorsList as $aut): ?>
                        <option value="<?= (int)$aut['id'] ?>"><?= e($aut['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>

        <!-- Indicador de Filtros Ativos e Limpar -->
        <div id="filterStatusWrap" class="flex items-center justify-between text-xs text-slate-400 pt-1" hidden>
            <div class="flex items-center gap-2">
                <i data-lucide="filter" class="w-3.5 h-3.5 text-indigo-400"></i>
                <span id="filterStatusText">Filtros aplicados</span>
            </div>
            <button type="button" onclick="resetFilters()" class="text-indigo-400 hover:text-indigo-300 font-medium flex items-center gap-1 transition">
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                Limpar todos os filtros
            </button>
        </div>
    </div>

    <!-- Grid de Cards de Notas (4 colunas inteligente) -->
    <div id="notesGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-4 gap-4">
        <!-- Renderizado dinamicamente via JS -->
    </div>

    <!-- Estado Vazio -->
    <div id="emptyNotesState" class="kd-glass rounded-2xl p-12 text-center max-w-lg mx-auto" hidden>
        <div class="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mx-auto mb-4">
            <i data-lucide="notebook-tabs" class="w-7 h-7"></i>
        </div>
        <h3 class="text-base font-bold text-white mb-1">Nenhuma nota encontrada</h3>
        <p class="text-xs text-slate-400 mb-5 leading-relaxed">
            Crie sua primeira anotação, ata de reunião ou diretriz para organizar o conhecimento dos clientes.
        </p>
        <button type="button" onclick="openNoteModal()" class="kd-btn kd-btn--primary mx-auto">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Criar primeira nota
        </button>
    </div>

</main>

<!-- Modal: Nova / Editar Nota -->
<div id="noteModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="noteModalTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--lg kd-glass kd-glass--raised">
        <div class="flex items-center justify-between pb-4 mb-5" style="border-bottom:1px solid var(--border)">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                </span>
                <h3 id="noteModalTitle" class="text-lg font-bold text-white">Nova Anotação</h3>
            </div>
            <button type="button" onclick="closeNoteModal()" class="kd-icon-btn" aria-label="Fechar">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="noteForm" onsubmit="saveNote(event)" class="space-y-4">
            <input type="hidden" id="note_id" value="">

            <div>
                <label for="note_title" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Título da Nota *</label>
                <input type="text" id="note_title" required placeholder="Ex: Diretrizes de Branding e Tom de Voz" class="kd-field">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="note_group_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Grupo de Notas</label>
                        <button type="button" onclick="openQuickGroupModal()" class="text-[11px] text-indigo-400 hover:text-indigo-300 font-medium">
                            + Novo Grupo
                        </button>
                    </div>
                    <select id="note_group_id" onchange="onNoteGroupChange()" class="kd-field text-xs">
                        <option value="">Sem grupo (Nota avulsa)</option>
                    </select>
                </div>

                <div>
                    <label for="note_client_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Cliente / Empresa Vinculada</label>
                    <select id="note_client_id" class="kd-field text-xs">
                        <option value="">Sem cliente (Uso interno / Geral)</option>
                        <?php foreach ($clientsList as $cl): ?>
                            <option value="<?= (int)$cl['id'] ?>"><?= e($cl['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

                        <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Conteúdo da Anotação *</label>
                    <span class="text-[11px] font-medium text-indigo-400 flex items-center gap-1">
                        <i data-lucide="sparkles" class="w-3 h-3"></i>
                        Aceita formatação & colar do Word
                    </span>
                </div>
                <div id="noteQuillEditor"></div>
                <input type="hidden" id="note_content">
            </div>

            <div class="flex items-center justify-between pt-3" style="border-top:1px solid var(--border)">
                <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300 font-medium select-none">
                    <input type="checkbox" id="note_is_pinned" class="rounded border-slate-700 bg-slate-900 text-indigo-600 focus:ring-0">
                    <span class="flex items-center gap-1.5">
                        <i data-lucide="pin" class="w-3.5 h-3.5 text-amber-400"></i>
                        Fixar nota no topo da listagem
                    </span>
                </label>

                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeNoteModal()" class="kd-btn">Cancelar</button>
                    <button type="submit" id="btnSaveNote" class="kd-btn kd-btn--primary">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span>Salvar Nota</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Visualização de Nota Completa -->
<div id="viewNoteModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="viewNoteTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--lg kd-glass kd-glass--raised max-w-2xl w-full">
        <div class="flex items-start justify-between gap-3 pb-4 mb-4" style="border-bottom:1px solid var(--border)">
            <div class="space-y-1.5 min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span id="viewNoteGroupBadge"></span>
                    <span id="viewNoteClientBadge"></span>
                    <span id="viewNotePinnedBadge" class="hidden inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/15 text-amber-400 border border-amber-500/30">
                        <i data-lucide="pin" class="w-3 h-3"></i> Fixada
                    </span>
                </div>
                <h3 id="viewNoteTitle" class="text-lg font-bold text-white break-words"></h3>
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    <span id="viewNoteAuthor"></span>
                    <span>&bull;</span>
                    <span id="viewNoteDate"></span>
                </div>
            </div>
            <button type="button" onclick="closeViewNoteModal()" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition" aria-label="Fechar">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Corpo com formatação rica renderizada -->
        <div id="viewNoteBody" class="note-rich-content max-h-[60vh] overflow-y-auto pr-2 space-y-2 text-sm">
        </div>

        <div class="flex items-center justify-between pt-4 mt-4" style="border-top:1px solid var(--border)">
            <button type="button" id="btnEditFromView" class="kd-btn text-xs flex items-center gap-1.5 text-indigo-400 hover:text-indigo-300">
                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                <span>Editar esta nota</span>
            </button>
            <button type="button" onclick="closeViewNoteModal()" class="kd-btn kd-btn--primary text-xs px-4">
                Fechar
            </button>
        </div>
    </div>
</div>

<!-- Modal: Gerenciamento de Grupos -->
<div id="groupsModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="groupsModalTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--lg kd-glass kd-glass--raised">
        <div class="flex items-center justify-between pb-4 mb-5" style="border-bottom:1px solid var(--border)">
            <div class="flex items-center gap-2.5">
                <span class="w-8 h-8 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center">
                    <i data-lucide="folder" class="w-4 h-4"></i>
                </span>
                <h3 id="groupsModalTitle" class="text-lg font-bold text-white">Grupos de Notas</h3>
            </div>
            <button type="button" onclick="closeGroupsModal()" class="kd-icon-btn" aria-label="Fechar">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Formulario Rapido de Novo Grupo -->
        <form id="groupForm" onsubmit="saveGroup(event)" class="p-3.5 rounded-2xl bg-slate-950/40 border border-slate-800/80 mb-5 space-y-3">
            <input type="hidden" id="group_id" value="">
            <div class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-1.5" id="groupFormHeading">
                <i data-lucide="plus-circle" class="w-3.5 h-3.5 text-indigo-400"></i>
                <span>Criar Novo Grupo</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                <div class="sm:col-span-5">
                    <label for="group_name" class="block text-[11px] font-semibold text-slate-400 mb-1">Nome do Grupo *</label>
                    <input type="text" id="group_name" required placeholder="Ex: Atas de Reunião" class="kd-field text-xs" style="min-height:36px;padding-top:0.35rem;padding-bottom:0.35rem">
                </div>

                <div class="sm:col-span-4">
                    <label for="group_client_id" class="block text-[11px] font-semibold text-slate-400 mb-1">Cliente Vinculado (Opcional)</label>
                    <select id="group_client_id" class="kd-field text-xs" style="min-height:36px;padding-top:0.35rem;padding-bottom:0.35rem">
                        <option value="">Geral (Todos os clientes)</option>
                        <?php foreach ($clientsList as $cl): ?>
                            <option value="<?= (int)$cl['id'] ?>"><?= e($cl['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sm:col-span-3 flex items-center gap-2">
                    <div class="flex-1">
                        <label for="group_color" class="block text-[11px] font-semibold text-slate-400 mb-1">Cor</label>
                        <input type="color" id="group_color" value="#6366f1" class="w-full h-9 rounded-xl bg-slate-900 border border-slate-800 cursor-pointer p-0.5">
                    </div>
                    <button type="submit" class="kd-btn kd-btn--primary text-xs h-9 px-3 self-end shadow-sm">
                        <span>Salvar</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Lista de Grupos Existentes -->
        <div class="space-y-2 max-h-72 overflow-y-auto pr-1" id="groupsListContainer">
            <!-- Renderizado dinamicamente -->
        </div>

        <div class="flex justify-end pt-4 mt-4" style="border-top:1px solid var(--border)">
            <button type="button" onclick="closeGroupsModal()" class="kd-btn">Fechar</button>
        </div>
    </div>
</div>

<script>
let allNotes = [];
let allGroups = [];
let allClients = [];
let allAuthors = [];
let filterTimeout = null;

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const initialClientId = urlParams.get('client_id');
    if (initialClientId) {
        const select = document.getElementById('filterClient');
        if (select) select.value = initialClientId;
    }
    initNoteQuill();
    loadNotes();
});

function debounceNotesFilter() {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(loadNotes, 250);
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterClient').value = '';
    document.getElementById('filterGroup').value = '';
    document.getElementById('filterPeriod').value = 'all';
    document.getElementById('filterAuthor').value = '';
    loadNotes();
}

async function loadNotes() {
    const search = document.getElementById('filterSearch')?.value.trim() || '';
    const clientId = document.getElementById('filterClient')?.value || '';
    const groupId = document.getElementById('filterGroup')?.value || '';
    const period = document.getElementById('filterPeriod')?.value || 'all';
    const authorId = document.getElementById('filterAuthor')?.value || '';

    const hasFilter = search !== '' || clientId !== '' || groupId !== '' || period !== 'all' || authorId !== '';
    const wrap = document.getElementById('filterStatusWrap');
    if (wrap) wrap.hidden = !hasFilter;

    const params = new URLSearchParams();
    if (search) params.set('search', search);
    if (clientId) params.set('client_id', clientId);
    if (groupId) params.set('group_id', groupId);
    if (period && period !== 'all') params.set('period', period);
    if (authorId) params.set('author_id', authorId);

    try {
        const res = await fetch(`<?= url('/api/notas') ?>?${params.toString()}`);
        const data = await res.json();
        if (data.ok) {
            allNotes = data.notes || [];
            allGroups = data.groups || [];
            allClients = data.clients || [];
            allAuthors = data.authors || [];

            renderNotesGrid(allNotes);
            renderGroupsSelect(allGroups);
            renderGroupsModalList(allGroups);

            const counter = document.getElementById('notesCounter');
            if (counter) counter.textContent = `${allNotes.length} nota(s)`;
        }
    } catch (err) {
        console.error('Erro ao carregar notas:', err);
    }
}

let noteQuill = null;

function initNoteQuill() {
    if (noteQuill) return;
    const el = document.getElementById('noteQuillEditor');
    if (!el) return;

    noteQuill = new Quill('#noteQuillEditor', {
        theme: 'snow',
        placeholder: 'Digite aqui suas anotações ou cole diretamente do Word / Docs...',
        modules: {
            toolbar: [
                [{ 'header': [2, 3, false] }],
                [{ 'color': [] }, { 'background': [] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                ['blockquote', 'code-block'],
                ['link', 'clean']
            ]
        }
    });
}

function stripHtml(html) {
    if (!html) return '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
}

function viewNote(id) {
    const note = allNotes.find(n => n.id === id);
    if (!note) return;

    document.getElementById('viewNoteTitle').textContent = note.title;
    
    // Grupo
    const grpBadge = document.getElementById('viewNoteGroupBadge');
    if (note.group_name) {
        const grpColor = note.group_color || '#6366f1';
        grpBadge.style.display = 'inline-flex';
        grpBadge.innerHTML = `<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider" style="background:${grpColor}20;color:${grpColor};border:1px solid ${grpColor}40">
            <span class="w-1.5 h-1.5 rounded-full" style="background:${grpColor}"></span>
            <span>${escapeHtml(note.group_name)}</span>
        </span>`;
    } else {
        grpBadge.style.display = 'none';
        grpBadge.innerHTML = '';
    }

    // Cliente
    const cliBadge = document.getElementById('viewNoteClientBadge');
    if (note.client_company) {
        cliBadge.style.display = 'inline-flex';
        cliBadge.innerHTML = `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-800 text-slate-300 border border-slate-700">
            <i data-lucide="building-2" class="w-3 h-3 text-indigo-400"></i>
            <span>${escapeHtml(note.client_company)}</span>
        </span>`;
    } else {
        cliBadge.style.display = 'none';
        cliBadge.innerHTML = '';
    }

    // Pin
    const pinBadge = document.getElementById('viewNotePinnedBadge');
    if (pinBadge) {
        pinBadge.classList.toggle('hidden', !note.is_pinned);
    }

    document.getElementById('viewNoteAuthor').textContent = `Criado por ${note.author_name || 'Equipe'}`;
    document.getElementById('viewNoteDate').textContent = formatDateRelative(note.created_at);

    // Corpo renderizado (se tiver HTML renderiza diretamente, se for texto puro converte quebras)
    const bodyEl = document.getElementById('viewNoteBody');
    if (note.content && (note.content.includes('<p>') || note.content.includes('<br>') || note.content.includes('<div>') || note.content.includes('<ul>') || note.content.includes('<h3>'))) {
        bodyEl.innerHTML = note.content;
    } else {
        bodyEl.innerHTML = `<p class="whitespace-pre-line">${escapeHtml(note.content || '')}</p>`;
    }

    // Botao de editar a partir da visualizacao
    const editBtn = document.getElementById('btnEditFromView');
    if (editBtn) {
        editBtn.onclick = function() {
            closeViewNoteModal();
            openNoteModal(note);
        };
    }

    KD.openModal('viewNoteModal');
    KD.icons();
}

function closeViewNoteModal() {
    KD.closeModal('viewNoteModal');
}

function renderNotesGrid(notes) {
    const grid = document.getElementById('notesGrid');
    const empty = document.getElementById('emptyNotesState');
    if (!grid) return;

    if (notes.length === 0) {
        grid.innerHTML = '';
        if (empty) empty.hidden = false;
        return;
    }

    if (empty) empty.hidden = true;

    grid.innerHTML = notes.map(note => {
        const grpColor = note.group_color || '#6366f1';
        const clientName = note.client_company || '';
        const authorInitial = (note.author_name || 'U').substring(0, 1).toUpperCase();
        const dateStr = formatDateRelative(note.created_at);

        return `
            <div class="kd-card kd-glass rounded-2xl p-4 flex flex-col justify-between transition-all group hover:border-slate-700 relative shadow-sm ${note.is_pinned ? 'border-amber-500/40 bg-gradient-to-b from-amber-500/[0.04] to-transparent' : ''}" data-note-id="${note.id}">
                <div>
                    <!-- Topo do Card: Grupo, Cliente e Pin -->
                    <div class="flex items-start justify-between gap-2 mb-2.5">
                        <div class="flex items-center gap-1.5 flex-wrap min-w-0">
                            ${note.group_name ? `
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider" style="background: ${grpColor}20; color: ${grpColor}; border: 1px solid ${grpColor}40">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background: ${grpColor}"></span>
                                    <span class="truncate max-w-[110px]">${escapeHtml(note.group_name)}</span>
                                </span>
                            ` : ''}

                            ${clientName ? `
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-800/90 text-slate-300 border border-slate-700/60 truncate max-w-[120px]" title="Cliente: ${escapeHtml(clientName)}">
                                    <i data-lucide="building-2" class="w-3 h-3 text-indigo-400 flex-shrink-0"></i>
                                    <span class="truncate">${escapeHtml(clientName)}</span>
                                </span>
                            ` : ''}
                        </div>

                        <button type="button" onclick="togglePinNote(${note.id})"
                                class="p-1 rounded-lg transition ${note.is_pinned ? 'text-amber-400 bg-amber-500/15' : 'text-slate-500 hover:text-slate-300 bg-slate-800/40'}"
                                title="${note.is_pinned ? 'Desafixar nota' : 'Fixar nota no topo'}">
                            <i data-lucide="pin" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>

                    <!-- Titulo -->
                    <h3 onclick="viewNote(${note.id})" class="font-bold text-sm text-white leading-snug line-clamp-2 mb-2 group-hover:text-indigo-300 transition cursor-pointer" title="${escapeHtml(note.title)}">
                        ${escapeHtml(note.title)}
                    </h3>

                    <!-- Conteudo Resumido / Snippet Limpo -->
                    <div onclick="viewNote(${note.id})" class="text-xs text-slate-300/90 mb-3 line-clamp-4 leading-relaxed cursor-pointer hover:text-white transition">
                        ${escapeHtml(stripHtml(note.content))}
                    </div>
                </div>

                <!-- Footer do Card: Autor, Data e Acoes -->
                <div class="flex items-center justify-between pt-2.5 mt-1 text-xs border-t border-slate-800/50">
                    <div class="flex items-center gap-1.5 min-w-0" title="Criado por ${escapeHtml(note.author_name || '')}">
                        <div class="w-5 h-5 rounded-full bg-indigo-600/30 text-indigo-300 font-bold text-[10px] flex items-center justify-center flex-shrink-0">
                            ${authorInitial}
                        </div>
                        <span class="text-[11px] text-slate-400 truncate">${dateStr}</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <button type="button" onclick="viewNote(${note.id})" class="p-1.5 bg-indigo-600/15 hover:bg-indigo-600/30 text-indigo-400 hover:text-indigo-300 rounded-lg transition" title="Ler anotação completa">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        </button>
                        <button onclick="editNote(${note.id})" class="p-1.5 bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg transition" title="Editar">
                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                        </button>
                        <button onclick="deleteNote(${note.id})" class="p-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg transition" title="Excluir">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    KD.icons();
}

function renderGroupsSelect(groups) {
    const sel = document.getElementById('note_group_id');
    if (!sel) return;
    const currentVal = sel.value;
    sel.innerHTML = '<option value="">Sem grupo (Nota avulsa)</option>' + groups.map(g => {
        const clientSuffix = g.client_name ? ` [${escapeHtml(g.client_name)}]` : '';
        return `<option value="${g.id}">${escapeHtml(g.name)}${clientSuffix}</option>`;
    }).join('');
    sel.value = currentVal;
}

function onNoteGroupChange() {
    const gid = document.getElementById('note_group_id')?.value;
    if (!gid) return;
    const grp = allGroups.find(g => String(g.id) === String(gid));
    if (grp && grp.client_id) {
        const clientSel = document.getElementById('note_client_id');
        if (clientSel && !clientSel.value) {
            clientSel.value = grp.client_id;
        }
    }
}

function renderGroupsModalList(groups) {
    const cont = document.getElementById('groupsListContainer');
    if (!cont) return;

    if (groups.length === 0) {
        cont.innerHTML = '<p class="text-xs text-slate-500 italic p-4 text-center">Nenhum grupo cadastrado ainda.</p>';
        return;
    }

    cont.innerHTML = groups.map(g => `
        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-3.5 h-3.5 rounded-full flex-shrink-0" style="background:${g.color || '#6366f1'}"></span>
                <div class="min-w-0">
                    <span class="text-xs font-semibold text-white truncate block">${escapeHtml(g.name)}</span>
                    <span class="text-[10px] text-slate-400 block">
                        ${g.client_name ? `Cliente: ${escapeHtml(g.client_name)} &bull; ` : 'Geral &bull; '}
                        ${g.notes_count || 0} nota(s)
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-1 flex-shrink-0">
                <button type="button" onclick="editGroup(${g.id})" class="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-white rounded-lg transition" title="Editar">
                    <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                </button>
                <button type="button" onclick="deleteGroup(${g.id})" class="p-1.5 hover:bg-rose-500/10 text-slate-400 hover:text-rose-400 rounded-lg transition" title="Excluir">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
            </div>
        </div>
    `).join('');

    KD.icons();
}

function openNoteModal(note = null) {
    initNoteQuill();
    document.getElementById('note_id').value = note ? note.id : '';
    document.getElementById('note_title').value = note ? note.title : '';
    
    const contentVal = note ? (note.content || '') : '';
    document.getElementById('note_content').value = contentVal;
    if (noteQuill) {
        if (contentVal && (contentVal.includes('<p>') || contentVal.includes('<div>') || contentVal.includes('<br>') || contentVal.includes('<ul>') || contentVal.includes('<h3>'))) {
            noteQuill.root.innerHTML = contentVal;
        } else if (contentVal) {
            noteQuill.setText(contentVal);
        } else {
            noteQuill.setText('');
        }
    }

    document.getElementById('note_group_id').value = note && note.group_id ? note.group_id : '';
    document.getElementById('note_client_id').value = note && note.client_id ? note.client_id : '';
    document.getElementById('note_is_pinned').checked = note ? Boolean(note.is_pinned) : false;
    document.getElementById('noteModalTitle').textContent = note ? 'Editar Anotação' : 'Nova Anotação';
    KD.openModal('noteModal');
    setTimeout(() => { if (noteQuill) noteQuill.focus(); }, 150);
}

function closeNoteModal() {
    KD.closeModal('noteModal');
}

function editNote(id) {
    const note = allNotes.find(n => n.id === id);
    if (note) openNoteModal(note);
}

async function saveNote(event) {
    event.preventDefault();
    const id = document.getElementById('note_id').value;
    const title = document.getElementById('note_title').value.trim();
    let contentHtml = '';
    if (noteQuill) {
        const textOnly = noteQuill.getText().trim();
        const rawHtml = noteQuill.root.innerHTML;
        if (!textOnly && !rawHtml.includes('<img') && !rawHtml.includes('<table')) {
            contentHtml = '';
        } else {
            contentHtml = rawHtml;
        }
    } else {
        contentHtml = document.getElementById('note_content').value.trim();
    }

    if (!contentHtml) {
        alert('Por favor, informe o conteúdo da anotação.');
        return;
    }
    const content = contentHtml;
    const group_id = document.getElementById('note_group_id').value || null;
    const client_id = document.getElementById('note_client_id').value || null;
    const is_pinned = document.getElementById('note_is_pinned').checked ? 1 : 0;

    const btn = document.getElementById('btnSaveNote');
    if (btn) btn.disabled = true;

    try {
        const res = await fetch('<?= url('/api/notas') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_note',
                id: id,
                title: title,
                content: content,
                group_id: group_id,
                client_id: client_id,
                is_pinned: is_pinned
            })
        });
        const data = await res.json();
        if (data.ok) {
            closeNoteModal();
            loadNotes();
        } else {
            alert(data.error || 'Erro ao salvar nota.');
        }
    } catch (err) {
        console.error(err);
        alert('Erro ao conectar com o servidor.');
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function togglePinNote(id) {
    try {
        const res = await fetch('<?= url('/api/notas') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_pin', id: id })
        });
        const data = await res.json();
        if (data.ok) loadNotes();
    } catch (err) {
        console.error(err);
    }
}

async function deleteNote(id) {
    if (!confirm('Deseja realmente excluir esta nota?')) return;
    try {
        const res = await fetch('<?= url('/api/notas') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_note', id: id })
        });
        const data = await res.json();
        if (data.ok) loadNotes();
    } catch (err) {
        console.error(err);
    }
}

function openGroupsModal() {
    document.getElementById('group_id').value = '';
    document.getElementById('group_name').value = '';
    document.getElementById('group_client_id').value = '';
    document.getElementById('group_color').value = '#6366f1';
    document.getElementById('groupFormHeading').innerHTML = '<i data-lucide="plus-circle" class="w-3.5 h-3.5 text-indigo-400"></i><span>Criar Novo Grupo</span>';
    KD.openModal('groupsModal');
    KD.icons();
}

function closeGroupsModal() {
    KD.closeModal('groupsModal');
}

function openQuickGroupModal() {
    closeNoteModal();
    openGroupsModal();
}

function editGroup(id) {
    const grp = allGroups.find(g => g.id === id);
    if (!grp) return;
    document.getElementById('group_id').value = grp.id;
    document.getElementById('group_name').value = grp.name;
    document.getElementById('group_client_id').value = grp.client_id || '';
    document.getElementById('group_color').value = grp.color || '#6366f1';
    document.getElementById('groupFormHeading').innerHTML = '<i data-lucide="edit-3" class="w-3.5 h-3.5 text-indigo-400"></i><span>Editar Grupo</span>';
    KD.icons();
}

async function saveGroup(event) {
    event.preventDefault();
    const id = document.getElementById('group_id').value;
    const name = document.getElementById('group_name').value.trim();
    const client_id = document.getElementById('group_client_id').value || null;
    const color = document.getElementById('group_color').value;

    try {
        const res = await fetch('<?= url('/api/notas') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_group',
                id: id,
                name: name,
                client_id: client_id,
                color: color
            })
        });
        const data = await res.json();
        if (data.ok) {
            document.getElementById('group_id').value = '';
            document.getElementById('group_name').value = '';
            document.getElementById('group_client_id').value = '';
            document.getElementById('groupFormHeading').innerHTML = '<i data-lucide="plus-circle" class="w-3.5 h-3.5 text-indigo-400"></i><span>Criar Novo Grupo</span>';
            loadNotes();
        } else {
            alert(data.error || 'Erro ao salvar grupo.');
        }
    } catch (err) {
        console.error(err);
    }
}

async function deleteGroup(id) {
    if (!confirm('Deseja realmente excluir este grupo? As notas deste grupo permanecerão salvas.')) return;
    try {
        const res = await fetch('<?= url('/api/notas') ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_group', id: id })
        });
        const data = await res.json();
        if (data.ok) loadNotes();
    } catch (err) {
        console.error(err);
    }
}

function formatDateRelative(dateStr) {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr.replace(/-/g, '/'));
        return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch (e) {
        return dateStr;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(str).replace(/[&<>"']/g, m => map[m]);
}
</script>

<?php view_footer(); ?>
