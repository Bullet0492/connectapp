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
            body { background: #fff; }
            .no-print { display: none !important; }
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

        .label-qr img, .label-qr canvas { width: 32mm !important; height: 32mm !important; display: block; }

        .label-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .label-titel {
            font-size: 8.5pt;
            font-weight: bold;
            color: #185E9B;
            letter-spacing: .3px;
        }

        .label-naam {
            font-size: 9pt;
            font-weight: bold;
            color: #111;
            word-break: break-word;
        }

        .label-bedrijf {
            font-size: 7.5pt;
            color: #555;
            word-break: break-word;
        }

        .label-sub {
            font-size: 7pt;
            color: #888;
            margin-top: auto;
            padding-top: 3px;
            border-top: 1px solid #eee;
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
        width: 121,
        height: 121,
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>

</body>
</html>
