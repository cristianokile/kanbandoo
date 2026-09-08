<?php
/**
 * Header compartilhado do KanbanDoo
 */
require_once __DIR__ . '/auth.php';

$user = current_user();
$theme = current_theme();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="pt-BR" class="<?= $theme === 'light' ? 'light' : 'dark' ?>" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <title><?= e($pageTitle) ?> &bull; <?= APP_NAME ?></title>

    <!-- Tema aplicado antes da pintura, para não piscar na troca de página -->
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('kanbandoo_theme');
                var theme = saved || document.documentElement.dataset.theme || 'dark';
                document.documentElement.classList.toggle('light', theme === 'light');
                document.documentElement.classList.toggle('dark', theme !== 'light');
                var glass = localStorage.getItem('kanbandoo_glass');
                if (glass === 'on' || glass === 'off') document.documentElement.dataset.glass = glass;
            } catch (e) { /* modo privado */ }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind Play CDN (ambiente de validação; substituir por build local antes de produção) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        brand: {
                            50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc',
                            400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca',
                            800: '#3730a3', 900: '#312e81', 950: '#1e1b4b'
                        }
                    }
                }
            }
        }
    </script>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    <link rel="stylesheet" href="<?= asset('assets/css/styles.css') ?>">

    <script>window.KD_BASE = <?= json_encode(BASE_PATH) ?>;</script>
</head>
<body class="min-h-screen flex flex-col font-sans">

<a href="#conteudo" class="sr-only">Pular para o conteúdo principal</a>
