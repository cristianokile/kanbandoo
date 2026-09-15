<?php
/**
 * KanbanDoo - Meu Perfil
 */
require_once dirname(__DIR__) . '/bootstrap.php';

require_login();

$pdo = get_pdo();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(url('/perfil'));
    }

    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    if ($fullName === '' || $email === '') {
        flash('danger', 'Nome e e-mail são obrigatórios.');
        redirect(url('/perfil'));
    }

    $workStartTime = trim((string)($_POST['work_start_time'] ?? '09:00'));
    $workEndTime   = trim((string)($_POST['work_end_time'] ?? '17:00'));
    $workDaysList  = isset($_POST['work_days']) && is_array($_POST['work_days']) ? implode(',', $_POST['work_days']) : '1,2,3,4,5';

    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $workStartTime)) $workStartTime = '09:00';
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $workEndTime)) $workEndTime = '17:00';

    try {
        if ($newPassword !== '') {
            // Verificar senha atual
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
            $stmt->execute([':id' => $user['id']]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($currentPassword, $hash)) {
                flash('danger', 'A senha atual está incorreta.');
                redirect(url('/perfil'));
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET full_name = :f, email = :e, password_hash = :p, work_start_time = :ws, work_end_time = :we, work_days = :wd WHERE id = :id');
            $stmt->execute([
                ':f' => $fullName,
                ':e' => $email,
                ':p' => $newHash,
                ':ws' => $workStartTime,
                ':we' => $workEndTime,
                ':wd' => $workDaysList,
                ':id' => $user['id'],
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET full_name = :f, email = :e, work_start_time = :ws, work_end_time = :we, work_days = :wd WHERE id = :id');
            $stmt->execute([
                ':f' => $fullName,
                ':e' => $email,
                ':ws' => $workStartTime,
                ':we' => $workEndTime,
                ':wd' => $workDaysList,
                ':id' => $user['id'],
            ]);
        }

        flash('success', 'Perfil atualizado com sucesso!');
    } catch (PDOException $e) {
        flash('danger', 'Erro ao atualizar perfil: ' . $e->getMessage());
    }

    redirect(url('/perfil'));
}

// Recarregar dados do usuário
$userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$userStmt->execute([':id' => $user['id']]);
$user = $userStmt->fetch() ?: $user;

$currentWorkStart = $user['work_start_time'] ?? '09:00';
$currentWorkEnd   = $user['work_end_time'] ?? '17:00';
$currentWorkDays  = !empty($user['work_days']) ? explode(',', $user['work_days']) : ['1', '2', '3', '4', '5'];

view_header('Meu Perfil');
?>

<main class="flex-1 w-full px-4 lg:px-8 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
            <i data-lucide="user-cog" class="w-6 h-6 text-indigo-400"></i>
            <span>Meu Perfil</span>
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">Atualize seus dados pessoais, expediente e preferências</p>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <form method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="flex items-center gap-4 pb-6 border-b border-slate-800">
                <div class="w-16 h-16 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-300 font-bold text-xl flex items-center justify-center">
                    <?= mb_strtoupper(mb_substr($user['full_name'], 0, 2, 'UTF-8')) ?>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white"><?= e($user['full_name']) ?></h3>
                    <p class="text-xs text-slate-400">@<?= e($user['username']) ?> &bull; <span class="capitalize"><?= $user['role'] ?></span></p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Nome Completo</label>
                    <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" required class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">E-mail</label>
                    <input type="email" name="email" value="<?= e($user['email']) ?>" required class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                </div>
            </div>

            <!-- Horário de Atuação / Expediente -->
            <div class="pt-4 border-t border-slate-800">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h4 class="text-xs font-semibold text-slate-300 uppercase tracking-wider flex items-center gap-2">
                            <i data-lucide="clock" class="w-4 h-4 text-indigo-400"></i>
                            Horário de Atuação (Expediente Diário)
                        </h4>
                        <p class="text-xs text-slate-400 mt-0.5">Define o limite diário de horas utilizado para os contadores das colunas no quadro.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Início do Expediente</label>
                        <input type="time" name="work_start_time" value="<?= e($currentWorkStart) ?>" required class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Fim do Expediente</label>
                        <input type="time" name="work_end_time" value="<?= e($currentWorkEnd) ?>" required class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-2">Dias de Trabalho Habitual</label>
                    <div class="flex flex-wrap gap-2">
                        <?php
                        $weekdaysMap = [
                            '1' => 'Segunda-feira',
                            '2' => 'Terça-feira',
                            '3' => 'Quarta-feira',
                            '4' => 'Quinta-feira',
                            '5' => 'Sexta-feira',
                            '6' => 'Sábado',
                            '0' => 'Domingo',
                        ];
                        foreach ($weekdaysMap as $dKey => $dLabel):
                            $isChecked = in_array((string)$dKey, $currentWorkDays, true);
                        ?>
                            <label class="flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-800 bg-slate-950/60 text-xs text-slate-300 cursor-pointer hover:border-slate-700 transition">
                                <input type="checkbox" name="work_days[]" value="<?= $dKey ?>" <?= $isChecked ? 'checked' : '' ?> class="rounded">
                                <span><?= $dLabel ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-800">
                <h4 class="text-xs font-semibold text-slate-300 uppercase tracking-wider mb-4">Alterar Senha</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Senha Atual</label>
                        <input type="password" name="current_password" placeholder="••••••••" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1.5">Nova Senha</label>
                        <input type="password" name="new_password" placeholder="••••••••" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end pt-4">
                <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl transition shadow-lg shadow-indigo-600/25 flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</main>

<?php view_footer(); ?>
