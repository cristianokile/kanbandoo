<?php
/**
 * Roteador do servidor embutido do PHP.
 *
 * Uso: php -S localhost:8000 router.php
 *
 * Entrega arquivos estáticos que existem em disco e manda todo o resto
 * para o ponto de entrada — o mesmo comportamento do .htaccess no Apache.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $path);

if ($path !== '/' && is_file($file)) {
    // Arquivos .php soltos (instalador) continuam sendo executados normalmente.
    if (str_ends_with($file, '.php')) {
        return false;
    }
    return false; // o servidor embutido entrega o estático
}

require __DIR__ . '/index.php';
