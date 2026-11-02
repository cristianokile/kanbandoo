/**
 * KanbanDoo - Service Worker de Notificações Push & Ações
 */
self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const data = event.notification.data || {};
    const taskId = data.taskId;

    if (event.action === 'snooze') {
        // Enviar sinal de adiar 5 minutos para as abas ativas
        event.waitUntil(
            self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
                for (let client of clients) {
                    client.postMessage({
                        action: 'snooze_reminder',
                        taskId: taskId,
                        minutes: 5,
                        taskTitle: data.taskTitle || ''
                    });
                }
            })
        );
    } else if (event.action === 'close') {
        // Apenas fechar
    } else {
        // Clicou no corpo da notificação: focar ou abrir o quadro
        event.waitUntil(
            self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clients) {
                for (let client of clients) {
                    if (client.url && client.url.includes('/tarefas') && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (self.clients.openWindow) {
                    return self.clients.openWindow(data.url || '/tarefas');
                }
            })
        );
    }
});

