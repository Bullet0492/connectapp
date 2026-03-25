<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$db = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$apparaat = $db->prepare('SELECT a.*, k.naam AS klant_naam, k.id AS klant_id FROM apparaten a LEFT JOIN klanten k ON k.id = a.klant_id WHERE a.id = ?');
$apparaat->execute([$id]);
$apparaat = $apparaat->fetch();
if (!$apparaat) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$base = basis_url();
$paginatitel = 'Apparaat ' . ($apparaat['qr_code'] ?? $id);
require_once __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= $base ?>/klanten/index.php">Klanten</a></li>
        <li class="breadcrumb-item"><a href="<?= $base ?>/klanten/detail.php?id=<?= $apparaat['klant_id'] ?>&tab=apparaten"><?= h($apparaat['klant_naam']) ?></a></li>
        <li class="breadcrumb-item active">Apparaat <?= h($apparaat['qr_code'] ?? $id) ?></li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h4 class="fw-bold mb-1"><?= h(trim($apparaat['merk'] . ' ' . $apparaat['model']) ?: ucfirst($apparaat['type'])) ?></h4>
        <p class="text-muted mb-0"><i class="ri-building-line me-1"></i><?= h($apparaat['klant_naam']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $base ?>/qr/label_apparaat.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="ri-qr-code-line"></i> Label
        </a>
        <a href="<?= $base ?>/klanten/detail.php?id=<?= $apparaat['klant_id'] ?>&tab=apparaten" class="btn btn-outline-secondary btn-sm">
            <i class="ri-arrow-left-line"></i> Terug
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-4">
            <h6 class="fw-bold mb-3">Apparaatinformatie</h6>
            <table class="table table-sm table-borderless mb-0">
                <?php if (!empty($apparaat['qr_code'])): ?>
                <tr><td class="text-muted" style="width:40%">QR-code</td><td><code><?= h($apparaat['qr_code']) ?></code></td></tr>
                <?php endif; ?>
                <tr><td class="text-muted">Type</td><td><?= h(ucfirst($apparaat['type'])) ?></td></tr>
                <?php if (!empty($apparaat['merk'])): ?>
                <tr><td class="text-muted">Merk</td><td><?= h($apparaat['merk']) ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($apparaat['model'])): ?>
                <tr><td class="text-muted">Model</td><td><?= h($apparaat['model']) ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($apparaat['serienummer'])): ?>
                <tr><td class="text-muted">Serienummer</td><td><code><?= h($apparaat['serienummer']) ?></code></td></tr>
                <?php endif; ?>
                <?php if (!empty($apparaat['locatie'])): ?>
                <tr><td class="text-muted">Locatie</td><td><?= h($apparaat['locatie']) ?></td></tr>
                <?php endif; ?>
                <tr><td class="text-muted">Status</td><td>
                    <span class="badge badge-<?= $apparaat['status'] ?> rounded-pill"><?= h($apparaat['status']) ?></span>
                </td></tr>
            </table>
        </div>
    </div>

    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-4">
            <h6 class="fw-bold mb-3">Netwerk & firmware</h6>
            <table class="table table-sm table-borderless mb-0">
                <?php if (!empty($apparaat['ip_adres'])): ?>
                <tr><td class="text-muted" style="width:40%">IP-adres</td><td><code><?= h($apparaat['ip_adres']) ?></code></td></tr>
                <?php endif; ?>
                <?php if (!empty($apparaat['mac_adres'])): ?>
                <tr><td class="text-muted">MAC-adres</td><td><code><?= h($apparaat['mac_adres']) ?></code></td></tr>
                <?php endif; ?>
                <?php if (!empty($apparaat['firmware'])): ?>
                <tr><td class="text-muted">Firmware</td><td><?= h($apparaat['firmware']) ?></td></tr>
                <?php endif; ?>
                <?php if (!empty($apparaat['aanschafdatum'])): ?>
                <tr><td class="text-muted">Aanschafdatum</td><td><?= h(date('d-m-Y', strtotime($apparaat['aanschafdatum']))) ?></td></tr>
                <?php endif; ?>
            </table>
            <?php if (empty($apparaat['ip_adres']) && empty($apparaat['mac_adres']) && empty($apparaat['firmware'])): ?>
            <p class="text-muted small mb-0">Geen netwerk/firmware info.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($apparaat['notities'])): ?>
    <div class="col-12">
        <div class="bg-white rounded-3 border p-4">
            <h6 class="fw-bold mb-3">Notities</h6>
            <p class="mb-0" style="white-space:pre-wrap;"><?= h($apparaat['notities']) ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
