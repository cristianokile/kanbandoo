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

    // Salvar / Editar Cliente
    if ($action === 'save_client') {
        $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
        $companyName = trim((string)($_POST['company_name'] ?? ''));
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
                    SET company_name = :comp, contact_name = :contact, email = :email, phone = :phone, status = :status, notes = :notes, links_json = :links 
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':comp' => $companyName,
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
                    INSERT INTO clients (company_name, contact_name, email, phone, status, notes, links_json) 
                    VALUES (:comp, :contact, :email, :phone, :status, :notes, :links)
                ');
                $stmt->execute([
                    ':comp' => $companyName,
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
    $sql .= " AND (c.company_name LIKE :s OR c.contact_name LIKE :s OR c.email LIKE :s)";
    $params[':s'] = "%{$search}%";
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

<main class="flex-1 w-full px-4 lg:px-8 py-6">
    
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
    <form method="GET" class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-3.5 mb-6 flex flex-wrap items-center gap-3">
        <div class="flex-1 min-w-[220px] relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                <i data-lucide="search" class="w-4 h-4"></i>
            </span>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Buscar por empresa, contato ou e-mail..." class="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
        </div>

        <div class="w-full sm:w-auto min-w-[140px]">
            <select name="status" onchange="this.form.submit()" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-indigo-500 transition">
                <option value="">Status: Todos</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativos</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inativos</option>
            </select>
        </div>

        <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl transition">
            Filtrar
        </button>
        <?php if ($search !== '' || $statusFilter !== ''): ?>
            <a href="<?= url('/clientes') ?>" class="p-2 text-slate-400 hover:text-white transition text-xs flex items-center gap-1">
                <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                Limpar
            </a>
        <?php endif; ?>
    </form>

    <!-- Tabela / Grade de Clientes -->
    <?php if (empty($clients)): ?>
        <div class="bg-slate-900/40 border border-slate-800 rounded-2xl p-12 text-center">
            <div class="w-12 h-12 rounded-2xl bg-slate-800 text-slate-400 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="building-2" class="w-6 h-6"></i>
            </div>
            <h3 class="text-sm font-bold text-white mb-1">Nenhum cliente encontrado</h3>
            <p class="text-xs text-slate-400 mb-4">Comece cadastrando sua primeira empresa parceira.</p>
            <button onclick="openClientModal()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition">
                + Cadastrar Cliente
            </button>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($clients as $client): ?>
                <div class="bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-2xl p-5 shadow-lg flex flex-col justify-between transition group">
                    <div>
                        <!-- Header do Card -->
                        <div class="flex items-start justify-between gap-2 mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 font-bold text-sm flex items-center justify-center">
                                    <?= mb_strtoupper(mb_substr($client['company_name'], 0, 2, 'UTF-8')) ?>
                                </div>
                                <div>
                                    <h3 class="font-bold text-sm text-white leading-tight group-hover:text-indigo-300 transition">
                                        <?= e($client['company_name']) ?>
                                    </h3>
                                    <?php if (!empty($client['contact_name'])): ?>
                                        <p class="text-xs text-slate-400"><?= e($client['contact_name']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $client['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-slate-800 text-slate-500' ?>">
                                <?= $client['status'] === 'active' ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </div>

                        <!-- Informações de Contato -->
                        <div class="space-y-1.5 py-3 border-y border-slate-800/60 text-xs text-slate-300">
                            <?php if (!empty($client['email'])): ?>
                                <div class="flex items-center gap-2 truncate">
                                    <i data-lucide="mail" class="w-3.5 h-3.5 text-slate-500 flex-shrink-0"></i>
                                    <a href="mailto:<?= e($client['email']) ?>" class="hover:text-indigo-400 truncate"><?= e($client['email']) ?></a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($client['phone'])): ?>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="phone" class="w-3.5 h-3.5 text-slate-500 flex-shrink-0"></i>
                                    <span><?= e($client['phone']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($client['notes'])): ?>
                                <p class="text-[11px] text-slate-400 line-clamp-2 pt-1 italic">
                                    &ldquo;<?= e($client['notes']) ?>&rdquo;
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Footer do Card: Demandas e Ações -->
                    <div class="flex items-center justify-between pt-4 mt-3 text-xs">
                        <a href="<?= url('/tarefas') ?>?client=<?= (int)$client['id'] ?>" class="inline-flex items-center gap-1.5 font-semibold text-indigo-400 hover:text-indigo-300 transition" title="Ver demandas no Kanban">
                            <i data-lucide="kanban" class="w-4 h-4"></i>
                            <span><?= (int)$client['open_tasks_count'] ?> abertas</span>
                        </a>

                        <div class="flex items-center gap-1.5">
                            <button onclick="editClient(<?= htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8') ?>)" class="p-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg transition" title="Editar">
                                <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta empresa?');" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="action" value="delete_client">
                                <input type="hidden" name="client_id" value="<?= (int)$client['id'] ?>">
                                <button type="submit" class="p-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg transition" title="Excluir">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Modal Novo / Editar Cliente -->
<div id="clientModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg shadow-2xl p-6 relative animate-fade-in my-8">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-5">
            <h3 id="clientModalTitle" class="text-lg font-bold text-white">Cadastrar Novo Cliente</h3>
            <button type="button" onclick="closeClientModal()" class="text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-800 transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_client">
            <input type="hidden" id="modal_client_id" name="client_id" value="">

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Nome da Empresa / Cliente *</label>
                <input type="text" id="modal_company_name" name="company_name" required placeholder="Ex: Studio Alpha &bull; Advocacia" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 text-sm focus:outline-none focus:border-indigo-500 transition">
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

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <button type="button" onclick="closeClientModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs rounded-xl transition">
                    Cancelar
                </button>
                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl transition shadow-lg shadow-indigo-600/25 flex items-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    Salvar Cliente
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openClientModal() {
    document.getElementById('modal_client_id').value = '';
    document.getElementById('modal_company_name').value = '';
    document.getElementById('modal_contact_name').value = '';
    document.getElementById('modal_email').value = '';
    document.getElementById('modal_phone').value = '';
    document.getElementById('modal_status').value = 'active';
    document.getElementById('modal_notes').value = '';
    document.getElementById('clientLinksList').innerHTML = '';
    document.getElementById('clientModalTitle').textContent = 'Cadastrar Novo Cliente';
    document.getElementById('clientModal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}

function editClient(client) {
    document.getElementById('modal_client_id').value = client.id;
    document.getElementById('modal_company_name').value = client.company_name;
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

    document.getElementById('clientModal').classList.remove('hidden');
    if (window.lucide) lucide.createIcons();
}

function closeClientModal() {
    document.getElementById('clientModal').classList.add('hidden');
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

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
</script>

<?php view_footer(); ?>
