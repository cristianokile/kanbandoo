<?php
/**
 * KanbanDoo - Bootstrap da aplicação
 *
 * Carrega a base compartilhada e define como montar URLs. Todo módulo
 * (páginas e APIs) parte daqui, então nenhum arquivo precisa saber em que
 * profundidade de pasta está nem em que subdiretório o app foi publicado.
 */

define('KD_ROOT', dirname(__DIR__));

require_once KD_ROOT . '/includes/auth.php';

/**
 * Prefixo em que a aplicação está publicada.
 * Vazio na raiz do domínio; "/kanbandoo" se estiver numa subpasta.
 */
if (!defined('BASE_PATH')) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    define('BASE_PATH', $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/'));
}

/** URL de uma rota da aplicação: url('/clientes') => /clientes */
function url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    return BASE_PATH . ($path === '/' ? '/' : rtrim($path, '/'));
}

/**
 * URL de um arquivo estático, com versão baseada na data de modificação.
 *
 * Sem isso o navegador serve o CSS/JS do cache e alterações de estilo
 * simplesmente não chegam a quem já abriu o sistema antes.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = KD_ROOT . '/' . $path;
    $version = is_file($file) ? filemtime($file) : null;

    return BASE_PATH . '/' . $path . ($version ? '?v=' . $version : '');
}

/** Caminho atual da requisição, sem prefixo, sem query e sem barra final. */
function current_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $uri = rawurldecode($uri);

    if (BASE_PATH !== '' && str_starts_with($uri, BASE_PATH)) {
        $uri = substr($uri, strlen(BASE_PATH));
    }

    $uri = '/' . trim($uri, '/');
    return $uri === '//' ? '/' : $uri;
}

/**
 * Renderiza o cabeçalho de uma página (head + navbar).
 * $pageTitle é lido por includes/header.php.
 */
function view_header(string $title): void
{
    $pageTitle = $title;
    require KD_ROOT . '/includes/header.php';
    require KD_ROOT . '/includes/navbar.php';
}

/** Renderiza os modais globais e os scripts. */
function view_footer(): void
{
    require KD_ROOT . '/includes/footer.php';
}
