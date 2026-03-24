<!DOCTYPE html>
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$db = db();

// Haal apparaten op: één specifiek apparaat of alle van een klant
$klant_id    = (int)($_GET['klant_id'] ?? 0);
$apparaat_id = (int)($_GET['apparaat_id'] ?? 0);

if ($apparaat_id) {
    $stmt = $db->prepare("SELECT a.*, k.naam AS klant_naam, k.bedrijf FROM apparaten a LEFT JOIN klanten k ON k.id = a.klant_id WHERE a.id = ?");
    $stmt->execute([$apparaat_id]);
    $apparaten = $stmt->fetchAll();
} elseif ($klant_id) {
    $stmt = $db->prepare("SELECT a.*, k.naam AS klant_naam, k.bedrijf FROM apparaten a LEFT JOIN klanten k ON k.id = a.klant_id WHERE a.klant_id = ? ORDER BY a.id");
    $stmt->execute([$klant_id]);
    $apparaten = $stmt->fetchAll();
} else {
    $apparaten = [];
}

$base_url = basis_url();
?>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>QR-labels afdrukken</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <style>
        body { background: #f8f9fa; }

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .label-grid { gap: 4mm !important; }
            .label-card { page-break-inside: avoid; break-inside: avoid; }
        }

        .label-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 16px;
        }

        .label-card {
            width: 62mm;
            min-height: 40mm;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 6px 8px;
            background: #fff;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-family: Arial, sans-serif;
            box-sizing: border-box;
        }

        .label-qr canvas,
        .label-qr img {
            width: 32mm;
            height: 32mm;
            display: block;
        }

        .label-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .label-qr-code {
            font-size: 9pt;
            font-weight: bold;
            color: #185E9B;
            letter-spacing: .5px;
        }

        .label-type {
            font-size: 7.5pt;
            color: #555;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .label-merk-model {
            font-size: 8pt;
            font-weight: bold;
            color: #111;
            word-break: break-word;
        }

        .label-sn {
            font-size: 7pt;
            color: #444;
            word-break: break-all;
        }

        .label-klant {
            font-size: 7pt;
            color: #666;
            margin-top: auto;
            padding-top: 3px;
            border-top: 1px solid #eee;
        }

        .label-bedrijf {
            font-size: 7pt;
            color: #888;
        }
    </style>
</head>
<body>

<!-- Bediening (niet afgedrukt) -->
<div class="no-print p-3 border-bottom bg-white d-flex align-items-center gap-3 flex-wrap">
    <div>
        <strong><?= count($apparaten) ?> label(s)</strong> klaar om af te drukken
    </div>
    <button onclick="window.print()" class="btn btn-primary">
        <i class="ri-printer-line"></i> Afdrukken
    </button>
    <button onclick="window.history.back()" class="btn btn-outline-secondary">
        ← Terug
    </button>
    <?php if ($klant_id): ?>
    <a href="labels.php?klant_id=<?= $klant_id ?>" class="btn btn-outline-secondary">Alle apparaten van klant</a>
    <?php endif; ?>
</div>

<?php if (empty($apparaten)): ?>
<div class="p-4 text-muted">Geen apparaten gevonden.</div>
<?php else: ?>

<div class="label-grid" id="labelGrid">
    <?php foreach ($apparaten as $a): ?>
    <div class="label-card" id="label-<?= $a['id'] ?>">
        <div class="label-qr">
            <canvas id="qr-<?= $a['id'] ?>"></canvas>
        </div>
        <div class="label-info">
            <div class="label-qr-code"><?= h($a['qr_code']) ?></div>
            <div class="label-type"><?= h(ucfirst($a['type'])) ?></div>
            <div class="label-merk-model">
                <?= h(trim($a['merk'] . ' ' . $a['model']) ?: 'Onbekend') ?>
            </div>
            <?php if (!empty($a['serienummer'])): ?>
            <div class="label-sn">S/N: <?= h($a['serienummer']) ?></div>
            <?php endif; ?>
            <?php if (!empty($a['locatie'])): ?>
            <div class="label-sn"><?= h($a['locatie']) ?></div>
            <?php endif; ?>
            <div class="label-klant"><?= h($a['klant_naam'] ?? '') ?></div>
            <?php if (!empty($a['bedrijf'])): ?>
            <div class="label-bedrijf"><?= h($a['bedrijf']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
// Genereer QR-codes na laden pagina
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($apparaten as $a): ?>
    QRCode.toCanvas(
        document.getElementById('qr-<?= $a['id'] ?>'),
        '<?= addslashes($base_url) ?>/qr/apparaat.php?qr=<?= urlencode($a['qr_code']) ?>',
        { width: 121, margin: 1 }, // ~32mm bij 96dpi
        function(err) { if (err) console.error(err); }
    );
    <?php endforeach; ?>
});
</script>

<?php endif; ?>
</body>
</html>
