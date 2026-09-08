<?php
/**
 * KanbanDoo - Instalador Automático de Banco de Dados (Estilo WordPress)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!function_exists('e')) {
    function e(?string $string): string
    {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbDriver = $_POST['db_driver'] ?? 'sqlite';
    $adminName = trim($_POST['admin_name'] ?? 'Administrador');
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminEmail = trim($_POST['admin_email'] ?? 'admin@kanbando.local');
    $adminPass = $_POST['admin_pass'] ?? 'admin123';

    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? 'kanbando');
    $dbUser = trim($_POST['db_user'] ?? 'root');
    $dbPass = $_POST['db_pass'] ?? '';

    if ($adminUser === '' || $adminPass === '') {
        $error = 'Por favor, informe o usuário e a senha do administrador.';
    } else {
        try {
            $storageDir = __DIR__ . '/../storage';
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0777, true);
            }
            $uploadsDir = __DIR__ . '/../storage/uploads';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }

            $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);

            if ($dbDriver === 'sqlite') {
                $dbFile = $storageDir . '/database.sqlite';
                $pdo = new PDO('sqlite:' . $dbFile, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                $pdo->exec('PRAGMA foreign_keys = ON;');

                $schemaFile = __DIR__ . '/schema_sqlite.sql';
                if (!file_exists($schemaFile)) {
                    throw new Exception("Arquivo schema_sqlite.sql não encontrado.");
                }
                $sql = file_get_contents($schemaFile);
                $pdo->exec($sql);

                // Inserir ou Atualizar Admin no SQLite
                $adminStmt = $pdo->prepare('
                    INSERT INTO users (username, password_hash, full_name, email, role) 
                    VALUES (:u, :p, :n, :e, "admin")
                    ON CONFLICT(username) DO UPDATE SET
                        password_hash = excluded.password_hash,
                        full_name = excluded.full_name,
                        email = excluded.email
                ');
                $adminStmt->execute([
                    ':u' => $adminUser,
                    ':p' => $passwordHash,
                    ':n' => $adminName,
                    ':e' => $adminEmail
                ]);

                // Salvar config.local.php
                $configContent = "<?php\n"
                    . "// Configuração gerada pelo Instalador do KanbanDoo\n"
                    . "define('DB_DRIVER', 'sqlite');\n"
                    . "define('DB_SQLITE_PATH', __DIR__ . '/../storage/database.sqlite');\n";
                file_put_contents(__DIR__ . '/../config/config.local.php', $configContent);

            } else {
                // Modo MySQL
                $pdoInit = new PDO("mysql:host={$dbHost};port={$dbPort};charset=utf8mb4", $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                $schemaFile = __DIR__ . '/schema.sql';
                if (!file_exists($schemaFile)) {
                    throw new Exception("Arquivo schema.sql não encontrado.");
                }
                $sql = file_get_contents($schemaFile);
                $pdo->exec($sql);

                // Atualizar/Inserir Admin
                $adminStmt = $pdo->prepare('
                    INSERT INTO users (id, username, password_hash, full_name, email, role) 
                    VALUES (1, :u, :p, :n, :e, "admin")
                    ON DUPLICATE KEY UPDATE 
                        username = VALUES(username),
                        password_hash = VALUES(password_hash),
                        full_name = VALUES(full_name),
                        email = VALUES(email)
                ');
                $adminStmt->execute([
                    ':u' => $adminUser,
                    ':p' => $passwordHash,
                    ':n' => $adminName,
                    ':e' => $adminEmail
                ]);

                // Salvar config.local.php
                $configContent = "<?php\n"
                    . "// Configuração gerada pelo Instalador do KanbanDoo\n"
                    . "define('DB_DRIVER', 'mysql');\n"
                    . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                    . "define('DB_PORT', " . var_export($dbPort, true) . ");\n"
                    . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                    . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                    . "define('DB_PASS', " . var_export($dbPass, true) . ");\n";
                file_put_contents(__DIR__ . '/../config/config.local.php', $configContent);
            }

            $success = true;
            $message = "KanbanDoo instalado com sucesso!";
        } catch (Exception $e) {
            $error = "Erro na instalação: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador &bull; KanbanDoo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 sm:p-6 relative overflow-x-hidden">
    
    <!-- Efeitos de Fundo -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-purple-600/15 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-xl bg-slate-900/90 backdrop-blur-xl border border-slate-800 rounded-3xl shadow-2xl p-6 sm:p-10 relative z-10">
        
        <!-- Topo / Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-indigo-500 to-indigo-700 rounded-2xl mb-4 shadow-xl shadow-indigo-500/30">
                <i data-lucide="kanban" class="w-8 h-8 text-white"></i>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Instalação do KanbanDoo</h1>
            <p class="text-slate-400 text-xs sm:text-sm mt-1.5">Configure sua aplicação em poucos cliques no estilo WordPress</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-rose-500/10 border border-rose-500/20 rounded-2xl text-rose-400 text-xs flex items-center gap-3">
                <i data-lucide="alert-triangle" class="w-5 h-5 flex-shrink-0"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 p-6 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl text-emerald-300 text-center space-y-2">
                <div class="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto mb-2">
                    <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                </div>
                <h3 class="text-lg font-bold text-white"><?= $message ?></h3>
                <p class="text-xs text-slate-400">Banco de dados configurado e usuário administrador criado.</p>
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-xs font-mono text-slate-300 mt-4">
                    Usuário: <strong><?= e($adminUser) ?></strong> | Senha: <strong><?= e($adminPass) ?></strong>
                </div>
            </div>

            <a href="../entrar" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                <span>Acessar o KanbanDoo Agora</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        <?php else: ?>
            <form method="POST" class="space-y-6">
                
                <!-- 1. Escolha do Banco de Dados -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <i data-lucide="database" class="w-4 h-4 text-indigo-400"></i>
                        1. Tipo de Banco de Dados
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        
                        <!-- Opção SQLite -->
                        <label class="relative flex flex-col p-4 bg-slate-950 border-2 border-indigo-500 rounded-2xl cursor-pointer hover:bg-slate-900/80 transition" id="cardSqlite">
                            <input type="radio" name="db_driver" value="sqlite" checked onchange="toggleDriverFields('sqlite')" class="sr-only">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <i data-lucide="zap" class="w-4 h-4 text-amber-400"></i>
                                    Local (SQLite)
                                </span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                    1 Clique
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-relaxed">
                                Zero configuração. Cria o banco localmente em arquivo para testes rápidos.
                            </p>
                        </label>

                        <!-- Opção MySQL -->
                        <label class="relative flex flex-col p-4 bg-slate-950 border-2 border-slate-800 rounded-2xl cursor-pointer hover:bg-slate-900/80 transition opacity-80" id="cardMysql">
                            <input type="radio" name="db_driver" value="mysql" onchange="toggleDriverFields('mysql')" class="sr-only">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <i data-lucide="server" class="w-4 h-4 text-indigo-400"></i>
                                    Servidor (MySQL)
                                </span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-800 text-slate-400">
                                    VPS / Nuvem
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-relaxed">
                                Conecta ao MySQL / MariaDB com host, usuário e senha.
                            </p>
                        </label>
                    </div>
                </div>

                <!-- Campos específicos do MySQL (Ocultos se SQLite selecionado) -->
                <div id="mysqlFields" class="hidden space-y-3 p-4 bg-slate-950/60 border border-slate-800 rounded-2xl animate-fade-in">
                    <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">Credenciais do MySQL</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">Host MySQL</label>
                            <input type="text" name="db_host" value="127.0.0.1" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">Porta</label>
                            <input type="text" name="db_port" value="3306" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Nome do Banco de Dados</label>
                        <input type="text" name="db_name" value="kanbando" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:border-indigo-500 focus:outline-none">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">Usuário MySQL</label>
                            <input type="text" name="db_user" value="root" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">Senha MySQL</label>
                            <input type="password" name="db_pass" placeholder="Em branco se sem senha" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- 2. Conta do Administrador -->
                <div class="space-y-3 pt-2">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <i data-lucide="user-check" class="w-4 h-4 text-indigo-400"></i>
                        2. Conta do Administrador
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">Nome Completo</label>
                            <input type="text" name="admin_name" value="Administrador" required class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">Usuário de Acesso (Login)</label>
                            <input type="text" name="admin_user" value="admin" required class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">E-mail</label>
                            <input type="email" name="admin_email" value="admin@kanbando.local" required class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:border-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">Senha</label>
                            <input type="password" name="admin_pass" value="admin123" required class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:border-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full py-4 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm rounded-xl transition shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                    <i data-lucide="play" class="w-4 h-4 fill-current"></i>
                    <span>Instalar e Iniciar KanbanDoo</span>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function toggleDriverFields(driver) {
            const mysqlFields = document.getElementById('mysqlFields');
            const cardSqlite = document.getElementById('cardSqlite');
            const cardMysql = document.getElementById('cardMysql');

            if (driver === 'sqlite') {
                mysqlFields.classList.add('hidden');
                cardSqlite.classList.remove('border-slate-800', 'opacity-80');
                cardSqlite.classList.add('border-indigo-500');
                cardMysql.classList.remove('border-indigo-500');
                cardMysql.classList.add('border-slate-800', 'opacity-80');
            } else {
                mysqlFields.classList.remove('hidden');
                cardMysql.classList.remove('border-slate-800', 'opacity-80');
                cardMysql.classList.add('border-indigo-500');
                cardSqlite.classList.remove('border-indigo-500');
                cardSqlite.classList.add('border-slate-800', 'opacity-80');
            }
        }
        lucide.createIcons();
    </script>
</body>
</html>
