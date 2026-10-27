<?php
/**
 * KanbanDoo - Gestão de Clientes e Empresas
 */
require_once dirname(__DIR__) . '/bootstrap.php';

require_login();

$pdo = get_pdo();
$user = current_user();

// Processar Ações POST (Criar, Editar, Excluir)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Token de segurança inválido.');
        redirect(url('/clientes'));
    }

    $action = $_POST['action'] ?? '';

    // Atualização rápida de Categoria direto da tabela ou card
    if ($action === 'quick_update_category') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $category = trim((string)($_POST['category'] ?? 'C'));
        if (!in_array($category, ['A+', 'A', 'B', 'C', 'D'])) {
            $category = 'C';
        }
        if ($clientId > 0) {
            $pdo->prepare("UPDATE clients SET category = :cat, updated_at = NOW() WHERE id = :id")->execute([':cat' => $category, ':id' => $clientId]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'category' => $category]);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Empresa inválida.'], 400);
        exit;
    }

    // Salvar / Editar Cliente
    if ($action === 'save_client') {
        $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
        $companyName = trim((string)($_POST['company_name'] ?? ''));
        $category = trim((string)($_POST['category'] ?? 'C'));
        if (!in_array($category, ['A+', 'A', 'B', 'C', 'D'])) {
            $category = 'C';
        }
        $contactName = trim((string)($_POST['contact_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive']) ? $_POST['status'] : 'active';
        $notes = trim((string)($_POST['notes'] ?? ''));

        // Processar Links Úteis do Cliente
        $linkTitles = $_POST['link_title'] ?? [];
        $linkUrls = $_POST['link_url'] ?? [];
        $linkTypes = $_POST['link_type'] ?? [];
        $links = [];

        if (is_array($linkTitles)) {
            for ($i = 0; $i < count($linkTitles); $i++) {
                $t = trim((string)($linkTitles[$i] ?? ''));
                $u = trim((string)($linkUrls[$i] ?? ''));
                $tp = trim((string)($linkTypes[$i] ?? 'other'));
                if ($t !== '' && $u !== '') {
                    $links[] = [
                        'id' => $i + 1,
                        'title' => $t,
                        'url' => $u,
                        'type' => $tp,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }
        $linksJson = json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($companyName === '') {
            flash('danger', 'O nome da empresa / cliente é obrigatório.');
            redirect(url('/clientes'));
        }

        try {
            if ($clientId > 0) {
                $stmt = $pdo->prepare('
                    UPDATE clients 
                    SET company_name = :comp, category = :cat, contact_name = :contact, email = :email, phone = :phone, status = :status, notes = :notes, links_json = :links 
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':comp' => $companyName,
                    ':cat' => $category,
                    ':contact' => $contactName,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':status' => $status,
                    ':notes' => $notes,
                    ':links' => $linksJson,
                    ':id' => $clientId
                ]);
                flash('success', "Cliente \"{$companyName}\" atualizado com sucesso!");
            } else {
                $stmt = $pdo->prepare('
                    INSERT INTO clients (company_name, category, contact_name, email, phone, status, notes, links_json) 
                    VALUES (:comp, :cat, :contact, :email, :phone, :status, :notes, :links)
                ');
                $stmt->execute([
                    ':comp' => $companyName,
                    ':cat' => $category,
                    ':contact' => $contactName,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':status' => $status,
                    ':notes' => $notes,
                    ':links' => $linksJson
                ]);
                flash('success', "Cliente \"{$companyName}\" cadastrado com sucesso!");
            }
        } catch (PDOException $e) {
            flash('danger', 'Erro ao salvar cliente: ' . $e->getMessage());
        }

        redirect(url('/clientes'));
    }

    // Excluir Cliente
    if ($action === 'delete_client') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId > 0) {
            try {
                $stmt = $pdo->prepare('DELETE FROM clients WHERE id = :id');
                $stmt->execute([':id' => $clientId]);
                flash('success', 'Cliente excluído com sucesso.');
            } catch (PDOException $e) {
                flash('danger', 'Erro ao excluir cliente: ' . $e->getMessage());
            }
        }
        redirect(url('/clientes'));
    }
}

// Listagem de Clientes com Contagem de Tarefas
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM tasks WHERE client_id = c.id AND status = 'open') AS open_tasks_count,
           (SELECT COUNT(*) FROM tasks WHERE client_id = c.id AND status = 'completed') AS completed_tasks_count
    FROM clients c
    WHERE 1=1
