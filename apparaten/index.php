<?php
$paginatitel = 'Apparaten';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$db   = db();
$zoek = trim($_GET['zoek'] ?? '');
$filter_klant = (int)($_GET['klant_id'] ?? 0);
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$per_pagina = 20;

// WHERE opbouwen
$where = [];
$params = [];
if ($zoek !== '') {
    $where[] = "(a.qr_code LIKE ? OR a.merk LIKE ? OR a.model LIKE ? OR a.serienummer LIKE ? OR a.locatie LIKE ? OR k.naam LIKE ?)";
    for ($i = 0; $i < 6; $i++) $params[] = "%$zoek%";
}
if ($filter_klant) {
    $where[] = "a.klant_id = ?";
    $params[] = $filter_klant;
}
$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$telStmt = $db->prepare("SELECT COUNT(*) FROM apparaten a LEFT JOIN klanten k ON k.id = a.klant_id $whereStr");
$telStmt->execute($params);
$totaal = (int)$telStmt->fetchColumn();
$totaal_paginas = max(1, (int)ceil($totaal / $per_pagina));
$pagina = min($pagina, $totaal_paginas);
$offset = ($pagina - 1) * $per_pagina;

$stmt = $db->prepare("SELECT a.*, k.naam AS klant_naam FROM apparaten a LEFT JOIN klanten k ON k.id = a.klant_id $whereStr ORDER BY a.id DESC LIMIT $per_pagina OFFSET $offset");
$stmt->execute($params);
$apparaten = $stmt->fetchAll();

function app_pagina_url(int $p): string {
    global $zoek, $filter_klant;
    $params = array_filter(['zoek' => $zoek, 'klant_id' => $filter_klant ?: '', 'pagina' => $p > 1 ? $p : ''], fn($v) => $v !== '');
    return 'index.php?' . http_build_query($params);
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Apparaten</h4>
    <p class="text-muted mb-0"><?= $totaal ?> apparaten geregistreerd</p>
</div>

<!-- Zoekbalk -->
<div class="mb-4">
    <form method="get" id="zoekform">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0">
                <i class="ri-search-line" style="font-size:16px;color:#6c757d;"></i>
            </span>
            <input type="search" name="zoek" id="zoek_input" class="form-control border-start-0 border-end-0 ps-0"
                   placeholder="Zoek op QR-code, merk, model, serienummer, klant..."
                   value="<?= h($zoek) ?>">
            <?php if ($zoek !== '' || $filter_klant): ?>
            <a href="index.php" class="input-group-text bg-white text-muted" title="Wissen">
                <i class="ri-close-line" style="font-size:16px;"></i>
            </a>
            <?php else: ?>
            <button type="submit" class="input-group-text bg-white text-muted border-start-0">
                <i class="ri-arrow-right-line" style="font-size:16px;"></i>
            </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if (empty($apparaten)): ?>
    <div class="text-center text-muted py-5">Geen apparaten gevonden.</div>
<?php else: ?>
<div class="bg-white rounded-3 border">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>QR-code</th>
                    <th>Type</th>
                    <th>Merk / Model</th>
                    <th>Klant</th>
                    <th>Locatie</th>
                    <th>Status</th>
                    <th class="text-end">Acties</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apparaten as $a): ?>
                <tr>
                    <td><code><?= h($a['qr_code']) ?></code></td>
                    <td><?= h(ucfirst($a['type'])) ?></td>
                    <td><?= h(trim($a['merk'] . ' ' . $a['model'])) ?></td>
                    <td>
                        <a href="<?= $base ?>/klanten/detail.php?id=<?= $a['klant_id'] ?>&tab=apparaten" class="text-decoration-none">
                            <?= h($a['klant_naam'] ?? '—') ?>
                        </a>
                    </td>
                    <td class="text-muted small"><?= h($a['locatie'] ?: '—') ?></td>
                    <td><span class="badge badge-<?= $a['status'] ?> rounded-pill" style="font-size:10px;"><?= h($a['status']) ?></span></td>
                    <td class="text-end">
                        <a href="<?= $base ?>/qr/labels.php?apparaat_id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank" title="QR-label">
                            <i class="ri-qr-code-line"></i>
                        </a>
                        <a href="<?= $base ?>/klanten/detail.php?id=<?= $a['klant_id'] ?>&tab=apparaten" class="btn btn-sm btn-outline-secondary">
                            <i class="ri-eye-line"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Paginering -->
<?php if ($totaal_paginas > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center flex-wrap">
        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(app_pagina_url($pagina - 1)) ?>">Vorige</a>
        </li>
        <?php foreach (pagina_reeks($pagina, $totaal_paginas) as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php else: ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="<?= h(app_pagina_url($p)) ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $pagina >= $totaal_paginas ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(app_pagina_url($pagina + 1)) ?>">Volgende</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<script>
(function() {
    var inp = document.getElementById('zoek_input');
    var form = document.getElementById('zoekform');
    if (!inp || !form) return;
    if (inp.value) { inp.focus(); inp.setSelectionRange(inp.value.length, inp.value.length); }
    var timer;
    inp.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() { form.submit(); }, 400);
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
