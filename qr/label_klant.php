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
        }

        /* ── Label ── */
        .label-card {
            width: 89mm;
            height: 36mm;
            background: #fff;
            border: 1px solid #bbb;
            border-radius: 3px;
            display: flex;
            flex-direction: row;
            align-items: center;
            padding: 3mm 4mm;
            gap: 4mm;
        }

        .label-qr { flex-shrink: 0; }
        .label-qr canvas, .label-qr img {
            width: 26mm !important;
            height: 26mm !important;
            display: block;
        }

        .label-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 2mm;
        }

        .label-titel {
            font-size: 12pt;
            font-weight: bold;
            color: #185E9B;
        }

        .label-sub {
            font-size: 8pt;
            color: #333;
            line-height: 1.5;
        }

        /* ── Print ── */
        @media print {
            @page {
                size: 89mm 36mm;
                margin: 0;
            }

            html, body {
                width: 89mm;
                height: 36mm;
                overflow: hidden;
                background: #fff;
            }

            .toolbar { display: none !important; }
            .preview { padding: 0 !important; }

            .label-card {
                border: none;
                border-radius: 0;
                width: 89mm !important;
                height: 36mm !important;
            }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <strong>QR-label: <?= h($klant['naam']) ?></strong>
    <button onclick="window.print()" class="btn btn-primary">Afdrukken</button>
    <a href="<?= $base_url ?>/klanten/detail.php?id=<?= $id ?>" class="btn btn-secondary">← Terug</a>
</div>

<div class="preview">
    <div class="label-card">
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
        width: 98,
        height: 98,
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>

</body>
</html>
