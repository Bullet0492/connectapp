<?php
$paginatitel = 'Klantoverzicht';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$db = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$klant = $db->prepare('SELECT * FROM klanten WHERE id = ?');
$klant->execute([$id]);
$klant = $klant->fetch();
if (!$klant) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$apparaten = $db->prepare('SELECT * FROM apparaten WHERE klant_id = ? ORDER BY type, merk');
$apparaten->execute([$id]);
$apparaten = $apparaten->fetchAll();

$contacten = $db->prepare('SELECT * FROM contactpersonen WHERE klant_id = ? ORDER BY naam');
$contacten->execute([$id]);
$contacten = $contacten->fetchAll();

$contracten = $db->prepare("SELECT * FROM contracten WHERE klant_id = ? AND (eind_datum IS NULL OR eind_datum >= CURDATE()) ORDER BY eind_datum ASC");
$contracten->execute([$id]);
$contracten = $contracten->fetchAll();

$base = basis_url();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($klant['naam']) ?> - Klantoverzicht</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .klant-header { background: #185E9B; color: #fff; padding: 20px 16px 16px; }
        .klant-header .naam { font-size: 1.3rem; font-weight: 700; }
        .klant-header .bedrijf { font-size: .9rem; opacity: .85; }
        .sectie-titel { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #888; padding: 12px 16px 4px; }
        .info-rij { background: #fff; display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #f0f0f0; font-size: .9rem; }
        .info-rij:last-child { border-bottom: none; }
        .info-rij i { color: #185E9B; font-size: 16px; flex-shrink: 0; }
        .info-rij a { color: inherit; text-decoration: none; }
        .apparaat-kaart { background: #fff; padding: 12px 16px; border-bottom: 1px solid #f0f0f0; }
        .apparaat-kaart:last-child { border-bottom: none; }
        .qr-badge { font-size: .72rem; color: #185E9B; font-weight: 600; }
        .status-actief { color: #198754; font-size: .75rem; }
        .status-defect { color: #dc3545; font-size: .75rem; }
        .status-retour { color: #fd7e14; font-size: .75rem; }
        .terug-btn { background: rgba(255,255,255,.15); border: none; color: #fff; padding: 4px 10px; border-radius: 6px; font-size: .82rem; text-decoration: none; }
    </style>
</head>
<body>

<!-- Header -->
<div class="klant-header">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <a href="<?= $base ?>/klanten/detail.php?id=<?= $id ?>" class="terug-btn">
            <i class="ri-arrow-left-line"></i> Volledig dossier
        </a>
        <a href="<?= $base ?>/qr/label_klant.php?id=<?= $id ?>" class="terug-btn" target="_blank">
            <i class="ri-printer-line"></i> Label
        </a>
    </div>
    <div class="naam"><?= h($klant['naam']) ?></div>
    <?php if (!empty($klant['bedrijf'])): ?>
    <div class="bedrijf"><i class="ri-building-line"></i> <?= h($klant['bedrijf']) ?></div>
    <?php endif; ?>
</div>

<!-- Contactgegevens -->
<div class="sectie-titel">Contactgegevens</div>
<div class="rounded-3 overflow-hidden mx-0 mb-3" style="border-radius:0!important;">
    <?php if (!empty($klant['telefoon'])): ?>
    <div class="info-rij">
        <i class="ri-phone-line"></i>
        <a href="tel:<?= h($klant['telefoon']) ?>"><?= h($klant['telefoon']) ?></a>
    </div>
    <?php endif; ?>
    <?php if (!empty($klant['email'])): ?>
    <div class="info-rij">
        <i class="ri-mail-line"></i>
        <a href="mailto:<?= h($klant['email']) ?>"><?= h($klant['email']) ?></a>
    </div>
    <?php endif; ?>
    <?php if (!empty($klant['adres'])): ?>
    <div class="info-rij">
        <i class="ri-map-pin-line"></i>
        <span>
            <?= h($klant['adres']) ?>
            <?php if (!empty($klant['postcode']) || !empty($klant['stad'])): ?>
            <br><small class="text-muted"><?= h(trim($klant['postcode'] . ' ' . $klant['stad'])) ?></small>
            <?php endif; ?>
        </span>
    </div>
    <?php endif; ?>
    <?php if (!empty($klant['website'])): ?>
    <div class="info-rij">
        <i class="ri-global-line"></i>
        <a href="<?= h($klant['website']) ?>" target="_blank"><?= h($klant['website']) ?></a>
    </div>
    <?php endif; ?>
    <?php if (!empty($klant['intra_id'])): ?>
    <div class="info-rij">
        <i class="ri-hashtag"></i>
        <span>Intelly: <?= h($klant['intra_id']) ?></span>
    </div>
    <?php endif; ?>
    <?php if (empty($klant['telefoon']) && empty($klant['email']) && empty($klant['adres'])): ?>
    <div class="info-rij text-muted">Geen contactgegevens.</div>
    <?php endif; ?>
</div>

<!-- Contactpersonen -->
<?php if (!empty($contacten)): ?>
<div class="sectie-titel">Contactpersonen</div>
<div class="mb-3">
    <?php foreach ($contacten as $c): ?>
    <div class="info-rij">
        <i class="ri-user-line"></i>
        <div>
            <div class="fw-medium"><?= h($c['naam']) ?></div>
            <?php if (!empty($c['functie'])): ?><div class="text-muted small"><?= h($c['functie']) ?></div><?php endif; ?>
            <?php if (!empty($c['telefoon'])): ?><div class="small"><a href="tel:<?= h($c['telefoon']) ?>"><?= h($c['telefoon']) ?></a></div><?php endif; ?>
            <?php if (!empty($c['email'])): ?><div class="small"><a href="mailto:<?= h($c['email']) ?>"><?= h($c['email']) ?></a></div><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Apparaten -->
<div class="sectie-titel">Apparaten (<?= count($apparaten) ?>)</div>
<div class="mb-3">
    <?php if (empty($apparaten)): ?>
    <div class="info-rij text-muted">Geen apparaten geregistreerd.</div>
    <?php else: ?>
    <?php foreach ($apparaten as $a): ?>
    <div class="apparaat-kaart">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="fw-medium"><?= h(trim($a['merk'] . ' ' . $a['model']) ?: 'Onbekend') ?></div>
                <div class="text-muted small"><?= h(ucfirst($a['type'])) ?></div>
                <?php if (!empty($a['serienummer'])): ?>
                <div class="text-muted small">S/N: <?= h($a['serienummer']) ?></div>
                <?php endif; ?>
                <?php if (!empty($a['locatie'])): ?>
                <div class="text-muted small"><i class="ri-map-pin-line"></i> <?= h($a['locatie']) ?></div>
                <?php endif; ?>
                <?php if (!empty($a['ip_adres'])): ?>
                <div class="text-muted small">IP: <?= h($a['ip_adres']) ?></div>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <div class="qr-badge"><?= h($a['qr_code']) ?></div>
                <div class="status-<?= h($a['status']) ?>"><?= h(ucfirst($a['status'])) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Contracten -->
<?php if (!empty($contracten)): ?>
<div class="sectie-titel">Actieve contracten</div>
<div class="mb-3">
    <?php foreach ($contracten as $c): ?>
    <div class="info-rij">
        <i class="ri-file-list-3-line"></i>
        <div>
            <div class="fw-medium"><?= h($c['omschrijving']) ?></div>
            <div class="text-muted small"><?= h(ucfirst($c['sla_niveau'])) ?>
                <?php if (!empty($c['eind_datum'])): ?>
                · tot <?= date('d-m-Y', strtotime($c['eind_datum'])) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="text-center text-muted py-4" style="font-size:.75rem;">
    connect4it.nl &middot; Klanten App
</div>

</body>
</html>
