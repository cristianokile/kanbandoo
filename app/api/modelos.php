<?php
/**
 * API REST de Modelos de Tarefas do KanbanDoo
 */
require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['error' => 'Não autorizado.'], 401);
}

$pdo = get_pdo();
$user = current_user();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // -------------------------------------------------------------
    // GET: Listagem ou Detalhes de um Modelo
    // -------------------------------------------------------------
    if ($method === 'GET') {
        $templateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($templateId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM task_templates WHERE id = :id");
            $stmt->execute([':id' => $templateId]);
            $template = $stmt->fetch();

            if (!$template) {
                json_response(['error' => 'Modelo não encontrado.'], 404);
            }

            $template['tags_list'] = parse_tags($template['tags'] ?? '[]');
            $checklist = json_decode($template['checklist'] ?? '[]', true);
            $template['checklist_items'] = is_array($checklist) ? $checklist : [];

            json_response(['success' => true, 'template' => $template]);
        }

        // Listar todos os modelos
        $templates = $pdo->query("
            SELECT t.*, u.full_name AS creator_name
            FROM task_templates t
            LEFT JOIN users u ON t.created_by = u.id
            ORDER BY t.title ASC
        ")->fetchAll();

        foreach ($templates as &$tpl) {
            $tpl['tags_list'] = parse_tags($tpl['tags'] ?? '[]');
            $checklist = json_decode($tpl['checklist'] ?? '[]', true);
            $tpl['checklist_items'] = is_array($checklist) ? $checklist : [];
            $tpl['checklist_count'] = count($tpl['checklist_items']);
        }
        unset($tpl);

        json_response(['success' => true, 'templates' => $templates]);
    }

    // -------------------------------------------------------------
    // POST: Criar, Editar ou Excluir Modelo
    // -------------------------------------------------------------
    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);

        if (!is_array($input)) {
            $input = $_POST;
        }

        $action = $input['action'] ?? 'save';

        // 1. Salvar ou Atualizar
        if ($action === 'save') {
            $templateId = !empty($input['id']) ? (int)$input['id'] : 0;
            $title = trim((string)($input['title'] ?? ''));
            $estimatedMinutes = !empty($input['estimated_minutes']) ? max(1, (int)$input['estimated_minutes']) : null;
            $description = trim((string)($input['description'] ?? ''));

            $tags = normalize_tags(is_array($input['tags'] ?? null) ? $input['tags'] : parse_tags($input['tags'] ?? []));
            $tagsJson = json_encode($tags, JSON_UNESCAPED_UNICODE);

            $rawChecklist = $input['checklist'] ?? [];
            if (!is_array($rawChecklist)) {
                $rawChecklist = explode("\n", (string)$rawChecklist);
            }
            $cleanChecklist = [];
            foreach ($rawChecklist as $item) {
                $itemText = is_array($item) ? ($item['title'] ?? '') : (string)$item;
                $itemText = trim($itemText);
                if ($itemText !== '') {
                    $cleanChecklist[] = $itemText;
                }
            }
            $checklistJson = json_encode($cleanChecklist, JSON_UNESCAPED_UNICODE);

            if ($title === '') {
                json_response(['error' => 'Informe o título do modelo.', 'field' => 'title'], 400);
            }
            if (mb_strlen($title) > 200) {
                json_response(['error' => 'O título deve ter no máximo 200 caracteres.', 'field' => 'title'], 400);
            }

            if ($templateId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE task_templates
                    SET title = :title, estimated_minutes = :est, tags = :tags,
                        description = :desc, checklist = :chk, updated_at = :now
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':title' => $title,
                    ':est' => $estimatedMinutes,
                    ':tags' => $tagsJson,
                    ':desc' => $description,
                    ':chk' => $checklistJson,
                    ':now' => now(),
                    ':id' => $templateId,
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO task_templates (title, estimated_minutes, tags, description, checklist, created_by, created_at, updated_at)
                    VALUES (:title, :est, :tags, :desc, :chk, :uid, :now, :now2)
                ");
                $stmt->execute([
                    ':title' => $title,
                    ':est' => $estimatedMinutes,
                    ':tags' => $tagsJson,
                    ':desc' => $description,
                    ':chk' => $checklistJson,
                    ':uid' => $user['id'],
                    ':now' => now(),
                    ':now2' => now(),
                ]);
                $templateId = (int)$pdo->lastInsertId();
            }

            json_response([
                'success' => true,
                'template_id' => $templateId,
                'message' => 'Modelo salvo com sucesso!'
            ]);
        }

        // 2. Excluir
        if ($action === 'delete') {
            $templateId = (int)($input['id'] ?? 0);
            if ($templateId <= 0) {
                json_response(['error' => 'Modelo inválido.'], 400);
            }

            $stmt = $pdo->prepare("DELETE FROM task_templates WHERE id = :id");
            $stmt->execute([':id' => $templateId]);

            json_response(['success' => true, 'message' => 'Modelo excluído com sucesso.']);
        }

        json_response(['error' => 'Ação não reconhecida.'], 400);
    }

    json_response(['error' => 'Método não permitido.'], 405);
} catch (Exception $e) {
    json_response(['error' => 'Erro interno: ' . $e->getMessage()], 500);
}
