/**
 * KanbanDoo - Módulo de Notificações Push & Lembretes de Tarefas
 * Monitora tarefas com horário agendado e dispara notificações nativas
 * do navegador com botões "Fechar" e "Lembrar-me em 5 minutos",
 * além de banner in-app flutuante e som suave de sino via Web Audio API.
 */
window.KD = window.KD || {};

(function (KD) {
    'use strict';

    let swRegistration = null;
    let monitoredReminders = [];
    let checkInterval = null;

    /**
     * Toca um som suave de chime (sino de dois tons harmônicos) sem arquivos externos
     */
    KD.playReminderChime = function () {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            const now = ctx.currentTime;

            // Tom 1: Ré 5 (587.33 Hz)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, now);
            gain1.gain.setValueAtTime(0.18, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.35);

            // Tom 2: Lá 5 (880 Hz)
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(880, now + 0.12);
            gain2.gain.setValueAtTime(0.22, now + 0.12);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.65);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(now + 0.12);
            osc2.stop(now + 0.65);
        } catch (err) {
            // Silencioso se bloqueado por autoplay policy
        }
    };

    /**
     * Solicita permissão de notificação nativa do navegador
     */
    KD.requestNotificationPermission = async function () {
        if (!('Notification' in window)) {
            KD.toast('Este navegador não suporta notificações de área de trabalho.', { tone: 'warning' });
            return false;
        }

        try {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                KD.toast('Notificações de lembretes ativadas com sucesso!', { tone: 'success' });
                return true;
            } else {
                KD.toast('Permissão de notificações não concedida.', { tone: 'info' });
                return false;
            }
        } catch (e) {
            return false;
        }
    };

    /**
     * Registra o Service Worker para suporte a botões de ação no push
     */
    async function registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            try {
                const swUrl = (KD.base ? KD.base : '') + '/sw.js';
                swRegistration = await navigator.serviceWorker.register(swUrl, { scope: (KD.base ? KD.base : '/') + '/' });
                
                // Escuta mensagens do SW (ex: ação de snooze acionada no push nativo do SO)
                navigator.serviceWorker.addEventListener('message', (event) => {
                    if (event.data && event.data.action === 'snooze_reminder') {
                        KD.snoozeTaskReminder(event.data.taskId, event.data.minutes || 5);
                    }
                });
            } catch (e) {
                // Se falhar registro do SW, notificações comuns ainda funcionam
            }
        }
    }

    /**
     * Adia o lembrete de uma tarefa por X minutos
     */
    KD.snoozeTaskReminder = function (taskId, minutes = 5) {
        if (!taskId) return;
        const snoozeUntil = Date.now() + (minutes * 60 * 1000);
        localStorage.setItem(`kd_snooze_${taskId}`, String(snoozeUntil));

        // Remove o banner in-app se estiver aberto
        const banner = document.getElementById(`inAppReminderBanner_${taskId}`);
        if (banner) {
            banner.remove();
        }

        KD.toast(`Lembrete adiado em ${minutes} minutos.`, { tone: 'info' });
    };

    /**
     * Fecha o banner in-app de lembrete
     */
    KD.dismissReminderBanner = function (taskId) {
        const banner = document.getElementById(`inAppReminderBanner_${taskId}`);
        if (banner) {
            banner.remove();
        }
    };

    /**
     * Exibe o banner de alerta in-app no topo da interface
     */
    function showInAppReminder(task) {
        const bannerId = `inAppReminderBanner_${task.id}`;
        if (document.getElementById(bannerId)) return; // Já visível

        let container = document.getElementById('kdReminderBannerContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'kdReminderBannerContainer';
            container.className = 'fixed top-4 right-4 sm:right-6 z-[999999] flex flex-col gap-2 max-w-sm sm:max-w-md w-full pointer-events-none px-3 sm:px-0';
            document.body.appendChild(container);
        }

        const banner = document.createElement('div');
        banner.id = bannerId;
        banner.className = 'pointer-events-auto p-4 rounded-2xl bg-slate-950/95 border border-indigo-500/40 shadow-2xl backdrop-blur-2xl animate-fade-in flex flex-col gap-3 text-slate-200';
        banner.style.boxShadow = '0 10px 35px -5px rgba(99, 102, 241, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1)';

        const timeStr = task.due_time ? task.due_time.substring(0, 5) : '';
        const clientStr = task.client_company ? KD.escapeHtml(task.client_company) : '';

        banner.innerHTML = `
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 flex items-center justify-center flex-shrink-0 animate-bounce">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    </span>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                                Lembrete de Horário
                            </span>
                            ${timeStr ? `<span class="text-xs font-mono font-bold text-amber-400">${timeStr}</span>` : ''}
                        </div>
                        <h4 class="font-bold text-sm text-white mt-1 leading-snug truncate" title="${KD.escapeHtml(task.title)}">
                            ${KD.escapeHtml(task.title)}
                        </h4>
                        ${clientStr ? `<p class="text-[11px] text-slate-400 truncate mt-0.5">${clientStr}</p>` : ''}
                    </div>
                </div>
                <button type="button" onclick="KD.dismissReminderBanner(${task.id})" class="text-slate-400 hover:text-white p-1 rounded-lg transition" title="Fechar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <!-- Botões de Ação -->
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800/80">
                <button type="button" onclick="KD.snoozeTaskReminder(${task.id}, 5)" class="px-2.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold flex items-center gap-1.5 transition border border-slate-700/60 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-amber-400"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>Lembrar em 5 min</span>
                </button>
                <button type="button" onclick="KD.openTaskDetail(${task.id}); KD.dismissReminderBanner(${task.id});" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold flex items-center gap-1 transition shadow-md shadow-indigo-600/30">
                    <span>Ver Tarefa</span>
                </button>
            </div>
        `;

        container.appendChild(banner);
    }

    /**
     * Dispara notificação push de sistema (Área de trabalho / Celular)
     */
    async function triggerSystemNotification(task) {
        if (!('Notification' in window) || Notification.permission !== 'granted') {
            return;
        }

        const title = `KanbanDoo: ${task.title}`;
        const timeStr = task.due_time ? task.due_time.substring(0, 5) : '';
        const bodyText = `Agendada para às ${timeStr}${task.client_company ? ' • ' + task.client_company : ''}`;

        try {
            if (swRegistration && swRegistration.showNotification) {
                await swRegistration.showNotification(title, {
                    body: bodyText,
                    icon: (KD.base ? KD.base : '') + '/assets/icons/icon-192.png',
                    badge: (KD.base ? KD.base : '') + '/assets/icons/icon-192.png',
                    tag: `kd_reminder_${task.id}`,
                    data: {
                        taskId: task.id,
                        taskTitle: task.title,
                        url: KD.url('/tarefas')
                    },
                    requireInteraction: true,
                    actions: [
                        { action: 'snooze', title: '⏱️ Lembrar em 5 min' },
                        { action: 'close', title: '✕ Fechar' }
                    ]
                });
            } else {
                new Notification(title, {
                    body: bodyText,
                    icon: (KD.base ? KD.base : '') + '/assets/icons/icon-192.png'
                });
            }
        } catch (e) {
            // Fallback caso options de actions falhem em certos navegadores
            try {
                new Notification(title, { body: bodyText });
            } catch (err) {}
        }
    }

    /**
     * Varre tarefas agendadas e dispara alarmes na hora configurada
     */
    async function checkReminders() {
        const now = Date.now();

        // 1. Atualiza lista de lembretes a cada 2 minutos ou usa cache
        try {
            const res = await KD.api.get(KD.url('/api/tarefas?action=upcoming_reminders'));
            if (res && res.reminders) {
                monitoredReminders = res.reminders;
            }
        } catch (e) {
            // Usa cache em caso de falha temporária de rede
        }

        if (!Array.isArray(monitoredReminders) || monitoredReminders.length === 0) return;

        for (const task of monitoredReminders) {
            if (!task.due_date || !task.due_time) continue;

            const remMinutes = (task.reminder_minutes !== undefined && task.reminder_minutes !== null) 
                ? parseInt(task.reminder_minutes, 10) 
                : 15;

            // Se configurado como "Sem lembrete" (-1)
            if (remMinutes < 0) continue;

            // Monta timestamp do compromisso
            const timeParts = task.due_time.split(':');
            const dateParts = task.due_date.split('-');
            if (dateParts.length !== 3 || timeParts.length < 2) continue;

            const taskDate = new Date(
                parseInt(dateParts[0], 10),
                parseInt(dateParts[1], 10) - 1,
                parseInt(dateParts[2], 10),
                parseInt(timeParts[0], 10),
                parseInt(timeParts[1], 10),
                0
            );

            const taskTimestamp = taskDate.getTime();
            if (isNaN(taskTimestamp)) continue;

            // Horário de disparo = compromisso menos antecedência configurada
            const triggerTime = taskTimestamp - (remMinutes * 60 * 1000);

            // Verifica se está no intervalo de disparo (entre o trigger e até 45 min depois do horário)
            if (now >= triggerTime && now <= taskTimestamp + (45 * 60 * 1000)) {
                // Checa snooze ativo
                const snoozeUntil = parseInt(localStorage.getItem(`kd_snooze_${task.id}`) || '0', 10);
                if (snoozeUntil > now) {
                    continue; // Adiado
                }

                // Checa se já alertou para este dia/horário
                const alertKey = `kd_alerted_${task.id}_${task.due_date}_${task.due_time}`;
                const alreadyAlerted = localStorage.getItem(alertKey);

                if (!alreadyAlerted || (snoozeUntil > 0 && snoozeUntil <= now)) {
                    // DISPARA ALARME!
                    localStorage.setItem(alertKey, String(now));
                    if (snoozeUntil > 0) {
                        localStorage.removeItem(`kd_snooze_${task.id}`);
                    }

                    KD.playReminderChime();
                    triggerSystemNotification(task);
                    showInAppReminder(task);
                }
            }
        }
    }

    /**
     * Inicializa o monitoramento de lembretes
     */
    KD.initReminders = async function () {
        await registerServiceWorker();

        // Primeira checagem imediata
        setTimeout(checkReminders, 1500);

        // Checagem periódica a cada 25 segundos
        if (checkInterval) clearInterval(checkInterval);
        checkInterval = setInterval(checkReminders, 25000);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', KD.initReminders);
    } else {
        KD.initReminders();
    }

})(window.KD);
