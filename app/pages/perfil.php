<?php
/**
 * KanbanDoo - Meu Perfil
 */
require_once dirname(__DIR__) . '/bootstrap.php';

require_login();

$pdo = get_pdo();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'enable_2fa') {
        $secret = trim($_POST['two_factor_secret'] ?? '');
        $code = trim($_POST['two_factor_code'] ?? '');

        if ($secret === '' || strlen($code) !== 6 || !ctype_digit($code)) {
            flash('danger', 'Código de autenticação inválido. Digite os 6 dígitos gerados no aplicativo.');
            redirect(url('/perfil'));
        }

        require_once dirname(__DIR__, 2) . '/includes/totp.php';
        if (TOTP::verifyCode($secret, $code)) {
            $upd = $pdo->prepare('UPDATE users SET two_factor_enabled = 1, two_factor_secret = :sec WHERE id = :id');
            $upd->execute([':sec' => $secret, ':id' => $user['id']]);
            flash('success', 'Verificação em 2 Passos ativada com sucesso! Sua conta agora está protegida.');
        } else {
            flash('danger', 'Código incorreto. Certifique-se de escanear o QR Code no seu aplicativo e digitar o código atual.');
        }
        redirect(url('/perfil'));
    }

    if ($action === 'disable_2fa') {
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $user['id']]);
        $currentHash = $stmt->fetchColumn();

        if ($currentHash && password_verify($password, $currentHash)) {
            $upd = $pdo->prepare('UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = :id');
            $upd->execute([':id' => $user['id']]);
            flash('success', 'Verificação em 2 Passos desativada com sucesso.');
        } else {
            flash('danger', 'Senha incorreta. A verificação em 2 passos não foi desativada.');
        }
        redirect(url('/perfil'));
    }

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


$pendingSecret = TOTP::generateSecret();
$otpAuthUrl = TOTP::getOtpAuthUrl($user['email'] ?: $user['username'], $pendingSecret, 'KanbanDoo');
$qrCodeUrl = TOTP::getQrCodeUrl($otpAuthUrl);

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
    <!-- Segurança & Verificação em 2 Passos (2FA) -->
    <div class="mt-6 bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-800">
            <div class="flex items-start gap-3">
                <div class="w-12 h-12 rounded-2xl <?= !empty($user['two_factor_enabled']) ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-slate-800 text-slate-400 border border-slate-700' ?> flex items-center justify-center flex-shrink-0">
                    <i data-lucide="shield-check" class="w-6 h-6"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-white">Verificação em 2 Passos (2FA)</h3>
                        <?php if (!empty($user['two_factor_enabled'])): ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">ATIVADA</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">DESATIVADA</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-slate-400 mt-1 max-w-xl">
                        Proteja sua conta e as informações de clientes. Ao ativar o 2FA, cada login solicitará o código de 6 dígitos gerado pelo seu app autenticador (Google Authenticator, Authy, etc.).
                    </p>
                </div>
            </div>

            <?php if (!empty($user['two_factor_enabled'])): ?>
                <div>
                    <button type="button" onclick="document.getElementById('disable2faModal').classList.remove('hidden')" class="px-4 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="shield-alert" class="w-4 h-4"></i>
                        <span>Desativar 2FA</span>
                    </button>
                </div>
            <?php else: ?>
                <div>
                    <button type="button" onclick="document.getElementById('setup2faModal').classList.remove('hidden')" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition shadow-lg shadow-indigo-600/30 flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="qr-code" class="w-4 h-4"></i>
                        <span>Configurar 2FA</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($user['two_factor_enabled'])): ?>
            <div class="pt-4 text-xs text-slate-400 flex items-center gap-2">
                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-400 flex-shrink-0"></i>
                <span>Sua conta está protegida por aplicativo autenticador.</span>
            </div>
        <?php else: ?>
            <div class="pt-4 text-xs text-slate-400 flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-indigo-400 flex-shrink-0"></i>
                <span>Recomendamos ativar para aumentar a segurança das operações do KanbanDoo.</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Configuração do 2FA -->
    <div id="setup2faModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden animate-fade-in">
        <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl p-6 sm:p-8 space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center">
                        <i data-lucide="qr-code" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Ativar Verificação em 2 Passos</h3>
                        <p class="text-xs text-slate-400">Google Authenticator / Authy / Microsoft Authenticator</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('setup2faModal').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-white transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div class="p-4 bg-slate-950/60 border border-slate-800 rounded-2xl flex flex-col sm:flex-row items-center gap-4">
                    <div class="bg-white p-2 rounded-xl flex-shrink-0 shadow-md">
                        <img src="<?= e($qrCodeUrl) ?>" alt="QR Code 2FA" class="w-36 h-36 object-contain">
                    </div>
                    <div class="text-xs text-slate-300 space-y-2">
                        <p class="font-semibold text-white">Passo 1: Escaneie o QR Code</p>
                        <p class="text-slate-400">Abra o aplicativo autenticador no seu celular e aponte a câmera para o código.</p>
                        <p class="text-[11px] text-slate-400 pt-1">Ou adicione manualmente a chave:</p>
                        <code class="block font-mono bg-slate-900 border border-slate-800 px-2 py-1 rounded text-indigo-300 select-all font-bold tracking-wider text-[11px]">
                            <?= chunk_split($pendingSecret, 4, ' ') ?>
                        </code>
                    </div>
                </div>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="enable_2fa">
                    <input type="hidden" name="two_factor_secret" value="<?= e($pendingSecret) ?>">

                    <div>
                        <label for="input2faCode" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Passo 2: Digite o código de 6 dígitos gerado
                        </label>
                        <input type="text" id="input2faCode" name="two_factor_code" required maxlength="6" pattern="[0-9]*" inputmode="numeric" placeholder="000000" class="w-full text-center text-2xl font-mono font-bold tracking-[0.3em] py-3 bg-slate-950 border border-slate-800 rounded-xl text-indigo-400 focus:outline-none focus:border-indigo-500 transition">
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" onclick="document.getElementById('setup2faModal').classList.add('hidden')" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium transition cursor-pointer">
                            Cancelar
                        </button>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition shadow-lg shadow-indigo-600/30 flex items-center gap-1.5 cursor-pointer">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            <span>Confirmar e Ativar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Desativação do 2FA -->
    <div id="disable2faModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden animate-fade-in">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl p-6 sm:p-8 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center">
                        <i data-lucide="shield-alert" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Desativar 2FA</h3>
                        <p class="text-xs text-slate-400">Confirme sua senha para desativar</p>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('disable2faModal').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-white transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="disable_2fa">

                <p class="text-xs text-slate-300">
                    Atenção: Ao desativar a verificação em 2 passos, sua conta ficará protegida apenas pela senha tradicional.
                </p>

                <div>
                    <label for="confirmPass2fa" class="block text-xs font-semibold text-slate-400 mb-1.5">Sua senha atual</label>
                    <input type="password" id="confirmPass2fa" name="password" required placeholder="••••••••" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-rose-500 transition">
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('disable2faModal').classList.add('hidden')" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium transition cursor-pointer">
                        Cancelar
                    </button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold transition shadow-lg shadow-rose-600/30 flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                        <span>Desativar</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</main>

<?php view_footer(); ?>
