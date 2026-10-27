<?php
/**
 * Footer compartilhado do KanbanDoo.
 * Carrega os dados dos selects e inclui os modais globais (em includes/partials).
 */
$pdo = get_pdo();

$activeClients = $pdo->query("SELECT id, company_name, category FROM clients WHERE status = 'active' ORDER BY company_name ASC")->fetchAll();
$teamUsers     = $pdo->query("SELECT id, full_name, role FROM users ORDER BY full_name ASC")->fetchAll();
$kanbanStages  = $pdo->query("SELECT id, name FROM task_stages ORDER BY sort_order ASC")->fetchAll();
$taskTemplates = $pdo->query("SELECT id, title, estimated_minutes, tags, description, checklist FROM task_templates ORDER BY title ASC")->fetchAll();
?>

<?php require __DIR__ . '/partials/modal-task-form.php'; ?>
<?php require __DIR__ . '/partials/modal-task-detail.php'; ?>
<?php require __DIR__ . '/partials/modal-work-hours.php'; ?>
<?php require __DIR__ . '/partials/modal-task-import.php'; ?>
<?php require __DIR__ . '/partials/modal-adjust-time.php'; ?>
<?php require __DIR__ . '/partials/modal-task-trash.php'; ?>

<!-- Avisos: o empilhamento é criado sob demanda por kd-core.js -->
<div id="kdToastStack" class="kd-toast-stack" role="status" aria-live="polite"></div>

<?php $curUser = current_user(); ?>
<script>
    window.KD_CURRENT_USER_ID = <?= json_encode($curUser ? (int)$curUser['id'] : null) ?>;
</script>
<script src="<?= asset('assets/js/kd-core.js') ?>"></script>
<script src="<?= asset('assets/js/kd-board.js') ?>"></script>
<script src="<?= asset('assets/js/kd-task.js') ?>"></script>
<script src="<?= asset('assets/js/kd-import.js') ?>"></script>
<script>
    if (window.lucide) lucide.createIcons();
</script>
</body>
</html>

