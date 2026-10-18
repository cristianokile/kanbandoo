<?php
/**
 * KanbanDoo - API de Gestão de Notas & Grupos (Compatível com SQLite & MySQL)
 */
require_once dirname(__DIR__) . '/bootstrap.php';

require_login();
header('Content-Type: application/json; charset=UTF-8');

$pdo = get_pdo();
$user = current_user();
$method = $_SERVER['REQUEST_METHOD'];

// ==============================================================
// GET - Listagem com Filtros
// ==============================================================
if ($method === 'GET') {
    $clientId = isset($_GET['client_id']) && $_GET['client_id'] !== '' ? $_GET['client_id'] : null;
    $groupId = isset($_GET['group_id']) && $_GET['group_id'] !== '' ? $_GET['group_id'] : null;
    $search = trim($_GET['search'] ?? $_GET['q'] ?? '');
    $authorId = isset($_GET['author_id']) && $_GET['author_id'] !== '' ? (int)$_GET['author_id'] : null;
    $period = trim($_GET['period'] ?? 'all');

    // Query principal
    $sql = "
        SELECT n.id, n.title, n.content, n.group_id, n.client_id, n.created_by, n.is_pinned, n.color,
               n.created_at, n.updated_at,
               g.name AS group_name, g.color AS group_color, g.client_id AS group_client_id,
               COALESCE(c_direct.company_name, c_group.company_name) AS client_company,
               COALESCE(n.client_id, g.client_id) AS resolved_client_id,
               u.full_name AS author_name, u.username AS author_username
        FROM notes n
        LEFT JOIN note_groups g ON g.id = n.group_id
        LEFT JOIN clients c_direct ON c_direct.id = n.client_id
        LEFT JOIN clients c_group ON c_group.id = g.client_id
        LEFT JOIN users u ON u.id = n.created_by
        WHERE n.deleted_at IS NULL
    ";
    $params = [];

    // Filtro por Cliente (direto ou via grupo)
    if ($clientId !== null) {
        if ($clientId === 'none') {
            $sql .= " AND n.client_id IS NULL AND (g.client_id IS NULL OR g.id IS NULL)";
        } else {
            $sql .= " AND (n.client_id = :cid OR (n.client_id IS NULL AND g.client_id = :cid_grp))";
            $params[':cid'] = (int)$clientId;
            $params[':cid_grp'] = (int)$clientId;
        }
    }

    // Filtro por Grupo
    if ($groupId !== null) {
        if ($groupId === 'none') {
            $sql .= " AND n.group_id IS NULL";
        } else {
            $sql .= " AND n.group_id = :gid";
            $params[':gid'] = (int)$groupId;
        }
    }

    // Filtro por Autor
    if ($authorId !== null && $authorId > 0) {
        $sql .= " AND n.created_by = :aid";
        $params[':aid'] = $authorId;
    }

    // Filtro de Busca Textual (título ou conteúdo)
    if ($search !== '') {
        $sql .= " AND (n.title LIKE :search OR n.content LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    // Filtro de Período (100% agnóstico a banco)
    if ($period === 'today') {
        $sql .= " AND n.created_at >= :p_today";
        $params[':p_today'] = date('Y-m-d 00:00:00');
    } elseif ($period === 'week') {
        $sql .= " AND n.created_at >= :p_week";
        $params[':p_week'] = date('Y-m-d 00:00:00', strtotime('-7 days'));
    } elseif ($period === 'month') {
        $sql .= " AND n.created_at >= :p_month";
        $params[':p_month'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
    }

    // Ordenação: fixadas primeiro, depois data de criação decrescente
    $sql .= " ORDER BY n.is_pinned DESC, n.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Carregar Grupos
    $groupsStmt = $pdo->query("
        SELECT g.*, c.company_name AS client_name,
               (SELECT COUNT(*) FROM notes n WHERE n.group_id = g.id AND n.deleted_at IS NULL) AS notes_count
        FROM note_groups g
        LEFT JOIN clients c ON c.id = g.client_id
        ORDER BY g.name ASC
    ");
    $groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Carregar Clientes para o select
    $clients = $pdo->query("SELECT id, company_name FROM clients ORDER BY company_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Carregar Autores
    $authors = $pdo->query("SELECT id, full_name, username FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'notes' => $notes,
        'groups' => $groups,
        'clients' => $clients,
        'authors' => $authors
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ==============================================================
// POST - Ações (Salvar Nota, Excluir, Fixar, Salvar Grupo, etc.)
// ==============================================================
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$action = $data['action'] ?? '';
$now = date('Y-m-d H:i:s');

try {
    // 1. Salvar ou Criar Nota
    if ($action === 'save_note') {
        $id = !empty($data['id']) ? (int)$data['id'] : 0;
        $title = trim((string)($data['title'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));
        $groupId = !empty($data['group_id']) ? (int)$data['group_id'] : null;
        $clientId = !empty($data['client_id']) ? (int)$data['client_id'] : null;
        $isPinned = !empty($data['is_pinned']) ? 1 : 0;

        if ($title === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'O título da nota é obrigatório.']);
            exit;
        }

        // Se o cliente não foi preenchido na nota, mas foi selecionado um grupo que tem cliente, herda o cliente do grupo
        if ($clientId === null && $groupId !== null) {
            $grpClientId = $pdo->prepare("SELECT client_id FROM note_groups WHERE id = :gid");
            $grpClientId->execute([':gid' => $groupId]);
            $inherited = $grpClientId->fetchColumn();
            if ($inherited) {
                $clientId = (int)$inherited;
            }
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE notes 
                SET title = :title, content = :content, group_id = :group_id, client_id = :client_id, is_pinned = :is_pinned, updated_at = :upd
                WHERE id = :id AND deleted_at IS NULL
            ");
            $stmt->execute([
                ':title' => $title,
                ':content' => $content,
                ':group_id' => $groupId,
                ':client_id' => $clientId,
                ':is_pinned' => $isPinned,
                ':upd' => $now,
                ':id' => $id
            ]);
            $noteId = $id;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO notes (title, content, group_id, client_id, created_by, is_pinned, created_at, updated_at)
                VALUES (:title, :content, :group_id, :client_id, :created_by, :is_pinned, :created_at, :updated_at)
            ");
            $stmt->execute([
                ':title' => $title,
                ':content' => $content,
                ':group_id' => $groupId,
                ':client_id' => $clientId,
                ':created_by' => (int)$user['id'],
                ':is_pinned' => $isPinned,
                ':created_at' => $now,
                ':updated_at' => $now
            ]);
            $noteId = (int)$pdo->lastInsertId();
        }

        echo json_encode(['ok' => true, 'note_id' => $noteId]);
        exit;
    }

    // 2. Alternar Pin (Fixar / Desfixar)
    if ($action === 'toggle_pin') {
        $id = (int)($data['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE notes SET is_pinned = CASE WHEN is_pinned = 1 THEN 0 ELSE 1 END WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // 3. Excluir Nota
    if ($action === 'delete_note') {
        $id = (int)($data['id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE notes SET deleted_at = :now WHERE id = :id");
        $stmt->execute([':now' => $now, ':id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // 4. Salvar / Criar Grupo
    if ($action === 'save_group') {
        $id = !empty($data['id']) ? (int)$data['id'] : 0;
        $name = trim((string)($data['name'] ?? ''));
        $clientId = !empty($data['client_id']) ? (int)$data['client_id'] : null;
        $color = trim((string)($data['color'] ?? '#6366f1'));

        if ($name === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'O nome do grupo é obrigatório.']);
            exit;
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE note_groups 
                SET name = :name, client_id = :client_id, color = :color, updated_at = :upd
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $name,
                ':client_id' => $clientId,
                ':color' => $color,
                ':upd' => $now,
                ':id' => $id
            ]);
            $groupId = $id;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO note_groups (name, client_id, color, created_by, created_at, updated_at)
                VALUES (:name, :client_id, :color, :created_by, :created_at, :updated_at)
            ");
            $stmt->execute([
                ':name' => $name,
                ':client_id' => $clientId,
                ':color' => $color,
                ':created_by' => (int)$user['id'],
                ':created_at' => $now,
                ':updated_at' => $now
            ]);
            $groupId = (int)$pdo->lastInsertId();
        }

        echo json_encode(['ok' => true, 'group_id' => $groupId]);
        exit;
    }

    // 5. Excluir Grupo
    if ($action === 'delete_group') {
        $id = (int)($data['id'] ?? 0);
        $pdo->prepare("UPDATE notes SET group_id = NULL WHERE group_id = :id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM note_groups WHERE id = :id")->execute([':id' => $id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ação inválida.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
