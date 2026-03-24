<?php
$paginatitel = 'Logboek';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_admin();

$db = db();
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$per_pagina = 30;

$telStmt = $db->query('SELECT COUNT(*) FROM logboek');
$totaal = (int)$telStmt->fetchColumn();
$totaal_paginas = max(1, (int)ceil($totaal / $per_pagina));
$pagina = min($pagina, $totaal_paginas);
$offset = ($pagina - 1) * $per_pagina;

$items = $db->query("SELECT * FROM logboek ORDER BY aangemaakt_op DESC LIMIT $per_pagina OFFSET $offset")->fetchAll();

function log_pagina_url(int $p): string {
    return 'logboek.php?' . ($p > 1 ? 'pagina=' . $p : '');
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Logboek</h4>
    <p class="text-muted mb-0"><?= $totaal ?> activiteiten geregistreerd</p>
</div>

<div class="bg-white rounded-3 border">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Datum/tijd</th>
                    <th>Gebruiker</th>
                    <th>Actie</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $log): ?>
            <tr>
                <td class="text-muted small text-nowrap"><?= h(date('d-m-Y H:i', strtotime($log['aangemaakt_op']))) ?></td>
                <td><?= h($log['user_naam'] ?? '—') ?></td>
                <td><code class="small"><?= h($log['actie'] ?? '') ?></code></td>
                <td class="text-muted small"><?= h($log['details'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totaal_paginas > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center flex-wrap">
        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(log_pagina_url($pagina - 1)) ?>">Vorige</a>
        </li>
        <?php foreach (pagina_reeks($pagina, $totaal_paginas) as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php else: ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="<?= h(log_pagina_url($p)) ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $pagina >= $totaal_paginas ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(log_pagina_url($pagina + 1)) ?>">Volgende</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
