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
$users = $pdo->query('SELECT id, username, full_name, email, role, created_at FROM users ORDER BY full_name ASC')->fetchAll();

view_header('Equipe & Usuários');
?>

<main class="flex-1 w-full px-4 lg:px-8 py-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="users" class="w-6 h-6 text-indigo-400"></i>
                <span>Equipe & Usuários</span>
            </h1>
            <p class="text-xs text-slate-400 mt-0.5">Gerencie os membros com acesso ao KanbanDoo</p>
        </div>

        <button onclick="openUserModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition shadow-lg shadow-indigo-600/25 flex items-center gap-2 self-start sm:self-auto">
            <i data-lucide="user-plus" class="w-4 h-4"></i>
            <span>Novo Membro</span>
        </button>
    </div>

    <!-- Tabela de Membros -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-950/60 border-b border-slate-800 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="px-6 py-4">Membro</th>
                        <th class="px-6 py-4">Usuário</th>
                        <th class="px-6 py-4">E-mail</th>
                        <th class="px-6 py-4">Função</th>
                        <th class="px-6 py-4 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-200">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="px-6 py-4 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-indigo-600/20 text-indigo-400 font-bold text-xs flex items-center justify-center">
                                    <?= mb_strtoupper(mb_substr($u['full_name'], 0, 1, 'UTF-8')) ?>
                                </div>
                                <span class="font-bold text-white"><?= e($u['full_name']) ?></span>
                            </td>
                            <td class="px-6 py-4 font-mono text-slate-400"><?= e($u['username']) ?></td>
                            <td class="px-6 py-4"><?= e($u['email']) ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $u['role'] === 'admin' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-slate-800 text-slate-400' ?>">
                                    <?= $u['role'] === 'admin' ? 'Administrador' : 'Membro' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button onclick="editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)" class="p-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg transition" title="Editar">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                                        <form method="POST" onsubmit="return confirm('Deseja realmente excluir este usuário?');" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="p-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg transition" title="Excluir">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal Novo / Editar Usuário -->
<div id="userModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-md shadow-2xl p-6 relative animate-fade-in my-8">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-5">
            <h3 id="userModalTitle" class="text-lg font-bold text-white">Adicionar Membro</h3>
            <button type="button" onclick="closeUserModal()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition">
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

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="closeUserModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl transition">
                    Cancelar
                </button>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl transition shadow-lg shadow-indigo-600/25 flex items-center gap-2">
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
    document.getElementById('userModal').classList.remove('hidden');
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
    document.getElementById('userModal').classList.remove('hidden');
}

function closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
}
</script>

<?php view_footer(); ?>
