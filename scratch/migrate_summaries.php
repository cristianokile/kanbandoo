<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
CREATE TABLE IF NOT EXISTS task_summaries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    summary_text TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
");

// Inserir amostras de links para os clientes de teste se estiverem vazios
$clients = $pdo->query("SELECT id, company_name, links_json FROM clients")->fetchAll(PDO::FETCH_ASSOC);
foreach ($clients as $c) {
    $existing = json_decode($c['links_json'] ?? '[]', true);
    if (empty($existing)) {
        $sampleLinks = [
            ['id' => 1, 'title' => 'Site Oficial', 'url' => 'https://example.com', 'type' => 'site', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'title' => 'Gerenciador de Negócios (BM)', 'url' => 'https://business.facebook.com', 'type' => 'facebook', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 3, 'title' => 'Painel Google Ads (MCC)', 'url' => 'https://ads.google.com', 'type' => 'google', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 4, 'title' => 'Instagram Oficial', 'url' => 'https://instagram.com', 'type' => 'instagram', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 5, 'title' => 'Pasta de Arquivos & Criativos (Drive)', 'url' => 'https://drive.google.com', 'type' => 'drive', 'created_at' => date('Y-m-d H:i:s')],
        ];
        $upd = $pdo->prepare("UPDATE clients SET links_json = :lj WHERE id = :id");
        $upd->execute([':lj' => json_encode($sampleLinks), ':id' => $c['id']]);
    }
}

// Inserir amostra de tags e estimativa para as tarefas
$pdo->exec("
    UPDATE tasks SET 
        estimated_minutes = 40,
        tags = '[\"relatorio\", \"google-ads\", \"mensal\"]',
        description = 'Compila resultados mensais de Google Ads com insights e próximos passos estratégicos para alinhamento com a diretoria.'
    WHERE id = 1 OR id = 5
");

// Inserir amostra de resumo da tarefa para histórico
$pdo->exec("
    INSERT OR IGNORE INTO task_summaries (id, task_id, user_id, summary_text, created_at)
    VALUES (1, 1, 1, 'Campanha otimizada: CPA reduzido em 18% e novos criativos de Páscoa ativados no conjunto principal.', datetime('now', '-2 days'))
");

echo "MIGRATION_SUMMARIES_OK\n";
