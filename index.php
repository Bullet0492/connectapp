<?php
$paginatitel = 'Dashboard';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
sessie_start();
vereist_login();

$db = db();

$stats = [
    'klanten'      => (int)$db->query('SELECT COUNT(*) FROM klanten')->fetchColumn(),
    'apparaten'    => (int)$db->query('SELECT COUNT(*) FROM apparaten')->fetchColumn(),
    'inloggegevens'=> (int)$db->query('SELECT COUNT(*) FROM inloggegevens')->fetchColumn(),
    'contracten'   => (int)$db->query('SELECT COUNT(*) FROM contracten')->fetchColumn(),
];

// Verlopen of bijna-verlopen contracten (30 dagen)
$verlopendeContracten = $db->query(
    "SELECT c.*, k.naam AS klant_naam FROM contracten c
     LEFT JOIN klanten k ON k.id = c.klant_id
     WHERE c.eind_datum IS NOT NULL AND c.eind_datum <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY c.eind_datum ASC LIMIT 10"
)->fetchAll();

// Recente klanten (5)
$recenteKlanten = $db->query(
    "SELECT * FROM klanten ORDER BY id DESC LIMIT 5"
)->fetchAll();

// Recente servicehistorie (5)
$recenteService = $db->query(
    "SELECT s.*, k.naam AS klant_naam, k.id AS klant_id
     FROM service_historie s
     LEFT JOIN klanten k ON k.id = s.klant_id
     ORDER BY s.datum DESC, s.id DESC LIMIT 5"
)->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
    <h4 class="fw-bold mb-1">Dashboard</h4>
    <p class="text-muted mb-0">Welkom, <?= h(huidig_gebruiker()['naam']) ?></p>
</div>

<!-- Statistieken -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= $base ?>/klanten/index.php" class="text-decoration-none">
            <div class="bg-white rounded-3 border p-3 text-center h-100">
                <div class="mb-2"><i class="ri-building-2-line" style="font-size:28px;color:#185E9B;"></i></div>
                <div class="fw-bold fs-3"><?= $stats['klanten'] ?></div>
                <div class="text-muted small">Klanten</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <div class="bg-white rounded-3 border p-3 text-center h-100">
            <div class="mb-2"><i class="ri-computer-line" style="font-size:28px;color:#185E9B;"></i></div>
            <div class="fw-bold fs-3"><?= $stats['apparaten'] ?></div>
            <div class="text-muted small">Apparaten</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="bg-white rounded-3 border p-3 text-center h-100">
            <div class="mb-2"><i class="ri-key-2-line" style="font-size:28px;color:#185E9B;"></i></div>
            <div class="fw-bold fs-3"><?= $stats['inloggegevens'] ?></div>
            <div class="text-muted small">Wachtwoorden</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= $base ?>/klanten/index.php" class="text-decoration-none">
            <div class="bg-white rounded-3 border p-3 text-center h-100">
                <div class="mb-2"><i class="ri-file-shield-2-line" style="font-size:28px;color:#185E9B;"></i></div>
                <div class="fw-bold fs-3"><?= $stats['contracten'] ?></div>
                <div class="text-muted small">Contracten</div>
            </div>
        </a>
    </div>
</div>

<!-- Waarschuwingen verlopen/bijna-verlopen contracten -->
<?php if (!empty($verlopendeContracten)): ?>
<div class="mb-4">
    <?php foreach ($verlopendeContracten as $c):
        $dagen = (int)((strtotime($c['eind_datum']) - time()) / 86400);
        $verlopen = $dagen < 0;
    ?>
    <div class="alert alert-<?= $verlopen ? 'danger' : 'warning' ?> d-flex align-items-center gap-2 py-2 mb-2">
        <i class="ri-file-shield-2-line"></i>
        <span>
            <?php if ($verlopen): ?>
                Contract <strong><?= h($c['omschrijving']) ?></strong> van <a href="<?= $base ?>/klanten/detail.php?id=<?= $c['klant_id'] ?>&tab=contract"><?= h($c['klant_naam']) ?></a> is <strong><?= abs($dagen) ?> dagen geleden verlopen</strong>.
            <?php else: ?>
                Contract <strong><?= h($c['omschrijving']) ?></strong> van <a href="<?= $base ?>/klanten/detail.php?id=<?= $c['klant_id'] ?>&tab=contract"><?= h($c['klant_naam']) ?></a> verloopt over <strong><?= $dagen ?> dagen</strong> (<?= h(date('d-m-Y', strtotime($c['eind_datum']))) ?>).
            <?php endif; ?>
        </span>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Twee kolommen -->
<div class="row g-3">
    <!-- Recente klanten -->
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Recente klanten</h6>
                <a href="<?= $base ?>/klanten/index.php" class="btn btn-sm btn-outline-secondary">Alle klanten</a>
            </div>
            <?php if (empty($recenteKlanten)): ?>
                <p class="text-muted small mb-0">Nog geen klanten.</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recenteKlanten as $k): ?>
                    <a href="<?= $base ?>/klanten/detail.php?id=<?= $k['id'] ?>" class="list-group-item list-group-item-action px-0 py-2 border-0 border-bottom text-decoration-none">
                        <div class="fw-medium small"><?= h($k['naam']) ?></div>
                        <?php if (!empty($k['bedrijf'])): ?>
                        <div class="text-muted" style="font-size:12px;"><?= h($k['bedrijf']) ?></div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recente servicehistorie -->
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Recente service</h6>
                <a href="<?= $base ?>/klanten/index.php" class="btn btn-sm btn-outline-secondary">Alle klanten</a>
            </div>
            <?php if (empty($recenteService)): ?>
                <p class="text-muted small mb-0">Nog geen servicehistorie.</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recenteService as $s): ?>
                    <a href="<?= $base ?>/klanten/detail.php?id=<?= $s['klant_id'] ?>&tab=service" class="list-group-item list-group-item-action px-0 py-2 border-0 border-bottom text-decoration-none">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-medium small"><?= h($s['klant_naam']) ?></div>
                                <div class="text-muted" style="font-size:12px;"><?= h(mb_strimwidth($s['omschrijving'], 0, 55, '…')) ?></div>
                            </div>
                            <span class="text-muted ms-2" style="font-size:11px;white-space:nowrap;"><?= h(date('d-m', strtotime($s['datum']))) ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
