<?php
/**
 * KanbanDoo - Portal do Cliente por Empresa
 *
 * Acesso exclusivo e seguro via token do cliente.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

$token = trim((string)($routeParams['token'] ?? $_GET['token'] ?? ''));

$pdo = get_pdo();
$client = null;

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE portal_token = :t LIMIT 1');
    $stmt->execute([':t' => $token]);
    $client = $stmt->fetch();
}

if (!$client) {
    http_response_code(404);
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Cliente &bull; KanbanDoo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 text-center shadow-2xl">
        <div class="w-14 h-14 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mx-auto mb-4">
            <i data-lucide="shield-alert" class="w-7 h-7"></i>
        </div>
        <h1 class="text-xl font-bold text-white mb-2">Acesso não encontrado</h1>
        <p class="text-xs text-slate-400 mb-6">O link que você acessou é inválido, expirou ou o portal da sua empresa não está ativo no momento. Entre em contato com a nossa equipe.</p>
        <a href="<?= url('/entrar') ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs text-slate-200 font-semibold transition">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Ir para o KanbanDoo</span>
        </a>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
<?php
    exit;
}

// Carregar tarefas deste cliente
$tasksStmt = $pdo->prepare("
    SELECT t.id, t.title, t.description, t.priority, t.due_date, t.due_time, t.completed_at, t.created_at,
           s.slug AS stage_slug, s.name AS stage_name,
           (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id) AS checklist_total,
           (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id AND is_completed = 1) AS checklist_done
    FROM tasks t
    LEFT JOIN task_stages s ON t.stage_id = s.id
    WHERE t.client_id = :cid
      AND t.deleted_at IS NULL
      AND (t.is_calendar_event = 0 OR t.show_in_kanban = 1)
    ORDER BY t.sort_order ASC, t.created_at DESC
");
$tasksStmt->execute([':cid' => $client['id']]);
$allTasks = $tasksStmt->fetchAll();

$todoTasks = [];
$doingTasks = [];
$doneTasks = [];

foreach ($allTasks as $task) {
    if ($task['stage_slug'] === 'done' || !empty($task['completed_at'])) {
        $doneTasks[] = $task;
    } elseif (in_array($task['stage_slug'], ['in_progress', 'review'], true)) {
        $doingTasks[] = $task;
    } else {
        $todoTasks[] = $task;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($client['company_name']) ?> &bull; Acompanhamento de Demandas</title>

    <link rel="icon" type="image/svg+xml" href="<?= asset('assets/img/favicon.svg') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?= asset('assets/css/styles.css') ?>">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col">

    <!-- Header do Portal -->
    <header class="border-b border-slate-800 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-40">
        <div class="max-w-[1600px] mx-auto px-4 lg:px-8 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex items-center justify-center text-white shadow-lg shadow-indigo-500/20">
                    <i data-lucide="building-2" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold text-white"><?= e($client['company_name']) ?></h1>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-500/15 text-indigo-400 border border-indigo-500/20">PORTAL DO CLIENTE</span>
                    </div>
                    <p class="text-xs text-slate-400">Acompanhamento de entregas e solicitação de demandas</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <!-- Métricas Rápidas -->
                <div class="hidden md:flex items-center gap-2">
                    <div class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs text-slate-300">
                        <span class="font-bold text-white"><?= count($allTasks) ?></span> demanda(s)
                    </div>
                    <div class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs text-amber-400">
                        <span class="font-bold"><?= count($doingTasks) ?></span> em andamento
                    </div>
                    <div class="px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-800 text-xs text-emerald-400">
                        <span class="font-bold"><?= count($doneTasks) ?></span> concluída(s)
                    </div>
                </div>

                <!-- Botão Nova Solicitação -->
                <button type="button" onclick="openRequestModal()" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs transition shadow-lg shadow-indigo-600/30 flex items-center gap-2 cursor-pointer">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>Nova Solicitação</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Conteúdo Principal: Quadro de 3 Colunas Read-Only -->
    <main class="flex-1 max-w-[1600px] w-full mx-auto px-4 lg:px-8 py-8 space-y-6">

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Coluna 1: A Fazer -->
            <div class="bg-slate-900/60 border border-slate-800 rounded-3xl p-5 flex flex-col min-h-[500px]">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">A Fazer</h2>
                    </div>
                    <span class="px-2 py-0.5 rounded-lg bg-slate-800 text-xs font-bold text-slate-300"><?= count($todoTasks) ?></span>
                </div>

                <div class="space-y-3 flex-1 overflow-y-auto">
                    <?php if (empty($todoTasks)): ?>
                        <div class="border border-dashed border-slate-800 rounded-2xl p-6 text-center text-xs text-slate-500">
                            Nenhuma demanda na fila no momento.
                        </div>
                    <?php else: ?>
                        <?php foreach ($todoTasks as $t): ?>
                            <?php renderPortalCard($t, '#f43f5e'); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Coluna 2: Fazendo / Em Andamento -->
            <div class="bg-slate-900/60 border border-slate-800 rounded-3xl p-5 flex flex-col min-h-[500px]">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Fazendo / Em Andamento</h2>
                    </div>
                    <span class="px-2 py-0.5 rounded-lg bg-slate-800 text-xs font-bold text-amber-400"><?= count($doingTasks) ?></span>
                </div>

                <div class="space-y-3 flex-1 overflow-y-auto">
                    <?php if (empty($doingTasks)): ?>
                        <div class="border border-dashed border-slate-800 rounded-2xl p-6 text-center text-xs text-slate-500">
                            Nenhuma demanda em execução neste instante.
                        </div>
                    <?php else: ?>
                        <?php foreach ($doingTasks as $t): ?>
                            <?php renderPortalCard($t, '#f59e0b'); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Coluna 3: Feito / Concluído -->
            <div class="bg-slate-900/60 border border-slate-800 rounded-3xl p-5 flex flex-col min-h-[500px]">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800/80">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                        <h2 class="text-sm font-bold text-white uppercase tracking-wider">Feito / Concluído</h2>
                    </div>
                    <span class="px-2 py-0.5 rounded-lg bg-slate-800 text-xs font-bold text-emerald-400"><?= count($doneTasks) ?></span>
                </div>

                <div class="space-y-3 flex-1 overflow-y-auto">
                    <?php if (empty($doneTasks)): ?>
                        <div class="border border-dashed border-slate-800 rounded-2xl p-6 text-center text-xs text-slate-500">
                            Nenhuma demanda concluída registrada recentemente.
                        </div>
                    <?php else: ?>
                        <?php foreach ($doneTasks as $t): ?>
                            <?php renderPortalCard($t, '#10b981'); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </main>

    <!-- Modal Nova Solicitação -->
    <div id="requestModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden animate-fade-in">
        <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl p-6 sm:p-8 space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center">
                        <i data-lucide="send" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Nova Solicitação de Demanda</h3>
                        <p class="text-xs text-slate-400">Envie detalhes para nossa equipe dar início</p>
                    </div>
                </div>
                <button type="button" onclick="closeRequestModal()" class="p-1 rounded-lg text-slate-400 hover:text-white transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form id="formNewRequest" onsubmit="submitClientRequest(event)" class="space-y-4">
                <div id="requestError" class="p-3 bg-rose-500/10 border border-rose-500/20 rounded-xl text-rose-400 text-xs hidden flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
                    <span id="requestErrorMsg"></span>
                </div>

                <div>
                    <label for="reqTitle" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Título da Demanda / O que precisa ser feito *
                    </label>
                    <input type="text" id="reqTitle" name="title" required placeholder="Ex: Ajuste no banner da campanha ou novo relatório" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                </div>

                <div>
                    <label for="reqDesc" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Descrição e Detalhes da Solicitação *
                    </label>
                    <textarea id="reqDesc" name="description" rows="4" required placeholder="Descreva os objetivos, referências, links e o que espera como resultado..." class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition resize-none"></textarea>
                </div>

                <div>
                    <label for="reqDue" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Prazo Desejado / Previsão Ideal (Opcional)
                    </label>
                    <input type="date" id="reqDue" name="due_date" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                </div>

                <div class="flex items-center justify-end gap-3 pt-3">
                    <button type="button" onclick="closeRequestModal()" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="submit" id="btnSubmitRequest" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition shadow-lg shadow-indigo-600/30 flex items-center gap-2 cursor-pointer">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        <span>Enviar Solicitação</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast de Confirmação -->
    <div id="portalToast" class="fixed bottom-6 right-6 z-50 p-4 bg-emerald-600 text-white rounded-2xl shadow-2xl flex items-center gap-3 hidden animate-slide-up text-xs font-semibold">
        <i data-lucide="check-circle" class="w-5 h-5"></i>
        <span id="portalToastMsg">Solicitação enviada com sucesso!</span>
    </div>

    <script>
        const CLIENT_PORTAL_TOKEN = <?= json_encode($token) ?>;

        function openRequestModal() {
            document.getElementById('requestModal').classList.remove('hidden');
            document.getElementById('reqTitle').focus();
        }

        function closeRequestModal() {
            document.getElementById('requestModal').classList.add('hidden');
            document.getElementById('formNewRequest').reset();
            document.getElementById('requestError').classList.add('hidden');
        }

        async function submitClientRequest(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmitRequest');
            const errBox = document.getElementById('requestError');
            const errMsg = document.getElementById('requestErrorMsg');

            errBox.classList.add('hidden');
            btn.disabled = true;
            btn.textContent = 'Enviando...';

            const payload = {
                action: 'new_request',
                token: CLIENT_PORTAL_TOKEN,
                title: document.getElementById('reqTitle').value.trim(),
                description: document.getElementById('reqDesc').value.trim(),
                due_date: document.getElementById('reqDue').value || null,
            };

            try {
                const res = await fetch('<?= url('/api/portal') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (!res.ok || data.error) {
                    throw new Error(data.error || 'Erro ao enviar solicitação.');
                }

                closeRequestModal();
                showToast(data.message || 'Sua solicitação foi enviada com sucesso!');
                setTimeout(() => location.reload(), 1500);
            } catch (err) {
                errMsg.textContent = err.message;
                errBox.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i><span>Enviar Solicitação</span>';
                lucide.createIcons();
            }
        }

        function showToast(msg) {
            const toast = document.getElementById('portalToast');
            document.getElementById('portalToastMsg').textContent = msg;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), 4000);
        }

        lucide.createIcons();
    </script>
</body>
</html>
<?php

function renderPortalCard(array $t, string $accentColor): void
{
    $dueFormatted = '';
    if (!empty($t['due_date'])) {
        $ts = strtotime($t['due_date']);
        $dueFormatted = date('d/m/Y', $ts);
    }
    $checklistTotal = (int)($t['checklist_total'] ?? 0);
    $checklistDone = (int)($t['checklist_done'] ?? 0);
?>
    <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-4 shadow-sm hover:border-slate-700 transition space-y-2.5">
        <div class="w-8 h-1 rounded-full" style="background-color: <?= $accentColor ?>;"></div>

        <h3 class="text-xs font-bold text-slate-100 leading-snug">
            <?= e($t['title']) ?>
        </h3>

        <?php if (!empty($t['description'])): ?>
            <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed">
                <?= e($t['description']) ?>
            </p>
        <?php endif; ?>

        <div class="flex items-center justify-between text-[11px] pt-2 border-t border-slate-800/60 text-slate-400">
            <div>
                <?php if ($dueFormatted): ?>
                    <span class="inline-flex items-center gap-1 font-semibold text-slate-300">
                        <i data-lucide="calendar" class="w-3 h-3 text-indigo-400"></i>
                        <span><?= $dueFormatted ?></span>
                    </span>
                <?php else: ?>
                    <span class="text-slate-500">Sem prazo definido</span>
                <?php endif; ?>
            </div>

            <?php if ($checklistTotal > 0): ?>
                <div class="inline-flex items-center gap-1 font-medium <?= $checklistDone === $checklistTotal ? 'text-emerald-400' : 'text-slate-400' ?>">
                    <i data-lucide="check-square" class="w-3 h-3"></i>
                    <span><?= $checklistDone ?>/<?= $checklistTotal ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php
}
