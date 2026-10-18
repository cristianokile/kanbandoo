<?php
/**
 * KanbanDoo - Gestão de Usuários da Equipe
 */
require_once dirname(__DIR__) . '/bootstrap.php';

require_admin();

$pdo = get_pdo();
$currentUser = current_user();

// Processar formulário de criação/edição/exclusão de usuários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(url('/equipe'));
    }

    $action = $_POST['action'] ?? '';

    // Salvar / Criar Usuário
    if ($action === 'save_user') {
        $userId = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $username = trim((string)($_POST['username'] ?? ''));
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = in_array($_POST['role'] ?? '', ['admin', 'member']) ? $_POST['role'] : 'member';
        $password = $_POST['password'] ?? '';

        if ($username === '' || $fullName === '' || $email === '') {
            flash('danger', 'Preencha todos os campos obrigatórios.');
            redirect(url('/equipe'));
        }

        try {
            if ($userId > 0) {
                // Atualizar
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET username = :u, full_name = :f, email = :e, role = :r, password_hash = :p WHERE id = :id');
                    $stmt->execute([':u' => $username, ':f' => $fullName, ':e' => $email, ':r' => $role, ':p' => $hash, ':id' => $userId]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET username = :u, full_name = :f, email = :e, role = :r WHERE id = :id');
                    $stmt->execute([':u' => $username, ':f' => $fullName, ':e' => $email, ':r' => $role, ':id' => $userId]);
                }
                flash('success', "Membro \"{$fullName}\" atualizado com sucesso!");
            } else {
                // Criar
                if ($password === '') {
                    flash('danger', 'A senha é obrigatória para novos membros.');
                    redirect(url('/equipe'));
                }
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, full_name, email, role, password_hash) VALUES (:u, :f, :e, :r, :p)');
                $stmt->execute([':u' => $username, ':f' => $fullName, ':e' => $email, ':r' => $role, ':p' => $hash]);
                flash('success', "Novo membro \"{$fullName}\" adicionado com sucesso!");
            }
        } catch (PDOException $e) {
            flash('danger', 'Erro ao salvar usuário: ' . $e->getMessage());
        }

        redirect(url('/equipe'));
    }

    // Excluir Usuário
    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === (int)$currentUser['id']) {
            flash('danger', 'Você não pode excluir sua própria conta.');
            redirect(url('/equipe'));
        }
        if ($userId > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute([':id' => $userId]);
                flash('success', 'Membro excluído da equipe.');
            } catch (PDOException $e) {
                flash('danger', 'Erro ao excluir membro: ' . $e->getMessage());
            }
        }
        redirect(url('/equipe'));
    }
}

// Listagem de Usuários
$users = $pdo->query("
    SELECT u.id, u.username, u.full_name, u.email, u.role, u.created_at,
           (SELECT COUNT(*) FROM tasks t 
            JOIN task_assignees ta ON ta.task_id = t.id 
            WHERE ta.user_id = u.id AND t.deleted_at IS NULL AND t.completed_at IS NULL) as active_tasks_count
    FROM users u 
    ORDER BY u.full_name ASC
")->fetchAll();

view_header('Equipe & Usuários');
?>

<main class="w-full px-4 lg:px-8 py-6 space-y-6 max-w-[1600px] mx-auto">
    <!-- Topo -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 shadow-inner">
                    <i data-lucide="users" class="w-5 h-5"></i>
                </span>
                <h1 class="text-2xl font-bold text-white tracking-tight">Equipe & Colaboradores</h1>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                    <?= count($users) ?> membros
                </span>
            </div>
            <p class="text-xs text-slate-400 mt-1">Gerencie os acessos, permissões e colaboradores com acesso ao KanbanDoo</p>
        </div>

        <button onclick="openUserModal()" class="kd-btn kd-btn--primary text-xs flex items-center gap-2 self-start sm:self-auto py-2.5 shadow-lg shadow-indigo-600/25">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span>Adicionar Membro</span>
        </button>
    </div>

    <!-- Barra de Busca Rápida de Membros -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-3 flex items-center justify-between gap-3 kd-glass">
        <div class="relative flex-1 max-w-md">
            <i data-lucide="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="search" id="searchTeam" oninput="filterTeamMembers()"
                   placeholder="Buscar membros por nome, login ou e-mail..."
                   class="kd-field pl-10" style="min-height:38px;padding-top:0.35rem;padding-bottom:0.35rem">
        </div>
        <div class="text-xs text-slate-400 font-medium px-2 hidden sm:block">
            Organização &bull; Equipe Ativa
        </div>
    </div>

    <!-- Grid de Cards da Equipe (4 colunas) -->
    <div id="teamGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-4 gap-4">
        <?php foreach ($users as $u): ?>
            <div class="kd-card kd-glass rounded-2xl p-4 flex flex-col justify-between transition-all group hover:border-slate-700 relative shadow-sm team-member-card"
                 data-name="<?= strtolower(e($u['full_name'])) ?>"
                 data-username="<?= strtolower(e($u['username'])) ?>"
                 data-email="<?= strtolower(e($u['email'])) ?>">
                <div>
                    <!-- Header do Card -->
                    <div class="flex items-start justify-between gap-2.5 mb-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-10 h-10 rounded-xl <?= $u['role'] === 'admin' ? 'bg-indigo-600/20 border border-indigo-500/30 text-indigo-400' : 'bg-slate-800 border border-slate-700/60 text-slate-300' ?> font-bold text-sm flex items-center justify-center flex-shrink-0 shadow-inner">
                                <?= mb_strtoupper(mb_substr($u['full_name'], 0, 1, 'UTF-8')) ?>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-sm text-white leading-tight group-hover:text-indigo-300 transition truncate" title="<?= e($u['full_name']) ?>">
                                    <?= e($u['full_name']) ?>
                                </h3>
                                <p class="text-[11px] font-mono text-slate-400 truncate mt-0.5">@<?= e($u['username']) ?></p>
                            </div>
                        </div>

                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider flex-shrink-0 <?= $u['role'] === 'admin' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-slate-800 text-slate-400' ?>">
                            <?= $u['role'] === 'admin' ? 'Admin' : 'Membro' ?>
                        </span>
                    </div>

                    <!-- Contatos e Detalhes -->
                    <div class="space-y-1.5 py-2.5 border-y border-slate-800/60 text-xs text-slate-300">
                        <div class="flex items-center gap-2 truncate text-[11px]">
                            <i data-lucide="mail" class="w-3.5 h-3.5 text-slate-500 flex-shrink-0"></i>
                            <a href="mailto:<?= e($u['email']) ?>" class="hover:text-indigo-400 truncate text-slate-300" title="<?= e($u['email']) ?>">
                                <?= e($u['email']) ?>
                            </a>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] text-slate-400">
                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-500 flex-shrink-0"></i>
                            <span>Desde <?= date('d/m/Y', strtotime($u['created_at'] ?? 'now')) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Footer do Card: Tarefas e Ações -->
                <div class="flex items-center justify-between pt-3 mt-2 text-xs border-t border-slate-800/40">
                    <a href="<?= url('/tarefas') ?>" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white text-[11px] font-medium transition" title="Ver tarefas no quadro">
                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5 text-emerald-400"></i>
                        <span><?= (int)($u['active_tasks_count'] ?? 0) ?> ativas</span>
                    </a>

                    <div class="flex items-center gap-1">
                        <button onclick="editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)" class="p-1.5 bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg transition" title="Editar">
                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                        </button>
                        <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                            <form method="POST" onsubmit="return confirm('Deseja realmente excluir este membro?');" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button type="submit" class="p-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg transition" title="Excluir">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<script>
