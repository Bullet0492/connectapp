<!DOCTYPE html>
<?php
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

$base_url = basis_url();
$scan_url = $base_url . '/qr/klant.php?id=' . $id;
?>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>QR-label - <?= h($klant['naam']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <style>
        body { background: #f8f9fa; }

        @media print {
            @page { size: 101mm 54mm landscape; margin: 0; }
            body { background: #fff; }
            .no-print { display: none !important; }
        }

        .label-card {
            width: 101mm;
            height: 54mm;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 5mm;
            background: #fff;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 5mm;
            font-family: Arial, sans-serif;
            box-sizing: border-box;
        }

        .label-qr { flex-shrink: 0; }
        .label-qr img, .label-qr canvas { width: 38mm !important; height: 38mm !important; display: block; }

        .label-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 2mm;
        }

        .label-titel {
            font-size: 13pt;
            font-weight: bold;
            color: #185E9B;
            letter-spacing: .3px;
        }

        .label-sub {
            font-size: 9pt;
            color: #444;
            line-height: 1.5;
        }
    </style>
</head>
<body>

<div class="no-print p-3 border-bottom bg-white d-flex align-items-center gap-3">
    <strong>QR-label klant: <?= h($klant['naam']) ?></strong>
    <button onclick="window.print()" class="btn btn-primary btn-sm">Afdrukken</button>
    <a href="<?= $base_url ?>/klanten/detail.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">← Terug</a>
</div>

<div class="p-4">
    <div class="label-card" id="label-klant">
        <div class="label-qr" id="qr-klant"></div>
        <div class="label-info">
            <div class="label-titel">Connect4IT</div>
            <div class="label-sub">www.connect4it.nl<br>085 105 3040</div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    new QRCode(document.getElementById('qr-klant'), {
        text: '<?= addslashes($scan_url) ?>',
        width: 144,
        height: 144,
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>

</body>
</html>
