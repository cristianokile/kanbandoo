<?php
$cookieFile = __DIR__ . '/cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// 1. Obter CSRF no login
$ch = curl_init('http://localhost:8000/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
$html = curl_exec($ch);

preg_match('/name="csrf_token" value="([^"]+)"/', $html, $matches);
$csrf = $matches[1] ?? '';

// 2. Fazer login
$ch = curl_init('http://localhost:8000/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'admin',
    'password' => 'admin123',
    'csrf_token' => $csrf
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($ch);

// 3. Testar API
$ch = curl_init('http://localhost:8000/api/tasks.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$apiRes = curl_exec($ch);
$apiJson = json_decode($apiRes, true);

echo "API SUCCESS: " . ($apiJson['success'] ? 'YES' : 'NO') . "\n";
echo "STAGES COUNT: " . count($apiJson['stages'] ?? []) . "\n";
foreach ($apiJson['stages'] ?? [] as $stg) {
    echo " - [{$stg['id']}] {$stg['name']} ({$stg['slug']})\n";
}
echo "TASKS COUNT: " . count($apiJson['tasks'] ?? []) . "\n";
foreach ($apiJson['tasks'] ?? [] as $tsk) {
    echo " - Task #{$tsk['id']}: {$tsk['title']} | Stage ID: {$tsk['stage_id']} | Source: {$tsk['source']} | Group: {$tsk['group_name']}\n";
}

// 4. Testar Clientes, Usuários e Perfil
$pages = ['clientes.php', 'usuarios.php', 'perfil.php', 'kanban.php'];
foreach ($pages as $p) {
    $ch = curl_init("http://localhost:8000/{$p}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "PAGE {$p}: HTTP {$code}\n";
}
