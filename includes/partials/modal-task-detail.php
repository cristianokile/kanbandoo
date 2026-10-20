<?php
/**
 * Painel de detalhes da tarefa (5 abas especializadas).
 * Todo o conteúdo é preenchido e gerenciado por assets/js/kd-task.js.
 */
?>
<div id="taskDetailModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="detailTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--xl kd-glass kd-glass--raised p-0 overflow-hidden flex flex-col">

        <!-- Cabecalho Fixo com Meta e Menu de Abas (permanece sempre visivel) -->
        <div class="kd-detail-header-wrap flex-shrink-0 px-6 pt-5 pb-0" style="border-bottom:1px solid var(--border)">

        <!-- Cabeçalho -->
        <div class="flex items-start justify-between gap-4 mb-3">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                    <span id="detailPriority" class="kd-due" data-tone="medium">Média</span>
                    <span id="detailRecurringBadge" class="kd-flag kd-flag--recurring" hidden title="Tarefa Recorrente">
                        <i data-lucide="repeat" class="w-3 h-3"></i>
                        <span id="detailRecurringText">Recorrente</span>
                    </span>
                    <span id="detailClientChip" class="kd-client" hidden></span>
                    <span id="detailIdBadge" class="text-xs font-mono text-slate-500">#0</span>
                </div>
                <h2 id="detailTitle" class="text-xl sm:text-2xl font-extrabold tracking-tight leading-tight"></h2>
            </div>

            <div class="flex items-center gap-1 flex-shrink-0">
                <button type="button" id="detailFocusBtn" class="kd-icon-btn" data-detail-action="focus"
                        aria-pressed="false" aria-label="Colocar tarefa em foco" title="Foco">
                    <i data-lucide="target" class="w-4 h-4"></i>
                </button>
                <button type="button" id="detailBombBtn" class="kd-icon-btn" data-detail-action="bomb"
                        aria-pressed="false" aria-label="Marcar tarefa como bomba" title="Bomba">
                    <i data-lucide="flame" class="w-4 h-4"></i>
                </button>
                <button type="button" id="detailCopyLinkBtn" class="kd-icon-btn" data-detail-action="copy-link" aria-label="Copiar link da tarefa" title="Copiar link">
                    <i data-lucide="link-2" class="w-4 h-4"></i>
                </button>
                <button type="button" id="detailDuplicateBtn" class="kd-icon-btn" data-detail-action="duplicate" aria-label="Duplicar tarefa" title="Duplicar">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                </button>
                <button type="button" id="detailDeleteBtn" class="kd-icon-btn text-red-400 hover:text-red-300 hover:bg-red-500/10" data-detail-action="delete" aria-label="Excluir tarefa (mover para a lixeira)" title="Excluir tarefa">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                </button>
                <button type="button" class="kd-icon-btn" data-detail-action="close" aria-label="Fechar (Esc)" title="Fechar">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        <div id="detailTags" class="kd-tags mb-3"></div>

        <!-- Informações principais -->
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-3 py-3 text-xs" style="border-block:1px solid var(--border)">
            <div>
                <dt class="text-slate-400 font-medium mb-1">Coluna / Status</dt>
                <dd class="flex items-center gap-2 flex-wrap">
                    <span id="detailStage" class="font-bold text-slate-200"></span>
                    <button type="button" id="detailCompleteBtn" class="kd-complete-pill" data-detail-action="complete" title="Concluir tarefa">
                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i>
                        <span>Concluir</span>
                    </button>
                </dd>
            </div>
            <div>
                <dt class="text-slate-400 font-medium mb-1">Prazo</dt>
                <dd id="detailDue" class="font-semibold text-slate-200"></dd>
            </div>
            <div>
                <dt class="text-slate-400 font-medium mb-1">Criada</dt>
                <dd id="detailCreated" class="font-semibold text-slate-200"></dd>
            </div>
            <div>
                <dt class="text-slate-400 font-medium mb-1">Conclusão</dt>
                <dd id="detailCompleted" class="font-semibold text-slate-200"></dd>
            </div>
            <div class="col-span-2">
                <dt class="text-slate-400 font-medium mb-1">Responsáveis</dt>
                <dd id="detailAssignees" class="kd-avatars"></dd>
            </div>
            <div class="col-span-1">
                <dt class="text-slate-400 font-medium mb-1">Tempo estimado</dt>
                <dd id="detailEstimated" class="font-semibold text-indigo-300">—</dd>
            </div>
            <div class="col-span-1">
                <dt class="text-slate-400 font-medium mb-1">Tempo dedicado</dt>
                <dd class="flex items-center gap-1.5 flex-wrap">
                    <span id="detailTimer" class="kd-timer font-mono text-xs font-bold" style="font-size:0.8125rem">00:00:00</span>
                    <button type="button" id="detailPlayBtn" class="kd-play" data-detail-action="timer" data-running="0"
                            aria-label="Iniciar cronômetro" title="Iniciar / Pausar cronômetro">
                        <i data-lucide="play" class="w-3.5 h-3.5"></i>
                    </button>
                    <button type="button" id="detailStopBtn" class="p-1.5 rounded-lg bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 border border-slate-700/60 transition inline-flex items-center justify-center cursor-pointer" data-detail-action="stop_timer"
                            aria-label="Parar tarefa" title="Parar tarefa e mover para A Fazer">
                        <i data-lucide="square" class="w-3.5 h-3.5"></i>
                    </button>
                    <!-- Ajustes rápidos de tempo: adicionar ou remover minutos -->
                    <div class="inline-flex items-center gap-1 ml-0.5" id="detailTimerAdjustWrap">
                        <button type="button" onclick="KD.adjustTaskTime(-15)" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-rose-300 hover:text-rose-200 text-[11px] font-mono border border-slate-700 transition" title="Remover 15 minutos do contador">
                            -15m
                        </button>
                        <button type="button" onclick="KD.adjustTaskTime(15)" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-emerald-300 hover:text-emerald-200 text-[11px] font-mono border border-slate-700 transition" title="Adicionar 15 minutos ao contador">
                            +15m
                        </button>
                        <button type="button" onclick="KD.adjustTaskTime(30)" class="px-1.5 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-emerald-300 hover:text-emerald-200 text-[11px] font-mono border border-slate-700 transition" title="Adicionar 30 minutos ao contador">
                            +30m
                        </button>
                        <button type="button" onclick="KD.openAdjustTimeModal()" class="p-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs border border-slate-700 transition" title="Ajuste personalizado (+ / - ou definir)">
                            <i data-lucide="sliders" class="w-3 h-3"></i>
                        </button>
                    </div>
                </dd>
            </div>
        </dl>

        <!-- Menção do WhatsApp -->
        <div id="detailMentionBox" class="mb-4 p-3 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3"
             style="background:var(--success-soft);border:1px solid color-mix(in srgb, var(--success) 30%, transparent)" hidden>
            <div class="flex items-start gap-3">
                <i data-lucide="message-square-quote" class="w-5 h-5 flex-shrink-0" style="color:var(--success)"></i>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide" style="color:var(--success)">
                        Menção no WhatsApp &bull; <span id="detailMentionGroup"></span>
                    </p>
                    <p id="detailMentionAuthor" class="text-xs text-slate-400 mt-0.5"></p>
                </div>
            </div>
            <a id="detailMentionLink" href="https://web.whatsapp.com" target="_blank" rel="noopener noreferrer" class="kd-btn">
                <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Abrir conversa
            </a>
        </div>

                <!-- Barra de Abas Especializadas Fixas -->
            <div class="flex items-center gap-1 overflow-x-auto scrollbar-none -mb-px" role="tablist" aria-label="Seções da tarefa">
                <button type="button" id="tabBtn-details" class="modal-tab-btn" role="tab" aria-selected="true"
                        aria-controls="tabPanel-details" data-detail-action="tab" data-tab="details">
                    <i data-lucide="align-left" class="w-4 h-4 inline"></i> Descrição
                </button>
                <button type="button" id="tabBtn-comments" class="modal-tab-btn" role="tab" aria-selected="false"
                        aria-controls="tabPanel-comments" data-detail-action="tab" data-tab="comments">
                    <i data-lucide="message-square" class="w-4 h-4 inline"></i> Comentários
                    <span id="detailCommentsTabBadge" class="text-[10px] font-mono px-1.5 py-0.5 rounded-full bg-slate-800 text-slate-300 ml-1">0</span>
                </button>
                <button type="button" id="tabBtn-links" class="modal-tab-btn" role="tab" aria-selected="false"
                        aria-controls="tabPanel-links" data-detail-action="tab" data-tab="links">
                    <i data-lucide="link-2" class="w-4 h-4 inline"></i> Links Úteis
                </button>
                <button type="button" id="tabBtn-client" class="modal-tab-btn" role="tab" aria-selected="false"
                        aria-controls="tabPanel-client" data-detail-action="tab" data-tab="client">
                    <i data-lucide="building-2" class="w-4 h-4 inline"></i> Cliente
                </button>
                <button type="button" id="tabBtn-history" class="modal-tab-btn" role="tab" aria-selected="false"
                        aria-controls="tabPanel-history" data-detail-action="tab" data-tab="history">
                    <i data-lucide="history" class="w-4 h-4 inline"></i> Histórico
                </button>
                <button type="button" id="tabBtn-notes" class="modal-tab-btn" role="tab" aria-selected="false"
                        aria-controls="tabPanel-notes" data-detail-action="tab" data-tab="notes">
                    <i data-lucide="notebook-tabs" class="w-4 h-4 inline text-amber-400"></i> Notas
                    <span id="detailNotesTabBadge" class="text-[10px] font-mono px-1.5 py-0.5 rounded-full bg-slate-800 text-slate-300 ml-1" hidden>0</span>
                </button>
            </div>
        </div>

        <!-- Conteúdo Ricamente Rolável das Abas -->
        <div class="kd-detail-body flex-1 overflow-y-auto px-6 py-5">

        <!-- Aba 1: Descrição e Subtarefas -->
        <div id="tabPanel-details" class="space-y-5" role="tabpanel" aria-labelledby="tabBtn-details">
            <section class="space-y-2">
                <div class="flex items-center justify-end">
                    <button type="button" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold flex items-center gap-1.5 transition px-2 py-1 rounded-lg hover:bg-slate-800/50" data-detail-action="edit" title="Editar descrição e tarefa">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Editar
                    </button>
                </div>
                <div id="detailDescription" class="detailDescription text-sm text-slate-200 leading-relaxed whitespace-pre-wrap min-h-[40px]"></div>
            </section>

            <section class="kd-panel kd-glass space-y-3">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider flex items-center gap-2 text-slate-300">
                        <i data-lucide="check-square" class="w-4 h-4 text-emerald-400"></i> Subtarefas
                    </h3>
                    <div class="flex items-center gap-3">
                        <span id="detailChecklistProgress" class="text-xs font-bold text-emerald-400"></span>
                        <button type="button" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold flex items-center gap-1.5 transition" data-detail-action="edit" title="Gerenciar subtarefas">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Editar
                        </button>
                    </div>
                </div>

                <div class="kd-progress__track" style="width:100%;height:6px" role="progressbar"
                     aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Progresso das subtarefas">
                    <div id="detailProgressBar" class="kd-progress__fill" style="width:0%"></div>
                </div>

                <!-- Lista de subtarefas em modo de visualização interativa -->
                <div id="detailChecklist" class="space-y-2"></div>

                <!-- Input inline para adição rápida de subtarefa -->
                <div class="pt-2 flex items-center gap-2">
                    <input type="text" id="detailQuickSubtaskInput"
                           placeholder="Adicionar nova subtarefa (Enter para salvar)..."
                           class="kd-field text-xs flex-1" style="min-height:34px;padding:0.4rem 0.75rem">
                    <button type="button" id="detailQuickSubtaskBtn" class="kd-btn kd-btn--primary text-xs" style="padding:0.4rem 0.8rem">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        <span>Adicionar</span>
                    </button>
                </div>
            </section>
        </div>

        <!-- Aba 2: Comentários (Divisão 50% / 50%: Feed à esquerda, Editor CRUD à direita) -->
        <div id="tabPanel-comments" class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start" role="tabpanel" aria-labelledby="tabBtn-comments" hidden>
            <!-- Lado Esquerdo (50%): Feed de Comentários -->
            <section class="kd-panel kd-glass space-y-3">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider flex items-center gap-2 text-slate-300">
                        <i data-lucide="message-square" class="w-4 h-4 text-indigo-400"></i> Comentários
                    </h3>
                    <span id="detailCommentsCount" class="text-xs text-slate-400 font-mono">0</span>
                </div>
                <div id="detailComments" class="space-y-3 max-h-[380px] overflow-y-auto pr-1"></div>
            </section>

            <!-- Lado Direito (50%): Mini Painel CRUD Editor de Mensagens -->
            <section class="kd-panel kd-glass space-y-3">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-2">
                    <h3 id="commentEditorHeading" class="text-xs font-bold uppercase tracking-wider flex items-center gap-2 text-slate-300">
                        <i data-lucide="edit-3" class="w-4 h-4 text-emerald-400"></i> Novo Comentário
                    </h3>
                    <span id="commentEditingIndicator" class="text-[10px] bg-amber-500/20 text-amber-300 border border-amber-500/30 px-2 py-0.5 rounded-full font-semibold" hidden>Modo Edição</span>
                </div>

                <form id="commentForm" onsubmit="KD.submitComment(event)" class="space-y-3">
                    <input type="hidden" id="editingCommentId" value="">
                    <div>
                        <label for="newCommentInput" class="sr-only">Comentário</label>
                        <textarea id="newCommentInput" rows="5" required class="kd-field w-full text-xs" style="resize:vertical"
                                  placeholder="Escreva sua mensagem ou atualização sobre a tarefa..."></textarea>
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" id="btnCancelEditComment" onclick="KD.cancelEditComment()" class="kd-btn text-xs" hidden>
                            Cancelar
                        </button>
                        <button type="submit" id="btnSubmitComment" class="kd-btn kd-btn--primary text-xs">
                            <i data-lucide="send" class="w-3.5 h-3.5"></i>
                            <span id="btnSubmitCommentLabel">Enviar comentário</span>
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <!-- Aba 3: Links Úteis -->
        <div id="tabPanel-links" class="space-y-4" role="tabpanel" aria-labelledby="tabBtn-links" hidden>
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <p class="text-xs text-slate-400">
                    Atalhos e links úteis associados a este cliente ou demanda.
                </p>
                <a href="<?= url('/clientes') ?>" class="kd-btn text-xs" title="Gerenciar no cadastro de clientes">
                    <i data-lucide="settings-2" class="w-3.5 h-3.5"></i> Gerenciar links
                </a>
            </div>

            <div id="detailLinks" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
        </div>

        <!-- Aba 4: Informações do Cliente -->
        <div id="tabPanel-client" class="space-y-4" role="tabpanel" aria-labelledby="tabBtn-client" hidden>
            <section class="kd-panel kd-glass space-y-4">
                <div class="pb-3 border-b border-slate-800">
                    <h3 id="clientName" class="text-base font-bold text-white"></h3>
                    <p id="clientContact" class="text-xs text-slate-400 mt-0.5"></p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="p-3 rounded-xl flex items-center gap-3 bg-slate-900/50 border border-slate-800">
                        <i data-lucide="mail" class="w-4 h-4 flex-shrink-0 text-indigo-400"></i>
                        <span class="min-w-0">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">E-mail</span>
                            <a href="#" id="clientEmail" class="truncate block text-slate-200 hover:text-indigo-400"></a>
                        </span>
                    </div>
                    <div class="p-3 rounded-xl flex items-center gap-3 bg-slate-900/50 border border-slate-800">
                        <i data-lucide="phone" class="w-4 h-4 flex-shrink-0 text-emerald-400"></i>
                        <span class="min-w-0">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">WhatsApp / Telefone</span>
                            <a href="#" target="_blank" rel="noopener noreferrer" id="clientPhone" class="truncate block font-semibold text-slate-200 hover:text-emerald-400"></a>
                        </span>
                    </div>
                </div>

                <div>
                    <span class="block text-[10px] uppercase font-bold text-slate-400 mb-1.5">Observações do Cliente</span>
                    <p id="clientNotes" class="text-xs leading-relaxed p-3 rounded-xl bg-slate-900/50 border border-slate-800 text-slate-300"></p>
                </div>

                <div class="pt-2">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-300">Outras demandas deste cliente</h4>
                        <span id="detailClientActivitiesCount" class="text-[11px] text-slate-400"></span>
                    </div>
                    <div id="detailClientActivities" class="space-y-2 max-h-48 overflow-y-auto pr-1"></div>
                </div>
            </section>
        </div>

        <!-- Aba 5: Histórico / Activity Log -->
        <div id="tabPanel-history" class="space-y-4" role="tabpanel" aria-labelledby="tabBtn-history" hidden>
            <section class="kd-panel kd-glass space-y-3">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider flex items-center gap-2 text-slate-300">
                        <i data-lucide="history" class="w-4 h-4 text-indigo-400"></i> Histórico da Tarefa (Activity Log)
                    </h3>
                    <span class="text-[11px] text-slate-400">Eventos e alterações registradas</span>
                </div>
                <div id="detailHistoryTimeline" class="space-y-3 max-h-[420px] overflow-y-auto pr-1"></div>
            </section>
        </div>

        <!-- Aba 6: Notas do Cliente da Tarefa -->
        <div id="tabPanel-notes" class="space-y-4" role="tabpanel" aria-labelledby="tabBtn-notes" hidden>
            <div class="flex items-center justify-between gap-3 pb-3 border-b border-slate-800">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-amber-500/15 text-amber-400 flex items-center justify-center">
                        <i data-lucide="notebook-tabs" class="w-4 h-4"></i>
                    </span>
                    <div>
                        <h4 class="text-sm font-bold text-white" id="detailClientNotesHeader">Notas do Cliente</h4>
                        <p class="text-[11px] text-slate-400" id="detailClientNotesSub">Diretrizes, credenciais e alinhamentos deste cliente</p>
                    </div>
                </div>
                <button type="button" id="btnAddNoteFromTaskBtn" onclick="KD.openQuickNoteForTaskClient()" class="kd-btn kd-btn--primary text-xs py-1.5 px-3 shadow-sm">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    <span>Nova Nota</span>
                </button>
            </div>

            <!-- Aviso se a tarefa não tiver cliente vinculado -->
            <div id="detailNoClientNotesNotice" class="p-6 rounded-2xl bg-slate-900/60 border border-slate-800 text-center" hidden>
                <div class="w-10 h-10 rounded-xl bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-2.5">
                    <i data-lucide="building-2" class="w-5 h-5"></i>
                </div>
                <p class="text-xs font-semibold text-white mb-1">Esta tarefa não possui cliente vinculado.</p>
                <p class="text-[11px] text-slate-400 max-w-sm mx-auto">Vincule um cliente à tarefa para visualizar suas notas, diretrizes estratégicas e atas de reuniões.</p>
            </div>

            <!-- Lista de Notas do Cliente -->
            <div id="detailClientNotesList" class="space-y-3">
                <!-- Preenchido via kd-task.js -->
            </div>
        </div>

        </div> <!-- fecha kd-detail-body -->
    </div> <!-- fecha kd-modal__panel -->
</div>
