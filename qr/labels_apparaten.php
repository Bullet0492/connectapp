<!DOCTYPE html>
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
sessie_start();
vereist_login();

$db       = db();
$klant_id = (int)($_GET['klant_id'] ?? 0);
if (!$klant_id) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$klant = $db->prepare('SELECT * FROM klanten WHERE id = ?');
$klant->execute([$klant_id]);
$klant = $klant->fetch();
if (!$klant) { header('Location: ' . basis_url() . '/klanten/index.php'); exit; }

$stmt = $db->prepare('SELECT * FROM apparaten WHERE klant_id = ? AND qr_code IS NOT NULL AND qr_code != "" ORDER BY id');
$stmt->execute([$klant_id]);
$apparaten = $stmt->fetchAll();

$base_url = basis_url();
?>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>QR-labels - <?= h($klant['naam']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        body { background: #f8f9fa; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .labels-grid { padding: 0 !important; }
        }
        .labels-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 16px;
        }
        .label-card {
            width: 62mm; min-height: 40mm;
            border: 1px solid #ccc; border-radius: 6px;
            padding: 6px 8px; background: #fff;
            display: flex; align-items: flex-start; gap: 8px;
            font-family: Arial, sans-serif; box-sizing: border-box;
            page-break-inside: avoid;
        }
        .label-qr img, .label-qr canvas { width: 32mm !important; height: 32mm !important; display: block; }
        .label-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
        .label-titel  { font-size: 8.5pt; font-weight: bold; color: #185E9B; }
        .label-naam   { font-size: 9pt; font-weight: bold; color: #111; word-break: break-word; }
        .label-bedrijf{ font-size: 7.5pt; color: #555; word-break: break-word; }
        .label-code   { font-size: 7pt; color: #888; font-family: monospace; }
        .label-sub    { font-size: 7pt; color: #888; margin-top: auto; padding-top: 3px; border-top: 1px solid #eee; }
    </style>
</head>
<body>

<div class="no-print p-3 border-bottom bg-white d-flex align-items-center gap-3 flex-wrap">
    <strong>QR-labels apparaten: <?= h($klant['naam']) ?> (<?= count($apparaten) ?>)</strong>
    <?php if (!empty($apparaten)): ?>
    <button onclick="window.print()" class="btn btn-primary btn-sm">Afdrukken</button>
    <?php endif; ?>
    <a href="<?= $base_url ?>/klanten/detail.php?id=<?= $klant_id ?>&tab=apparaten" class="btn btn-outline-secondary btn-sm">← Terug</a>
</div>

<?php if (empty($apparaten)): ?>
<div class="p-4 text-muted">Geen apparaten met QR-code gevonden.</div>
<?php else: ?>
<div class="labels-grid" id="labels-container">
    <?php foreach ($apparaten as $i => $a): ?>
    <div class="label-card">
        <div class="label-qr" id="qr-<?= $i ?>"></div>
        <div class="label-info">
            <div class="label-titel">Connect4IT</div>
            <div class="label-sub">www.connect4it.nl<br>085 105 3040</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
var apparaten = <?= json_encode(array_values(array_map(fn($a) => [
    'id'  => $a['id'],
    'url' => $base_url . '/qr/apparaat.php?id=' . $a['id'],
], $apparaten))) ?>;

document.addEventListener('DOMContentLoaded', function() {
    apparaten.forEach(function(a, i) {
        new QRCode(document.getElementById('qr-' + i), {
            text: a.url,
            width: 121, height: 121,
            correctLevel: QRCode.CorrectLevel.M
        });
    });
});
</script>
</body>
</html>
