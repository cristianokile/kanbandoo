<?php
/**
 * KanbanDoo - Mapa de Rotas
 *
 * Uma linha por tela. Cada módulo vive em app/pages ou app/api e não conhece
 * a URL pela qual é acessado, então endereços podem mudar sem tocar no módulo.
 *
 * add(caminho, arquivo em app/, opções)
 *   auth    => exige login (padrão: true)
 *   admin   => exige perfil de administrador
 *   methods => métodos HTTP aceitos
 */

/** @var Router $router */

// -------- Páginas --------
$router->add('/',          'pages/tarefas.php');
$router->add('/tarefas',   'pages/tarefas.php');
$router->add('/tarefas/:id', 'pages/tarefas.php');   // abre a tarefa direto pelo link
$router->add('/clientes',  'pages/clientes.php');
$router->add('/equipe',    'pages/equipe.php', ['admin' => true]);
$router->add('/perfil',    'pages/perfil.php');

// -------- Conta --------
$router->add('/entrar',    'pages/login.php',  ['auth' => false]);
$router->add('/sair',      'pages/logout.php');

// -------- API --------
$router->add('/api/tarefas',      'api/tarefas.php');
$router->add('/api/preferencias', 'api/preferencias.php', ['methods' => ['POST']]);

// -------- Endereços antigos (mantêm links e favoritos funcionando) --------
$router->legacy('/index.php',    '/tarefas');
$router->legacy('/kanban.php',   '/tarefas');
$router->legacy('/clientes.php', '/clientes');
$router->legacy('/usuarios.php', '/equipe');
$router->legacy('/perfil.php',   '/perfil');
$router->legacy('/login.php',    '/entrar');
$router->legacy('/logout.php',   '/sair');
