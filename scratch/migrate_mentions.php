<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../storage/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$cols = array_column($pdo->query('PRAGMA table_info(tasks)')->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('source', $cols)) {
    $pdo->exec("ALTER TABLE tasks ADD COLUMN source TEXT DEFAULT 'manual'");
}
if (!in_array('group_name', $cols)) {
    $pdo->exec("ALTER TABLE tasks ADD COLUMN group_name TEXT DEFAULT NULL");
}
if (!in_array('mentioned_by', $cols)) {
    $pdo->exec("ALTER TABLE tasks ADD COLUMN mentioned_by TEXT DEFAULT NULL");
}
if (!in_array('whatsapp_url', $cols)) {
    $pdo->exec("ALTER TABLE tasks ADD COLUMN whatsapp_url TEXT DEFAULT NULL");
}

$pdo->exec("
    UPDATE tasks 
    SET source = 'whatsapp_mention', 
        group_name = 'DekMídia & Clientes', 
        mentioned_by = 'Larissa Mendes', 
        whatsapp_url = 'https://web.whatsapp.com', 
        title = 'Revisar orçamento e criativo final',
        description = 'Larissa Mendes te mencionou no grupo DekMídia & Clientes:\n\n\"@Cristiano por favor, confere o orçamento e o criativo final da campanha de Páscoa antes de enviarmos para o cliente.\"' 
    WHERE id = 5
");

echo "MIGRATION_OK\n";
