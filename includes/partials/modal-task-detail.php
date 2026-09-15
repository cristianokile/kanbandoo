<?php
/**
 * Painel de detalhes da tarefa (5 abas especializadas).
 * Todo o conteúdo é preenchido e gerenciado por assets/js/kd-task.js.
 */
?>
<div id="taskDetailModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="detailTitle" hidden>
    <div class="kd-modal__panel kd-glass kd-glass--raised" style="max-width:58rem">

        <!-- Cabeçalho -->
        <div class="flex items-start justify-between gap-4 mb-3">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                    <span id="detailPriority" class="kd-due" data-tone="medium">Média</span>
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
                <button type="button" class="kd-icon-btn" data-detail-action="close" aria-label="Fechar (Esc)" title="Fechar">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        <div id="detailTags" class="kd-tags mb-3"></div>

        <!-- Informações principais -->
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-3 py-3 text-xs" style="border-block:1px solid var(--border)">
            <div>
                <dt class="text-slate-400 font-medium mb-0.5">Coluna</dt>
                <dd id="detailStage" class="font-semibold text-slate-200"></dd>
            </div>
            <div>
                <dt class="text-slate-400 font-medium mb-0.5">Prazo</dt>
                <dd id="detailDue" class="font-semibold text-slate-200"></dd>
            </div>
            <div>
                <dt class="text-slate-400 font-medium mb-0.5">Criada</dt>
                <dd id="detailCreated" class="font-semibold text-slate-200"></dd>
            </div>
            <div>
                <dt class="text-slate-400 font-medium mb-0.5">Conclusão</dt>
                <dd id="detailCompleted" class="font-semibold text-slate-200"></dd>
            </div>
            <div class="col-span-2">
                <dt class="text-slate-400 font-medium mb-0.5">Responsáveis</dt>
                <dd id="detailAssignees" class="kd-avatars"></dd>
            </div>
            <div class="col-span-1">
                <dt class="text-slate-400 font-medium mb-0.5">Tempo estimado</dt>
                <dd id="detailEstimated" class="font-semibold text-indigo-300">—</dd>
            </div>
            <div class="col-span-1">
                <dt class="text-slate-400 font-medium mb-0.5">Tempo dedicado</dt>
                <dd class="flex items-center gap-2">
                    <span id="detailTimer" class="kd-timer" style="font-size:0.8125rem">00:00:00</span>
                    <button type="button" id="detailPlayBtn" class="kd-play" data-detail-action="timer" data-running="0"
                            aria-label="Iniciar cronômetro">
                        <i data-lucide="play" class="w-3.5 h-3.5"></i>
                    </button>
                </dd>
            </div>
        </dl>

        <!-- Ações principais -->
        <div class="flex flex-wrap items-center justify-between gap-2 py-3">
            <div class="flex items-center gap-2">
                <button type="button" class="kd-btn kd-btn--primary" data-detail-action="edit">
                    <i data-lucide="edit-3" class="w-4 h-4"></i> <span>Editar tarefa</span>
                </button>
                <button type="button" id="detailCompleteBtn" class="kd-btn" data-detail-action="complete">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i> <span>Concluir</span>
                </button>
            </div>
        </div>

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

        <!-- Barra de Abas Especializadas -->
        <div class="flex items-center gap-1 mb-4 overflow-x-auto" style="border-bottom:1px solid var(--border)" role="tablist" aria-label="Seções da tarefa">
            <button type="button" id="tabBtn-details" class="modal-tab-btn" role="tab" aria-selected="true"
                    aria-controls="tabPanel-details" data-detail-action="tab" data-tab="details">
                <i data-lucide="file-text" class="w-4 h-4 inline"></i> Detalhes
            </button>
            <button type="button" id="tabBtn-comments" class="modal-tab-btn" role="tab" aria-selected="false"
                    aria-controls="tabPanel-comments" data-detail-action="tab" data-tab="comments">
                <i data-lucide="message-square" class="w-4 h-4 inline"></i> Comentários
                <span id="detailCommentsTabBadge" class="text-[10px] font-mono px-1.5 py-0.5 rounded-full bg-slate-800 text-slate-300 ml-1">0</span>
            </button>
            <button type="button" id="tabBtn-links" class="modal-tab-btn" role="tab" aria-selected="false"
                    aria-controls="tabPanel-links" data-detail-action="tab" data-tab="links">
                <i data-lucide="link-2" class="w-4 h-4 inline"></i> Links úteis
            </button>
            <button type="button" id="tabBtn-client" class="modal-tab-btn" role="tab" aria-selected="false"
                    aria-controls="tabPanel-client" data-detail-action="tab" data-tab="client">
                <i data-lucide="building-2" class="w-4 h-4 inline"></i> Cliente
            </button>
            <button type="button" id="tabBtn-history" class="modal-tab-btn" role="tab" aria-selected="false"
                    aria-controls="tabPanel-history" data-detail-action="tab" data-tab="history">
                <i data-lucide="history" class="w-4 h-4 inline"></i> Histórico
            </button>
        </div>

        <!-- Aba 1: Detalhes (Descrição e Subtarefas apenas em modo de visualização) -->
        <div id="tabPanel-details" class="space-y-4" role="tabpanel" aria-labelledby="tabBtn-details">
            <section class="kd-panel kd-glass space-y-2">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider flex items-center gap-2 text-slate-300">
                        <i data-lucide="align-left" class="w-4 h-4 text-indigo-400"></i> Descrição da Tarefa
                    </h3>
                    <button type="button" class="text-xs text-indigo-400 hover:text-indigo-300 font-semibold flex items-center gap-1.5 transition" data-detail-action="edit" title="Editar descrição e tarefa">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Editar
                    </button>
                </div>
                <div id="detailDescription" class="text-sm text-slate-300 leading-relaxed whitespace-pre-wrap p-3.5 rounded-xl bg-slate-900/40 border border-slate-800/60 min-h-[60px]"></div>
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

    </div>
</div>
