<?php
/**
 * Painel de detalhes da tarefa (4 abas).
 * Todo o conteúdo é preenchido por assets/js/kd-task.js.
 */
?>
<div id="taskDetailModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="detailTitle" hidden>
    <div class="kd-modal__panel kd-glass kd-glass--raised" style="max-width:56rem">

        <!-- Cabeçalho -->
        <div class="flex items-start justify-between gap-4 mb-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap mb-2">
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
                <button type="button" class="kd-icon-btn" data-detail-action="copy-link" aria-label="Copiar link da tarefa" title="Copiar link">
                    <i data-lucide="link-2" class="w-4 h-4"></i>
                </button>
                <button type="button" class="kd-icon-btn" data-detail-action="duplicate" aria-label="Duplicar tarefa" title="Duplicar">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                </button>
                <button type="button" class="kd-icon-btn" data-detail-action="close" aria-label="Fechar (Esc)" title="Fechar">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>

        <div id="detailTags" class="kd-tags mb-4"></div>

        <p id="detailDescription" class="text-sm text-slate-400 mb-5 leading-relaxed"></p>

        <!-- Informações principais -->
        <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 py-4 text-xs" style="border-block:1px solid var(--border)">
            <div>
                <dt class="text-slate-400 font-medium mb-1">Coluna</dt>
                <dd id="detailStage" class="font-semibold"></dd>
            </div>
            <div>
                <dt class="text-slate-400 font-medium mb-1">Prazo</dt>
                <dd id="detailDue" class="font-semibold"></dd>
            </div>
            <div>
                <dt class="text-slate-400 font-medium mb-1">Criada</dt>
                <dd id="detailCreated" class="font-semibold"></dd>
            </div>
            <div>
                <dt class="text-slate-400 font-medium mb-1">Conclusão</dt>
                <dd id="detailCompleted" class="font-semibold"></dd>
            </div>
            <div class="col-span-2">
                <dt class="text-slate-400 font-medium mb-1">Responsáveis</dt>
                <dd id="detailAssignees" class="kd-avatars"></dd>
            </div>
            <div class="col-span-2">
                <dt class="text-slate-400 font-medium mb-1">Tempo dedicado</dt>
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
        <div class="flex flex-wrap items-center gap-2 py-4">
            <button type="button" class="kd-btn kd-btn--primary" data-detail-action="edit">
                <i data-lucide="edit-3" class="w-4 h-4"></i> Editar
            </button>
            <button type="button" id="detailCompleteBtn" class="kd-btn" data-detail-action="complete">
                <i data-lucide="check-circle-2" class="w-4 h-4"></i> <span>Concluir</span>
            </button>
        </div>

        <!-- Menção do WhatsApp -->
        <div id="detailMentionBox" class="mb-5 p-4 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3"
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

        <!-- Abas -->
        <div class="flex items-center gap-1 mb-5 overflow-x-auto" style="border-bottom:1px solid var(--border)" role="tablist" aria-label="Seções da tarefa">
            <button type="button" id="tabBtn-activities" class="modal-tab-btn" role="tab" aria-selected="true"
                    aria-controls="tabPanel-activities" data-detail-action="tab" data-tab="activities">
                <i data-lucide="list-checks" class="w-4 h-4 inline"></i> Atividades
            </button>
            <button type="button" id="tabBtn-links" class="modal-tab-btn" role="tab" aria-selected="false"
                    aria-controls="tabPanel-links" data-detail-action="tab" data-tab="links">
                <i data-lucide="link" class="w-4 h-4 inline"></i> Links úteis
            </button>
            <button type="button" id="tabBtn-summary" class="modal-tab-btn" role="tab" aria-selected="false"
                    aria-controls="tabPanel-summary" data-detail-action="tab" data-tab="summary">
                <i data-lucide="file-text" class="w-4 h-4 inline"></i> Resumo
            </button>
            <button type="button" id="tabBtn-client" class="modal-tab-btn" role="tab" aria-selected="false"
                    aria-controls="tabPanel-client" data-detail-action="tab" data-tab="client">
                <i data-lucide="building-2" class="w-4 h-4 inline"></i> Cliente
            </button>
        </div>

        <!-- Aba: Atividades -->
        <div id="tabPanel-activities" class="space-y-5" role="tabpanel" aria-labelledby="tabBtn-activities">

            <section class="kd-panel kd-glass space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="check-square" class="w-4 h-4" style="color:var(--brand-strong)"></i>
                        Subtarefas
                    </h3>
                    <span id="detailChecklistProgress" class="text-xs font-bold" style="color:var(--brand-strong)"></span>
                </div>

                <div class="kd-progress__track" style="width:100%;height:6px" role="progressbar"
                     aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Progresso das subtarefas">
                    <div id="detailProgressBar" class="kd-progress__fill" style="width:0%"></div>
                </div>

                <div id="detailChecklist" class="space-y-2"></div>

                <form onsubmit="KD.addChecklistItem(event)" class="flex gap-2 pt-1">
                    <label for="newChecklistInput" class="sr-only">Nova subtarefa</label>
                    <input type="text" id="newChecklistInput" placeholder="Adicionar subtarefa..." class="kd-field" autocomplete="off">
                    <button type="submit" class="kd-btn kd-btn--primary">Adicionar</button>
                </form>
            </section>

            <section class="kd-panel kd-glass space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                    <i data-lucide="message-square" class="w-4 h-4" style="color:var(--brand-strong)"></i>
                    Comentários
                </h3>
                <div id="detailComments" class="space-y-2 max-h-64 overflow-y-auto pr-1"></div>
                <form onsubmit="KD.addComment(event)" class="flex gap-2">
                    <label for="newCommentInput" class="sr-only">Novo comentário</label>
                    <input type="text" id="newCommentInput" placeholder="Escreva um comentário..." class="kd-field" autocomplete="off">
                    <button type="submit" class="kd-btn kd-btn--primary">Enviar</button>
                </form>
            </section>

            <section class="kd-panel kd-glass space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="activity" class="w-4 h-4" style="color:var(--success)"></i>
                        Outras demandas deste cliente
                    </h3>
                    <span id="detailClientActivitiesCount" class="text-[11px] text-slate-400"></span>
                </div>
                <div id="detailClientActivities" class="space-y-2 max-h-60 overflow-y-auto pr-1"></div>
            </section>
        </div>

        <!-- Aba: Links úteis (somente consulta — editar é no cadastro do cliente) -->
        <div id="tabPanel-links" class="space-y-4" role="tabpanel" aria-labelledby="tabBtn-links" hidden>
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <p class="text-xs text-slate-400">
                    Atalhos do cliente (site, BM, MCC, redes sociais, pastas). Clique para abrir em outra aba.
                </p>
                <a href="<?= url('/clientes') ?>" class="kd-btn" title="Adicionar ou remover atalhos no cadastro">
                    <i data-lucide="settings-2" class="w-3.5 h-3.5"></i>
                    Gerenciar no cadastro
                </a>
            </div>

            <div id="detailLinks" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
        </div>

        <!-- Aba: Resumo -->
        <div id="tabPanel-summary" class="space-y-4" role="tabpanel" aria-labelledby="tabBtn-summary" hidden>
            <form onsubmit="KD.addSummary(event)" class="kd-panel kd-glass space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider">Registrar o que foi feito</h3>
                <label for="taskSummaryInput" class="sr-only">Resumo do que foi feito</label>
                <textarea id="taskSummaryInput" rows="3" required class="kd-field" style="resize:vertical"
                          placeholder="Ex: ajustei os lances de conversão, criei novos anúncios e validei o relatório de KPIs..."></textarea>
                <div class="flex justify-end">
                    <button type="submit" class="kd-btn kd-btn--primary">
                        <i data-lucide="check" class="w-3.5 h-3.5"></i> Salvar no histórico
                    </button>
                </div>
            </form>

            <section class="kd-panel kd-glass space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider flex items-center gap-2">
                    <i data-lucide="history" class="w-4 h-4" style="color:var(--success)"></i>
                    Histórico de execução
                </h3>
                <div id="detailSummaries" class="space-y-3"></div>
            </section>
        </div>

        <!-- Aba: Cliente -->
        <div id="tabPanel-client" class="space-y-4" role="tabpanel" aria-labelledby="tabBtn-client" hidden>
            <section class="kd-panel kd-glass space-y-4">
                <div class="pb-4" style="border-bottom:1px solid var(--border)">
                    <h3 id="clientName" class="text-base font-bold"></h3>
                    <p id="clientContact" class="text-xs text-slate-400"></p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="p-3 rounded-xl flex items-center gap-3" style="background:var(--surface-1);border:1px solid var(--border)">
                        <i data-lucide="mail" class="w-4 h-4 flex-shrink-0" style="color:var(--brand-strong)"></i>
                        <span class="min-w-0">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">E-mail</span>
                            <a href="#" id="clientEmail" class="truncate block"></a>
                        </span>
                    </div>
                    <div class="p-3 rounded-xl flex items-center gap-3" style="background:var(--surface-1);border:1px solid var(--border)">
                        <i data-lucide="phone" class="w-4 h-4 flex-shrink-0" style="color:var(--success)"></i>
                        <span class="min-w-0">
                            <span class="block text-[10px] uppercase font-bold text-slate-400">WhatsApp</span>
                            <a href="#" target="_blank" rel="noopener noreferrer" id="clientPhone" class="truncate block font-semibold"></a>
                        </span>
                    </div>
                </div>

                <div>
                    <span class="block text-[10px] uppercase font-bold text-slate-400 mb-1.5">Observações</span>
                    <p id="clientNotes" class="text-xs leading-relaxed p-3 rounded-xl"
                       style="background:var(--surface-1);border:1px solid var(--border)"></p>
                </div>
            </section>
        </div>
    </div>
</div>
