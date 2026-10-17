<?php
/**
 * Modal visual para ajuste manual do tempo da tarefa (adicionar / subtrair / definir).
 */
?>
<div id="adjustTimeModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="adjustTimeModalTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--sm kd-glass kd-glass--raised">

        <div class="flex items-center justify-between gap-3 pb-3 mb-4" style="border-bottom:1px solid var(--border)">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </span>
                <div>
                    <h2 id="adjustTimeModalTitle" class="text-base font-bold text-white">Ajustar Tempo da Tarefa</h2>
                    <p id="adjustTimeTaskSubtitle" class="text-[11px] text-slate-400 truncate max-w-[220px] sm:max-w-[280px]">Alterar tempo dedicado</p>
                </div>
            </div>
            <button type="button" class="kd-icon-btn" onclick="KD.closeModal('adjustTimeModal')" aria-label="Fechar (Esc)">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="adjustTimeForm" onsubmit="KD.submitAdjustTimeForm(event)" class="space-y-4">
            <input type="hidden" id="adjust_time_task_id" value="">

            <!-- Placar de tempo atual e tempo previsto -->
            <div class="grid grid-cols-2 gap-3 p-3 rounded-2xl bg-slate-900/80 border border-slate-800 text-center">
                <div>
                    <span class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-0.5">Tempo Atual</span>
                    <span id="adjustTimeCurrentDisplay" class="font-mono text-base font-bold text-slate-200">00:00:00</span>
                </div>
                <div>
                    <span class="block text-[11px] font-semibold text-indigo-300 uppercase tracking-wider mb-0.5">Novo Tempo</span>
                    <span id="adjustTimePreviewDisplay" class="font-mono text-base font-bold text-emerald-400">00:00:00</span>
                </div>
            </div>

            <!-- Botões de Atalhos Rápidos (+ e -) -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                    Atalhos Rápidos
                </label>
                <div class="grid grid-cols-4 gap-1.5 mb-2">
                    <button type="button" onclick="KD.applyQuickTimeDelta(-60)" class="px-2 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 text-xs font-mono font-medium transition text-center" title="Subtrair 1 hora">
                        -60m
                    </button>
                    <button type="button" onclick="KD.applyQuickTimeDelta(-30)" class="px-2 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 text-xs font-mono font-medium transition text-center" title="Subtrair 30 minutos">
                        -30m
                    </button>
                    <button type="button" onclick="KD.applyQuickTimeDelta(-15)" class="px-2 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 text-xs font-mono font-medium transition text-center" title="Subtrair 15 minutos">
                        -15m
                    </button>
                    <button type="button" onclick="KD.applyQuickTimeDelta(-5)" class="px-2 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 text-xs font-mono font-medium transition text-center" title="Subtrair 5 minutos">
                        -5m
                    </button>
                </div>
                <div class="grid grid-cols-4 gap-1.5">
                    <button type="button" onclick="KD.applyQuickTimeDelta(5)" class="px-2 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-mono font-medium transition text-center" title="Adicionar 5 minutos">
                        +5m
                    </button>
                    <button type="button" onclick="KD.applyQuickTimeDelta(15)" class="px-2 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-mono font-medium transition text-center" title="Adicionar 15 minutos">
                        +15m
                    </button>
                    <button type="button" onclick="KD.applyQuickTimeDelta(30)" class="px-2 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-mono font-medium transition text-center" title="Adicionar 30 minutos">
                        +30m
                    </button>
                    <button type="button" onclick="KD.applyQuickTimeDelta(60)" class="px-2 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-mono font-medium transition text-center" title="Adicionar 1 hora">
                        +60m
                    </button>
                </div>
            </div>

            <!-- Modos de Ajuste: Somar/Subtrair vs Fixar Tempo Exato -->
            <div class="space-y-3 pt-2 border-t border-slate-800">
                <div class="flex items-center gap-4 text-xs">
                    <label class="flex items-center gap-1.5 cursor-pointer text-slate-300 font-medium">
                        <input type="radio" name="adjust_time_mode" id="adjust_mode_delta" value="delta" checked onchange="KD.toggleAdjustTimeMode('delta')">
                        <span>Adicionar / Subtrair minutos</span>
                    </label>
                    <label class="flex items-center gap-1.5 cursor-pointer text-slate-300 font-medium">
                        <input type="radio" name="adjust_time_mode" id="adjust_mode_exact" value="exact" onchange="KD.toggleAdjustTimeMode('exact')">
                        <span>Definir tempo exato</span>
                    </label>
                </div>

                <!-- Painel Modo Delta -->
                <div id="adjustDeltaPanel" class="space-y-2">
                    <div class="flex items-center gap-2">
                        <select id="adjust_delta_sign" onchange="KD.recalcAdjustTimePreview()" class="kd-field w-32 text-xs">
                            <option value="1">➕ Adicionar (+)</option>
                            <option value="-1">➖ Subtrair (-)</option>
                        </select>
                        <div class="relative flex-1">
                            <input type="number" id="adjust_delta_minutes" min="0" step="1" value="15" placeholder="Ex: 30"
                                   class="kd-field text-center font-mono text-sm" style="min-height:38px"
                                   oninput="KD.recalcAdjustTimePreview()">
                            <span class="absolute right-3 top-2.5 text-xs text-slate-400 pointer-events-none">minutos</span>
                        </div>
                    </div>
                </div>

                <!-- Painel Modo Exato -->
                <div id="adjustExactPanel" class="space-y-2" hidden>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="adjust_exact_hours" class="block text-[11px] text-slate-400 mb-1">Horas</label>
                            <input type="number" id="adjust_exact_hours" min="0" step="1" value="0"
                                   class="kd-field text-center font-mono text-sm" style="min-height:38px"
                                   oninput="KD.recalcAdjustTimePreview()">
                        </div>
                        <div>
                            <label for="adjust_exact_minutes" class="block text-[11px] text-slate-400 mb-1">Minutos</label>
                            <input type="number" id="adjust_exact_minutes" min="0" max="59" step="1" value="0"
                                   class="kd-field text-center font-mono text-sm" style="min-height:38px"
                                   oninput="KD.recalcAdjustTimePreview()">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-2 pt-3 border-t border-slate-800">
                <button type="button" onclick="KD.resetTimeZero()" class="px-3 py-2 text-xs font-medium text-slate-400 hover:text-rose-400 transition" title="Zerar tempo dedicado desta tarefa">
                    Zerar tempo
                </button>
                <div class="flex items-center gap-2">
                    <button type="button" class="kd-btn kd-glass" onclick="KD.closeModal('adjustTimeModal')">
                        Cancelar
                    </button>
                    <button type="submit" id="btnSubmitAdjustTime" class="kd-btn kd-btn--primary">
                        <i data-lucide="check" class="w-4 h-4"></i> Salvar Ajuste
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