function filterTeamMembers() {
    const q = (document.getElementById('searchTeam')?.value || '').toLowerCase().trim();
    document.querySelectorAll('.team-member-card').forEach(card => {
        const name = card.dataset.name || '';
        const user = card.dataset.username || '';
        const email = card.dataset.email || '';
        const match = !q || name.includes(q) || user.includes(q) || email.includes(q);
        card.style.display = match ? '' : 'none';
    });
}
</script>

<!-- Modal Novo / Editar Usuário -->
<div id="userModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="userModalTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--md kd-glass kd-glass--raised">
        <div class="flex items-center justify-between pb-4 mb-5" style="border-bottom: 1px solid var(--border)">
            <h3 id="userModalTitle" class="text-lg font-bold text-white">Adicionar Membro</h3>
            <button type="button" onclick="closeUserModal()" class="kd-icon-btn" aria-label="Fechar">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" id="modal_user_id" name="user_id" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Nome Completo *</label>
                <input type="text" id="modal_user_name" name="full_name" required placeholder="Ex: Ana Silva" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Usuário (Login) *</label>
                <input type="text" id="modal_user_username" name="username" required placeholder="ana.silva" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">E-mail *</label>
                <input type="email" id="modal_user_email" name="email" required placeholder="ana@equipe.com" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Função / Permissão</label>
                <select id="modal_user_role" name="role" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                    <option value="member">Membro da Equipe</option>
                    <option value="admin">Administrador Geral</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2" id="passwordLabel">Senha *</label>
                <input type="password" id="modal_user_password" name="password" placeholder="••••••••" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                <span id="passwordHint" class="hidden text-[10px] text-slate-500 mt-1 block">Deixe em branco para manter a senha atual.</span>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4" style="border-top: 1px solid var(--border)">
                <button type="button" onclick="closeUserModal()" class="kd-btn">
                    Cancelar
                </button>
                <button type="submit" class="kd-btn kd-btn--primary">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Salvar Membro
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openUserModal() {
    document.getElementById('modal_user_id').value = '';
    document.getElementById('modal_user_name').value = '';
    document.getElementById('modal_user_username').value = '';
    document.getElementById('modal_user_email').value = '';
    document.getElementById('modal_user_role').value = 'member';
    document.getElementById('modal_user_password').value = '';
    document.getElementById('modal_user_password').required = true;
    document.getElementById('passwordHint').classList.add('hidden');
    document.getElementById('userModalTitle').textContent = 'Adicionar Novo Membro';
    KD.openModal('userModal');
}

function editUser(user) {
    document.getElementById('modal_user_id').value = user.id;
    document.getElementById('modal_user_name').value = user.full_name;
    document.getElementById('modal_user_username').value = user.username;
    document.getElementById('modal_user_email').value = user.email;
    document.getElementById('modal_user_role').value = user.role;
    document.getElementById('modal_user_password').value = '';
    document.getElementById('modal_user_password').required = false;
    document.getElementById('passwordHint').classList.remove('hidden');
    document.getElementById('userModalTitle').textContent = 'Editar Membro';
    KD.openModal('userModal');
}

function closeUserModal() {
    KD.closeModal('userModal');
}
</script>

<?php view_footer(); ?>