";
$params = [];

if ($search !== '') {
    $driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';
    if ($driver === 'sqlite') {
        $words = array_values(array_filter(preg_split('/\s+/', kd_normalize_text($search))));
        $clauses = [];
        foreach ($words as $idx => $w) {
            $pk = ":s_$idx";
            $clauses[] = "(kd_norm(c.company_name) LIKE $pk OR kd_norm(c.contact_name) LIKE $pk OR kd_norm(c.email) LIKE $pk)";
            $params[$pk] = "%$w%";
        }
        if (!empty($clauses)) {
            $sql .= " AND (" . implode(" AND ", $clauses) . ")";
        }
    } else {
        $sql .= " AND (c.company_name LIKE :s_comp OR c.contact_name LIKE :s_cont OR c.email LIKE :s_mail)";
        $params[':s_comp'] = "%{$search}%";
        $params[':s_cont'] = "%{$search}%";
        $params[':s_mail'] = "%{$search}%";
    }
}
if ($statusFilter !== '') {
    $sql .= " AND c.status = :st";
    $params[':st'] = $statusFilter;
}

$sql .= " ORDER BY c.company_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

view_header('Clientes & Empresas');
?>

<main class="w-full px-4 lg:px-8 py-6 space-y-6 max-w-[1600px] mx-auto">
    
    <!-- Topo -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i data-lucide="building-2" class="w-6 h-6 text-indigo-400"></i>
                <span>Clientes & Empresas</span>
            </h1>
            <p class="text-xs text-slate-400 mt-0.5">Cadastre as empresas que aparecem nos cards de atividades</p>
        </div>

        <button onclick="openClientModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition shadow-lg shadow-indigo-600/25 flex items-center gap-2 self-start sm:self-auto">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>Novo Cliente</span>
        </button>
    </div>

    <!-- Barra de Busca e Filtro de Clientes -->
    <!-- Barra de Busca Live-Search e Alternador de Visualizacao -->
    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-3.5 mb-6 flex flex-wrap items-center justify-between gap-3 shadow-sm kd-glass">
        <div class="flex flex-1 flex-wrap items-center gap-3 min-w-[280px]">
            <!-- Live Search Input -->
            <div class="flex-1 min-w-[220px] relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">
                    <i data-lucide="search" class="w-4 h-4"></i>
                </span>
                <input type="search" id="clientSearchInput" value="<?= e($search) ?>" oninput="filterClientsLive()"
                       placeholder="Buscar por empresa, contato, e-mail ou telefone em tempo real..."
                       class="w-full pl-9 pr-9 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                <button type="button" id="btnClearSearch" onclick="clearClientSearch()" class="hidden absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white" title="Limpar busca">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </button>
            </div>

            <!-- Filtro de Status -->
            <div class="w-full sm:w-auto min-w-[130px]">
                <select id="clientStatusFilter" onchange="filterClientsLive()"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-indigo-500 transition cursor-pointer">
                    <option value="">Status: Todos</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativos</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inativos</option>
                </select>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <!-- Contador Dinamico -->
            <span id="clientCounter" class="text-xs text-slate-400 font-medium whitespace-nowrap">
                Exibindo <?= count($clients) ?> empresa(s)
            </span>

            <!-- Alternador de Modos de Visualizacao (Cards vs Lista) -->
            <div class="inline-flex items-center p-1 rounded-xl bg-slate-950 border border-slate-800" role="group">
                <button type="button" id="btnViewGrid" onclick="setClientsView('grid')"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 text-white bg-indigo-600 shadow-sm cursor-pointer"
                        title="Visualização em Cards">
                    <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                    <span class="hidden sm:inline">Cards</span>
                </button>
                <button type="button" id="btnViewList" onclick="setClientsView('list')"
                        class="px-2.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 text-slate-400 hover:text-white cursor-pointer"
                        title="Visualização em Lista">
                    <i data-lucide="list" class="w-3.5 h-3.5"></i>
                    <span class="hidden sm:inline">Lista</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Estado Vazio (quando nao ha clientes cadastrados) -->
    <?php if (empty($clients)): ?>
        <div class="bg-slate-900/40 border border-slate-800 rounded-2xl p-12 text-center">
            <div class="w-12 h-12 rounded-2xl bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="building-2" class="w-6 h-6"></i>
            </div>
            <h3 class="text-sm font-bold text-white mb-1">Nenhum cliente cadastrado</h3>
            <p class="text-xs text-slate-400 mb-4">Cadastre a primeira empresa para gerenciar demandas, notas e links.</p>
            <button onclick="openClientModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition">
                + Cadastrar Cliente
            </button>
        </div>
    <?php else: ?>

        <!-- Aviso de Live-Search Sem Resultados -->
        <div id="clientsEmptySearch" class="hidden bg-slate-900/40 border border-slate-800 rounded-2xl p-10 text-center">
            <div class="w-10 h-10 rounded-xl bg-slate-800/80 text-slate-400 flex items-center justify-center mx-auto mb-2.5">
                <i data-lucide="search-x" class="w-5 h-5"></i>
            </div>
            <h4 class="text-sm font-bold text-white mb-1">Nenhuma empresa encontrada</h4>
            <p class="text-xs text-slate-400 mb-3">Tente buscar por outro termo ou limpe o filtro atual.</p>
            <button type="button" onclick="clearClientSearch()" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-slate-200 transition">
                Limpar busca
            </button>
        </div>

        <!-- 1. MODO CARDS (GRID COMPACTO E ELEGANTE) -->
        <div id="clientsGridView" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3.5">
            <?php foreach ($clients as $client): 
                $cleanPhone = preg_replace('/\D/', '', (string)($client['phone'] ?? ''));
                $initials = mb_strtoupper(mb_substr($client['company_name'], 0, 2, 'UTF-8'));
            ?>
                <div class="client-item kd-card kd-glass rounded-2xl p-3.5 flex flex-col justify-between transition-all group hover:border-slate-700 relative shadow-sm"
                     data-company="<?= e(mb_strtolower($client['company_name'])) ?>"
                     data-contact="<?= e(mb_strtolower($client['contact_name'] ?? '')) ?>"
                     data-email="<?= e(mb_strtolower($client['email'] ?? '')) ?>"
                     data-phone="<?= e(mb_strtolower($client['phone'] ?? '')) ?>"
                     data-notes="<?= e(mb_strtolower($client['notes'] ?? '')) ?>"
                     data-status="<?= e($client['status']) ?>">
                    
                    <div>
                        <!-- Header do Card -->
                        <div class="flex items-start justify-between gap-2 mb-2.5">
                            <div class="flex items-center gap-2.5 min-w-0 flex-1">
                                <div class="w-8 h-8 rounded-xl bg-indigo-600/15 border border-indigo-500/25 text-indigo-400 font-bold text-xs flex items-center justify-center flex-shrink-0 shadow-inner">
                                    <?= $initials ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <button type="button" onclick="editClient(<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>)" class="text-left font-bold text-sm text-white leading-tight hover:text-indigo-300 hover:underline cursor-pointer transition truncate block w-full" title="Clique para editar <?= e($client['company_name']) ?>">
                                        <?= e($client['company_name']) ?>
                                    </button>
                                    <?php if (!empty($client['contact_name'])): ?>
                                        <p class="text-[11px] text-slate-400 truncate mt-0.5"><?= e($client['contact_name']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider flex-shrink-0 <?= $client['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-500' ?>">
                                <?= $client['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </div>

                        <!-- Informacoes de Contato Compactas -->
                        <div class="space-y-1 py-2 border-y border-slate-800/60 text-xs">
                            <?php if (!empty($client['email'])): ?>
                                <div class="flex items-center gap-2 truncate text-[11px]">
                                    <i data-lucide="mail" class="w-3.5 h-3.5 text-slate-500 flex-shrink-0"></i>
                                    <a href="mailto:<?= e($client['email']) ?>" class="hover:text-indigo-400 truncate text-slate-300 transition"><?= e($client['email']) ?></a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($client['phone'])): ?>
                                <div class="flex items-center gap-2 truncate text-[11px]">
                                    <i data-lucide="phone" class="w-3.5 h-3.5 text-slate-500 flex-shrink-0"></i>
                                    <a href="https://wa.me/55<?= $cleanPhone ?>" target="_blank" rel="noopener noreferrer" class="hover:text-emerald-400 truncate text-slate-300 transition" title="Abrir conversa no WhatsApp">
                                        <?= e($client['phone']) ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($client['notes'])): ?>
                                <p class="text-[11px] text-slate-400 line-clamp-1 italic pt-0.5" title="<?= e($client['notes']) ?>">
                                    &ldquo;<?= e($client['notes']) ?>&rdquo;
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Footer do Card: Demandas, Notas e Acoes -->
                    <div class="flex items-center justify-between pt-2.5 mt-1 text-xs">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <a href="<?= url('/tarefas') ?>?client=<?= (int)$client['id'] ?>" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 text-[11px] font-semibold transition" title="Ver tarefas no Kanban">
                                <i data-lucide="layout-grid" class="w-3 h-3"></i>
                                <span><?= (int)$client['open_tasks_count'] ?> abertas</span>
                            </a>
                            <a href="<?= url('/notas') ?>?client_id=<?= (int)$client['id'] ?>" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white text-[11px] font-medium transition" title="Ver notas deste cliente">
                                <i data-lucide="notebook-tabs" class="w-3 h-3 text-amber-400"></i>
                                <span><?= (int)($client['notes_count'] ?? 0) ?> notas</span>
                            </a>
                        </div>

                        <div class="flex items-center gap-1">
                            <button type="button" onclick="copyPortalLink('<?= e($client['portal_token'] ?? '') ?>', '<?= e($client['company_name']) ?>')" class="p-1.5 bg-slate-800/80 hover:bg-slate-700 text-indigo-400 hover:text-indigo-300 rounded-lg transition cursor-pointer" title="Copiar Link do Portal do Cliente">
                                <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                            </button>
                            <button onclick="editClient(<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>)" class="p-1.5 bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg transition cursor-pointer" title="Editar">
                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta empresa?');" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="delete_client">
                                <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
                                <button type="submit" class="p-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg transition cursor-pointer" title="Excluir">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 2. MODO LISTA (TABELA ELEGANTE E COMPACTA) -->
        <div id="clientsListView" class="hidden bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden kd-glass shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/40 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                            <th class="py-3 px-4">Empresa & Contato</th>
                            <th class="py-3 px-4">Categoria</th>
                            <th class="py-3 px-4">E-mail & Telefone</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Atividades</th>
                            <th class="py-3 px-4">Portal do Cliente</th>
                            <th class="py-3 px-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        <?php foreach ($clients as $client): 
                            $cleanPhone = preg_replace('/\D/', '', (string)($client['phone'] ?? ''));
                            $initials = mb_strtoupper(mb_substr($client['company_name'], 0, 2, 'UTF-8'));
                        ?>
                            <tr class="client-item hover:bg-slate-800/30 transition group"
                                data-company="<?= e(mb_strtolower($client['company_name'])) ?>"
                                data-contact="<?= e(mb_strtolower($client['contact_name'] ?? '')) ?>"
                                data-email="<?= e(mb_strtolower($client['email'] ?? '')) ?>"
                                data-phone="<?= e(mb_strtolower($client['phone'] ?? '')) ?>"
                                data-notes="<?= e(mb_strtolower($client['notes'] ?? '')) ?>"
                                data-status="<?= e($client['status']) ?>">
                                
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl bg-indigo-600/15 border border-indigo-500/25 text-indigo-400 font-bold text-xs flex items-center justify-center flex-shrink-0">
                                            <?= $initials ?>
                                        </div>
                                        <div class="min-w-0">
                                            <button type="button" onclick="editClient(<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>)" class="text-left font-bold text-white text-xs leading-snug hover:text-indigo-300 hover:underline cursor-pointer transition block truncate" title="Clique para editar <?= e($client['company_name']) ?>">
                                                <?= e($client['company_name']) ?>
                                            </button>
                                            <?php if (!empty($client['contact_name'])): ?>
                                                <p class="text-[11px] text-slate-400"><?= e($client['contact_name']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="space-y-0.5">
                                        <?php if (!empty($client['email'])): ?>
                                            <div class="flex items-center gap-1.5 text-[11px]">
                                                <i data-lucide="mail" class="w-3 h-3 text-slate-500"></i>
                                                <a href="mailto:<?= e($client['email']) ?>" class="hover:text-indigo-400 truncate"><?= e($client['email']) ?></a>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($client['phone'])): ?>
                                            <div class="flex items-center gap-1.5 text-[11px]">
                                                <i data-lucide="phone" class="w-3 h-3 text-slate-500"></i>
                                                <a href="https://wa.me/55<?= $cleanPhone ?>" target="_blank" rel="noopener noreferrer" class="hover:text-emerald-400 truncate">
                                                    <?= e($client['phone']) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider <?= $client['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-500' ?>">
                                        <?= $client['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>

                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1.5">
                                        <a href="<?= url('/tarefas') ?>?client=<?= (int)$client['id'] ?>" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 text-[11px] font-semibold transition" title="Ver tarefas no Kanban">
                                            <i data-lucide="layout-grid" class="w-3 h-3"></i>
                                            <span><?= (int)$client['open_tasks_count'] ?></span>
                                        </a>
                                        <a href="<?= url('/notas') ?>?client_id=<?= (int)$client['id'] ?>" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white text-[11px] font-medium transition" title="Ver notas deste cliente">
                                            <i data-lucide="notebook-tabs" class="w-3 h-3 text-amber-400"></i>
                                            <span><?= (int)($client['notes_count'] ?? 0) ?></span>
                                        </a>
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    <button type="button" onclick="copyPortalLink('<?= e($client['portal_token'] ?? '') ?>', '<?= e($client['company_name']) ?>')"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-indigo-400 hover:text-indigo-300 text-[11px] font-medium transition cursor-pointer" title="Copiar link do portal">
                                        <i data-lucide="external-link" class="w-3 h-3"></i>
                                        <span>Copiar Link</span>
                                    </button>
                                </td>

                                <td class="py-3 px-4 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button onclick="editClient(<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>)" class="p-1.5 bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg transition cursor-pointer" title="Editar">
                                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta empresa?');" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete_client">
                                            <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
                                            <button type="submit" class="p-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg transition cursor-pointer" title="Excluir">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>
<div id="clientModal" class="kd-modal" role="dialog" aria-modal="true" aria-labelledby="clientModalTitle" hidden>
    <div class="kd-modal__panel kd-modal__panel--md kd-glass kd-glass--raised">
        <div class="flex items-center justify-between pb-4 mb-5" style="border-bottom: 1px solid var(--border)">
            <h3 id="clientModalTitle" class="text-lg font-bold text-white">Cadastrar Novo Cliente</h3>
            <button type="button" onclick="closeClientModal()" class="kd-icon-btn" aria-label="Fechar">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_client">
            <input type="hidden" id="modal_client_id" name="client_id" value="">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Nome da Empresa / Cliente *</label>
                    <input type="text" id="modal_company_name" name="company_name" required placeholder="Ex: Studio Alpha &bull; Advocacia" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Categoria *</label>
                    <select id="modal_category" name="category" class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition font-bold">
                        <option value="A+">Categoria A+ (Máxima)</option>
                        <option value="A">Categoria A</option>
                        <option value="B">Categoria B</option>
                        <option value="C" selected>Categoria C</option>
                        <option value="D">Categoria D</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Pessoa de Contato</label>
                    <input type="text" id="modal_contact_name" name="contact_name" placeholder="Ex: Dr. Roberto" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Telefone / WhatsApp</label>
                    <input type="text" id="modal_phone" name="phone" placeholder="(11) 99999-9999" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">E-mail</label>
                    <input type="email" id="modal_email" name="email" placeholder="contato@empresa.com" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Status</label>
                    <select id="modal_status" name="status" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Observações / Notas</label>
                <textarea id="modal_notes" name="notes" rows="2" placeholder="Informações contratuais, nicho de atuação..." class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition resize-none"></textarea>
            </div>

            <!-- Seção de Links Úteis & Acessos do Cliente -->
            <div class="pt-4 border-t border-slate-800 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-bold text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <i data-lucide="link-2" class="w-4 h-4 text-indigo-400"></i>
                            Links Úteis & Acessos do Cliente
                        </h4>
                        <p class="text-[11px] text-slate-400">Estes links aparecem como atalhos rápidos nas tarefas deste cliente</p>
                    </div>
                </div>

                <!-- Botões de Atalhos Rápidos para Criar Links Comuns -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    <button type="button" onclick="addClientLinkPreset('Site Oficial', 'https://', 'site')" class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 text-[11px] font-medium transition flex items-center gap-1">
                        <span>🌐 Site</span>
                    </button>
                    <button type="button" onclick="addClientLinkPreset('BM Facebook / Meta', 'https://business.facebook.com', 'facebook')" class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 text-[11px] font-medium transition flex items-center gap-1">
                        <span>📘 BM Meta</span>
                    </button>
                    <button type="button" onclick="addClientLinkPreset('MCC Google Ads', 'https://ads.google.com', 'google')" class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 text-[11px] font-medium transition flex items-center gap-1">
                        <span>📊 Google Ads</span>
                    </button>
                    <button type="button" onclick="addClientLinkPreset('Instagram Oficial', 'https://instagram.com/', 'instagram')" class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 text-[11px] font-medium transition flex items-center gap-1">
                        <span>📸 Instagram</span>
                    </button>
                    <button type="button" onclick="addClientLinkPreset('Pasta no Google Drive', 'https://drive.google.com', 'drive')" class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-slate-800 text-slate-300 hover:text-white border border-slate-800 text-[11px] font-medium transition flex items-center gap-1">
                        <span>📁 Drive / Docs</span>
                    </button>
                    <button type="button" onclick="addClientLinkPreset('', '', 'other')" class="px-2.5 py-1 rounded-lg bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/30 text-[11px] font-semibold transition flex items-center gap-1">
                        <i data-lucide="plus" class="w-3 h-3"></i>
                        <span>Outro Link</span>
                    </button>
                </div>

                <!-- Lista Dinâmica de Links -->
                <div id="clientLinksList" class="space-y-2 max-h-48 overflow-y-auto pr-1"></div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4" style="border-top: 1px solid var(--border)">
                <button type="button" onclick="closeClientModal()" class="kd-btn">
                    Cancelar
                </button>
                <button type="submit" class="kd-btn kd-btn--primary">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Salvar Cliente
                </button>
            </div>
        </form>
    </div>
</div>

<script>

// -------------------------------------------------------------
// Live-Search & Alternador de Visualizacao (Cards vs Lista)
// -------------------------------------------------------------
function setClientsView(mode) {
    const grid = document.getElementById('clientsGridView');
    const list = document.getElementById('clientsListView');
    const btnGrid = document.getElementById('btnViewGrid');
    const btnList = document.getElementById('btnViewList');
    if (!grid || !list) return;

    if (mode === 'list') {
        grid.classList.add('hidden');
        list.classList.remove('hidden');
        if (btnList) {
            btnList.className = 'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 text-white bg-indigo-600 shadow-sm cursor-pointer';
        }
        if (btnGrid) {
            btnGrid.className = 'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 text-slate-400 hover:text-white cursor-pointer';
        }
        localStorage.setItem('kd_clients_view_mode', 'list');
    } else {
        list.classList.add('hidden');
        grid.classList.remove('hidden');
        if (btnGrid) {
            btnGrid.className = 'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 text-white bg-indigo-600 shadow-sm cursor-pointer';
        }
        if (btnList) {
            btnList.className = 'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 text-slate-400 hover:text-white cursor-pointer';
        }
        localStorage.setItem('kd_clients_view_mode', 'grid');
    }
}

function normalizeStr(str) {
    if (!str) return '';
    return str.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function filterClientsLive() {
    const searchInput = document.getElementById('clientSearchInput');
    const statusSelect = document.getElementById('clientStatusFilter');
    const btnClear = document.getElementById('btnClearSearch');
    const counter = document.getElementById('clientCounter');
    const emptyNotice = document.getElementById('clientsEmptySearch');

    const query = normalizeStr(searchInput ? searchInput.value.trim() : '');
    const status = statusSelect ? statusSelect.value : '';

    if (btnClear) {
        btnClear.classList.toggle('hidden', query === '');
    }

    const items = document.querySelectorAll('.client-item');
    items.forEach(el => {
        const company = el.dataset.company || '';
        const contact = el.dataset.contact || '';
        const email = el.dataset.email || '';
        const phone = el.dataset.phone || '';
        const notes = el.dataset.notes || '';
        const itemStatus = el.dataset.status || '';

        const fullText = normalizeStr(`${company} ${contact} ${email} ${phone} ${notes}`);
        const matchesQuery = query === '' || fullText.includes(query);
        const matchesStatus = status === '' || itemStatus === status;

        const show = matchesQuery && matchesStatus;
        if (show) {
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    });

    // Contagem de visiveis (somente no grid para nao contar dobrado)
    let visibleCount = 0;
    const gridItems = document.querySelectorAll('#clientsGridView .client-item');
    gridItems.forEach(el => {
        if (el.style.display !== 'none') visibleCount++;
    });

    if (counter) {
        counter.textContent = query !== '' || status !== '' 
            ? `Exibindo ${visibleCount} de ${gridItems.length} empresas`
            : `Exibindo ${gridItems.length} empresa(s)`;
    }

    if (emptyNotice) {
        emptyNotice.classList.toggle('hidden', visibleCount > 0);
    }
}

function clearClientSearch() {
    const searchInput = document.getElementById('clientSearchInput');
    const statusSelect = document.getElementById('clientStatusFilter');
    if (searchInput) searchInput.value = '';
    if (statusSelect) statusSelect.value = '';
    filterClientsLive();
    if (searchInput) searchInput.focus();
}

// Inicializacao da visualizacao salva
document.addEventListener('DOMContentLoaded', () => {
    const savedMode = localStorage.getItem('kd_clients_view_mode') || 'grid';
    setClientsView(savedMode);
    filterClientsLive();
});

function openClientModal() {
    document.getElementById('modal_client_id').value = '';
    document.getElementById('modal_company_name').value = '';
    const catEl = document.getElementById('modal_category'); if (catEl) catEl.value = 'C';
    document.getElementById('modal_contact_name').value = '';
    document.getElementById('modal_email').value = '';
    document.getElementById('modal_phone').value = '';
    document.getElementById('modal_status').value = 'active';
    document.getElementById('modal_notes').value = '';
    document.getElementById('clientLinksList').innerHTML = '';
    document.getElementById('clientModalTitle').textContent = 'Cadastrar Novo Cliente';
    KD.openModal('clientModal');
    if (window.lucide) lucide.createIcons();
}

function copyPortalLink(token, companyName) {
    if (!token) {
        alert('Este cliente ainda não possui um link de portal configurado.');
        return;
    }
    const baseUrl = window.location.origin + <?= json_encode(url('/portal')) ?>;
    const portalUrl = `${baseUrl}/${token}`;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(portalUrl).then(() => {
            alert(`Link do Portal copiado com sucesso para ${companyName}!\n\n` + portalUrl);
        }).catch(() => {
            prompt(`Copie o link do portal para ${companyName}:`, portalUrl);
        });
    } else {
        prompt(`Copie o link do portal para ${companyName}:`, portalUrl);
    }
}

function editClient(client) {
    document.getElementById('modal_client_id').value = client.id;
    document.getElementById('modal_company_name').value = client.company_name;
    document.getElementById('modal_category').value = client.category || 'C';
    document.getElementById('modal_contact_name').value = client.contact_name || '';
    document.getElementById('modal_email').value = client.email || '';
    document.getElementById('modal_phone').value = client.phone || '';
    document.getElementById('modal_status').value = client.status || 'active';
    document.getElementById('modal_notes').value = client.notes || '';
    document.getElementById('clientModalTitle').textContent = 'Editar Cliente';

    // Carregar Links Úteis
    const listEl = document.getElementById('clientLinksList');
    listEl.innerHTML = '';
    let links = [];
    try {
        links = typeof client.links_json === 'string' ? JSON.parse(client.links_json || '[]') : (client.links_json || []);
    } catch(e) { links = []; }

    if (Array.isArray(links) && links.length > 0) {
        links.forEach(l => {
            addClientLinkRow(l.title || '', l.url || '', l.type || 'other');
        });
    }

    KD.openModal('clientModal');
    if (window.lucide) lucide.createIcons();
}

function closeClientModal() {
    KD.closeModal('clientModal');
}

function addClientLinkPreset(title, url, type) {
    addClientLinkRow(title, url, type);
    if (window.lucide) lucide.createIcons();
}

function addClientLinkRow(title = '', url = '', type = 'other') {
    const listEl = document.getElementById('clientLinksList');
    const rowId = 'link_row_' + Math.random().toString(36).substr(2, 9);
    
    const div = document.createElement('div');
    div.id = rowId;
    div.className = 'flex items-center gap-2 p-2 bg-slate-950 border border-slate-800 rounded-xl animate-fade-in';
    div.innerHTML = `
        <select name="link_type[]" class="w-28 px-2 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200 focus:outline-none">
            <option value="site" ${type === 'site' ? 'selected' : ''}>🌐 Site</option>
            <option value="facebook" ${type === 'facebook' ? 'selected' : ''}>📘 Meta BM</option>
            <option value="google" ${type === 'google' ? 'selected' : ''}>📊 Google Ads</option>
            <option value="instagram" ${type === 'instagram' ? 'selected' : ''}>📸 Instagram</option>
            <option value="drive" ${type === 'drive' ? 'selected' : ''}>📁 Drive / Docs</option>
            <option value="other" ${type === 'other' ? 'selected' : ''}>🔗 Outro</option>
        </select>
        <input type="text" name="link_title[]" value="${escapeHtml(title)}" placeholder="Título (ex: Site Oficial)" required class="flex-1 min-w-[120px] px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
        <input type="url" name="link_url[]" value="${escapeHtml(url)}" placeholder="https://..." required class="flex-1 min-w-[140px] px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500">
        <button type="button" onclick="document.getElementById('${rowId}').remove()" class="p-1.5 text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition" title="Remover Link">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
        </button>
    `;
    listEl.appendChild(div);
}

async function updateClientCategory(clientId, category, selectEl) {
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
    const originalValue = selectEl.getAttribute('data-prev-val') || category;
    
    // Atualização visual imediata das classes
    const classMap = {
        'A+': 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        'A':  'bg-indigo-500/15 text-indigo-300 border-indigo-500/30',
        'B':  'bg-sky-500/15 text-sky-300 border-sky-500/30',
        'C':  'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        'D':  'bg-slate-500/15 text-slate-300 border-slate-500/30',
    };
    
    // Remove classes anteriores
    Object.values(classMap).forEach(cls => {
        cls.split(' ').forEach(c => selectEl.classList.remove(c));
    });
    // Adiciona novas classes
    (classMap[category] || classMap['C']).split(' ').forEach(c => selectEl.classList.add(c));

    try {
        const formData = new FormData();
        formData.append('action', 'quick_update_category');
        formData.append('csrf_token', csrfToken);
        formData.append('client_id', clientId);
        formData.append('category', category);

        const resp = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
        });
        const res = await resp.json();
        if (res.success) {
            selectEl.setAttribute('data-prev-val', category);
            if (window.KD && KD.toast) {
                KD.toast(`Categoria atualizada para ${category}!`, { tone: 'success' });
            }
        } else {
            throw new Error(res.error || 'Erro ao atualizar.');
        }
    } catch (e) {
        alert(e.message || 'Falha ao atualizar categoria.');
        selectEl.value = originalValue;
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
</script>

<?php view_footer(); ?>
