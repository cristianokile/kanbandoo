<?php
/**
 * KanbanDoo - Tela de Autenticação
 */
require_once dirname(__DIR__) . '/bootstrap.php';

if (is_logged_in()) {
    redirect(url('/tarefas'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usernameOrEmail === '' || $password === '') {
        $error = 'Por favor, preencha o usuário/e-mail e a senha.';
    } else {
        if (attempt_login($usernameOrEmail, $password)) {
            redirect(url('/tarefas'));
        } else {
            $error = 'Usuário ou senha incorretos.';
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
        
        <!-- Header -->
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

            <button type="submit" class="w-full mt-6 py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                <span>Entrar no Sistema</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </form>

        <!-- Dica de Acesso Padrão -->
        <div class="mt-8 pt-6 border-t border-slate-800/80 text-center">
            <p class="text-[11px] text-slate-400">
                Acesso padrão: <span class="text-slate-300 font-mono">admin</span> / <span class="text-slate-300 font-mono">admin123</span>
            </p>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
