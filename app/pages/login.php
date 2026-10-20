<?php
/**
 * KanbanDoo - Tela de Autenticação com 2FA e Manter Conectado
 */
require_once dirname(__DIR__) . '/bootstrap.php';

if (is_logged_in()) {
    redirect(url('/tarefas'));
}

$error = '';
$step = !empty($_SESSION['2fa_pending_user_id']) ? '2fa' : 'credentials';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'verify_2fa') {
        $pendingUserId = (int)($_SESSION['2fa_pending_user_id'] ?? 0);
        $code = trim($_POST['two_factor_code'] ?? '');
        $remember = !empty($_SESSION['2fa_pending_remember']);

        if ($code === '' || strlen($code) !== 6 || !ctype_digit($code)) {
            $error = 'Por favor, digite o código numérico de 6 dígitos.';
            $step = '2fa';
        } else {
            if (verify_2fa_login($pendingUserId, $code, $remember)) {
                unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_remember']);
                redirect(url('/tarefas'));
            } else {
                $error = 'Código de verificação incorreto ou expirado. Tente novamente.';
                $step = '2fa';
            }
        }
    } elseif ($action === 'cancel_2fa') {
        unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_pending_remember']);
        $step = 'credentials';
    } else {
        $usernameOrEmail = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        if ($usernameOrEmail === '' || $password === '') {
            $error = 'Por favor, preencha o usuário/e-mail e a senha.';
        } else {
            $loginRes = attempt_login($usernameOrEmail, $password, $remember);
            if ($loginRes['status'] === 'success') {
                redirect(url('/tarefas'));
            } elseif ($loginRes['status'] === '2fa_required') {
                $_SESSION['2fa_pending_user_id'] = $loginRes['user_id'];
                $_SESSION['2fa_pending_remember'] = $remember ? 1 : 0;
                $step = '2fa';
            } else {
                $error = 'Usuário ou senha incorretos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login &bull; <?= APP_NAME ?></title>

    <!-- Favicon do KanbanDoo -->
    <link rel="icon" type="image/svg+xml" href="<?= asset('assets/img/favicon.svg') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('assets/img/favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= asset('assets/img/favicon-16x16.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= asset('assets/img/apple-touch-icon.png') ?>">
    <link rel="shortcut icon" href="<?= asset('assets/img/favicon.ico') ?>">

    <!-- Script de Inicialização de Tema (Evita FOUC) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('kanbandoo_theme');
            if (savedTheme === 'light') {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
            } else {
                document.documentElement.classList.add('dark');
                document.documentElement.classList.remove('light');
            }
        })();
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?= asset('assets/css/styles.css') ?>">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    
    <!-- Efeito de Luz de Fundo -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-3xl shadow-2xl p-8 relative z-10">
        
        <?php if ($step === 'credentials'): ?>
            <!-- Header Login -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-2xl mb-4 shadow-xl shadow-indigo-500/30">
                    <i data-lucide="kanban" class="w-7 h-7 text-white"></i>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Acesse o KanbanDoo</h1>
                <p class="text-slate-400 text-xs mt-1">Gerencie suas tarefas e clientes com agilidade</p>
            </div>

            <?php if ($error): ?>
                <div role="alert" class="mb-6 p-4 bg-rose-500/10 border border-rose-500/20 rounded-2xl text-rose-400 text-xs flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Formulário de Login -->
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="login">

                <div>
                    <label for="loginUser" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Usuário ou e-mail</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <i data-lucide="user" class="w-4 h-4"></i>
                        </span>
                        <input type="text" id="loginUser" name="username" required autofocus autocomplete="username" placeholder="seu.usuario ou email" class="w-full pl-10 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-sm focus:outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <div>
                    <label for="loginPass" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Senha</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </span>
                        <input type="password" id="loginPass" name="password" required autocomplete="current-password" placeholder="••••••••" class="w-full pl-10 pr-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 text-sm focus:outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <!-- Manter conectado -->
                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center gap-2 text-slate-300 cursor-pointer select-none">
                        <input type="checkbox" name="remember" value="1" checked class="w-4 h-4 rounded bg-slate-950 border border-slate-800 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-900 transition">
                        <span>Manter conectado neste dispositivo</span>
                    </label>
                </div>

                <button type="submit" class="w-full mt-6 py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2 cursor-pointer">
                    <span>Entrar no Sistema</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>

        <?php else: ?>
            <!-- Header 2FA -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-gradient-to-br from-emerald-500 to-teal-700 rounded-2xl mb-4 shadow-xl shadow-emerald-500/30">
                    <i data-lucide="shield-check" class="w-7 h-7 text-white"></i>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Verificação em 2 Passos</h1>
                <p class="text-slate-400 text-xs mt-1">Abra seu aplicativo autenticador (Google Authenticator, Authy, etc.) e digite o código de 6 dígitos gerado.</p>
            </div>

            <?php if ($error): ?>
                <div role="alert" class="mb-6 p-4 bg-rose-500/10 border border-rose-500/20 rounded-2xl text-rose-400 text-xs flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Formulário 2FA -->
            <form method="POST" class="space-y-5">
                <input type="hidden" name="action" value="verify_2fa">

                <div>
                    <label for="twoFactorCode" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2 text-center">Código de Autenticação</label>
                    <input type="text" id="twoFactorCode" name="two_factor_code" required autofocus autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" class="w-full text-center text-3xl font-mono font-bold tracking-[0.35em] py-3.5 bg-slate-950 border border-slate-800 rounded-xl text-indigo-400 focus:outline-none focus:border-indigo-500 transition shadow-inner">
                </div>

                <button type="submit" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2 cursor-pointer">
                    <span>Validar Código</span>
                    <i data-lucide="check" class="w-4 h-4"></i>
                </button>

                <div class="text-center pt-2">
                    <button type="submit" name="action" value="cancel_2fa" class="text-xs text-slate-400 hover:text-slate-200 transition underline cursor-pointer">
                        Cancelar e voltar ao login
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
