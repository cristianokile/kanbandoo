<?php
/**
 * Modal rápido para definir o horário de atuação / expediente diário.
 */
?>
<div id="workHoursModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="workHoursModalTitle" hidden>
    <div class="kd-modal__panel kd-glass kd-glass--raised" style="max-width:28rem">

        <div class="flex items-center justify-between gap-3 pb-3 mb-4" style="border-bottom:1px solid var(--border)">
            <div class="flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl flex items-center justify-center bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    <i data-lucide="clock" class="w-5 h-5"></i>
                </span>
                <div>
                    <h2 id="workHoursModalTitle" class="text-base font-bold">Horário de Expediente</h2>
                    <p class="text-[11px] text-slate-400">Capacidade diária utilizada nas colunas</p>
                </div>
            </div>
            <button type="button" class="kd-icon-btn" onclick="KD.closeModal('workHoursModal')" aria-label="Fechar (Esc)">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="workHoursForm" onsubmit="KD.submitWorkHoursForm(event)" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="work_start_time_input" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Início</label>
                    <input type="time" id="work_start_time_input" name="work_start_time" value="09:00" required class="kd-field text-center font-mono" onchange="KD.calculateWorkHoursPreview()">
                </div>
                <div>
                    <label for="work_end_time_input" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Término</label>
                    <input type="time" id="work_end_time_input" name="work_end_time" value="17:00" required class="kd-field text-center font-mono" onchange="KD.calculateWorkHoursPreview()">
                </div>
            </div>

            <div class="p-3 rounded-xl bg-slate-900/60 border border-slate-800 text-center">
                <span class="text-xs text-slate-400">Carga horária diária:</span>
                <span id="workHoursCalculated" class="text-sm font-bold text-indigo-400 ml-1.5 font-mono">8h 00m (480 min)</span>
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
