<?php
/**
 * Modal rápido para definir o horário de atuação / expediente diário e almoço.
 */
?>
<div id="workHoursModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="workHoursModalTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--sm kd-glass kd-glass--raised">

        <div class="flex items-center justify-between gap-3 pb-3 mb-4" style="border-bottom:1px solid var(--border)">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </span>
                <div>
                    <h2 id="workHoursModalTitle" class="text-base font-bold">Horário de Expediente</h2>
                    <p class="text-[11px] text-slate-400">Jornada de trabalho e intervalo de almoço</p>
                </div>
            </div>
            <button type="button" class="kd-icon-btn" onclick="KD.closeModal('workHoursModal')" aria-label="Fechar (Esc)">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>

        <form id="workHoursForm" onsubmit="KD.submitWorkHoursForm(event)" class="space-y-4">
            <!-- Horário de Entrada e Saída -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="work_start_time_input" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Início</label>
                    <input type="time" id="work_start_time_input" name="work_start_time" value="09:00" required class="kd-field text-center font-mono" onchange="KD.calculateWorkHoursPreview()">
                </div>
                <div>
                    <label for="work_end_time_input" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Término</label>
                    <input type="time" id="work_end_time_input" name="work_end_time" value="18:00" required class="kd-field text-center font-mono" onchange="KD.calculateWorkHoursPreview()">
                </div>
            </div>

            <!-- Horário de Almoço (Intervalo de 1h) -->
            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 space-y-2.5">
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-slate-200">
                        <input type="checkbox" id="work_has_lunch_input" checked onchange="KD.toggleLunchInputs(this.checked); KD.calculateWorkHoursPreview();" class="rounded text-indigo-600 focus:ring-0">
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="utensils" class="w-3.5 h-3.5 text-amber-400"></i>
                            Intervalo de Almoço
                        </span>
                    </label>
                    <span id="lunchDurationBadge" class="text-[11px] font-bold text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-md border border-amber-500/20">1h de almoço</span>
                </div>

                <div id="lunchInputsRow" class="grid grid-cols-2 gap-3 pt-1">
                    <div>
                        <label for="lunch_start_time_input" class="block text-[11px] font-medium text-slate-400 mb-1">Início Almoço</label>
                        <input type="time" id="lunch_start_time_input" name="lunch_start_time" value="12:00" class="kd-field text-center font-mono text-xs" onchange="KD.calculateWorkHoursPreview()">
                    </div>
                    <div>
                        <label for="lunch_end_time_input" class="block text-[11px] font-medium text-slate-400 mb-1">Retorno Almoço</label>
                        <input type="time" id="lunch_end_time_input" name="lunch_end_time" value="13:00" class="kd-field text-center font-mono text-xs" onchange="KD.calculateWorkHoursPreview()">
                    </div>
                </div>
            </div>

            <!-- Resumo e Carga Horária Líquida -->
            <div class="p-3.5 rounded-xl bg-slate-900/80 border border-slate-800 space-y-1.5 text-center">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-400">Jornada útil de trabalho:</span>
                    <span id="workHoursCalculated" class="text-sm font-bold text-indigo-400 font-mono">8h 00m (480 min)</span>
                </div>
                <div id="workHoursBreakdown" class="text-[11px] text-slate-400 flex items-center justify-between pt-1 border-t border-slate-800/60">
                    <span>Horário bruto: <b id="grossHoursText" class="text-slate-300">9h</b></span>
                    <span class="text-amber-400/90">Desconto almoço: <b id="lunchDiscountText">-1h</b></span>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" class="kd-btn kd-glass" onclick="KD.closeModal('workHoursModal')">Cancelar</button>
                <button type="submit" class="kd-btn kd-btn--primary">
                    <i data-lucide="check" class="w-4 h-4"></i> Salvar Expediente
                </button>
            </div>
        </form>
    </div>
</div>
