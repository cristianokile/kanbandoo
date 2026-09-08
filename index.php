<?php
/**
 * KanbanDoo - Ponto de entrada único
 *
 * Toda requisição passa por aqui, ganha o contexto da aplicação e é entregue
 * ao módulo correspondente pelo roteador.
 */
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/Router.php';

$router = new Router();
require __DIR__ . '/routes.php';

$path = current_path();

// Quem não está autenticado vai para o login, exceto na própria tela de login.
if (!is_logged_in() && !in_array($path, ['/entrar', '/login.php'], true) && !str_starts_with($path, '/api/')) {
    redirect(url('/entrar'));
}

$router->dispatch($path);
