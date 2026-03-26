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
            @page { size: 101mm 54mm landscape; margin: 0; }
            body { background: #fff; }
            .no-print { display: none !important; }
            .labels-grid { padding: 0 !important; }
            .label-card { page-break-after: always; }
        }
        .labels-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 16px;
        }
        .label-card {
            width: 101mm; height: 54mm;
            border: 1px solid #ccc; border-radius: 4px;
            padding: 5mm; background: #fff;
            display: flex; flex-direction: row; align-items: center; gap: 5mm;
            font-family: Arial, sans-serif; box-sizing: border-box;
            page-break-inside: avoid;
        }
        .label-qr { flex-shrink: 0; }
        .label-qr img, .label-qr canvas { width: 38mm !important; height: 38mm !important; display: block; }
        .label-info { display: flex; flex-direction: column; justify-content: center; gap: 2mm; }
        .label-titel { font-size: 13pt; font-weight: bold; color: #185E9B; }
        .label-sub   { font-size: 9pt; color: #444; line-height: 1.5; }
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
            width: 144, height: 144,
            correctLevel: QRCode.CorrectLevel.M
        });
    });
});
</script>
</body>
</html>
