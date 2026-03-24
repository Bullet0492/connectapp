<?php
$paginatitel = 'Wachtwoordkluis';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$db   = db();
$zoek = trim($_GET['zoek'] ?? '');
$filter_cat = trim($_GET['cat'] ?? '');
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$per_pagina = 20;

$where  = [];
$params = [];
if ($zoek !== '') {
    $where[]  = "(ig.label LIKE ? OR ig.gebruikersnaam LIKE ? OR ig.url LIKE ? OR k.naam LIKE ?)";
    for ($i = 0; $i < 4; $i++) $params[] = "%$zoek%";
}
if ($filter_cat !== '') {
    $where[]  = "ig.categorie = ?";
    $params[] = $filter_cat;
}
$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$telStmt = $db->prepare("SELECT COUNT(*) FROM inloggegevens ig LEFT JOIN klanten k ON k.id = ig.klant_id $whereStr");
$telStmt->execute($params);
$totaal = (int)$telStmt->fetchColumn();
$totaal_paginas = max(1, (int)ceil($totaal / $per_pagina));
$pagina = min($pagina, $totaal_paginas);
$offset = ($pagina - 1) * $per_pagina;

$stmt = $db->prepare("SELECT ig.*, k.naam AS klant_naam FROM inloggegevens ig LEFT JOIN klanten k ON k.id = ig.klant_id $whereStr ORDER BY k.naam, ig.categorie, ig.label LIMIT $per_pagina OFFSET $offset");
$stmt->execute($params);
$items = $stmt->fetchAll();

function ig_pagina_url(int $p): string {
    global $zoek, $filter_cat;
    $params = array_filter(['zoek' => $zoek, 'cat' => $filter_cat, 'pagina' => $p > 1 ? $p : ''], fn($v) => $v !== '');
    return 'index.php?' . http_build_query($params);
}
$cat_labels = ['netwerk' => 'Netwerk', 'server' => 'Server', 'cloud' => 'Cloud', 'portaal' => 'Portaal', 'overig' => 'Overig'];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Wachtwoordkluis</h4>
    <p class="text-muted mb-0"><?= $totaal ?> inloggegevens opgeslagen</p>
</div>

<!-- Zoek + filter -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <form method="get" id="zoekform" class="flex-grow-1">
        <input type="hidden" name="cat" value="<?= h($filter_cat) ?>">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0">
                <i class="ri-search-line" style="font-size:16px;color:#6c757d;"></i>
            </span>
            <input type="search" name="zoek" id="zoek_input" class="form-control border-start-0 ps-0"
                   placeholder="Zoek op label, gebruikersnaam, URL, klant..."
                   value="<?= h($zoek) ?>">
        </div>
    </form>
    <div class="d-flex gap-1 flex-wrap">
        <a href="index.php" class="btn btn-sm <?= $filter_cat === '' ? 'btn-primary' : 'btn-outline-secondary' ?>">Alle</a>
        <?php foreach ($cat_labels as $cat => $lbl): ?>
        <a href="index.php?cat=<?= $cat ?><?= $zoek ? '&zoek=' . urlencode($zoek) : '' ?>"
           class="btn btn-sm <?= $filter_cat === $cat ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= $lbl ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (empty($items)): ?>
    <div class="text-center text-muted py-5">Geen inloggegevens gevonden.</div>
<?php else: ?>
<div class="bg-white rounded-3 border">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Label</th>
                    <th>Categorie</th>
                    <th>Klant</th>
                    <th>Gebruikersnaam</th>
                    <th>Wachtwoord</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $ig): ?>
                <tr>
                    <td class="fw-medium"><?= h($ig['label']) ?></td>
                    <td><span class="badge badge-<?= $ig['categorie'] ?> rounded-pill" style="font-size:10px;"><?= $cat_labels[$ig['categorie']] ?? $ig['categorie'] ?></span></td>
                    <td>
                        <a href="<?= $base ?>/klanten/detail.php?id=<?= $ig['klant_id'] ?>&tab=wachtwoorden" class="text-decoration-none small">
                            <?= h($ig['klant_naam'] ?? '—') ?>
                        </a>
                    </td>
                    <td class="small text-muted"><?= h($ig['gebruikersnaam'] ?: '—') ?></td>
                    <td>
                        <code class="ww-tekst small" data-id="<?= $ig['id'] ?>">••••••••</code>
                        <button class="btn btn-sm p-0 ms-1 text-muted" onclick="toggleWachtwoord(<?= $ig['id'] ?>, this)" title="Tonen">
                            <i class="ri-eye-line" style="font-size:13px;"></i>
                        </button>
                        <button class="btn btn-sm p-0 ms-1 text-muted" onclick="kopieerWachtwoord(<?= $ig['id'] ?>, this)" title="Kopiëren">
                            <i class="ri-file-copy-line" style="font-size:13px;"></i>
                        </button>
                    </td>
                    <td class="text-end">
                        <a href="<?= $base ?>/klanten/detail.php?id=<?= $ig['klant_id'] ?>&tab=wachtwoorden" class="btn btn-sm btn-outline-secondary">
                            <i class="ri-eye-line"></i>
                        </a>
                    </td>
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
            <a class="page-link" href="<?= h(ig_pagina_url($pagina - 1)) ?>">Vorige</a>
        </li>
        <?php foreach (pagina_reeks($pagina, $totaal_paginas) as $p): ?>
            <?php if ($p === '...'): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php else: ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="<?= h(ig_pagina_url($p)) ?>"><?= $p ?></a>
            </li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li class="page-item <?= $pagina >= $totaal_paginas ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= h(ig_pagina_url($pagina + 1)) ?>">Volgende</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<script>
var wwCache = {};
var csrfToken = '<?= h(csrf_token()) ?>';
var baseUrl   = '<?= $base ?>';

function toggleWachtwoord(id, btn) {
    var span = document.querySelector('.ww-tekst[data-id="' + id + '"]');
    if (!span) return;
    if (span.dataset.zichtbaar === '1') {
        span.textContent = '••••••••';
        span.dataset.zichtbaar = '0';
        btn.querySelector('i').className = 'ri-eye-line';
        return;
    }
    if (wwCache[id]) {
        span.textContent = wwCache[id];
        span.dataset.zichtbaar = '1';
        btn.querySelector('i').className = 'ri-eye-off-line';
        return;
    }
    fetch(baseUrl + '/inloggegevens/toon.php?id=' + id + '&csrf=' + csrfToken)
        .then(r => r.json())
        .then(data => {
            if (data.ww !== undefined) {
                wwCache[id] = data.ww;
                span.textContent = data.ww || '(leeg)';
                span.dataset.zichtbaar = '1';
                btn.querySelector('i').className = 'ri-eye-off-line';
            }
        });
}

function kopieerWachtwoord(id, btn) {
    var doCopy = (ww) => {
        navigator.clipboard.writeText(ww);
        var i = btn.querySelector('i');
        i.className = 'ri-check-line';
        setTimeout(() => i.className = 'ri-file-copy-line', 1500);
    };
    if (wwCache[id] !== undefined) { doCopy(wwCache[id]); return; }
    fetch(baseUrl + '/inloggegevens/toon.php?id=' + id + '&csrf=' + csrfToken)
        .then(r => r.json())
        .then(data => { if (data.ww !== undefined) { wwCache[id] = data.ww; doCopy(data.ww); } });
}

(function() {
    var inp = document.getElementById('zoek_input');
    var form = document.getElementById('zoekform');
    if (!inp || !form) return;
    var timer;
    inp.addEventListener('input', function() {
        clearTimeout(timer);
        timer = setTimeout(function() { form.submit(); }, 400);
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
