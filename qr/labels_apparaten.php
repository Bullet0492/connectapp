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
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body { background: #f0f0f0; font-family: Arial, sans-serif; }

        .toolbar {
            background: #fff;
            border-bottom: 1px solid #ddd;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toolbar strong { font-size: 14px; }
        .btn {
            padding: 5px 14px;
            border-radius: 4px;
            border: 1px solid #ccc;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #185E9B; color: #fff; border-color: #185E9B; }
        .btn-secondary { background: #fff; color: #333; }

        .preview {
            padding: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        /* ── Label ── */
        .label-card {
            width: 101mm;
            height: 54mm;
            background: #fff;
            border: 1px solid #bbb;
            border-radius: 3px;
            display: flex;
            flex-direction: row;
            align-items: center;
            padding: 4mm 5mm;
            gap: 5mm;
        }

        .label-qr { flex-shrink: 0; }
        .label-qr canvas, .label-qr img {
            width: 40mm !important;
            height: 40mm !important;
            display: block;
        }

        .label-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 2.5mm;
        }

        .label-titel {
            font-size: 15pt;
            font-weight: bold;
            color: #185E9B;
        }

        .label-sub {
            font-size: 9.5pt;
            color: #333;
            line-height: 1.6;
        }

        /* ── Print: elke label op eigen pagina ── */
        @media print {
            @page {
                size: 101mm 54mm;
                margin: 0;
            }

            html, body {
                width: 101mm;
                background: #fff;
            }

            .toolbar { display: none !important; }
            .preview { padding: 0 !important; gap: 0 !important; }

            .label-card {
                border: none;
                border-radius: 0;
                width: 101mm !important;
                height: 54mm !important;
                page-break-after: always;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <strong>QR-labels apparaten: <?= h($klant['naam']) ?> (<?= count($apparaten) ?>)</strong>
    <?php if (!empty($apparaten)): ?>
    <button onclick="window.print()" class="btn btn-primary">Afdrukken</button>
    <?php endif; ?>
    <a href="<?= $base_url ?>/klanten/detail.php?id=<?= $klant_id ?>&tab=apparaten" class="btn btn-secondary">← Terug</a>
</div>

<?php if (empty($apparaten)): ?>
<div style="padding:20px;color:#888;">Geen apparaten met QR-code gevonden.</div>
<?php else: ?>
<div class="preview" id="labels-container">
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
            width: 151,
            height: 151,
            correctLevel: QRCode.CorrectLevel.M
        });
    });
});
</script>
</body>
</html>
